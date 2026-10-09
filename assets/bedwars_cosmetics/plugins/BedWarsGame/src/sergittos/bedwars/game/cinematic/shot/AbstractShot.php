<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\shot;

use pocketmine\math\Vector3;
use pocketmine\player\Player;

abstract class AbstractShot implements Shot{

    public function onStart(Player $player) : void{
        // no-op by default
    }

    /** Smoothstep easing, nicer than raw linear for push/descent shots. */
    protected static function ease(float $t) : float{
        $t = max(0.0, min(1.0, $t));
        return $t * $t * (3.0 - 2.0 * $t);
    }

    protected static function lerp(float $from, float $to, float $t) : float{
        return $from + ($to - $from) * $t;
    }

    protected static function eyeTarget(Vector3 $center, float $eyeHeight) : Vector3{
        return new Vector3($center->x, $center->y + $eyeHeight, $center->z);
    }

}
