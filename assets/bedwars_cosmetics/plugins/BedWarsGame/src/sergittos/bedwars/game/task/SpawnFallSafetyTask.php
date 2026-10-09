<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\task;

use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\Position;
use pocketmine\world\World;
use sergittos\bedwars\game\BedWarsGame;
use sergittos\bedwars\session\Session;

/**
 * Safety-net against a player falling underground right after being
 * teleported to their team spawn at the very start of a match.
 *
 * Game::teleportToTeamSpawn() (see its doc comment for the full
 * explanation) now makes sure the chunk under a team spawn is actually
 * loaded before teleporting there in the first place, which is the real
 * fix for players randomly falling through their island at match start.
 * This task is what's left over as a last-resort net underneath that fix:
 * the session is given brief spawn protection right at the teleport (see
 * Game::finishTeamSpawnTeleport() / Session::grantSpawnProtection() /
 * GameListener::onReceiveDamage()) and this task watches the player's
 * position for the next second or two, snapping them back if they ended
 * up well below where they should be - so even some still-unknown edge
 * case slipping past the chunk check can't turn into fall/void damage or
 * a death. Spawn protection is only ever cleared from here, once this
 * task is done watching, so there's no path where a session is left
 * protected indefinitely.
 *
 * Only ever started for that one initial team-spawn teleport - not for
 * any other teleport during the match.
 */
final class SpawnFallSafetyTask extends Task{

    /** Checked this often while watching (in ticks). */
    private const PERIOD = 4;

    /** Stop watching after this many checks even if nothing ever looked wrong - PERIOD * MAX_RUNS ticks total. */
    private const MAX_RUNS = 10;

    /** Horizontal (X/Z) drift below this is assumed to be "still at spawn", not the player walking off. */
    private const MAX_HORIZONTAL_DRIFT = 2.0;

    /** Only correct once the player is at least this far below the expected spawn. */
    private const MIN_VERTICAL_DROP = 4.0;

    private ?TaskHandler $handler = null;

    private int $runs = 0;

    public static function start(Session $session, Vector3 $expectedSpawn, World $world): void{
        $task = new self($session, $expectedSpawn, $world);
        $task->handler = BedWarsGame::getInstance()->getScheduler()->scheduleRepeatingTask($task, self::PERIOD);
    }

    private function __construct(
        private readonly Session $session,
        private readonly Vector3 $expectedSpawn,
        private readonly World $world
    ){}

    public function onRun(): void{
        $this->runs++;

        if($this->finishIfDone()){
            return;
        }

        $this->check();

        if($this->runs >= self::MAX_RUNS){
            $this->stopWatching();
        }
    }

    /**
     * @return bool true if the task already stopped caring (player gone,
     *              dead, or no longer in the arena world) - watching is
     *              cancelled immediately in that case instead of waiting
     *              for MAX_RUNS.
     */
    private function finishIfDone(): bool{
        $player = $this->session->getPlayer();

        if(!$player->isConnected() || !$player->isAlive() || $player->getWorld() !== $this->world){
            $this->stopWatching();
            return true;
        }

        return false;
    }

    private function check(): void{
        $player = $this->session->getPlayer();
        $position = $player->getPosition();

        $horizontalDrift = (new Vector3($position->getX(), 0, $position->getZ()))
            ->distance(new Vector3($this->expectedSpawn->getX(), 0, $this->expectedSpawn->getZ()));

        if($horizontalDrift >= self::MAX_HORIZONTAL_DRIFT){
            // Not underground - the player actually walked away from spawn.
            return;
        }

        $verticalDrop = $this->expectedSpawn->getY() - $position->getY();
        if($verticalDrop <= self::MIN_VERTICAL_DROP){
            return;
        }

        $player->teleport(Position::fromObject($this->expectedSpawn, $this->world));
        $player->setMotion(Vector3::zero());
    }

    /**
     * Cancels the repeating task and, crucially, hands spawn protection
     * back off - this is the only place that clears it, so however this
     * task ends (player left, died, MAX_RUNS reached, ...) the session is
     * never left protected longer than this brief watch window.
     */
    private function stopWatching(): void{
        $this->handler?->cancel();
        $this->session->clearSpawnProtection();
    }
}
