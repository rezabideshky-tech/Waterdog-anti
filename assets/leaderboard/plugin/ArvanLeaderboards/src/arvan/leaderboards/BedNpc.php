<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * BedNpc — تخت بزرگ BedWars با شمشیر شناور (مدل arvan:bedwars_bed).
 * کارش: بنر/پیام تبلیغاتی یا شمارش معکوس رویداد (BedWars Night، فصل جدید، ...)
 */
final class BedNpc extends FloatingNpc
{
    public const NETWORK_ID = 'arvan:bedwars_bed';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'bed';
    }

    public function refreshNameTag(): void
    {
        $lines = [(string) $this->cfg('title', '§l§cBED§9WARS')];
        $lines[] = '§7————————————';
        foreach ((array) $this->cfg('lines', []) as $line) {
            $lines[] = (string) $line;
        }
        $until = (int) $this->cfg('countdown-until', 0);   // timestamp یونیکس (۰ = خاموش)
        if ($until > time()) {
            $left = $until - time();
            $d = intdiv($left, 86400);
            $h = intdiv($left % 86400, 3600);
            $m = intdiv($left % 3600, 60);
            $lines[] = '§e⏳ شمارش معکوس: §f' . ($d > 0 ? $d . ' روز و ' : '') . $h . ' ساعت و ' . $m . ' دقیقه';
        }
        $lines[] = '§8👆 ضربه بزن برای اطلاعات';
        $this->setNameTag(implode("\n", $lines));
    }
}
