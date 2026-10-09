<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\profile\ui;

use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\lobby\profile\ProfileMenu;
use sergittos\bedwars\lobby\profile\ProfileView;
use sergittos\bedwars\profile\PlayerProfile;
use sergittos\bedwars\profile\ProfileCatalog;
use sergittos\bedwars\profile\ProfileConfig;
use sergittos\bedwars\profile\ProfileService;
use sergittos\bedwars\profile\RankedTiers;
use function array_slice;
use function array_keys;
use function count;
use function in_array;
use function floor;
use function intdiv;
use function max;
use function min;
use function number_format;
use function round;
use function sprintf;
use function str_pad;
use function strtoupper;
use function time;
use function usort;
use const STR_PAD_LEFT;

/** Turns a loaded ProfileView into the slot => value map of the matching layout (text or texture path). */
final class ProfileValues{

    private const TEX = "textures/bwp/";

    /** @return array<string, string> */
    public static function build(ProfileView $v) : array{
        $svc = BedWarsCore::getInstance()->getProfileService();
        $out = self::shell($v, $svc);
        $out += match($v->tab){
            "home" => self::home($v, $svc),
            "stats" => self::stats($v),
            "rank" => self::rank($v, $svc),
            "medals" => self::medals($v),
            "ach" => self::achievements($v),
            "history" => self::history($v),
            "board" => self::board($v, $svc),
            default => [],
        };
        return $out;
    }

    // ------------------------------------------------------------------ helpers

    private static function n(int|float $x) : string{
        return number_format($x);
    }

    private static function bar(string $style, float $ratio) : string{
        $step = (int) round(40 * max(0.0, min(1.0, $ratio)));
        return self::TEX . "bars/" . $style . str_pad((string) $step, 2, "0", STR_PAD_LEFT);
    }

    public static function xpNeeded(int $level) : int{
        // Same curve as Session::getRequiredXPForNextLevel(): 5,000 XP per level.
        return 5000 * max(1, $level);
    }

    private static function duration(int $secs) : string{
        $secs = max(0, $secs);
        if($secs >= 3600){
            return intdiv($secs, 3600) . "h " . intdiv($secs % 3600, 60) . "m";
        }
        return intdiv($secs, 60) . "m " . ($secs % 60) . "s";
    }

    private static function ago(int $ts) : string{
        $d = max(0, time() - $ts);
        if($d < 60){
            return "just now";
        }
        if($d < 3600){
            return intdiv($d, 60) . "m ago";
        }
        if($d < 86400){
            return intdiv($d, 3600) . "h ago";
        }
        return intdiv($d, 86400) . "d ago";
    }

    private static function countdown(int $secs) : string{
        $secs = max(0, $secs);
        if($secs >= 86400){
            return intdiv($secs, 86400) . "d " . intdiv($secs % 86400, 3600) . "h";
        }
        if($secs >= 3600){
            return intdiv($secs, 3600) . "h " . intdiv($secs % 3600, 60) . "m";
        }
        return max(1, intdiv($secs, 60)) . "m";
    }

    /** @return array{tier: int, division: int, floor: int, next: ?int, tierFloor: int, into: int, span: int} */
    private static function loc(ProfileService $svc, int $rp) : array{
        return $svc->locate($rp);
    }

    /** @return array<string, string> */
    private static function shell(ProfileView $v, ProfileService $svc) : array{
        $p = $v->profile;
        $loc = self::loc($svc, $p->rp);
        $need = self::xpNeeded($p->level);
        $ends = $svc->getSeasonEnds();

        if($v->tab === "board"){
            $footer = $p->rankPosition !== null
                ? "§7Your position: §f#" . self::n($p->rankPosition) . "\n§8Ranks reset when a new season begins"
                : "§7Play a ranked match to enter the board\n§8Ranks reset when a new season begins";
        }elseif(!$v->self){
            $footer = "§7Viewing the profile of §f" . $p->displayName . "\n§8Season ranks reset every season";
        }elseif($p->games === 0){
            $footer = "§7Detailed tracking begins with your\n§7first ranked match";
        }else{
            $footer = "§7Season §f" . $svc->getSeasonId() . " §8| §7Ranks reset every season\n§8Win matches to earn RP";
        }

        $pageText = "";
        if(in_array($v->tab, ["medals", "ach", "history"], true)){
            $pageText = "§fPAGE " . ($v->page + 1) . " §8/ §f" . $v->pages;
        }

        return [
            "name" => "§l§f" . $p->displayName,
            "lvl" => "§dLEVEL §f" . $p->level,
            "uid" => "§8UID §7" . $p->uid(),
            "xpbar" => self::bar("p", $need > 0 ? $p->xp / $need : 0.0),
            "xp" => "§f" . self::n($p->xp) . " §8/ §d" . self::n($need) . " XP",
            "rank_icon" => self::TEX . "rank/" . RankedTiers::tierId($loc["tier"]) . "_sm",
            "rank_line" => "§l" . RankedTiers::coloredLabel($loc) . "\n§6" . self::n($p->rp) . " §7RP",
            "season" => "§6" . $svc->getSeasonLabel(),
            "season_sub" => $ends > 0 ? "§7Season ends in §f" . self::countdown($ends - time()) : "§7Ranked season in progress",
            "footer" => $footer,
            "page" => $pageText,
        ];
    }

    // ------------------------------------------------------------------ home

    /** @return array<string, string> */
    private static function home(ProfileView $v, ProfileService $svc) : array{
        $p = $v->profile;
        $loc = self::loc($svc, $p->rp);
        $out = [];

        $out["hero_icon"] = self::TEX . "rank/" . RankedTiers::tierId($loc["tier"]);
        $out["hero_name"] = "§l" . RankedTiers::coloredLabel($loc);
        $out["hero_rp"] = "§6" . self::n($p->rp) . " §eRP";
        if($loc["next"] === null){
            $out["hero_bar"] = self::bar("g", 1.0);
            $out["hero_next"] = "§dTop rank reached - keep stacking RP";
        }else{
            $out["hero_bar"] = self::bar("g", $loc["span"] > 0 ? $loc["into"] / $loc["span"] : 0.0);
            $target = self::loc($svc, $loc["next"]);
            $out["hero_next"] = "§7" . self::n($loc["next"] - $p->rp) . " RP to §f" . RankedTiers::label($target);
        }
        $out["hero_peak"] = $p->peakRp > 0 ? "§7SEASON PEAK §f" . RankedTiers::label(self::loc($svc, $p->peakRp)) : "§7SEASON PEAK §8none yet";
        $out["hero_pos"] = $p->rankPosition !== null ? "§7POSITION §f#" . self::n($p->rankPosition) : "§7POSITION §8unranked";

        $out["q_wins"] = "§a" . self::n($p->wins);
        $out["q_wr"] = "§b" . sprintf("%.1f", $p->winRate()) . "%";
        $out["q_kd"] = "§e" . sprintf("%.2f", $p->kd());
        $out["q_final"] = "§d" . self::n($p->finals);
        $out["q_beds"] = "§6" . self::n($p->beds);
        $out["q_streak"] = "§c" . self::n($p->bestStreak);

        // next goals: the unfinished achievements that are closest to completion
        $cands = [];
        foreach(ProfileCatalog::ACHIEVEMENTS as $id => $def){
            $cur = $p->statValue($def["stat"]);
            if($cur >= $def["goal"]){
                continue;
            }
            $cands[] = ["id" => $id, "def" => $def, "cur" => $cur, "ratio" => $cur / $def["goal"]];
        }
        usort($cands, static fn(array $a, array $b) : int => $b["ratio"] <=> $a["ratio"]);
        for($i = 0; $i < 3; $i++){
            $c = $cands[$i] ?? null;
            if($c === null){
                $out["g" . $i . "_icon"] = "";
                $out["g" . $i . "_txt"] = "";
                $out["g" . $i . "_bar"] = "";
                continue;
            }
            $out["g" . $i . "_icon"] = self::TEX . "ach/" . $c["id"];
            $out["g" . $i . "_txt"] = "§l§f" . $c["def"]["name"] . "\n§7" . $c["def"]["desc"] . "\n§d" . self::n((int) floor($c["cur"])) . " §8/ §d" . self::n($c["def"]["goal"]);
            $out["g" . $i . "_bar"] = self::bar("p", $c["ratio"]);
        }
        return $out;
    }

    // ------------------------------------------------------------------ stats

    /** @return array<string, string> */
    private static function stats(ProfileView $v) : array{
        $p = $v->profile;
        return [
            "s_wins" => "§a" . self::n($p->wins),
            "s_losses" => "§c" . self::n($p->losses),
            "s_games" => "§d" . self::n($p->games),
            "s_wr" => "§b" . sprintf("%.1f", $p->winRate()) . "%",
            "s_kills" => "§a" . self::n($p->kills),
            "s_deaths" => "§c" . self::n($p->deaths),
            "s_kd" => "§e" . sprintf("%.2f", $p->kd()),
            "s_finals" => "§d" . self::n($p->finals),
            "s_beds" => "§6" . self::n($p->beds),
            "s_streak" => "§c" . self::n($p->bestStreak),
            "s_time" => "§b" . self::duration($p->playtime),
            "s_mvp" => "§e" . self::n($p->mvps),
        ];
    }

    // ------------------------------------------------------------------ rank

    /** @return array<string, string> */
    private static function rank(ProfileView $v, ProfileService $svc) : array{
        $p = $v->profile;
        $loc = self::loc($svc, $p->rp);
        $peak = self::loc($svc, $p->peakRp);
        $out = [];
        for($t = 0; $t < RankedTiers::TIER_COUNT; $t++){
            $id = RankedTiers::tierId($t);
            $suffix = $t === $loc["tier"] ? "_cur" : ($t <= $peak["tier"] ? "" : "_dim");
            $out["r" . $t] = self::TEX . "rank/" . $id . $suffix;
        }
        $out["rk_title"] = "§l" . RankedTiers::coloredLabel($loc);
        if($loc["next"] === null){
            $out["rk_rp"] = "§6" . self::n($p->rp) . " §eRP";
            $out["rk_bar"] = self::bar("g", 1.0);
            $out["rk_hint"] = "§dGrandmaster - the highest rank. Defend it until the season ends.";
        }else{
            $out["rk_rp"] = "§6" . self::n($p->rp) . " §8/ §6" . self::n($loc["next"]) . " §eRP";
            $out["rk_bar"] = self::bar("g", $loc["span"] > 0 ? $loc["into"] / $loc["span"] : 0.0);
            $out["rk_hint"] = "§7" . self::n($loc["next"] - $p->rp) . " RP until §f" . RankedTiers::label(self::loc($svc, $loc["next"]));
        }

        $cfg = $svc->getConfig();
        $rows = $v->extra["seasons"] ?? [];
        for($i = 0; $i < 4; $i++){
            $r = $rows[$i] ?? null;
            if($r === null){
                $out["h" . $i . "_s"] = "";
                $out["h" . $i . "_r"] = $i === 0 && $rows === [] ? "§7No finished seasons yet" : "";
                $out["h" . $i . "_p"] = "";
                continue;
            }
            $season = (int) $r["season"];
            $peakRp = (int) $r["peak_rp"];
            $sizes = RankedTiers::sizes($cfg, $season);
            $out["h" . $i . "_s"] = "§8SERIES " . str_pad((string) $season, 2, "0", STR_PAD_LEFT);
            $out["h" . $i . "_r"] = RankedTiers::coloredLabel(RankedTiers::locate($sizes, $peakRp));
            $out["h" . $i . "_p"] = "§6" . self::n($peakRp) . " §7peak RP";
        }
        return $out;
    }

    // ------------------------------------------------------------------ medals

    /** @return array<string, string> */
    private static function medals(ProfileView $v) : array{
        $p = $v->profile;
        $ids = array_keys(ProfileCatalog::MEDALS);
        $slice = array_slice($ids, $v->page * ProfileMenu::MEDALS_PER_PAGE, ProfileMenu::MEDALS_PER_PAGE);
        $out = [];
        for($i = 0; $i < ProfileMenu::MEDALS_PER_PAGE; $i++){
            $id = $slice[$i] ?? null;
            if($id === null){
                $out["m" . $i . "_icon"] = "";
                $out["m" . $i . "_txt"] = "";
                continue;
            }
            $def = ProfileCatalog::MEDALS[$id];
            $count = $p->medals[$id] ?? 0;
            if($count > 0){
                $out["m" . $i . "_icon"] = self::TEX . "medals/" . $id;
                $out["m" . $i . "_txt"] = "§l§f" . $def["name"] . "\n§7" . $def["desc"] . "\n§aEARNED §fx" . self::n($count);
            }else{
                $out["m" . $i . "_icon"] = self::TEX . "medals/" . $id . "_lock";
                $out["m" . $i . "_txt"] = "§l§8" . $def["name"] . "\n§8" . $def["desc"] . "\n§5LOCKED";
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ achievements

    /** @return array<string, string> */
    private static function achievements(ProfileView $v) : array{
        $p = $v->profile;
        $ids = array_keys(ProfileCatalog::ACHIEVEMENTS);
        $slice = array_slice($ids, $v->page * ProfileMenu::ACHIEVEMENTS_PER_PAGE, ProfileMenu::ACHIEVEMENTS_PER_PAGE);
        $out = [];
        for($i = 0; $i < ProfileMenu::ACHIEVEMENTS_PER_PAGE; $i++){
            $id = $slice[$i] ?? null;
            if($id === null){
                $out["a" . $i . "_icon"] = "";
                $out["a" . $i . "_txt"] = "";
                $out["a" . $i . "_bar"] = "";
                continue;
            }
            $def = ProfileCatalog::ACHIEVEMENTS[$id];
            $cur = $p->statValue($def["stat"]);
            $done = $cur >= $def["goal"];
            $shown = (int) floor(min($cur, (float) $def["goal"]));
            $out["a" . $i . "_icon"] = self::TEX . "ach/" . $id . ($done ? "" : "_lock");
            $out["a" . $i . "_txt"] = ($done ? "§l§f" : "§l§7") . $def["name"] . "\n§7" . $def["desc"] . "\n" . ($done ? "§aCOMPLETE" : "§d" . self::n($shown) . " §8/ §d" . self::n($def["goal"]));
            $out["a" . $i . "_bar"] = self::bar("p", $done ? 1.0 : $cur / $def["goal"]);
        }
        return $out;
    }

    // ------------------------------------------------------------------ history

    /** @return array<string, string> */
    private static function history(ProfileView $v) : array{
        $rows = $v->extra["history"] ?? [];
        $out = [];
        for($i = 0; $i < ProfileMenu::HISTORY_PER_PAGE; $i++){
            $r = $rows[$i] ?? null;
            if($r === null){
                $out["x" . $i . "_chip"] = "";
                $out["x" . $i . "_main"] = "";
                $out["x" . $i . "_stats"] = "";
                $out["x" . $i . "_rp"] = "";
                continue;
            }
            $res = (string) $r["result"];
            $chip = match($res){"WIN" => "chip_win", "TIE" => "chip_tie", "LEFT" => "chip_left", default => "chip_loss"};
            $delta = (int) $r["rp_delta"];
            $map = (string) $r["map"];
            $out["x" . $i . "_chip"] = self::TEX . $chip;
            $out["x" . $i . "_main"] = "§l§f" . strtoupper((string) $r["mode"]) . ($map !== "" ? " §8- §7" . $map : "") . "\n§8" . self::duration((int) $r["duration"]);
            $out["x" . $i . "_stats"] = "§7Kills §f" . (int) $r["kills"] . "  §7Finals §f" . (int) $r["finals"] . "\n§7Beds §f" . (int) $r["beds"] . "  §7Deaths §f" . (int) $r["deaths"];
            $out["x" . $i . "_rp"] = ($delta >= 0 ? "§l§a+" : "§l§c") . $delta . " RP\n§8" . self::ago((int) $r["ts"]);
        }
        $out["x_empty"] = $rows === [] ? "§7No ranked matches yet.\n§8Finish a match and it will show up here." : "";
        return $out;
    }

    // ------------------------------------------------------------------ board

    /** @return array<string, string> */
    private static function board(ProfileView $v, ProfileService $svc) : array{
        $rows = $v->extra["top"] ?? [];
        $out = [];
        for($i = 0; $i < 10; $i++){
            $r = $rows[$i] ?? null;
            if($r === null){
                $out["b" . $i . "_icon"] = "";
                $out["b" . $i . "_name"] = "";
                $out["b" . $i . "_rp"] = "";
                continue;
            }
            $rp = (int) $r["rp"];
            $loc = self::loc($svc, $rp);
            $name = (string) ($r["display_name"] !== "" ? $r["display_name"] : $r["username"]);
            $me = strtolower((string) $r["username"]) === strtolower($v->viewerName);
            $out["b" . $i . "_icon"] = self::TEX . "rank/" . RankedTiers::tierId($loc["tier"]) . "_sm";
            $out["b" . $i . "_name"] = ($me ? "§l§e" : "§l§f") . $name . "\n" . RankedTiers::coloredLabel($loc);
            $out["b" . $i . "_rp"] = "§6" . self::n($rp) . " §eRP";
        }
        $out["b_empty"] = $rows === [] ? "§7Nobody is ranked yet this season.\n§8Be the first to climb the ladder." : "";
        return $out;
    }
}
