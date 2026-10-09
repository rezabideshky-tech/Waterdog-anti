<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\game\BedWarsGame;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\SessionFactory;
use function strtolower;

/**
 * Why this listener exists:
 *
 * Spectators here are put into a real pocketmine GameMode::SPECTATOR(), and
 * the engine's own network handler drops item-use packets for that gamemode
 * before a PlayerItemUseEvent/PlayerInteractEvent is ever fired - matching
 * vanilla Bedrock, where a spectator cannot "use" a held item. Because of
 * that, ItemListener/SpectatorProtectionListener (which both listen for
 * those events) never get called for a true spectator, so none of the
 * spectator items (Teleporter, Play Again, Spectator Settings, Return to
 * Lobby, ...) ever open their menu - the click simply never reaches any
 * plugin code.
 *
 * This listener reads the raw InventoryTransactionPacket at
 * DataPacketReceiveEvent, which fires before the engine's internal handler
 * gets a chance to drop it, and manually runs the same BedwarsItem::onInteract
 * logic the other listeners already use. It only ever *adds* this one extra
 * hook for spectators clicking their own items - it never cancels the
 * packet and never touches movement/visibility/noclip, so spectator
 * gamemode itself, and every other item, keep working exactly as before.
 */
final class SpectatorRawItemUseListener implements Listener{

    /** @var array<string, int> */
    private array $lastHandledTick = [];

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void{
        // Defensive by design: this reaches into a raw protocol packet whose exact
        // shape can vary between engine versions. Any mismatch here must never be
        // able to crash a packet-handling call for a real player, so the whole
        // detection is wrapped and simply does nothing on failure - normal item
        // use (ItemListener/SpectatorProtectionListener) and spectator gamemode
        // itself are completely unaffected either way.
        try{
            $this->tryHandle($event);
        }catch(\Throwable){
            // Swallow - never let a protocol mismatch affect packet processing.
        }
    }

    private function tryHandle(DataPacketReceiveEvent $event): void{
        $packet = $event->getPacket();
        if(!$packet instanceof InventoryTransactionPacket){
            return;
        }

        $data = $packet->trData;
        if(!$data instanceof UseItemTransactionData){
            return;
        }

        // Only right-click "use" actions open a menu - ignore break-block
        // transactions so we never react to something that wasn't a use.
        if($data->getActionType() === UseItemTransactionData::ACTION_BREAK_BLOCK){
            return;
        }

        $player = $event->getOrigin()->getPlayer();
        if(!$player instanceof Player || $player->getGamemode() !== GameMode::SPECTATOR()){
            return;
        }

        if(!SessionFactory::hasSession($player)){
            return;
        }

        $item = $player->getInventory()->getItemInHand();
        $tag = $item->getNamedTag()->getTag("bedwars_name");
        if($tag === null){
            return;
        }

        $tick = Server::getInstance()->getTick();
        $name = strtolower($player->getName());
        if(($this->lastHandledTick[$name] ?? -1) === $tick){
            return;
        }
        $this->lastHandledTick[$name] = $tick;

        try{
            $bwItem = BedwarsItems::get(strtolower((string) $tag->getValue()));
        }catch(\Throwable){
            return;
        }

        if($bwItem instanceof BedwarsItem){
            $session = SessionFactory::getSession($player);
            BedWarsGame::getInstance()->getScheduler()->scheduleTask(new class($bwItem, $session) extends \pocketmine\scheduler\Task{
                public function __construct(private BedwarsItem $item, private \sergittos\bedwars\session\Session $session){}
                public function onRun(): void{
                    try{
                        $this->item->onInteract($this->session);
                    }catch(\Throwable){
                        // Never let a stale reference (player left, session gone) throw.
                    }
                }
            });
        }
    }
}
