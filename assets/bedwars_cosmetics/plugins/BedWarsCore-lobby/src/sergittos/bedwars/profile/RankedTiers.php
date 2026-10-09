<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use function array_map;
use function array_slice;
use function count;
use function intdiv;
use function max;
use function min;
use function round;

/**
 * Ranked ladder maths: Bronze -> Silver -> Gold -> Platinum -> Diamond -> Master (3 divisions each: I, II, III)
 * and finally Grandmaster (no divisions). Division sizes grow every season (see season.size-growth-per-season).
 * (Tier ids below still use their original rookie/veteran/elite/pro/master/grandmaster/legendary keys -
 * only the display names were rebranded, see ProfileCatalog::TIERS.)
 */
final class RankedTiers{

    public const DIVISIONS = 3;
    public const TIER_COUNT = 7;
    public const LEGENDARY = 6;

    private const ROMAN = [1 => "I", 2 => "II", 3 => "III"];

    /** @return list<int> RP needed to climb one division, for tiers 0..5 */
    public static function sizes(ProfileConfig $cfg, int $season) : array{
        $base = $cfg->intList("ranked.division-size", [200, 250, 300, 350, 400, 450]);
        while(count($base) < 6){
            $base[] = $base[count($base) - 1] ?? 200;
        }
        $growth = max(0.0, $cfg->float("season.size-growth-per-season", 0.0));
        $cap = max(1.0, $cfg->float("season.max-scale", 1.0));
        $scale = min($cap, 1.0 + $growth * max(0, $season - 1));
        return array_map(static fn(int $v) : int => max(10, (int) (round($v * $scale / 10) * 10)), array_slice($base, 0, 6));
    }

    /** @param list<int> $sizes RP at which the given tier begins */
    public static function tierStart(array $sizes, int $tier) : int{
        $total = 0;
        for($i = 0; $i < min($tier, 6); $i++){
            $total += $sizes[$i] * self::DIVISIONS;
        }
        return $total;
    }

    /**
     * @param list<int> $sizes
     * @return array{tier: int, division: int, floor: int, next: ?int, tierFloor: int, into: int, span: int}
     */
    public static function locate(array $sizes, int $rp) : array{
        $rp = max(0, $rp);
        for($t = 0; $t < 6; $t++){
            $start = self::tierStart($sizes, $t);
            $end = $start + $sizes[$t] * self::DIVISIONS;
            if($rp < $end){
                $d = intdiv($rp - $start, $sizes[$t]);
                $floor = $start + $d * $sizes[$t];
                return ["tier" => $t, "division" => $d + 1, "floor" => $floor, "next" => $floor + $sizes[$t], "tierFloor" => $start, "into" => $rp - $floor, "span" => $sizes[$t]];
            }
        }
        $start = self::tierStart($sizes, 6);
        return ["tier" => 6, "division" => 0, "floor" => $start, "next" => null, "tierFloor" => $start, "into" => $rp - $start, "span" => 0];
    }

    public static function tierId(int $tier) : string{
        return ProfileCatalog::TIERS[max(0, min(6, $tier))]["id"];
    }

    public static function tierName(int $tier) : string{
        return ProfileCatalog::TIERS[max(0, min(6, $tier))]["name"];
    }

    public static function tierColor(int $tier) : string{
        return ProfileCatalog::TIERS[max(0, min(6, $tier))]["color"];
    }

    /** @param array{tier: int, division: int} $loc */
    public static function label(array $loc) : string{
        $name = self::tierName($loc["tier"]);
        return $loc["division"] > 0 ? $name . " " . self::ROMAN[$loc["division"]] : $name;
    }

    /** @param array{tier: int, division: int} $loc */
    public static function coloredLabel(array $loc) : string{
        return self::tierColor($loc["tier"]) . self::label($loc);
    }
}
