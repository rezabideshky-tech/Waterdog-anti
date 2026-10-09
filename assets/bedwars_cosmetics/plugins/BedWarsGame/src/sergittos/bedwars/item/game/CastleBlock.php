<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\ClickSound;
use pocketmine\world\World;
use sergittos\bedwars\game\BedWarsGame;
use sergittos\bedwars\game\Game;
use function array_slice;
use function ceil;
use function count;
use function max;

/**
 * Castle/watchtower block, geometry ported directly from the reference
 * PopupTower::getLayers() shape: a hollow tower wall (R), a solid roof,
 * and a wider overhanging crown (OUTER_R) with the same crenellation
 * pattern (sparse merlon points at the crown's base layer, a full ring
 * at mid-height, and alternating merlons at the very top) - plus a
 * single-column ladder and a single-block-wide door, exactly as in the
 * reference. Rotation for all 4 facings is handled the same way the
 * reference does it: Vector3::getSide() on the entrance facing, so the
 * body itself (built from raw local x/z offsets) stays symmetric and
 * only the door/ladder move.
 */
final class CastleBlock{

    public const TAG = "bedwars_castle";

    private const R = 2;
    private const OUTER_R = 3;
    private const BUILD_TICKS = 55;

    public static function create(): Item{
        $item = VanillaBlocks::CHEST()->asItem();
        $item->setCustomName(TextFormat::AQUA . "Castle Block");
        $item->setLore([TextFormat::GRAY . "Place to build a watchtower"]);
        $item->getNamedTag()->setByte(self::TAG, 1);
        return $item;
    }

    public static function isCastleBlock(Item $item): bool{
        return $item->getNamedTag()->getByte(self::TAG, 0) === 1;
    }

    public static function build(Game $game, Vector3 $base, DyeColor $color, int $entranceFacing): void{
        $world = $game->getWorld();
        if($world === null){
            return;
        }

        $wall = VanillaBlocks::WOOL()->setColor($color);
        $ladder = VanillaBlocks::LADDER()->setFacing($entranceFacing);
        $air = VanillaBlocks::AIR();

        // Keyed by block hash so a later write (door/ladder) always
        // overwrites an earlier one (wall) at the same coordinate -
        // mirrors how the reference's per-layer Selection dictionary
        // works, and avoids the placement step ever needing to punch
        // through a block it already placed itself.
        $blocks = [];

        // Tower walls: hollow ring, R, y 0..3 (reference's "Corners" step -
        // despite the name it's the full wall ring, not just the 4 corners).
        for($y = 0; $y <= 3; $y++){
            for($x = -self::R; $x <= self::R; $x++){
                for($z = -self::R; $z <= self::R; $z++){
                    if($x !== -self::R && $x !== self::R && $z !== -self::R && $z !== self::R){
                        continue;
                    }
                    self::addBlock($blocks, $base->add($x, $y, $z), clone $wall);
                }
            }
        }

        // Roof: solid floor at y 4, R.
        for($x = -self::R; $x <= self::R; $x++){
            for($z = -self::R; $z <= self::R; $z++){
                self::addBlock($blocks, $base->add($x, 4, $z), clone $wall);
            }
        }

        // Crown: wider ring (OUTER_R), y 4..6, same crenellation pattern as
        // the reference - sparse corner/mid-edge merlon bases at y4, a full
        // ring at y5, alternating merlons at y6.
        for($y = 4; $y <= 6; $y++){
            for($x = -self::OUTER_R; $x <= self::OUTER_R; $x++){
                for($z = -self::OUTER_R; $z <= self::OUTER_R; $z++){
                    if($x !== -self::OUTER_R && $x !== self::OUTER_R && $z !== -self::OUTER_R && $z !== self::OUTER_R){
                        continue;
                    }

                    if($y === 6 && ($x % 2 === 0 || $z % 2 === 0)){
                        continue;
                    }

                    if($y === 4 && ($x % 3 !== 0 || $z % 3 !== 0)){
                        continue;
                    }

                    self::addBlock($blocks, $base->add($x, $y, $z), clone $wall);
                }
            }
        }

        // Ladder: single column, one block in from center on the side
        // opposite the entrance, y 0..4 - same offset as the reference.
        $ladderPos = $base->getSide($entranceFacing, -1);
        for($y = 0; $y <= 4; $y++){
            self::addBlock($blocks, $ladderPos->add(0, $y, 0), clone $ladder);
        }

        // Door: single-block-wide opening straight through the wall ring,
        // y 0..2 - same offset (R) as the reference, added last so it
        // overwrites the wall block already queued at that coordinate.
        $doorPos = $base->getSide($entranceFacing, self::R);
        for($y = 0; $y <= 2; $y++){
            self::addBlock($blocks, $doorPos->add(0, $y, 0), clone $air);
        }

        self::placeGradually($game, $world, $blocks, 0);
    }

    /**
     * @param array<int, array{0:Vector3, 1:Block}> $blocks
     */
    private static function addBlock(array &$blocks, Vector3 $pos, Block $block): void{
        $blocks[World::blockHash($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())] = [$pos, $block];
    }

    private static function isInsideAnyClaim(Game $game, Vector3 $position): bool{
        foreach($game->getTeams() as $team){
            if($team->getClaim()->isInside($position)){
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{0:Vector3, 1:Block}> $blocks
     */
    private static function placeGradually(Game $game, World $world, array $blocks, int $index): void{
        if(!$world->isLoaded() || $index >= count($blocks)){
            return;
        }

        $perTick = max(1, (int) ceil(count($blocks) / self::BUILD_TICKS));
        $batch = array_slice($blocks, $index, $perTick, true);

        // Play one click sound for the whole tick's batch instead of one per
        // individual block - placing 20-30 blocks in the same tick used to fire
        // that many identical, inaudibly-overlapping sound packets to every
        // nearby player every tick of the whole build. Same audible result,
        // far fewer packets/allocations per tick. A small break particle per
        // block is still spawned individually (matches the reference popup
        // tower's block-by-block "pop" feedback) since particles are much
        // cheaper than sound packets and this is capped to a handful of
        // blocks per tick by BUILD_TICKS above.
        $soundPos = null;

        foreach($batch as [$pos, $block]){
            $x = $pos->getFloorX();
            $y = $pos->getFloorY();
            $z = $pos->getFloorZ();

            if($y < 0){
                continue;
            }

            // Never build over another team's protected claim (matches the
            // reference's canPlaceBlock check) or anything that isn't empty
            // air - both guard against overwriting a bed, an enemy base, or
            // a block a player has already placed there.
            if(self::isInsideAnyClaim($game, $pos)){
                continue;
            }

            $current = $world->getBlockAt($x, $y, $z);
            if($current->getTypeId() !== BlockTypeIds::AIR || $current instanceof Bed){
                continue;
            }

            $world->setBlock($pos, $block);
            $world->addParticle($pos, new BlockBreakParticle($block));
            $soundPos = $pos;
            $game->addBlock(Position::fromObject($pos, $world));
        }

        if($soundPos !== null){
            $world->addSound($soundPos, new ClickSound());
        }

        BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($game, $world, $blocks, $index, $perTick): void{
                self::placeGradually($game, $world, $blocks, $index + $perTick);
            }),
            1
        );
    }
}
