<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\misc;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\Position;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\stage\PlayingStage;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\item\game\EggBridge;
use sergittos\bedwars\session\SessionFactory;

/**
 * The walking "turtle" that lays a single-file bridge one block at a time,
 * ported from the reference bedwars\utils\entity\BridgeEgg into this
 * plugin's Entity/Game/Team API (this codebase's mobs extend Entity and
 * use onUpdate()/attack(EntityDamageEvent) rather than Living/entityBaseTick,
 * see HostileMob/Villager).
 *
 * Spawned by EggBridgeEntity (the thrown egg) once it lands - see
 * EggBridgeEntity::spawnBridgeBuilder(). Walks in a single fixed direction
 * (captured at spawn from the thrower's facing) placing one team-coloured
 * wool block per step, stopping on an obstacle, a build-limit breach, or a
 * team's protected claim. Can be attacked to stop it early: the owner (or a
 * teammate) "picks it up" and gets an Egg Bridge item back pre-loaded with
 * whatever blocks are left, an enemy destroys it outright with nothing
 * returned.
 */
class BridgeBuilderEntity extends Entity{

    /** Below this many remaining blocks, returning an item isn't worth it - mirrors the reference's BUFFER_REMOVE_AMOUNT. */
    private const BUFFER_REMOVE_AMOUNT = 5;

    private const MOVE_SPEED = 0.13;
    private const MAX_BUILD_Y = 230;

    private ?Player $owner = null;
    private ?Game $game = null;
    private DyeColor $color;
    private int $facing = Facing::SOUTH;
    private int $blocksRemaining = 0;
    private int $blocksPlaced = 0;
    private bool $configured = false;

    public function __construct(Location $location){
        parent::__construct($location);
        $this->color = DyeColor::WHITE();
        $this->setCanSaveWithChunk(false);
    }

    /**
     * Must be called once, right after construction and before spawnToAll(),
     * to give the walker its owner, colour, game context, direction and
     * block budget. An entity that never gets configured (e.g. reloaded
     * from a world's NBT after a server restart, orphaned) simply despawns
     * on its first tick - see onUpdate().
     */
    public function setup(Player $owner, DyeColor $color, ?Game $game, int $facing, int $blocks): void{
        $this->owner = $owner;
        $this->color = $color;
        $this->game = $game;
        $this->facing = $facing;
        $this->blocksRemaining = $blocks;
        $this->configured = true;

        $this->setRotation($this->yawForFacing($facing), 0.0);
    }

    public static function getNetworkTypeId(): string{
        return EntityIds::TURTLE;
    }

    public function getName(): string{
        return "Bridge Builder";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo{
        return new EntitySizeInfo(0.8, 2.4);
    }

    protected function getInitialGravity(): float{
        return 0.0;
    }

    protected function getInitialDragMultiplier(): float{
        return 0.0;
    }

    public function attack(EntityDamageEvent $source): void{
        // The walker is never meant to actually die from combat damage - it
        // is only ever stopped through the pick-up/destroy logic below.
        $source->cancel();

        if(!$source instanceof EntityDamageByEntityEvent){
            return;
        }

        $damager = $source->getDamager();
        if(!$damager instanceof Player || !SessionFactory::hasSession($damager)){
            $this->terminate(null);
            return;
        }

        $damagerSession = SessionFactory::getSession($damager);
        $damagerTeam = $damagerSession->getTeam();

        $ownerTeam = null;
        if($this->owner !== null && SessionFactory::hasSession($this->owner)){
            $ownerTeam = SessionFactory::getSession($this->owner)->getTeam();
        }

        if($ownerTeam instanceof Team && $damagerTeam instanceof Team && $damagerTeam->getName() === $ownerTeam->getName()){
            // Teammate (or the owner themselves) stopped the bridge - it's picked back up.
            if($damager === $this->owner){
                $damagerSession->message("{GREEN}Picked up your bridge builder!");
            }else{
                $damagerSession->message("{YELLOW}You picked up your teammate's bridge!");
                if($this->owner !== null && SessionFactory::hasSession($this->owner)){
                    SessionFactory::getSession($this->owner)->message("{GREEN}Your teammate stopped your bridge.");
                }
            }

            $this->terminate($damager);
            return;
        }

        // Enemy (or an unrelated player) destroyed it - nothing is returned.
        if($this->owner !== null && SessionFactory::hasSession($this->owner)){
            SessionFactory::getSession($this->owner)->message("{RED}Your bridge builder was destroyed!");
        }
        $damagerSession->message("{RED}You destroyed an enemy's bridge builder!");

        $this->flagForDespawn();
    }

    public function onUpdate(int $currentTick): bool{
        $hasUpdate = parent::onUpdate($currentTick);

        if($this->isFlaggedForDespawn()){
            return $hasUpdate;
        }

        if(!$this->configured || $this->game === null || !($this->game->getStage() instanceof PlayingStage)){
            $this->flagForDespawn();
            return $hasUpdate;
        }

        if(!$this->isAlive()){
            return $hasUpdate;
        }

        $world = $this->getWorld();
        if($world === null || !$world->isLoaded()){
            $this->flagForDespawn();
            return true;
        }

        $pos = $this->getPosition();
        [$dx, $dz] = $this->facingOffset();

        $bx = $pos->getFloorX();
        $by = $pos->getFloorY();
        $bz = $pos->getFloorZ();

        // Same-level block directly ahead must be clear to walk into.
        $ahead = $world->getBlockAt($bx + $dx, $by, $bz + $dz);
        // Two steps ahead, one below - if that's occupied the walker would be
        // bridging into/under existing terrain, so stop instead of tunnelling.
        $twoAheadBelow = $world->getBlockAt($bx + (2 * $dx), $by - 1, $bz + (2 * $dz));

        $bridgePos = new Vector3($bx + $dx, $by - 1, $bz + $dz);

        if(
            $ahead->getTypeId() !== BlockTypeIds::AIR ||
            $twoAheadBelow->getTypeId() !== BlockTypeIds::AIR ||
            $bridgePos->getY() < 0 ||
            $bridgePos->getY() > self::MAX_BUILD_Y ||
            $this->isInsideAnyClaim($bridgePos)
        ){
            $this->terminate($this->owner);
            return true;
        }

        $bridgeBlock = $world->getBlockAt($bridgePos->getFloorX(), $bridgePos->getFloorY(), $bridgePos->getFloorZ());
        if($bridgeBlock->getTypeId() === BlockTypeIds::AIR){
            $world->setBlockAt($bridgePos->getFloorX(), $bridgePos->getFloorY(), $bridgePos->getFloorZ(), VanillaBlocks::WOOL()->setColor($this->color));
            $this->game->addBlock(Position::fromObject($bridgePos, $world));
            $this->blocksPlaced++;

            if(--$this->blocksRemaining <= 0){
                // Fully used up - finished normally, nothing to give back.
                $this->flagForDespawn();
                return true;
            }
        }

        $this->setMotion(new Vector3($dx * self::MOVE_SPEED, 0.0, $dz * self::MOVE_SPEED));

        return true;
    }

    /**
     * @return array{0:int,1:int} [dx, dz] step for the walker's fixed facing.
     */
    private function facingOffset(): array{
        return match($this->facing){
            Facing::SOUTH => [0, 1],
            Facing::WEST => [-1, 0],
            Facing::NORTH => [0, -1],
            default => [1, 0], // Facing::EAST
        };
    }

    private function yawForFacing(int $facing): float{
        return match($facing){
            Facing::SOUTH => 180.0,
            Facing::WEST => 90.0,
            Facing::NORTH => 0.0,
            default => 270.0, // Facing::EAST
        };
    }

    private function isInsideAnyClaim(Vector3 $position): bool{
        if($this->game === null){
            return false;
        }

        foreach($this->game->getTeams() as $team){
            if($team->getClaim()->isInside($position)){
                return true;
            }
        }

        return false;
    }

    /**
     * Stops the walker and, if there's a sensible amount of unused blocks
     * left, hands the given player a fresh Egg Bridge item pre-loaded with
     * that many blocks so nothing is wasted picking it back up.
     */
    private function terminate(?Player $returnTo): void{
        $this->flagForDespawn();

        if($returnTo === null || !$returnTo->isConnected()){
            return;
        }

        if($this->blocksRemaining < self::BUFFER_REMOVE_AMOUNT){
            return;
        }

        $returnTo->getInventory()->addItem(EggBridge::withBlocks($this->blocksRemaining));
    }

    protected function onDispose(): void{
        parent::onDispose();
        $this->owner = null;
        $this->game = null;
    }
}
