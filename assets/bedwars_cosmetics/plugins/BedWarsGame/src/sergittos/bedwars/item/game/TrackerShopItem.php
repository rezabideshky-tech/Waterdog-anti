<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\form\tracker\TrackerShopForm;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\session\Session;

final class TrackerShopItem extends BedwarsItem{

    public function __construct(){
        // false = don't lock this item in place. Every other BedwarsItem stays
        // locked (can't be moved/dropped) by default, but the tracker is meant
        // to behave like a normal inventory item - the player can drag it
        // around their inventory or drop it just like the wooden sword.
        parent::__construct("Tracker Shop", false);
    }

    public function onInteract(Session $session) : void{
        $session->getPlayer()->sendForm(new TrackerShopForm($session));
    }

    protected function realItem() : Item{
        return VanillaItems::COMPASS();
    }
}