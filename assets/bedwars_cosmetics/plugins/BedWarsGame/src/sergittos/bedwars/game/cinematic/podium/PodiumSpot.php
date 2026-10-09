<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\podium;

use pocketmine\math\Vector3;
use sergittos\bedwars\session\Session;

/**
 * One ranked slot on the podium (1st/2nd/3rd place) within the winning
 * team, together with the in-match stats that earned that rank and the
 * exact block position they stand on top of.
 *
 * Purely a data holder - PodiumBuilder computes standPosition, PodiumCeremony
 * ranks sessions into these and drives the camera/dance/floating text off
 * them.
 */
final class PodiumSpot{

    public function __construct(
        public readonly int $rank,
        public readonly Session $session,
        public readonly int $points,
        public readonly int $kills,
        public readonly int $finalKills,
        public readonly int $bedsBroken,
        public readonly Vector3 $standPosition
    ){}

    public function isMvp() : bool{
        return $this->rank === 1;
    }

    /**
     * Short human-readable summary of what earned this rank, e.g.
     * "7 Kills, 2 Final Kills, 1 Bed" - used for the floating text above
     * the podium and the on-screen labels.
     */
    public function describeStats() : string{
        $parts = [];
        $parts[] = $this->kills . ($this->kills === 1 ? " Kill" : " Kills");
        $parts[] = $this->finalKills . ($this->finalKills === 1 ? " Final Kill" : " Final Kills");
        $parts[] = $this->bedsBroken . ($this->bedsBroken === 1 ? " Bed" : " Beds");
        return implode(", ", $parts);
    }

    public function rankLabel() : string{
        return match($this->rank){
            1 => "1st Place",
            2 => "2nd Place",
            3 => "3rd Place",
            default => "#" . $this->rank
        };
    }

}
