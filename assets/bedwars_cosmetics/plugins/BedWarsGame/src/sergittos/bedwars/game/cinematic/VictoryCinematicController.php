<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\game\cinematic\camera\CinematicCamera;
use sergittos\bedwars\game\cinematic\shot\FastCloseOrbitShot;
use sergittos\bedwars\game\cinematic\shot\LowPushInShot;
use sergittos\bedwars\game\cinematic\shot\OrbitWideShot;
use sergittos\bedwars\game\cinematic\shot\Shot;
use sergittos\bedwars\game\cinematic\shot\SpiralDescentShot;

/**
 * Runs the victory orbit camera for a single spectator, following one
 * winning player or, for a team win, the live centroid of every online
 * team member - so the camera keeps tracking the winner(s) even if they
 * walk around during the ending countdown.
 *
 * Outside spectators cycle through the full shot sequence (wide orbit ->
 * low push-in -> spiral descent -> fast close orbit -> repeat).
 *
 * A winner watching their own self-view camera instead cycles through
 * only the two close, near-eye-level shots (fast close orbit and low
 * push-in) - a tight, stable third-person view right around themselves,
 * rather than the far/high establishing shots meant for showing off the
 * whole scene to an outside spectator. That keeps it readable and
 * pleasant to watch your own Victory Dance play out, instead of a
 * disorienting wide drone shot 12 blocks above your own head.
 */
final class VictoryCinematicController{

    private const TICKS_PER_SECOND = 20;
    private const DEFAULT_EYE_HEIGHT = 1.62;

    /** @var Shot[] */
    private array $shots;

    private int $shotIndex = 0;
    private int $shotStartTick = 0;
    private int $elapsedTicks = 0;

    private ?TaskHandler $task = null;
    private int $actionBarTicks = 0;

    /**
     * Real (invisible/noclip) body position offset from the winner
     * centroid, in a horizontal ring a few blocks out - see
     * syncSpectatorPosition() for why this exists.
     */
    private const BODY_OFFSET_RADIUS = 4.0;
    private readonly float $bodyOffsetAngleRad;

    /**
     * @param Player[] $winners
     * @param bool $selfView True when $spectator is themselves one of the
     *                       winners watching their own Victory Dance,
     *                       rather than an outside spectator. Suppresses
     *                       the "X has won the game" title and the
     *                       "SPECTATING X" action bar, which don't make
     *                       sense addressed to the winner themselves,
     *                       switches to the close third-person shot
     *                       pair described above, and drives the same
     *                       detached camera so they can actually see the
     *                       dance play on their own player model.
     */
    public function __construct(
        private readonly Plugin $plugin,
        private readonly Player $spectator,
        private array $winners,
        private readonly string $label,
        private readonly bool $selfView = false
    ){
        $this->shots = $this->selfView
            ? [
                new FastCloseOrbitShot(),
                new LowPushInShot(),
            ]
            : [
                new OrbitWideShot(),
                new LowPushInShot(),
                new SpiralDescentShot(),
                new FastCloseOrbitShot(),
            ];

        // Deterministic per-controller angle (stable for the lifetime of
        // this controller instance) so every viewer currently watching the
        // cinematic - every outside spectator, plus every online member of
        // a winning TEAM watching their own self-view - gets spread around
        // the winner centroid on a small ring instead of all landing on
        // the exact same point. See syncSpectatorPosition().
        $this->bodyOffsetAngleRad = (spl_object_id($spectator) % 360) * (M_PI / 180.0);
    }

    /**
     * @param Player[] $winners
     */
    public function updateWinners(array $winners) : void{
        $this->winners = $winners;
    }

    public function start() : void{
        $this->shotIndex = 0;
        $this->shotStartTick = 0;
        $this->elapsedTicks = 0;
        $this->actionBarTicks = 0;

        $this->syncSpectatorPosition();

        CinematicCamera::sendPreset($this->spectator);
        $this->shots[$this->shotIndex]->onStart($this->spectator);

        if($this->selfView){
            $this->spectator->sendTitle(
                TF::AQUA . TF::BOLD . "VICTORY",
                TF::YELLOW . "Watch yourself celebrate!",
                10,
                50,
                20
            );
        }else{
            $this->spectator->sendTitle(
                TF::AQUA . TF::BOLD . "VICTORY",
                TF::YELLOW . $this->label . TF::GRAY . " has won the game",
                10,
                50,
                20
            );
        }

        $this->task = $this->plugin->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() : void{
                $this->tick();
            }),
            1
        );
    }

    public function stop() : void{
        $this->task?->cancel();
        $this->task = null;

        if($this->spectator->isConnected()){
            CinematicCamera::clear($this->spectator);
        }
    }

    private function currentCenter() : ?Vector3{
        $x = 0.0;
        $y = 0.0;
        $z = 0.0;
        $count = 0;

        foreach($this->winners as $winner){
            if(!$winner->isConnected()){
                continue;
            }
            $pos = $winner->getPosition();
            $x += $pos->x;
            $y += $pos->y;
            $z += $pos->z;
            $count++;
        }

        if($count === 0){
            return null;
        }

        return new Vector3($x / $count, $y / $count, $z / $count);
    }

    private function currentEyeHeight() : float{
        foreach($this->winners as $winner){
            if($winner->isConnected()){
                return $winner->getEyeHeight();
            }
        }
        return self::DEFAULT_EYE_HEIGHT;
    }

    /**
     * The victory camera only overrides what's *rendered* on the
     * spectator's screen (CameraInstructionPacket) - it never moves
     * their actual server-tracked entity position. Entity visibility
     * (who gets sent the AddPlayerPacket for whom) is governed purely by
     * that real position via chunk-based view tracking, completely
     * independent of the fake detached camera view.
     *
     * Without this, a spectator whose real body is still sitting wherever
     * they died/were spectating from - often nowhere near the winner -
     * would have the winner's entity never actually sent to their client
     * at all, making the winner invisible throughout the whole cinematic
     * despite the camera correctly orbiting an empty spot in the world.
     *
     * Silently teleporting the spectator's real (already invisible,
     * noclip) body to the winner fixes that, and is imperceptible to the
     * player since CameraInstructionPacket fully overrides what they see
     * regardless of where their real entity is standing.
     *
     * The real body is placed a few blocks off the exact centroid (see
     * BODY_OFFSET_RADIUS / bodyOffsetAngleRad) rather than landing exactly
     * on top of it. Two real entities occupying the same point both left
     * the winner's own model visibly "double up" for anyone whose client
     * doesn't render the invisibility flag on their own player entity
     * (e.g. the winner watching their own self-view close-up shots, or
     * multiple online members of a winning team all orbiting the same
     * centroid) - offsetting each viewer around a small ring keeps every
     * real body clear of the winner's hitbox regardless of that.
     */
    private function syncSpectatorPosition() : void{
        if(!$this->spectator->isConnected()){
            return;
        }

        $center = $this->currentCenter();
        if($center === null){
            return;
        }

        $offset = new Vector3(
            $center->x + cos($this->bodyOffsetAngleRad) * self::BODY_OFFSET_RADIUS,
            $center->y,
            $center->z + sin($this->bodyOffsetAngleRad) * self::BODY_OFFSET_RADIUS
        );

        // teleport(Vector3) keeps the spectator in their current world -
        // they and the winner(s) are always in the same match world here,
        // so there's nothing further to resolve.
        $this->spectator->teleport($offset);
    }

    private function tick() : void{
        if(!$this->spectator->isConnected()){
            $this->stop();
            return;
        }

        $center = $this->currentCenter();
        if($center === null){
            // Every winner disconnected - nothing left to orbit around.
            $this->stop();
            return;
        }

        $eyeHeight = $this->currentEyeHeight();

        $shot = $this->shots[$this->shotIndex];
        $durationTicks = max(1, (int) round($shot->getDuration() * self::TICKS_PER_SECOND));
        $localTick = $this->elapsedTicks - $this->shotStartTick;

        if($localTick >= $durationTicks){
            $this->shotIndex = ($this->shotIndex + 1) % count($this->shots);
            $this->shotStartTick = $this->elapsedTicks;
            $localTick = 0;
            $shot = $this->shots[$this->shotIndex];

            $this->syncSpectatorPosition();
            CinematicCamera::fade($this->spectator, 0.12, 0.0, 0.12);
            $shot->onStart($this->spectator);
        }

        $durationTicks = max(1, (int) round($shot->getDuration() * self::TICKS_PER_SECOND));
        $t = min(1.0, $localTick / $durationTicks);
        $state = $shot->computeState($center, $eyeHeight, $t);

        CinematicCamera::setPosition($this->spectator, $state['position'], $state['lookAt']);

        $this->actionBarTicks++;
        if($this->actionBarTicks >= self::TICKS_PER_SECOND * 4){
            $this->actionBarTicks = 0;
            if($this->selfView){
                $this->spectator->sendActionBarMessage(
                    TF::AQUA . TF::BOLD . "VICTORY DANCE"
                );
            }else{
                $this->spectator->sendActionBarMessage(
                    TF::AQUA . TF::BOLD . "SPECTATING " . TF::RESET . TF::GOLD . $this->label
                );
            }
        }

        $this->elapsedTicks += 1;
    }

}
