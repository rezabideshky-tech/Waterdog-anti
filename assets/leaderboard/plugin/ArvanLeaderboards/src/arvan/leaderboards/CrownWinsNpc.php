<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * CrownWinsNpc — تاج غول‌پیکر دستهٔ wins (جواهر gold).
 * مدل ریسورس‌پک: arvan:giant_crown_wins
 */
final class CrownWinsNpc extends CrownNpc
{
    public const NETWORK_ID = 'arvan:giant_crown_wins';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function stat(): string
    {
        return 'wins';
    }
}
