<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\task;

use pocketmine\scheduler\Task;
use sergittos\bedwars\lobby\BedWarsLobby;

class LeaderboardUpdateTask extends Task {

    public function __construct(private BedWarsLobby $plugin) {}

    public function onRun(): void {
        $this->plugin->getLeaderboardManager()->refreshAll();
    }
}
