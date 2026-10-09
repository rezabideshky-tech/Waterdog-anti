<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\shot;

use pocketmine\math\Vector3;
use pocketmine\player\Player;

/**
 * One "shot" in the victory cinematic sequence: a pure function of time
 * that returns where the camera should be and what it should look at.
 *
 * $t is normalized progress through the shot, in range [0, 1].
 * $center is the current world position the camera orbits/moves around
 * (the winner's position, or the live centroid of a winning team).
 * $eyeHeight is the winner's eye height, used to aim at their face.
 */
interface Shot{

    /** Duration of this shot, in seconds. */
    public function getDuration() : float;

    /**
     * @return array{position: Vector3, lookAt: Vector3}
     */
    public function computeState(Vector3 $center, float $eyeHeight, float $t) : array;

    /** Called once, right when this shot becomes active (e.g. to trigger a shake). */
    public function onStart(Player $player) : void;

}
