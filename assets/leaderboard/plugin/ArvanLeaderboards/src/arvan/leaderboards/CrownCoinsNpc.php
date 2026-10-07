<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * CrownCoinsNpc — تاج غول‌پیکر دستهٔ coins (جواهر orange).
 * مدل ریسورس‌پک: arvan:giant_crown_coins
 */
final class CrownCoinsNpc extends CrownNpc
{
    public const NETWORK_ID = 'arvan:giant_crown_coins';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function stat(): string
    {
        return 'coins';
    }
}
