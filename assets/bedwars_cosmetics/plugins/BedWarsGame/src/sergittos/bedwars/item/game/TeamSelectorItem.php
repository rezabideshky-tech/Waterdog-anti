<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use sergittos\bedwars\gui\TeamSelectorGui;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\session\Session;

final class TeamSelectorItem extends BedwarsItem{

    private ?DyeColor $color = null;

    public function __construct(){
        parent::__construct("{AQUA}{BOLD}Team Selector");
    }

    public function setColor(DyeColor $color) : self{
        $this->color = $color;
        return $this;
    }

    public function onInteract(Session $session) : void{
        (new TeamSelectorGui())->open($session->getPlayer());
    }

    protected function realItem() : Item{
        $color = $this->color ?? DyeColor::WHITE();
        return VanillaBlocks::WOOL()->setColor($color)->asItem();
    }
}