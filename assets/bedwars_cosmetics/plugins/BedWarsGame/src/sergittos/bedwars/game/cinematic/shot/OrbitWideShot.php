<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\shot;

use pocketmine\math\Vector3;

/**
 * A slow, wide 360° orbit above and around the winner(s).
 * The classic "establishing shot" that opens the cinematic loop.
 */
final class OrbitWideShot extends AbstractShot{

    private const DURATION = 10.0;
    private const RADIUS = 8.0;
    private const HEIGHT_ABOVE_EYES = 4.0;

    public function getDuration() : float{
        return self::DURATION;
    }

    public function computeState(Vector3 $center, float $eyeHeight, float $t) : array{
        $angle = $t * 2 * M_PI;

        $position = new Vector3(
            $center->x + self::RADIUS * cos($angle),
            $center->y + $eyeHeight + self::HEIGHT_ABOVE_EYES,
            $center->z + self::RADIUS * sin($angle)
        );

        return [
            'position' => $position,
            'lookAt' => self::eyeTarget($center, $eyeHeight),
        ];
    }

}
