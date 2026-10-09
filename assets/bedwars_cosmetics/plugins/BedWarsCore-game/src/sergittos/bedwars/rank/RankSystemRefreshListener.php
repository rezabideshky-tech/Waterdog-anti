<?php

declare(strict_types=1);

namespace sergittos\bedwars\rank;

use IvanCraft623\RankSystem\event\UserRankRemoveEvent;
use IvanCraft623\RankSystem\event\UserRankSetEvent;
use pocketmine\event\Listener;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\BedWarsCore;

/**
 * Only ever registered when RankSystem is actually installed (see
 * BedWarsCore::onEnable()). Its whole job is to make a rank change
 * done through RankSystem (command, form, API, ...) show up on
 * BedWars' own nametag/scoreboard immediately instead of only on the
 * player's next join.
 *
 * RankSystem fires UserRankSetEvent/UserRankRemoveEvent BEFORE the
 * change has actually finished writing to the database (that part
 * happens asynchronously right after), so this waits a couple of
 * ticks before reading the rank back through RankSystemBridge - by
 * then RankSystem's own session has finished re-syncing and the
 * bridge will return the new value. If a player disconnects in that
 * window this is a no-op; their nametag will simply be correct next
 * time they join like normal.
 */
final class RankSystemRefreshListener implements Listener {

    private const REFRESH_DELAY_TICKS = 20;

    public function onRankSet(UserRankSetEvent $event): void {
        $this->scheduleRefresh($event->getSession()->getName());
    }

    public function onRankRemove(UserRankRemoveEvent $event): void {
        $this->scheduleRefresh($event->getSession()->getName());
    }

    private function scheduleRefresh(string $username): void {
        $plugin = BedWarsCore::getInstance();
        $plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($username): void {
            $session = BedWarsCore::getInstance()->getSessionManager()->getByName($username);
            if ($session === null) {
                return;
            }
            $session->updateNametag();
            $session->updateScoreboard();
        }), self::REFRESH_DELAY_TICKS);
    }
}
