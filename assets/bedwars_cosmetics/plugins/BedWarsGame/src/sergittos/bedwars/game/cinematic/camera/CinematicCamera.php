<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\camera;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\CameraInstructionPacket;
use pocketmine\network\mcpe\protocol\CameraPresetsPacket;
use pocketmine\network\mcpe\protocol\CameraShakePacket;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstructionColor;
use pocketmine\network\mcpe\protocol\types\camera\CameraFadeInstructionTime;
use pocketmine\network\mcpe\protocol\types\camera\CameraPreset;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstruction;
use pocketmine\network\mcpe\protocol\types\camera\CameraSetInstructionRotation;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Minimal, dependency-free driver for the Bedrock camera protocol
 * (CameraPresetsPacket / CameraInstructionPacket / CameraShakePacket),
 * built directly against this server's actual protocol classes instead
 * of a third-party wrapper library.
 *
 * Only what the BedWars victory cinematic actually needs: one custom
 * "free" camera preset, positioning/looking the camera, fading, shaking,
 * and clearing.
 *
 * We register exactly ONE preset per player (index 0). The Bedrock
 * client rebuilds its preset list from whatever CameraPresetsPacket it
 * last received, so CameraSetInstruction's numeric preset ID only needs
 * to be consistent with what *we* sent - it doesn't need to match
 * vanilla's preset ordering.
 */
final class CinematicCamera{

    private const FREE_PRESET_INDEX = 0;

    /**
     * Sends this player the single custom "free" camera preset they'll
     * need for the cinematic. Called automatically by
     * VictoryCinematicController::start() right before the orbit begins.
     */
    public static function sendPreset(Player $player) : void{
        // Must be named exactly "minecraft:free" (not a custom identifier) -
        // the Bedrock client appears to special-case this specific vanilla
        // preset name to enable true detached/noclip camera rendering.
        // A custom name still registers without error, but the client
        // doesn't know how to render it and the screen just stays black.
        $preset = new CameraPreset(
            "minecraft:free", // name
            "",                // parent
            0.0, 0.0, 0.0,     // x, y, z
            0.0, 0.0,          // pitch, yaw
            null,              // rotationSpeed
            null,              // snapToTarget
            null, null,        // horizontalRotationLimit, verticalRotationLimit
            null,              // continueTargeting
            null,              // blockListeningRadius
            null,              // viewOffset
            null,              // entityOffset
            null,              // radius
            null, null,        // yawLimitMin, yawLimitMax
            CameraPreset::AUDIO_LISTENER_TYPE_CAMERA,
            null,              // playerEffects
            null,              // alignTargetAndCameraForward
            null,              // aimAssist
            null,              // controlScheme
        );

        $player->getNetworkSession()->sendDataPacket(CameraPresetsPacket::create([$preset]));
    }

    /**
     * Moves the detached camera to $position, looking at $lookAt.
     */
    public static function setPosition(Player $player, Vector3 $position, Vector3 $lookAt) : void{
        [$pitch, $yaw] = self::rotationTowards($position, $lookAt);

        $instruction = new CameraSetInstruction(
            self::FREE_PRESET_INDEX,
            null, // ease
            $position,
            new CameraSetInstructionRotation($pitch, $yaw),
            null, // facingPosition
            null, // viewOffset
            null, // entityOffset
            null, // default
            false // ignoreStartingValuesComponent
        );

        $player->getNetworkSession()->sendDataPacket(
            CameraInstructionPacket::create(...self::buildInstructionArgs($instruction, null, null))
        );
    }

    /**
     * Fades the player's screen to a color and back.
     *
     * @param float[] $rgb [r, g, b], each 0.0-1.0. Defaults to black.
     */
    public static function fade(Player $player, float $fadeIn, float $stay, float $fadeOut, array $rgb = [0.0, 0.0, 0.0]) : void{
        $instruction = new CameraFadeInstruction(
            new CameraFadeInstructionTime($fadeIn, $stay, $fadeOut),
            new CameraFadeInstructionColor($rgb[0], $rgb[1], $rgb[2])
        );

        $player->getNetworkSession()->sendDataPacket(
            CameraInstructionPacket::create(...self::buildInstructionArgs(null, null, $instruction))
        );
    }

    /**
     * Shakes the player's camera.
     */
    public static function shake(
        Player $player,
        float $intensity = 0.5,
        float $duration = 1.0,
        int $type = CameraShakePacket::TYPE_POSITIONAL
    ) : void{
        $player->getNetworkSession()->sendDataPacket(CameraShakePacket::create(
            $intensity,
            $duration,
            $type,
            CameraShakePacket::ACTION_ADD
        ));
    }

    /**
     * Clears all active camera instructions and returns the player to
     * normal first-person control.
     *
     * Sent with $immediate = true (bypassing the normal per-tick batch
     * send buffer) because this is almost always called right before the
     * player gets teleported to the hub or transferred to another server
     * (see EndingStage). Queuing it normally and letting a
     * teleport/transfer happen in the same tick risked the transfer
     * closing/repurposing the session before the batched clear packet
     * was actually flushed to the client, leaving them stuck in the
     * detached cinematic camera even after arriving back in the lobby.
     */
    public static function clear(Player $player) : void{
        $player->getNetworkSession()->sendDataPacket(
            CameraInstructionPacket::create(...self::buildInstructionArgs(null, true, null)),
            true
        );
    }

    /**
     * Builds the full positional argument list for
     * CameraInstructionPacket::create() from its *actual* parameter list
     * instead of a hardcoded count.
     *
     * Why this exists: different PocketMine-MP forks (and different
     * versions of the same fork) have shipped CameraInstructionPacket
     * with a different number of constructor parameters as Mojang keeps
     * adding new instruction types to the Bedrock camera protocol (e.g.
     * $target/$removeTarget were added after this code was first
     * written). A hardcoded "5 positional args" call broke the moment the
     * server updated to a build that requires 6+, crashing with
     * "ArgumentCountError: Too few arguments ... exactly 6 expected".
     *
     * The three instructions this class actually uses ($set, $clear,
     * $fade) have been the first three parameters since this packet was
     * introduced and every field added since has been appended after
     * them, never inserted in between - so filling those first three
     * positionally and then padding whatever the fork added after them
     * (with null, or false for non-nullable bool flags) stays correct
     * across protocol/fork updates without needing to track every new
     * field by name.
     *
     * @return array<int, mixed>
     */
    private static function buildInstructionArgs(
        ?CameraSetInstruction $set,
        ?bool $clear,
        ?CameraFadeInstruction $fade
    ) : array{
        static $parameters = null;
        if($parameters === null){
            $parameters = (new ReflectionMethod(CameraInstructionPacket::class, "create"))->getParameters();
        }

        $known = [$set, $clear, $fade];
        $args = [];

        foreach($parameters as $index => $parameter){
            if($index < count($known)){
                $args[] = $known[$index];
                continue;
            }

            if($parameter->allowsNull()){
                $args[] = null;
            }elseif($parameter->isDefaultValueAvailable()){
                $args[] = $parameter->getDefaultValue();
            }elseif($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() === "bool"){
                $args[] = false;
            }else{
                // No sensible fallback for a required, non-nullable,
                // no-default parameter of an unknown type - leave it
                // unset so PHP raises a clear "missing argument" error
                // instead of silently sending a malformed packet.
                continue;
            }
        }

        return $args;
    }

    /**
     * Same pitch/yaw-from-two-points math the bundled CameraAPI library
     * used: computes the rotation a camera at $from would need to look
     * directly at $to.
     *
     * @return array{0: float, 1: float} [pitch, yaw] in degrees.
     */
    private static function rotationTowards(Vector3 $from, Vector3 $to) : array{
        $xDist = $to->x - $from->x;
        $zDist = $to->z - $from->z;

        $horizontal = sqrt($xDist ** 2 + $zDist ** 2);
        $vertical = $to->y - $from->y;
        $pitch = $horizontal > 0.0
            ? -atan2($vertical, $horizontal) / M_PI * 180
            : ($vertical > 0.0 ? -90.0 : 90.0);

        $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90.0;
        if($yaw < 0.0){
            $yaw += 360.0;
        }

        return [$pitch, $yaw];
    }

}
