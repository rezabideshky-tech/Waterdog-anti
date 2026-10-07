<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * CrownFinalKillsNpc — تاج غول‌پیکر دستهٔ final_kills (جواهر purple).
 * مدل ریسورس‌پک: arvan:giant_crown_final_kills
 */
final class CrownFinalKillsNpc extends CrownNpc
{
    public const NETWORK_ID = 'arvan:giant_crown_final_kills';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function stat(): string
    {
        return 'final_kills';
    }
}
