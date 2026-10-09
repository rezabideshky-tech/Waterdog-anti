<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\task;

use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\World;
use sergittos\bedwars\lobby\BedWarsLobby;
use function abs;
use function spl_object_id;

/**
 * Makes a player spawn well above the exact /setlobby position and glide
 * smoothly down onto it, instead of teleporting straight to a
 * World::getSafeSpawn()-adjusted position.
 *
 * Why: World::getSafeSpawn() scans for the nearest "safe" Y at the given
 * X/Z, which can land a player somewhere other than the exact /setlobby
 * spot (a different floor of the lobby build, a ledge, etc.), and gives no
 * real protection against spawning inside solid terrain if something got
 * built/generated above the saved spot later. Starting well above the
 * exact saved coordinate and gliding straight down onto it sidesteps both
 * problems: the player always ends up exactly where /setlobby was run,
 * approached from open air, so there is nothing above them left to spawn
 * stuck inside of.
 */
final class LobbySpawnDescentTask extends Task{

    /** Height, in blocks, above the saved spawn the player starts at. */
    public const DESCENT_HEIGHT = 7.0;

    /** Blocks moved down per tick (1.0 block/tick = a 20-block descent takes 1 second). */
    private const DESCENT_SPEED = 1.0;

    /**
     * Players currently mid-descent, by spl_object_id(). Used only so
     * LobbyListener can suppress suffocation/fall damage for exactly the
     * players this effect is actively moving - never anyone else.
     *
     * @var array<int, true>
     */
    private static array $descending = [];

    private ?TaskHandler $handler = null;

    /**
     * @param callable(Player):void $onComplete Called once the player has
     *        landed exactly on $target (or the descent was aborted because
     *        something else moved/removed the player - either way, this is
     *        always called exactly once so callers can safely re-apply
     *        lobby state - gamemode, items, scoreboard, etc. - afterwards).
     */
    public static function start(Player $player, World $world, Vector3 $target, callable $onComplete): void{
        $start = new Vector3($target->getX(), $target->getY() + self::DESCENT_HEIGHT, $target->getZ());
        $player->teleport($start);

        $hadAllowFlight = $player->getAllowFlight();
        $hadFlying = $player->isFlying();
        $hadCollision = $player->hasBlockCollision();
        $originalGamemode = $player->getGamemode();

        // Reuse the same "fake spectator" technique used elsewhere in this
        // plugin suite (see Session::giveSpectatorItems() in the game
        // plugin): flight + disabled block collision layered on top of
        // whatever gamemode the player is already in, so the descent
        // glides smoothly through anything in the way without needing a
        // real spectator gamemode.
        $player->setAllowFlight(true);
        $player->setFlying(true);
        $player->setHasBlockCollision(false);

        self::$descending[spl_object_id($player)] = true;

        $task = new self($player, $world, $target, $onComplete, $hadAllowFlight, $hadFlying, $hadCollision, $originalGamemode);
        $handler = BedWarsLobby::getInstance()->getScheduler()->scheduleRepeatingTask($task, 1);
        $task->handler = $handler;
    }

    public static function isDescending(Player $player): bool{
        return isset(self::$descending[spl_object_id($player)]);
    }

    /** @param callable(Player):void $onComplete */
    private function __construct(
        private Player $player,
        private World $world,
        private Vector3 $target,
        private $onComplete,
        private bool $hadAllowFlight,
        private bool $hadFlying,
        private bool $hadCollision,
        private GameMode $originalGamemode
    ){}

    public function onRun(): void{
        $player = $this->player;

        if(!$player->isConnected()){
            $this->finish(false);
            return;
        }

        $pos = $player->getPosition();
        $world = $pos->getWorld();

        // Someone else (a GUI teleport, a game rejoin, an admin command,
        // ...) moved this player off the descent path - stop silently
        // rather than fighting whatever moved them.
        if($world->getFolderName() !== $this->world->getFolderName()
            || abs($pos->getX() - $this->target->getX()) > 3.0
            || abs($pos->getZ() - $this->target->getZ()) > 3.0){
            $this->finish(false);
            return;
        }

        $remaining = $pos->getY() - $this->target->getY();
        if($remaining <= self::DESCENT_SPEED){
            $this->finish(true);
            return;
        }

        $player->teleport($pos->subtract(0, self::DESCENT_SPEED, 0));
    }

    private function finish(bool $land): void{
        unset(self::$descending[spl_object_id($this->player)]);
        $this->handler?->cancel();

        $player = $this->player;
        if(!$player->isConnected()){
            return;
        }

        if($land){
            $player->teleport($this->target);
        }

        $player->setFlying($this->hadFlying);
        $player->setAllowFlight($this->hadAllowFlight);
        $player->setHasBlockCollision($this->hadCollision);
        if($player->getGamemode() !== $this->originalGamemode){
            $player->setGamemode($this->originalGamemode);
        }

        ($this->onComplete)($player);
    }
}
