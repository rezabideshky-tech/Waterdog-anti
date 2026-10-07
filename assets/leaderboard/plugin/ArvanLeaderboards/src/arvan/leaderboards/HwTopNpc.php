<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * HwTopNpc — تخته‌سنگ هالووین با تاجِ کدو تنبل و شبکهٔ عنکبوت
 * مدل ریسورس‌پک: arvan:hw_top
 */
final class HwTopNpc extends TopNpc
{
    public const NETWORK_ID = 'arvan:hw_top';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'hw_top';
    }

    protected function defaultTitle(): string
    {
        return '§l§6🎃 §eTEMPLE OF TOP§6 §7| §fSPOOKY TOP';
    }
}
