<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward\task;

use pocketmine\scheduler\Task;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\reward\NotificationService;
use sergittos\bedwars\reward\playtime\PlaytimeRewardManager;

/**
 * One single repeating task backs the whole reward system on purpose,
 * instead of one task per feature - fewer scheduler entries, one place to
 * see the total cost, and a guarantee that the notification queue always
 * gets drained even if a future feature forgets to schedule its own tick.
 *
 * Runs every server tick so the notification queue drains promptly, but
 * only does the (slightly heavier) playtime accounting once a second -
 * everything else here is O(online players) array access, not I/O.
 */
final class RewardHeartbeatTask extends Task{

    private const PLAYTIME_INTERVAL_TICKS = 20;

    private int $ticks = 0;

    public function __construct(
        private BedWarsCore $plugin,
        private NotificationService $notifications,
        private PlaytimeRewardManager $playtime
    ){}

    public function onRun(): void{
        $this->notifications->tick();

        $this->ticks++;
        if($this->ticks < self::PLAYTIME_INTERVAL_TICKS){
            return;
        }
        $elapsed = $this->ticks;
        $this->ticks = 0;

        $this->playtime->tick((int) ($elapsed / 20), $this->plugin->getSessionManager()->getAll());
    }
}
