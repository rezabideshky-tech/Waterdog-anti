<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Cancellable;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\SessionFactory;
use function strtolower;

final class ItemListener implements Listener{

    /** @var array<string, int> */
    private array $lastHandledTick = [];

    public function onTransaction(InventoryTransactionEvent $event): void{
        foreach($event->getTransaction()->getActions() as $action){
            if($action->getSourceItem()->getNamedTag()->getTag("bedwars_item") !== null){
                $event->cancel();
                return;
            }
        }
    }

    public function onDrop(PlayerDropItemEvent $event): void{
        if($event->getItem()->getNamedTag()->getTag("bedwars_item") !== null){
            $event->cancel();
        }
    }

    public function onUse(PlayerItemUseEvent $event): void{
        $this->handle($event->getPlayer(), $event->getItem(), $event);
    }

    public function onInteract(PlayerInteractEvent $event): void{
        if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            return;
        }
        $this->handle($event->getPlayer(), $event->getItem(), $event);
    }

    private function handle(Player $player, Item $item, Cancellable $event): void{
        if($item->getNamedTag()->getTag("setup") !== null){
            return;
        }

        $tag = $item->getNamedTag()->getTag("bedwars_name");
        if($tag === null){
            return;
        }

        if(!SessionFactory::hasSession($player)){
            return;
        }

        $name = strtolower($player->getName());
        $tick = Server::getInstance()->getTick();
        if(($this->lastHandledTick[$name] ?? -1) === $tick){
            return;
        }
        $this->lastHandledTick[$name] = $tick;

        $session = SessionFactory::getSession($player);
        $key = strtolower((string) $tag->getValue());

        try{
            $bwItem = BedwarsItems::get($key);
        }catch(\Throwable){
            return;
        }

        $bwItem->onInteract($session);

        if($item->getNamedTag()->getTag("bedwars_item") !== null){
            $event->cancel();
        }
    }
}