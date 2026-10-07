<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * HwProjectorNpc — دیگ جادو با شعلهٔ روح و روح شناور — نمایشگر آمار هالووینی
 * مدل ریسورس‌پک: arvan:hw_projector
 */
final class HwProjectorNpc extends TopNpc
{
    public const NETWORK_ID = 'arvan:hw_projector';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'hw_projector';
    }

    protected function defaultTitle(): string
    {
        return '§a§l👻 HAUNTED STATS';
    }
}
