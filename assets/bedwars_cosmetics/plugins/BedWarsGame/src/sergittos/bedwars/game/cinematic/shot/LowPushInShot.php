<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\shot;

use pocketmine\math\Vector3;

/**
 * A dramatic low-angle hero shot: starts far away near ground level
 * and eases in toward eye level, with a slight drifting orbit for
 * a "movie trailer" push-in feel.
 */
final class LowPushInShot extends AbstractShot{

    private const DURATION = 6.0;
    private const START_RADIUS = 10.0;
    private const END_RADIUS = 3.0;
    private const START_HEIGHT = 0.6;
    private const END_HEIGHT = 1.4;
    private const BASE_ANGLE = M_PI / 4;
    private const ANGLE_DRIFT = 0.6;

    public function getDuration() : float{
        return self::DURATION;
    }

    public function computeState(Vector3 $center, float $eyeHeight, float $t) : array{
        $eased = self::ease($t);

        $radius = self::lerp(self::START_RADIUS, self::END_RADIUS, $eased);
        $height = self::lerp(self::START_HEIGHT, self::END_HEIGHT, $eased);
        $angle = self::BASE_ANGLE + self::ANGLE_DRIFT * $t;

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
