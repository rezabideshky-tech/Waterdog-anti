<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\setup;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\session\Session;

final class ClearVillagersItem extends SetupItem{

    public function __construct(){
        parent::__construct("Remove all villagers");
    }

    public function onInteract(Session $session): void{}

    public function asItem(): Item{
        $item = parent::asItem();
        $item->getNamedTag()->setString("bedwars_name", "remove_all_villagers");
        return $item;
    }

    protected function realItem(): Item{
        return VanillaItems::PAPER();
    }
}