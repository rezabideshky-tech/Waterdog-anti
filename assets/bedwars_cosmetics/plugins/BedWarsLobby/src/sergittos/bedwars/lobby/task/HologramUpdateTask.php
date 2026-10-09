<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\task;

use pocketmine\scheduler\Task;
use sergittos\bedwars\lobby\manager\LobbyManager;

class HologramUpdateTask extends Task {

    private LobbyManager $manager;

    public function __construct(LobbyManager $manager) {
        $this->manager = $manager;
    }

    public function onRun(): void {
        $this->manager->updateAllHolograms();
    }
}