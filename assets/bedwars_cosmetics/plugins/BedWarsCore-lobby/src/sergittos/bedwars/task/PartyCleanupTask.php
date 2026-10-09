<?php

declare(strict_types=1);

namespace sergittos\bedwars\task;

use pocketmine\scheduler\Task;
use sergittos\bedwars\BedWarsCore;

class PartyCleanupTask extends Task {

    public function __construct(private BedWarsCore $plugin) {}

    public function onRun(): void {
        $this->plugin->getPartyManager()->tickCleanup();
    }
}
