<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * HwSignNpc — تابلوی آویزان از زنجیر زیر طاق چوبی — جمع‌وجور برای گوشهٔ لابی
 * مدل ریسورس‌پک: arvan:hw_sign
 */
final class HwSignNpc extends TopNpc
{
    public const NETWORK_ID = 'arvan:hw_sign';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'hw_sign';
    }

    protected function defaultTitle(): string
    {
        return '§6§lTRICK OR TREAT';
    }
}
