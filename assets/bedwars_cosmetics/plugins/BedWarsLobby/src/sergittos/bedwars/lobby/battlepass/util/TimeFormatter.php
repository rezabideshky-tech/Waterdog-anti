<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\util;

final class TimeFormatter{

    public static function left(int $to): string{
        $now = time();
        $sec = $to - $now;
        if($sec <= 0){
            return "0s";
        }

        $d = intdiv($sec, 86400);
        $sec %= 86400;

        $h = intdiv($sec, 3600);
        $sec %= 3600;

        $m = intdiv($sec, 60);
        $s = $sec % 60;

        if($d > 0) return $d . "d " . $h . "h";
        if($h > 0) return $h . "h " . $m . "m";
        if($m > 0) return $m . "m " . $s . "s";
        return $s . "s";
    }

    /** "34 days 12h" style used on the Battle Pass screen */
    public static function season(int $to): string{
        $sec = $to - time();
        if($sec <= 0){
            return "Ended";
        }

        $d = intdiv($sec, 86400);
        $h = intdiv($sec % 86400, 3600);
        $m = intdiv($sec % 3600, 60);

        if($d > 0) return $d . ($d === 1 ? " day " : " days ") . $h . "h";
        if($h > 0) return $h . "h " . $m . "m";
        return max(1, $m) . "m";
    }
}
