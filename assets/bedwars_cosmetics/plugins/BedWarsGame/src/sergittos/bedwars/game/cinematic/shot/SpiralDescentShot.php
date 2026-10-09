<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\shot;

use pocketmine\math\Vector3;

/**
 * A two-turn spiral that starts high above the winner(s) and corkscrews
 * down toward them, radius shrinking as it descends.
 */
final class SpiralDescentShot extends AbstractShot{

    private const DURATION = 8.0;
    private const START_RADIUS = 6.0;
    private const END_RADIUS = 2.0;
    private const START_HEIGHT = 12.0;
    private const END_HEIGHT = 4.0;
    private const TURNS = 2.0;

    public function getDuration() : float{
        return self::DURATION;
    }

    public function computeState(Vector3 $center, float $eyeHeight, float $t) : array{
        $angle = $t * self::TURNS * 2 * M_PI;
        $radius = self::lerp(self::START_RADIUS, self::END_RADIUS, $t);
        $height = self::lerp(self::START_HEIGHT, self::END_HEIGHT, $t);

        $position = new Vector3(
            $center->x + $radius * cos($angle),
            $center->y + $height,
            $center->z + $radius * sin($angle)
        );

        return [
            'position' => $position,
            'lookAt' => self::eyeTarget($center, $eyeHeight),
        ];
    }

}
