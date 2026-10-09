<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward\streak;

/**
 * A special reward at a specific streak day (e.g. the weekly day-7 bonus or
 * the day-11 XP Boost perk). Milestones are looked up by exact day number.
 */
final class StreakMilestone{

    public function __construct(
        public readonly int $day,
        public readonly string $label,
        public readonly int $coins,
        public readonly int $xp,
        public readonly bool $grantsFreeze = false,
        public readonly ?string $perk = null
    ){}
}
