<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\setup;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\session\Session;

final class SetWaitingSpawnItem extends SetupItem{

    public function __construct(){
        parent::__construct("Set waiting spawn");
    }

    public function onInteract(Session $session): void{}

    public function asItem(): Item{
        $item = parent::asItem();
        $item->getNamedTag()->setString("bedwars_name", "waiting_spawn");
        return $item;
    }

    protected function realItem(): Item{
        return VanillaItems::COMPASS();
    }
}