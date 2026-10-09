<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\podium;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function floor;

/**
 * Builds (and only builds - never has to clean up after itself, see below)
 * the physical 3-tier Podium Ceremony structure: a gold column for 1st
 * place, iron for 2nd, and orange stained clay ("bronze") for 3rd,
 * arranged with gold in the center and slightly taller than its
 * neighbours either side.
 *
 * Only the ranks actually present in the winning team (1-3 depending on
 * Solo/Doubles/Triples/Squads and how many of that team are online) get a
 * column - e.g. a Solo win only ever gets the single gold column, a
 * Doubles win gets gold + silver, never a phantom empty 3rd pedestal.
 *
 * No explicit teardown is needed: EndingStage::reset() always fully
 * unloads the match world once the ceremony/countdown ends (Game::reset()
 * -> Game::unloadWorld()), so these blocks never need to be reverted by
 * hand - they simply cease to exist along with the rest of the arena.
 */
final class PodiumBuilder{

    /** Column footprint - 2x2 so the podium reads as a solid structure instead of a single 1-wide pole. */
    private const FOOTPRINT_RADIUS = 1;

    /** Column height (in blocks) per rank - gold tallest, bronze shortest, matching a real medal podium. */
    private const RANK_HEIGHT = [1 => 3, 2 => 2, 3 => 1];

    /** Horizontal offset (blocks along the podium's own side-to-side axis, perpendicular to the forward direction) per rank - gold centered, silver left, bronze right. */
    private const RANK_OFFSET_X = [1 => 0, 2 => -3, 3 => 3];

    /** How far in front of the reference point (bed / spawn) the whole podium is placed. */
    private const FORWARD_OFFSET = 5;

    /**
     * Furthest a column is ever allowed to sit above/below the reference
     * point's own Y before findGroundY() gives up looking for solid
     * ground and just builds level with the reference instead. Keeps the
     * ground search a small, constant-size scan (not a full world-height
     * one) even on a map where "in front of" a bed happens to point over
     * open air or a void - both a correctness fix (no more columns
     * silently searching down to bedrock/void and landing somewhere
     * absurd) and a performance one (this runs synchronously on the main
     * thread right as the match ends).
     */
    private const MAX_GROUND_SEARCH = 16;

    /**
     * Default forward direction used only when the caller can't provide
     * a meaningful one (see build()'s $forwardDirection) - e.g. a map
     * where a team's spawn point and bed position happen to be the exact
     * same spot, so there's no spawn->bed vector to derive a facing from.
     */
    private const FALLBACK_DIRECTION_X = 0.0;
    private const FALLBACK_DIRECTION_Z = 1.0;

    /**
     * @param int[] $ranks Which ranks (1, 2 and/or 3) actually have a
     *                      winner to stand on them.
     * @param Vector3|null $forwardDirection Which way "in front of" the
     *                      reference point actually is - typically the
     *                      losing team's own spawn->bed vector (see
     *                      EndingStage::preparePodiumCeremony()), i.e.
     *                      the direction that team's players already
     *                      face when walking out of their base. Only the
     *                      X/Z components are used (podiums are always
     *                      built level). Null, a zero vector, or a
     *                      vector too short to normalize safely all fall
     *                      back to a fixed direction instead of dividing
     *                      by ~zero.
     * @return array<int, Vector3> rank => the block position a player
     *                                      should stand on (the walkable
     *                                      block directly above the column).
     */
    public static function build(World $world, Vector3 $reference, array $ranks, ?Vector3 $forwardDirection = null) : array{
        $groundY = self::findGroundY($world, $reference);

        [$forwardX, $forwardZ] = self::normalizeHorizontal($forwardDirection);
        // Perpendicular (rotate 90°) to the forward direction, used to lay
        // the gold/silver/bronze columns out side-by-side across the
        // podium's actual "width" instead of always along the world's raw
        // X axis - which only looked right by coincidence on maps whose
        // beds happen to face north/south.
        $sideX = -$forwardZ;
        $sideZ = $forwardX;

        $standPositions = [];

        foreach($ranks as $rank){
            $height = self::RANK_HEIGHT[$rank] ?? 1;
            $offset = self::RANK_OFFSET_X[$rank] ?? 0;

            $rawBase = new Vector3(
                $reference->x + ($forwardX * self::FORWARD_OFFSET) + ($sideX * $offset),
                $groundY,
                $reference->z + ($forwardZ * self::FORWARD_OFFSET) + ($sideZ * $offset)
            );

            // Snap to the block grid *before* doing anything else with this
            // base point. World::setBlock() floors whatever coordinate it's
            // given to decide which block cell to actually write to, but
            // $rawBase is built from a rotated unit vector (forwardX/sideX,
            // see normalizeHorizontal()) times an offset, so it's fractional
            // essentially every time - only ever landing on a whole number
            // by coincidence. placeColumn() below was therefore writing its
            // two blocks per axis at floor($rawBase.x) and floor($rawBase.x)-1,
            // while the "center" used for the stand position was computed
            // from the *unfloored* $rawBase.x - two different numbers
            // whenever the fractional part was non-zero. The gap between
            // them is exactly what put the player standing off toward
            // whichever edge of the pedestal the leftover fraction happened
            // to point at, instead of in the middle of the 2x2 footprint
            // (the offset seen in the Podium Ceremony screenshot). Flooring
            // here first means placeColumn() and the stand position below
            // are always talking about the exact same integer block cell.
            $columnBase = new Vector3(
                (float) ((int) floor($rawBase->x)),
                $rawBase->y,
                (float) ((int) floor($rawBase->z))
            );

            $block = self::blockForRank($rank);
            self::placeColumn($world, $columnBase, $height, $block);

            // placeColumn() lays two blocks on each axis: one at
            // ($base.x - 1) and one at $base.x (world-space occupies
            // [base.x-1, base.x-1+1) and [base.x, base.x+1) respectively),
            // so together the 2x2 footprint spans the continuous range
            // [base.x - 1, base.x + 1) - and the midpoint of that range is
            // $base.x itself, not $base.x - 0.5. Now that $columnBase is
            // guaranteed integer-valued (see above), this really is the
            // exact center of the two placed blocks, not just a value that
            // happens to look centered.
            $standPositions[$rank] = new Vector3(
                $columnBase->x,
                $columnBase->y + $height,
                $columnBase->z
            );
        }

        return $standPositions;
    }

    /**
     * @return array{0: float, 1: float} unit-length [x, z] - never [0, 0],
     *                                    so callers can always multiply by
     *                                    an offset without special-casing.
     */
    private static function normalizeHorizontal(?Vector3 $direction) : array{
        if($direction !== null){
            $x = $direction->x;
            $z = $direction->z;
            $length = sqrt($x * $x + $z * $z);
            // Anything shorter than this is close enough to "straight up
            // or the exact same point" that normalizing it would just
            // amplify floating-point noise into a essentially random
            // direction - the fixed fallback below is more predictable.
            if($length >= 0.05){
                return [$x / $length, $z / $length];
            }
        }

        return [self::FALLBACK_DIRECTION_X, self::FALLBACK_DIRECTION_Z];
    }

    private static function blockForRank(int $rank) : Block{
        // NOTE: this fork of PocketMine-MP doesn't ship VanillaBlocks::TERRACOTTA(),
        // GOLD_BLOCK() or IRON_BLOCK() factories (confirmed by grepping the rest of
        // the codebase - SetTeamGeneratorItem.php uses IRON(), the ore generators use
        // DIAMOND()/EMERALD(), and the shop's "Hardened Clay" product uses
        // STAINED_CLAY() instead, see BlocksCategory.php).
        // Calling a nonexistent static method throws an Error, which
        // silently aborted the *entire* Podium Ceremony (caught by the
        // try/catch around PodiumBuilder::build() in EndingStage) even for
        // Solo/Doubles wins that never touch the bronze column - this is
        // exactly the bug that made the ceremony never show up at all.
        return match($rank){
            1 => VanillaBlocks::GOLD(),
            2 => VanillaBlocks::IRON(),
            default => VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::ORANGE())
        };
    }

    /**
     * Solid 2x2xheight column of $block, centered on $base (base is the
     * bottom-most layer's Y level).
     */
    private static function placeColumn(World $world, Vector3 $base, int $height, Block $block) : void{
        $r = self::FOOTPRINT_RADIUS;

        for($y = 0; $y < $height; $y++){
            for($dx = -$r; $dx <= 0; $dx++){
                for($dz = -$r; $dz <= 0; $dz++){
                    $world->setBlock(new Vector3($base->x + $dx, $base->y + $y, $base->z + $dz), $block, false);
                }
            }
        }
    }

    /**
     * Walks a few blocks up/down from the reference point's Y to find
     * solid ground to build on, instead of assuming the reference
     * position (usually a destroyed bed's coordinates) is exactly at
     * floor level. Falls back to the reference's own Y if nothing solid
     * is found nearby, which still produces a valid (if partly floating)
     * podium rather than throwing.
     */
    private static function findGroundY(World $world, Vector3 $reference) : int{
        $x = (int) floor($reference->x);
        $z = (int) floor($reference->z);
        $startY = max($world->getMinY() + 1, min($world->getMaxY() - 1, (int) floor($reference->y)));

        // Bounded to MAX_GROUND_SEARCH instead of walking all the way
        // down to $world->getMinY(): on a map where "in front of" the
        // reference point (see the forward-direction handling in build())
        // happens to be open air or a void, an unbounded scan used to
        // walk the entire world height on the main thread right as the
        // match ends, and would still have handed back a Y from far below
        // the arena - a valid int, but not a usable "ground". Bailing out
        // to the reference's own Y after a small, constant-size scan is
        // both cheaper and produces a saner (if occasionally floating)
        // podium than one built at bedrock level.
        $minY = max($world->getMinY(), $startY - self::MAX_GROUND_SEARCH);

        for($y = $startY; $y > $minY; $y--){
            // getTypeId() !== AIR instead of isSolid() - a plain type-ID
            // check against a constant already proven to work elsewhere
            // in this codebase (GameListener.php), rather than trusting a
            // Block method this project has never actually called before.
            if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
                return $y + 1;
            }
        }

        return $startY;
    }

}
