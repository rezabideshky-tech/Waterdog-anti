<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * HwBedNpc — تخت خون‌آشام با بال خفاش، جمجمه و شمشیر روح
 * مدل ریسورس‌پک: arvan:hw_bed
 */
final class HwBedNpc extends TopNpc
{
    public const NETWORK_ID = 'arvan:hw_bed';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'hw_bed';
    }

    protected function defaultTitle(): string
    {
        return '§c§l🦇 VAMPIRE BED';
    }
}
