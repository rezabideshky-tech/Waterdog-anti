<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * BedWarsSource — پل ارتباطی با پلاگین BedWarsCore v2 (bedwars_v7).
 *
 * اگر پلاگین BedWarsCore روی همین سرور فعال باشد، آمار لیدربوردها را مستقیم از
 * provider خودش (همان کوئری‌های bw_players که /bwhologram استفاده می‌کند) می‌خواند
 * تا اعداد **مو‌به‌مو** با لیدربوردهای خودِ BedWars یکی باشند.
 *
 * اگر BedWarsCore نبود (یا provider جواب نداد)، بی‌صدا به StatsStore محلی برمی‌گردد.
 * هیچ استثنایی بیرون نمی‌زند → بدون اختلال روی سرور.
 */
final class BedWarsSource
{
    /** دسته‌هایی که هر دو طرف می‌شناسند (لیست مجاز خود BedWarsCore). */
    public const STATS = ['kills', 'wins', 'beds_broken', 'final_kills', 'deaths', 'level', 'coins', 'win_streak'];

    /** برچسب فارسی هر دسته برای خط اول تاج */
    public const LABELS = [
        'kills' => 'KILLS · کشته‌ها',
        'wins' => 'WINS · بردها',
        'beds_broken' => 'BEDS · تخته‌های شکسته',
        'final_kills' => 'FINAL KILLS · فینال‌کیل',
        'level' => 'LEVEL · سطح',
        'coins' => 'COINS · سکه‌ها',
    ];

    /** آیکن هر دسته (برای سرتیتر نام‌تگ) */
    public const ICONS = [
        'kills' => '⚔', 'wins' => '🏆', 'beds_broken' => '🛏',
        'final_kills' => '🎯', 'level' => '🧪', 'coins' => '💰',
    ];

    /** بازیابی آیا BedWarsCore با provider زنده در دسترس است */
    public static function hasBedWars(): bool
    {
        if (!class_exists('sergittos\\bedwars\\BedWarsCore')) {
            return false;
        }
        try {
            $core = \sergittos\bedwars\BedWarsCore::getInstance();
            return $core !== null && method_exists($core, 'getProvider');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * گرفتن لیدربورد یک دسته.
     *
     * @param callable $callback function(array $rows): void  —  rows: [['name'=>string,'value'=>int], ...]
     */
    public static function leaderboard(string $stat, int $limit, callable $callback): void
    {
        if (in_array($stat, self::STATS, true) && self::hasBedWars()) {
            try {
                $provider = \sergittos\bedwars\BedWarsCore::getInstance()->getProvider();
                if (method_exists($provider, 'getLeaderboard')) {
                    $provider->getLeaderboard($stat, $limit, function (array $rows) use ($stat, $callback): void {
                        $out = [];
                        foreach ($rows as $row) {
                            $name = (string) ($row['username'] ?? $row['name'] ?? '');
                            if ($name === '') {
                                continue;
                            }
                            $out[] = ['name' => $name, 'value' => (int) ($row[$stat] ?? $row['value'] ?? 0)];
                        }
                        $callback($out);
                    });
                    return;   // نتیجه به‌صورت async می‌آید
                }
            } catch (\Throwable $e) {
                // هر خطای غیرمنتظره → فال‌بک محلی، بدون کرش
                Main::get()->getLogger()->warning('BedWarsCore leaderboard ' . $stat . ' failed: ' . $e->getMessage());
            }
        }

        // ---- فالبک: آمار محلی همین پلاگین (محلی stats.yml)
        $rows = [];
        $localTrack = match ($stat) {
            'beds_broken' => 'beds',
            'coins', 'level' => 'points',
            default => $stat,
        };
        foreach (Main::get()->stats()->top($localTrack, $limit) as $row) {
            $rows[] = ['name' => (string) $row['name'], 'value' => (int) $row['value']];
        }
        $callback($rows);
    }
}
