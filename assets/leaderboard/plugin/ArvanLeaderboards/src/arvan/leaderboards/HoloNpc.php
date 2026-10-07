<?php
declare(strict_types=1);

namespace arvan\leaderboards;

use arvan\leaderboards\StatsStore;

/**
 * HoloNpc — پروژکتور هولوگرام (مدل arvan:hologram).
 * می‌تواند آمار کل سرور، اطلاعات یک آرنا یا وضعیت «به‌زودی» را نشان دهد.
 */
final class HoloNpc extends FloatingNpc
{
    public const NETWORK_ID = 'arvan:hologram';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'holo';
    }

    public function refreshNameTag(): void
    {
        $title = (string) $this->cfg('title', '§l§bARVAN§fGAMING §7| §eLIVE');
        $lines = [$title, '§7————————————'];
        foreach ((array) $this->cfg('lines', []) as $line) {
            $lines[] = (string) $line;
        }
        switch ((string) $this->cfg('mode', 'server')) {
            case 'arena':
                $arena = (string) $this->cfg('arena', 'Arena-1');
                $lines[] = '§fآرنا: §e' . $arena;
                $lines[] = '§fوضعیت: §a' . ((string) $this->cfg('state', 'آماده'));
                break;
            case 'soon':
                $lines[] = '§e§lبه‌زودی!';
                $lines[] = '§7جزئیات را به‌زودی می‌بینی';
                break;
            default: // server
                $lines[] = '§fبازیکنان ثبت‌شده: §a' . $this->store()->playerCount();
                $lines[] = '§fکل کشته‌ها: §a' . $this->store()->total('kills');
                $lines[] = '§fکل تخت‌های شکسته: §a' . $this->store()->total('beds');
                $lines[] = '§fکل بردها: §a' . $this->store()->total('wins');
        }
        $lines[] = '§8👆 ضربه بزن برای جدول کامل';
        $this->setNameTag(implode("\n", $lines));
    }
}
