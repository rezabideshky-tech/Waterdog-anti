<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\session\Session;

class ModLeaveItem extends BedwarsItem {

    public function __construct() {
        parent::__construct("{RED}Leave (Return to Lobby)");
    }

    public function onInteract(Session $session): void {
        if ($session->getGame() !== null && $session->isSpectator()) {
            $session->getGame()->removeSpectator($session);
            return;
        }
        $session->setGame(null);
        $session->setTeam(null);
        $session->teleportToHub();
    }

    protected function realItem(): Item {
        return VanillaBlocks::BED()->setColor(DyeColor::RED())->asItem();
    }

}
