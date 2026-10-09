<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use function strtolower;

final class SpectatorProtectionListener implements Listener{

    /** @var array<string,int> */
    private array $lastHandledTick = [];

    public function onDamage(EntityDamageEvent $event): void{
        $e = $event->getEntity();
        if(!$e instanceof Player || !SessionFactory::hasSession($e)){
            return;
        }
        $s = SessionFactory::getSession($e);
        if($this->isSpectatorLike($s)){
            $event->cancel();
        }
    }

    public function onDamageByEntity(EntityDamageByEntityEvent $event): void{
        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if($damager instanceof Player && SessionFactory::hasSession($damager)){
            $sd = SessionFactory::getSession($damager);
            if($this->isSpectatorLike($sd)){
                $event->cancel();
                return;
            }
        }

        if($entity instanceof Player && SessionFactory::hasSession($entity)){
            $se = SessionFactory::getSession($entity);
            if($this->isSpectatorLike($se)){
                $event->cancel();
            }
        }
    }

    public function onBreak(BlockBreakEvent $event): void{
        $p = $event->getPlayer();
        if(!SessionFactory::hasSession($p)){
            return;
        }
        $s = SessionFactory::getSession($p);
        if($this->isSpectatorLike($s)){
            $event->cancel();
        }
    }

    public function onPlace(BlockPlaceEvent $event): void{
        $p = $event->getPlayer();
        if(!SessionFactory::hasSession($p)){
            return;
        }
        $s = SessionFactory::getSession($p);
        if($this->isSpectatorLike($s)){
            $event->cancel();
        }
    }

    public function onItemHeld(PlayerItemHeldEvent $event): void{
        // Switching the hotbar slot onto a spectator item (scrolling, number keys,
        // or dragging it into the hand slot from the inventory) must NOT trigger the
        // item's action. Only an actual use/interact should fire onInteract() - this
        // previously ran the item's effect (e.g. Play Again) the instant it reached
        // the player's hand, before it was ever "held and used".
    }

    /**
     * @handleCancelled true
     *
     * Real GameMode::SPECTATOR() players have their PlayerItemUseEvent
     * cancelled by the engine before this listener runs (matching vanilla
     * Bedrock, where a spectator can't "use" a held item through the normal
     * item-use pipeline). Without @handleCancelled, a cancelled event never
     * reaches this method at all, so the spectator item logic below - and
     * therefore Teleporter/Play Again/Return to Lobby - would silently never
     * run for a true spectator. Non-spectators are completely unaffected:
     * handleSpectatorItem() only acts on sessions that are actually
     * spectating, so an already-cancelled event for a normal player is
     * simply ignored here as before.
     */
    public function onItemUse(PlayerItemUseEvent $event): void{
        $p = $event->getPlayer();
        if($this->handleSpectatorItem($p, $event->getItem())){
            $event->cancel();
        }
    }

    /**
     * @handleCancelled true
     *
     * Same reasoning as onItemUse() above - the engine (and, for touch/mobile
     * UI, the client itself) can mark PlayerInteractEvent cancelled for a
     * real spectator before it reaches a normal-priority listener.
     */
    public function onInteract(PlayerInteractEvent $event): void{
        $p = $event->getPlayer();

        if($this->handleSpectatorItem($p, $event->getItem())){
            $event->cancel();
            return;
        }

        if(!SessionFactory::hasSession($p)){
            return;
        }

        $s = SessionFactory::getSession($p);
        if($this->isSpectatorLike($s)){
            $event->cancel();
        }
    }

    private function isSpectatorLike(Session $session): bool{
        $p = $session->getPlayer();
        return $session->isSpectator() || $p->getGamemode() === GameMode::SPECTATOR();
    }

    private function handleSpectatorItem(Player $player, Item $item): bool{
        if(!SessionFactory::hasSession($player)){
            return false;
        }

        $session = SessionFactory::getSession($player);
        if(!$this->isSpectatorLike($session)){
            return false;
        }

        $key = $item->getNamedTag()->getString("bedwars_name", "");
        if($key === ""){
            return true;
        }

        $nowTick = Server::getInstance()->getTick();
        $name = strtolower($player->getName());
        if(($this->lastHandledTick[$name] ?? -1) === $nowTick){
            return true;
        }
        $this->lastHandledTick[$name] = $nowTick;

        try{
            $bw = BedwarsItems::get(strtolower($key));
        }catch(\Throwable){
            return true;
        }

        if($bw instanceof BedwarsItem){
            $bw->onInteract($session);
        }

        return true;
    }
}