<?php
declare(strict_types=1);

namespace arvan\leaderboards;

use pocketmine\utils\Config;

/**
 * StatsStore — نگه‌دارندهٔ آمار بازیکنان (قهرمان‌ها، کشته‌ها، تخت‌ها، بازی‌ها).
 *
 * ذخیره‌سازی در پوشهٔ پلاگین: stats.yml
 * ساختار:  players: { "ali": { kills: 12, wins: 3, beds: 5, deaths: 4, points: 120 } }
 *
 * اگر سرور شما (BedWars/Waterdog) آمار خودش را دارد، فقط کافی است در Main
 * متد push() را از رویدادهای همان پلاگین صدا بزنید؛ بقیهٔ لیدربوردها
 * خودشان با فاصلهٔ مشخص (refresh-seconds) به‌روز می‌شوند.
 */
final class StatsStore
{
    public const TRACKS = ['kills', 'wins', 'beds', 'deaths', 'points'];

    private Config $config;

    public function __construct(private string $file)
    {
        $this->config = new Config($file, Config::YAML, ['players' => []]);
    }

    public function config(): Config
    {
        return $this->config;
    }

    /** افزودن مقدار به آمار یک بازیکن: push("ali", "kills", 1) */
    public function push(string $player, string $track, int $amount = 1): void
    {
        $player = strtolower($player);
        $all = (array) $this->config->get('players', []);
        $row = (array) ($all[$player] ?? []);
        $row[$track] = (int) ($row[$track] ?? 0) + $amount;
        $row['name'] = $player;
        $all[$player] = $row;
        $this->config->set('players', $all);
        $this->config->save();
    }

    /** تنظیم مستقیم مقدار: set("ali", "wins", 7) */
    public function set(string $player, string $track, int $value): void
    {
        $player = strtolower($player);
        $all = (array) $this->config->get('players', []);
        $row = (array) ($all[$player] ?? []);
        $row[$track] = $value;
        $row['name'] = $player;
        $all[$player] = $row;
        $this->config->set('players', $all);
        $this->config->save();
    }

    public function of(string $player, string $track): int
    {
        $all = (array) $this->config->get('players', []);
        return (int) (((array) ($all[strtolower($player)] ?? []))[$track] ?? 0);
    }

    /**
     * برترین‌ها:  [["name" => "ali", "value" => 42], ...]
     * @return list<array{name:string, value:int}>
     */
    public function top(string $track, int $limit = 10): array
    {
        $all = (array) $this->config->get('players', []);
        $rows = [];
        foreach ($all as $key => $data) {
            $data = (array) $data;
            $rows[] = [
                'name' => (string) ($data['name'] ?? $key),
                'value' => (int) ($data[$track] ?? 0),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $b['value'] <=> $a['value']);
        return array_slice($rows, 0, max(1, $limit));
    }

    /** مجموع یک ستون در کل سرور (برای هولوگرام آمار) */
    public function total(string $track): int
    {
        $all = (array) $this->config->get('players', []);
        $sum = 0;
        foreach ($all as $data) {
            $sum += (int) (((array) $data)[$track] ?? 0);
        }
        return $sum;
    }

    public function playerCount(): int
    {
        return count((array) $this->config->get('players', []));
    }
}
