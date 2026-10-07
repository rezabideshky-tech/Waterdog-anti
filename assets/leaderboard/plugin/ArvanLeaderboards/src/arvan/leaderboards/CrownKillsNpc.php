<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * CrownKillsNpc — تاج غول‌پیکر دستهٔ kills (جواهر red).
 * مدل ریسورس‌پک: arvan:giant_crown_kills
 */
final class CrownKillsNpc extends CrownNpc
{
    public const NETWORK_ID = 'arvan:giant_crown_kills';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function stat(): string
    {
        return 'kills';
    }
}
