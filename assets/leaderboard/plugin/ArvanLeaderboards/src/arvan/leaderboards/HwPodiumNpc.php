<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * HwPodiumNpc — سکوی سه کدوی قهرمانان با تاج‌های طلایی و خفاش‌های مداری
 * مدل ریسورس‌پک: arvan:hw_podium
 */
final class HwPodiumNpc extends TopNpc
{
    public const NETWORK_ID = 'arvan:hw_podium';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'hw_podium';
    }

    protected function defaultTitle(): string
    {
        return '§l§6🎃 §ePUMPKIN PODIUM';
    }
}
