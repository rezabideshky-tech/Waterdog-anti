<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\setup;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\session\Session;

final class RemoveVillagerItem extends SetupItem{

    public function __construct(){
        parent::__construct("Remove villager");
    }

    public function onInteract(Session $session): void{}

    public function asItem(): Item{
        $item = parent::asItem();
        $item->getNamedTag()->setString("bedwars_name", "remove_villager");
        return $item;
    }

    protected function realItem(): Item{
        return VanillaItems::BLAZE_ROD();
    }
}