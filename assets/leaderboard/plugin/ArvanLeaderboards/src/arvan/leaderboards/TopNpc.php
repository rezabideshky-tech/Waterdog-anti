<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * TopNpc — لیدربورد لیدربورد طلایی (مدل arvan:leaderboard).
 * نام‌تگ = فهرست برترین‌ها با رنگ‌بندی مدال (طلا/نقره/برنز).
 */
final class TopNpc extends FloatingNpc
{
    public const NETWORK_ID = 'arvan:leaderboard';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'top';
    }

    /** ستونی که این لیدربورد نشان می‌دهد (kills/wins/beds/...) */
    public function track(): string
    {
        $t = (string) $this->cfg('track', 'wins');
        return in_array($t, StatsStore::TRACKS, true) ? $t : 'wins';
    }

    public function refreshNameTag(): void
    {
        $title = (string) $this->cfg('title', '§l§6BEDWARS §fTOP');
        $limit = (int) $this->cfg('limit', 10);
        $track = $this->track();
        $label = (string) $this->cfg('tracks.' . $track . '.label', $track);

        $lines = [$title, '§7————————————'];
        $rows = $this->store()->top($track, $limit);
        $medals = ['§e§l1', '§7§l2', '§6§l3'];
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            $num = $medals[$i - 1] ?? ('§8' . $i);
            $name = self::pretty($row['name']);
            $lines[] = $num . ' §r§f' . $name . ' §8| §a' . $row['value'] . ' §7' . $label;
        }
        if ($i === 0) {
            $lines[] = '§7هنوز آماری ثبت نشده';
        }
        $lines[] = '§7————————————';
        $lines[] = '§8👆 برای دیدن جدول کامل ضربه بزن';
        $this->setNameTag(implode("\n", $lines));
    }

    public static function pretty(string $name): string
    {
        return ucfirst($name);
    }
}
