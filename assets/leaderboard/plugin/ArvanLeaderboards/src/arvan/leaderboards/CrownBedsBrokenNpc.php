<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * CrownBedsBrokenNpc — تاج غول‌پیکر دستهٔ beds_broken (جواهر cyan).
 * مدل ریسورس‌پک: arvan:giant_crown_beds_broken
 */
final class CrownBedsBrokenNpc extends CrownNpc
{
    public const NETWORK_ID = 'arvan:giant_crown_beds_broken';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function stat(): string
    {
        return 'beds_broken';
    }
}
