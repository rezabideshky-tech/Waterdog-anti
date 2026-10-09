<?php

declare(strict_types=1);

namespace sergittos\bedwars\rank;

use pocketmine\event\Listener;
use pocketmine\event\plugin\PluginEnableEvent;
use pocketmine\plugin\PluginBase;

/**
 * BedWarsCore loads at the STARTUP phase (see plugin.yml) so that it's
 * ready before worlds load; RankSystem has no "load:" override, so it
 * loads at the default POSTWORLD phase. PocketMine always finishes
 * enabling every STARTUP-phase plugin before it enables any POSTWORLD
 * one, no matter what depend/softdepend say - so at the exact moment
 * BedWarsCore::onEnable() runs, RankSystem (if installed at all) is
 * guaranteed to NOT be enabled yet. A plain "is RankSystem available
 * right now?" check at that point would always say no, even on a
 * server that has RankSystem installed.
 *
 * This watches for RankSystem's own PluginEnableEvent instead, which
 * fires later regardless of phase ordering, and only registers
 * RankSystemRefreshListener (the one that type-hints RankSystem's
 * event classes) once RankSystem's classes are actually loaded and
 * safe to reflect on. If RankSystem was somehow already enabled by
 * the time this runs, it registers immediately instead of waiting for
 * an event that already happened.
 */
final class RankSystemAvailabilityWatcher implements Listener {

    private bool $registered = false;

    public function __construct(private readonly PluginBase $owner) {
    }

    public function tryRegisterNow(): void {
        if ($this->registered) {
            return;
        }
        if (!RankSystemBridge::isAvailable()) {
            return;
        }
        $this->registered = true;
        $this->owner->getServer()->getPluginManager()->registerEvents(new RankSystemRefreshListener(), $this->owner);
    }

    public function onPluginEnable(PluginEnableEvent $event): void {
        if ($this->registered) {
            return;
        }
        if ($event->getPlugin()->getName() !== "RankSystem") {
            return;
        }
        $this->tryRegisterNow();
    }
}
