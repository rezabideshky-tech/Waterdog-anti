<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\listener;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\friends\FriendsMenu;
use sergittos\bedwars\lobby\clan\ClanMenu;
use sergittos\bedwars\lobby\gui\CosmeticsGui;
use sergittos\bedwars\lobby\gui\PartyGui;
use sergittos\bedwars\lobby\gui\ServerSelectorGui;
use sergittos\bedwars\cosmetics\wearable\WearableRenderService;
use sergittos\bedwars\cosmetics\wearable\WearableSkinService;
use sergittos\bedwars\lobby\LobbyItems;
use sergittos\bedwars\lobby\pets\PetRenderService;
use sergittos\bedwars\lobby\task\LobbySpawnDescentTask;
use sergittos\bedwars\utils\CameraReset;

class LobbyListener implements Listener{

    public function __construct(private BedWarsLobby $plugin){}

    public function onJoin(PlayerJoinEvent $event) : void{
        $player = $event->getPlayer();
        $core = BedWarsCore::getInstance();

        // Safety net for players arriving straight out of a game server's
        // victory cinematic (see CameraReset for why this can't just be
        // left to the game side alone).
        CameraReset::clear($player);

        // Capture the player's real skin as early as possible, before any
        // wing/cape compositing ever touches it.
        WearableSkinService::captureOriginal($player);


        $lobbySpawn = $this->plugin->getLobbyManager()->getLobbySpawn();
        $world = $this->plugin->getLobbyManager()->getLobbyWorld();
        if($lobbySpawn !== null && $world !== null){
            // Spawn from above and glide down onto the exact /setlobby
            // position (see LobbySpawnDescentTask) instead of a
            // getSafeSpawn()-adjusted spot - the descent itself takes
            // about a second, which also gives skins/resources the same
            // warm-up time the old fixed 20-tick delay used to provide.
            LobbySpawnDescentTask::start($player, $world, $lobbySpawn, function(Player $player) use ($core): void{
                $this->applyLobbyState($player, $core);
            });
        }else{
            // No /setlobby set yet - fall back to applying lobby state
            // immediately, same timing as before.
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $core) : void{
                $this->applyLobbyState($player, $core);
            }), 20);
        }

        // Join broadcast disabled per server owner request. Kept as an empty
        // string (not removed) so this stays the single obvious place to
        // re-enable it later, and so nothing downstream that reads
        // PlayerJoinEvent::getJoinMessage() breaks on a missing call.
        $event->setJoinMessage("");
    }

    private function applyLobbyState(Player $player, BedWarsCore $core): void{
        if(!$player->isOnline()){
            return;
        }

        $session = $core->getSessionManager()->get($player);
        if($session === null){
            return;
        }

        LobbyItems::give($session);
        $session->syncXpBar();
        $session->setScoreboard(new \sergittos\bedwars\session\scoreboard\LobbyScoreboard());

        $this->plugin->getQuestData()->syncPlayer($player);
        $this->plugin->getChallengesData()->syncNow($player, $this->plugin->getChallengesRegistry());
        $this->plugin->getBattlePassData()->syncNow($player, $this->plugin->getBattlePassRegistry());

        WearableRenderService::applyAll($player);
        PetRenderService::apply($player);

        // Player has just landed in the lobby: make sure every leaderboard
        // hologram is (re)sent now that the client is surely ready.
        $core->getHologramManager()->scheduleResync($player);
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        $player = $event->getPlayer();
        PetRenderService::despawn($player);
        WearableSkinService::forget($player);
        $this->plugin->getFriendManager()->touchLastSeen($player);
        $this->plugin->getQuestData()->saveNow($player);

        // Quit broadcast disabled per server owner request - see onJoin()'s
        // matching change. Every other cleanup above (pet despawn, wearable
        // skin cache, friend "last seen" timestamp, quest data save) still
        // runs exactly as before; only the chat line is suppressed.
        $event->setQuitMessage("");
    }

    public function onDeath(PlayerDeathEvent $event) : void{
        // Dying in the lobby (falling into the void, etc.) would otherwise
        // spill the lobby items (server selector, cosmetics, party, ...) onto
        // the ground as normal item drops, and since nothing ever gave them
        // back afterwards they were effectively gone for good. The lobby
        // items get fully re-given in onRespawn() below, so there's nothing
        // useful to drop here.
        $event->setDrops([]);
        $event->setXpDropAmount(0);
    }

    public function onRespawn(PlayerRespawnEvent $event) : void{
        $player = $event->getPlayer();

        // Force the respawn position back to the configured lobby spawn
        // (/setlobby) instead of letting the server fall back to the world's
        // default spawn, which is what was sending players who died
        // somewhere like underground back to a random/underground spot
        // instead of the actual lobby.
        $lobbySpawn = $this->plugin->getLobbyManager()->getLobbySpawn();
        $world = $this->plugin->getLobbyManager()->getLobbyWorld();
        if($lobbySpawn !== null && $world !== null){
            // The client still needs a concrete respawn position for this
            // event; the actual glide-down happens right after, in the
            // scheduled task below, the same way a fresh join does.
            $event->setRespawnPosition(new \pocketmine\world\Position($lobbySpawn->getX(), $lobbySpawn->getY(), $lobbySpawn->getZ(), $world));
        }

        $core = BedWarsCore::getInstance();
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $core, $lobbySpawn, $world) : void{
            if(!$player->isOnline()){
                return;
            }

            if($lobbySpawn !== null && $world !== null){
                LobbySpawnDescentTask::start($player, $world, $lobbySpawn, function(Player $player) use ($core): void{
                    $session = $core->getSessionManager()->get($player);
                    if($session === null){
                        return;
                    }
                    // Re-gives the lobby items and resets health/food/gamemode,
                    // the same way a fresh join does.
                    LobbyItems::give($session);
                });
                return;
            }

            $session = $core->getSessionManager()->get($player);
            if($session === null){
                return;
            }
            LobbyItems::give($session);
        }), 5);
    }

    public function onDamage(EntityDamageEvent $event) : void{
        // A player mid-descent (see LobbySpawnDescentTask) is intentionally
        // flying with block collision disabled through whatever terrain
        // sits between the descent's start height and the lobby spawn -
        // suffocation or fall damage from that is never something the
        // player actually caused, so it's suppressed for exactly the
        // duration of the descent and nothing else.
        $entity = $event->getEntity();
        if($entity instanceof Player && LobbySpawnDescentTask::isDescending($entity)){
            $event->cancel();
        }
    }

    public function onItemUse(PlayerItemUseEvent $event) : void{
        $player = $event->getPlayer();
        $lobbyId = LobbyItems::getLobbyItemId($event->getItem());
        if($lobbyId === null){
            return;
        }

        $event->cancel();

        match($lobbyId){
            LobbyItems::ITEM_SERVER_SELECTOR => (new ServerSelectorGui())->open($player),
            LobbyItems::ITEM_QUESTS          => \sergittos\bedwars\lobby\quests\menu\QuestsMenu::openMain($player, $this->plugin->getQuestData()),
            LobbyItems::ITEM_COSMETICS       => (new CosmeticsGui())->openMain($player),
            LobbyItems::ITEM_PROFILE         => \sergittos\bedwars\lobby\profile\ProfileMenu::open($player, $player->getName()),
            LobbyItems::ITEM_PARTY           => (new PartyGui())->open($player),
            LobbyItems::ITEM_FRIENDS         => FriendsMenu::openMain($player),
            LobbyItems::ITEM_CLAN            => ClanMenu::openMain($player),
            default                          => null,
        };
    }

    public function onInteract(PlayerInteractEvent $event) : void{
        if(LobbyItems::getLobbyItemId($event->getItem()) !== null){
            $event->cancel();
        }
    }

    public function onDrop(PlayerDropItemEvent $event) : void{
        if(LobbyItems::getLobbyItemId($event->getItem()) !== null){
            $event->cancel();
        }
    }

    public function onInventoryTransaction(InventoryTransactionEvent $event) : void{
        foreach($event->getTransaction()->getActions() as $action){
            if($action instanceof SlotChangeAction){
                if(LobbyItems::getLobbyItemId($action->getSourceItem()) !== null){
                    $event->cancel();
                    return;
                }
            }
        }
    }
}