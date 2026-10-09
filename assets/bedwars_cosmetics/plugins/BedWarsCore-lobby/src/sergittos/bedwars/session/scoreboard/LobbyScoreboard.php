<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\scoreboard;

use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\session\Session;

final class LobbyScoreboard extends Scoreboard{

    /**
     * Pulls the "LobbyScore" logo from the TitleAndScore resource pack
     * instead of the shared "BedWarsScore" one every other Scoreboard
     * subclass uses (see Scoreboard::RESOURCE_OBJECTIVE_NAME) - this is
     * the network hub board, not an arena board, so it should show the
     * Lobby logo the pack ships for exactly that objective name.
     */
    protected function getObjectiveName(): string{
        return "LobbyScore";
    }

    /**
     * چیدمان دقیقاً مطابق عکس مرجع بازسازی شده: زیر تایتل "BedWars" فقط
     * تاریخ می‌آد (بدون متن "BedWars Lobby" - قبلاً این متن جزو همین خط
     * تاریخ چاپ می‌شد که طبق درخواست حذف شده)، بعد بخش ". INFO" با
     * Name/Level/Progress + نوار پیشرفت، بعد بخش ". STATS" با Coins
     * (قبلاً Tokens بود)/Wins/Kills|Finals/Deaths، و در پایین آی‌پی سرور
     * با یک آیکون رعد.
     */
    protected function getLines(Session $session): array{
        $ip = (string) BedWarsCore::getInstance()->getConfig()->get("ip", "ArvanGaming.IR");

        $xp = $session->getXp();
        $need = $session->getRequiredXPForNextLevel();

        return [
            16 => "{GRAY}" . date("m/d/y"),
            15 => " ",
            14 => "{AQUA}. §d§lINFO",
            13 => "{WHITE}Name: {YELLOW}" . $session->getUsername(),
            12 => "{WHITE}Level: {GREEN}" . $session->getLevel() . $session->getLevelLetter(),
            11 => "{WHITE}Progress: {AQUA}" . $this->shortNumber($xp) . "{GRAY}/{AQUA}" . $this->shortNumber($need),
            10 => $this->bar($xp, $need, 16),
            9  => "  ",
            8  => "{AQUA}. §d§lSTATS",
            7  => "{WHITE}Coins: {GREEN}" . $session->getCoins(),
            6  => "{WHITE}Wins: {GREEN}" . $session->getWins(),
            5  => "{WHITE}Kills: {GREEN}" . $session->getKills() . " {GRAY}| {WHITE}Finals: {GREEN}" . $session->getFinalKills(),
            4  => "{WHITE}Deaths: {GREEN}" . $session->getDeaths(),
            3  => "   ",
            2  => "{YELLOW}" . $ip,
        ];
    }

    private function bar(int $value, int $max, int $size): string{
        if($max <= 0){
            $max = 1;
        }
        $filled = (int) floor(($value / $max) * $size);
        if($filled < 0) $filled = 0;
        if($filled > $size) $filled = $size;

        return "{AQUA}" . str_repeat("■", $filled) . "{DARK_GRAY}" . str_repeat("■", $size - $filled);
    }

    private function shortNumber(int $n): string{
        if($n >= 1_000_000){
            return (int) floor($n / 1_000_000) . "m";
        }
        if($n >= 1_000){
            return (int) floor($n / 1_000) . "k";
        }
        return (string) $n;
    }
}