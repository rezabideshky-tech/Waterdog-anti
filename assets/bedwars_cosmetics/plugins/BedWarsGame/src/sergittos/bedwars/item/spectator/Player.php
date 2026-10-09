<?php

namespace sergittos\bedwars\item\spectator;

use pocketmine\player\Player as PMPlayer;

class Player {
    private PMPlayer $player;

    public function __construct(PMPlayer $player) {
        $this->player = $player;
    }

    public function getPlayer(): PMPlayer {
        return $this->player;
    }
}
