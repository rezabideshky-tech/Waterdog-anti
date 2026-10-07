<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * CrownNpc — پایهٔ «تاجِ غول‌پیکر» 👑 (Giant Crowns).
 *
 * یک تاج روی بالش مخملی که در بازی آرام می‌چرخد (انیمیشن مدل) و نام‌تگش
 * فهرست برترین‌های همان دسته است — رنگ جواهر وسط تاج نشان می‌دهد کدام دسته است:
 *   قرمز = kills · طلایی = wins · فیروزه‌ای = beds_broken
 *   بنفش = final_kills · سبز = level · نارنجی = coins
 *
 * داده دقیقاً از همان provider پلاگین BedWarsCore (bedwars_v7) خوانده می‌شود؛
 * پس اعداد با /bwhologram خود سرور یکسان‌اند.
 */
abstract class CrownNpc extends FloatingNpc
{
    /** دستهٔ آمار این تاج (kills/wins/...) */
    abstract public function stat(): string;

    public function key(): string
    {
        return 'crown_' . $this->stat();
    }

    /** ستون فهرست را نشان می‌دهد (برای پیام ضربه) */
    public function track(): string
    {
        return $this->stat();
    }

    /** ساخت خطوط نام‌تگ از دادهٔ آماده (استفادهٔ مشترک مدیر و رفرش) */
    public static function format(string $stat, array $rows, int $limit = 8): array
    {
        $icon = BedWarsSource::ICONS[$stat] ?? '👑';
        $label = BedWarsSource::LABELS[$stat] ?? strtoupper($stat);
        $lines = ['§l§6👑 ' . $icon . ' §e' . $label, '§7━━━━━━━━━━━━━━'];
        $medals = ['§e§l🥇', '§7§l🥈', '§6§l🥉'];
        $i = 0;
        foreach (array_slice($rows, 0, $limit) as $row) {
            $i++;
            $num = $medals[$i - 1] ?? ('§8' . $i . '.');
            $lines[] = $num . ' §f' . self::pretty((string) $row['name'])
                . ' §8| §a' . number_format((int) $row['value']);
        }
        if ($i === 0) {
            $lines[] = '§7هنوز آماری ثبت نشده';
        }
        $lines[] = '§7━━━━━━━━━━━━━━';
        $lines[] = '§8👆 ضربه = جدول کامل';
        return $lines;
    }

    public function refreshNameTag(): void
    {
        $title = (string) $this->cfg('title', '§l§6👑 GIANT CROWN');
        $limit = (int) $this->cfg('limit', 8);
        $stat = $this->stat();

        // خط‌های اول ثابت می‌مانند تا وقتی داده از دیتابیس می‌رسد نام‌تگ خالی نباشد
        $this->setNameTag(implode("\n", [$title, '§7در حال خواندن آمار…']));

        BedWarsSource::leaderboard($stat, $limit, function (array $rows) use ($title, $limit, $stat): void {
            if (!$this->isAlive() || $this->isClosed()) {
                return;   // NPC حذف شده؛ دیگر چیزی آپدیت نکن (جلوگیری از خطا)
            }
            $lines = CrownNpc::format($stat, $rows, $limit);
            $lines[0] = $title . '  §7' . (BedWarsSource::ICONS[$stat] ?? '');
            $this->setNameTag(implode("\n", $lines));
        });
    }

    public static function pretty(string $name): string
    {
        return ucfirst($name);
    }
}
