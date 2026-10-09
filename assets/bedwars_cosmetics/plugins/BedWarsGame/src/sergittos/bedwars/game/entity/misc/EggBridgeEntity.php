<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\misc;

use pocketmine\block\utils\DyeColor;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use sergittos\bedwars\game\Game;

/**
 * The thrown egg itself. It behaves like a normal projectile (arcs, falls,
 * bounces) and carries no bridging logic of its own anymore - once it
 * lands (either by hitting something or touching the ground), it spawns a
 * BridgeBuilderEntity at the landing spot, which is the entity that
 * actually walks forward and lays the bridge one block at a time (ported
 * from the reference bedwars\utils\entity\BridgeEgg). This replaces the
 * old behaviour of instantly painting a 3-wide wool trail under the egg's
 * flight path.
 */
class EggBridgeEntity extends Throwable{

    private DyeColor $color;
    private ?Game $game;
    private int $blocks;

    public function __construct(Location $location, ?Player $shootingEntity, DyeColor $color, ?Game $game = null, int $blocks = 40){
        $this->color = $color;
        $this->game = $game;
        $this->blocks = $blocks;
        parent::__construct($location, $shootingEntity);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::EGG;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.25, 0.25);
    }

    protected function getInitialGravity() : float{
        return 0.03;
    }

    protected function getInitialDragMultiplier() : float{
        return 0.01;
    }

    public function onUpdate(int $currentTick) : bool{
        $hasUpdate = parent::onUpdate($currentTick);

        if(!$this->isFlaggedForDespawn() && $this->isAlive() && $this->isOnGround()){
            $this->spawnBridgeBuilder($this->getPosition());
        }

        return $hasUpdate;
    }

    protected function onHit(ProjectileHitEvent $event) : void{
        $this->spawnBridgeBuilder($event->getRayTraceResult()->getHitVector());
    }

    private function spawnBridgeBuilder(Vector3 $hitVector) : void{
        if($this->isFlaggedForDespawn()){
            return;
        }
        $this->flagForDespawn();

        $owner = $this->getOwningEntity();
        if(!$owner instanceof Player || !$owner->isConnected() || $this->blocks <= 0){
            return;
        }

        $world = $this->getWorld();
        if($world === null || !$world->isLoaded()){
            return;
        }

        $floor = $hitVector->floor();
        if($floor->getY() < 1){
            return;
        }

        $spawn = new Vector3($floor->getX() + 0.5, $floor->getY(), $floor->getZ() + 0.5);
        $location = Location::fromObject($spawn, $world, $owner->getLocation()->getYaw(), 0.0);

        $walker = new BridgeBuilderEntity($location);
        $walker->setup($owner, $this->color, $this->game, $owner->getHorizontalFacing(), $this->blocks);

        $chunkPos = $walker->getPosition()->floor();
        $world->requestChunkPopulation($chunkPos->getX() >> Chunk::COORD_BIT_SIZE, $chunkPos->getZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
            static fn() => $walker->spawnToAll(),
            static fn() => null
        );
    }

    protected function onDispose(): void{
        parent::onDispose();
        $this->game = null;
    }
}
