<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\spectator;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\game\Matchmaker;
use sergittos\bedwars\session\Session;

class PlayAgainItem extends SpectatorItem {

    public function __construct() {
        parent::__construct("{GREEN}Play Again");
    }

    protected function onSpectatorInteract(Session $session): void {
        $player = $session->getPlayer();

        $game = $session->getGame();
        if($game !== null){
            $game->leaveSpectating($session);
        }

        $session->clearAllInventories();
        $session->setTrackingSession(null);

        $session->message("{YELLOW}Searching for a new match...");
        Matchmaker::queue($player);
    }

    protected function realItem(): Item {
        return VanillaItems::PAPER();
    }
}