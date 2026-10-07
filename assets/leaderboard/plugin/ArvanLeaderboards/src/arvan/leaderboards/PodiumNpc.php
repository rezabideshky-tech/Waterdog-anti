<?php
declare(strict_types=1);

namespace arvan\leaderboards;

/**
 * PodiumNpc — سکوی سه‌نفرهٔ قهرمانان (مدل arvan:podium).
 * ستون نور و تاج مدل، همراه با نام‌تگ سه قهرمان اول.
 */
final class PodiumNpc extends FloatingNpc
{
    public const NETWORK_ID = 'arvan:podium';

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function key(): string
    {
        return 'podium';
    }

    public function track(): string
    {
        $t = (string) $this->cfg('track', 'wins');
        return in_array($t, StatsStore::TRACKS, true) ? $t : 'wins';
    }

    public function refreshNameTag(): void
    {
        $track = $this->track();
        $label = (string) $this->cfg('tracks.' . $track . '.label', $track);
        $rows = $this->store()->top($track, 3);
        $crowns = ['§e§l👑', '§7§l🥈', '§6§l🥉'];
        $lines = ['§l§6قهرمانان ' . $label, '§7————————————'];
        foreach ($rows as $i => $row) {
            $lines[] = $crowns[$i] . ' §r§f' . TopNpc::pretty($row['name']) . ' §8» §a' . $row['value'];
        }
        while (count($lines) < 5) {
            $lines[] = '§8— خالی —';
        }
        $this->setNameTag(implode("\n", $lines));
    }
}
