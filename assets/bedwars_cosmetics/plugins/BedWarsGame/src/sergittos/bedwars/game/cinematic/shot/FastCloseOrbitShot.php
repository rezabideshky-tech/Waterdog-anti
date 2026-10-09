<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\shot;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use sergittos\bedwars\game\cinematic\camera\CinematicCamera;

/**
 * The "climax" shot: a fast, tight orbit at near eye-level with a subtle
 * vertical bob and a light camera shake kicked off when the shot starts.
 */
final class FastCloseOrbitShot extends AbstractShot{

    private const DURATION = 8.0;
    private const RADIUS = 3.0;
    private const REVOLUTIONS = 1.5;
    private const BOB_AMPLITUDE = 0.3;

    public function getDuration() : float{
        return self::DURATION;
    }

    public function onStart(Player $player) : void{
        CinematicCamera::shake($player, 0.12, 0.4);
    }

    public function computeState(Vector3 $center, float $eyeHeight, float $t) : array{
        $angle = $t * self::REVOLUTIONS * 2 * M_PI;
        $bob = self::BOB_AMPLITUDE * sin($angle * 3);

        $position = new Vector3(
            $center->x + self::RADIUS * cos($angle),
            $center->y + $eyeHeight + $bob,
            $center->z + self::RADIUS * sin($angle)
        );

        return [
            'position' => $position,
            'lookAt' => self::eyeTarget($center, $eyeHeight),
        ];
    }

}
