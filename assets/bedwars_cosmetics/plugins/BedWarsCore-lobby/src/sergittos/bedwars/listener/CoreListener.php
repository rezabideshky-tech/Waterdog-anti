<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;
use pocketmine\player\chat\LegacyRawChatFormatter;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\rejoin\RejoinTicketItem;
use sergittos\bedwars\rejoin\RejoinTicketManager;
use function json_encode;
use function time;

class CoreListener implements Listener{

    // Must match RejoinDisconnectListener::REJOIN_TTL in the BedWarsGame plugin -
    // that listener is the one that actually creates/owns the rejoin database
    // record on disconnect, this constant only controls how long the lobby-side
    // "would you like to go back?" prompt still trusts that record. These plugins
    // ship separately, so the value can't be imported and has to be kept in sync
    // by hand: previously this was 60s while the game side used 120s, so this
    // form would silently clearRejoin() a still-valid record after only half the
    // real grace window had passed, permanently losing the player's spot.
    private const REJOIN_TTL = 120;
    private const REJOIN_LOOKUP_RETRIES = 10;
    private const REJOIN_LOOKUP_DELAY_TICKS = 10;

    public function __construct(private BedWarsCore $plugin){}

    public function onJoin(PlayerJoinEvent $event): void{
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->create($player);

        $session->updateNametag();
        $this->plugin->getPartyManager()->reconnect($session);
        $this->plugin->getHologramManager()->showAllTo($player);
        $this->plugin->getHologramManager()->scheduleResync($player);

        $this->plugin->getPlaytimeRewardManager()->loadPlayer($player);
        $this->plugin->getDailyLoginStreakManager()->onJoin($player);

        if($this->plugin->getNetworkManager()->getServerType() === "lobby"){
            $this->plugin->getScheduler()->scheduleDelayedTask(
                new ClosureTask(fn() => $this->trySendRejoinTicket($player, 0)),
                10
            );
        }

        $event->setJoinMessage("");
    }

    /**
     * Instead of blocking the player with a form the instant they load in,
     * this hands them a "Rejoin Ticket" item (see RejoinTicketItem /
     * RejoinTicketManager) they can act on whenever they're ready. Right-
     * clicking it sends them back; ignoring it just lets it expire on its
     * own once the grace window (REJOIN_TTL) runs out.
     */
    private function trySendRejoinTicket(Player $player, int $attempt): void{
        if(!$player->isConnected()){
            return;
        }

        $this->plugin->getProvider()->getRejoin($player->getName(), function(?array $row) use ($player, $attempt): void{
            if(!$player->isConnected()){
                return;
            }

            // DIAGNOSTIC LOGGING (disabled - console output silenced by request).
            // See matching note in JoinListener::tryPromptRejoin() (BedWarsGame plugin).
            // $this->plugin->getLogger()->info(
            //     "[Rejoin] trySendRejoinTicket(" . $player->getName() . ", attempt=" . $attempt . "): row=" . ($row === null ? "null" : json_encode($row))
            // );

            if($row === null){
                if($attempt < self::REJOIN_LOOKUP_RETRIES){
                    $this->plugin->getScheduler()->scheduleDelayedTask(
                        new ClosureTask(fn() => $this->trySendRejoinTicket($player, $attempt + 1)),
                        self::REJOIN_LOOKUP_DELAY_TICKS
                    );
                }
                return;
            }

            $created = (int) ($row["created_at"] ?? 0);
            $left = self::REJOIN_TTL - (time() - $created);
            if($left <= 0){
                $this->plugin->getProvider()->clearRejoin($player->getName());
                return;
            }

            $targetServer = (string) ($row["target_server"] ?? "");
            if($targetServer === ""){
                $this->plugin->getProvider()->clearRejoin($player->getName());
                return;
            }

            $mode = (string) ($row["mode"] ?? "Solo");
            $team = (string) ($row["team"] ?? "");

            RejoinTicketManager::give($player, $targetServer, $mode, $team, $left);
        });
    }

    /**
     * Right-click handling for the Rejoin Ticket item. Mirrors what the old
     * RejoinMatchForm's "Yes, Take Me Back" button used to do - the target
     * server is read straight back off the item's own NBT (stamped in by
     * RejoinTicketItem::build() when the ticket was handed out), so this
     * needs no extra database round trip to act on.
     */
    public function onItemUse(PlayerItemUseEvent $event): void{
        $item = $event->getItem();
        if(!RejoinTicketItem::isTicket($item)){
            return;
        }

        $event->cancel();

        $player = $event->getPlayer();
        $targetServer = RejoinTicketItem::getTargetServer($item);
        if($targetServer === ""){
            return;
        }

        $player->sendMessage(TF::AQUA . TF::BOLD . "RECONNECTING" . TF::RESET . TF::GRAY . " » " . TF::WHITE . "Sending you back to your match...");

        // Stamp the record as explicitly confirmed BEFORE transferring -
        // this is what tells the Game server's JoinListener it's safe to
        // silently drop this player straight back into their match with no
        // extra prompt. Without this flag, a player who never touches the
        // ticket and instead queues into a brand new match through normal
        // matchmaking could still get auto-rejoined into the old one the
        // moment matchmaking happens to land them on the same server that's
        // still running it - which is exactly the "I didn't click it but it
        // sent me back anyway" bug this fixes.
        $this->plugin->getProvider()->confirmRejoin($player->getName());

        if(!$this->plugin->getNetworkManager()->transferToServer($player, $targetServer)){
            // Transfer never happened - leave the ticket and its countdown
            // completely untouched (don't clear the in-memory state) so the
            // player can simply try again; nothing about their pending
            // rejoin actually changed.
            $player->sendMessage(TF::RED . TF::BOLD . "TRANSFER FAILED" . TF::RESET . TF::GRAY . " » " . TF::WHITE . "Couldn't reach that server right now. Try again in a moment.");
            return;
        }

        // Transfer succeeded - the player is on their way out of this
        // server, so the in-memory countdown state (and its scheduled tick)
        // is no longer needed. The DB record itself is left alone; the
        // game server's own JoinListener is what actually consumes/clears
        // it once the player lands and rejoins.
        RejoinTicketManager::clear($player);
    }

    /**
     * Blocks the ticket from doing anything as a placeable/usable block
     * item (right-click-on-block case) - the actual rejoin action is
     * handled by onItemUse() above.
     */
    public function onInteract(PlayerInteractEvent $event): void{
        if(RejoinTicketItem::isTicket($event->getItem())){
            $event->cancel();
        }
    }

    public function onDrop(PlayerDropItemEvent $event): void{
        if(RejoinTicketItem::isTicket($event->getItem())){
            $event->cancel();
        }
    }

    /**
     * Keeps the ticket pinned to its fixed hotbar slot - no dragging it
     * elsewhere, no swapping another item into its slot, no dumping it into
     * a chest/crafting grid/etc. Same pattern LobbyItems' own items use in
     * BedWarsLobby's LobbyListener.
     */
    public function onInventoryTransaction(InventoryTransactionEvent $event): void{
        foreach($event->getTransaction()->getActions() as $action){
            if($action instanceof SlotChangeAction && RejoinTicketItem::isTicket($action->getSourceItem())){
                $event->cancel();
                return;
            }
        }
    }

    /**
     * Fixes holograms (leaderboards, generator/status text, ...) that used
     * to keep showing at the same coordinates in every other world while a
     * game was running elsewhere: holograms were only ever (re)synced on
     * PlayerJoinEvent, so once a player changed world (queue -> game world,
     * one game world -> another, etc.) whatever was already rendered on
     * their screen just stayed there. PocketMine has no dedicated "world
     * change" event, so this listens to EntityTeleportEvent (what actually
     * fires on every world change) and only acts when the from/to worlds
     * differ, explicitly hiding the old world's holograms and showing the
     * new world's ones.
     */
    public function onWorldChange(EntityTeleportEvent $event): void{
        $player = $event->getEntity();
        if(!$player instanceof Player) return;

        $from = $event->getFrom()->getWorld();
        $to = $event->getTo()->getWorld();
        if($from === $to) return;

        $this->plugin->getHologramManager()->hideAllFrom($player, $from);

        // EntityTeleportEvent fires *before* the entity is actually moved
        // into the new world, so $player->getWorld() (which showAllTo()
        // reads) would still report the old world if called synchronously
        // here. Deferring one tick lets the teleport finish first.
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void{
            if($player->isConnected()){
                $this->plugin->getHologramManager()->showAllTo($player);
            }
        }), 1);
    }

    public function onQuit(PlayerQuitEvent $event): void{
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->get($player);
        if($session !== null){
            $session->save();
            $this->plugin->getPartyManager()->onPlayerQuit($session);
        }

        // Drop any in-memory rejoin ticket countdown state - the DB record
        // (if still valid) is left completely untouched, only the local
        // "keep ticking/refreshing the item" state is cleared so it doesn't
        // keep scheduling itself for a player who is no longer here.
        RejoinTicketManager::clear($player);

        $this->plugin->getPlaytimeRewardManager()->unloadPlayer($player);
        $this->plugin->getDailyLoginStreakManager()->onQuit($player);
        $this->plugin->getNotificationService()->clear($player);

        $event->setQuitMessage("");
    }

    /**
     * @priority MONITOR
     */
    public function onQuitMonitor(PlayerQuitEvent $event): void{
        $this->plugin->getSessionManager()->remove($event->getPlayer());
    }

    /**
     * HIGHEST so this always has the final say on chat formatting even
     * if RankSystem is installed with its own chat formatting left
     * enabled (RankSystem's built-in chat handler runs at HIGH). This
     * only ever reads rank prefix/color values from RankSystem (via
     * Session::getRankPrefix()/getRankColor()/getRankChatColor()) - it
     * never lets RankSystem's own formatter take over the lobby chat
     * line, which needs to keep the level badge BedWars adds.
     *
     * @priority HIGHEST
     */
    public function onChat(PlayerChatEvent $event): void{
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->get($player);
        if($session === null){
            return;
        }

        if($session->getGame() !== null){
            return;
        }

        $prefix    = $session->getRankPrefix();
        $nameColor = $session->getRankColor();
        $chatColor = $session->getRankChatColor();
        $level     = $session->getFormattedLevel();

        $event->setFormatter(new LegacyRawChatFormatter(
            $level . " " . $prefix . $nameColor . "{%0}" . TF::DARK_GRAY . " » " . $chatColor . "{%1}"
        ));
    }
}