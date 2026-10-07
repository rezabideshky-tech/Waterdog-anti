<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * CrownLevelNpc — تاج غول‌پیکر دستهٔ level (جواهر green).
 * مدل ریسورس‌پک: arvan:giant_crown_level
 */
final class CrownLevelNpc extends CrownNpc
{
    public const NETWORK_ID = 'arvan:giant_crown_level';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function stat(): string
    {
        return 'level';
    }
}
