<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward\playtime;

/**
 * One step of the stepped/pyramid playtime reward table: at $minutes of
 * *active* (non-AFK) playtime today, the player is granted $coins and $xp.
 * Later tiers are meant to have both bigger rewards and bigger gaps, so
 * staying online longer is progressively more rewarding.
 */
final class PlaytimeTier{

    public function __construct(
        public readonly int $minutes,
        public readonly int $coins,
        public readonly int $xp
    ){}

    public function seconds(): int{
        return $this->minutes * 60;
    }

    public function label(): string{
        if($this->minutes % 60 === 0 && $this->minutes >= 60){
            $hours = intdiv($this->minutes, 60);
            return $hours . " " . ($hours === 1 ? "Hour" : "Hours");
        }
        return $this->minutes . " Minutes";
    }
}
