<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\gui;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\session\Session;

class StatsGui {

    public function open(Player $player, Session $session): void {
        // Reads the level curve from Session itself instead of duplicating
        // the formula here, so this screen can never drift out of sync with
        // the actual leveling logic (and the level-up loop) in Session.
        $xpForNext = $session->getRequiredXPForNextLevel();
        $xpCurrent = $session->getXp() % max(1, $xpForNext);
        $progress  = $xpForNext > 0 ? min(100, (int)(($xpCurrent / $xpForNext) * 100)) : 0;
        $bar       = $this->makeBar($progress);

        $content =
            $session->getFormattedLevel() . " " . $session->getRankColor() . $session->getUsername() . "\n" .
            TF::DARK_GRAY . "━━━━━━━━━━━━━━━━━━━━\n\n" .
            TF::GOLD . "⚔ Kills:        " . TF::WHITE . number_format($session->getKills()) . "\n" .
            TF::RED  . "💀 Final Kills: " . TF::WHITE . number_format($session->getFinalKills()) . "\n" .
            TF::GREEN . "🏆 Wins:        " . TF::WHITE . number_format($session->getWins()) . "\n" .
            TF::AQUA  . "🛏 Beds Broken: " . TF::WHITE . number_format($session->getBedsBroken()) . "\n\n" .
            TF::YELLOW . "§f\u{F167} " . TF::YELLOW . "Level: " . TF::WHITE . $session->getLevel() . "\n" .
            TF::GRAY   . "XP: " . TF::WHITE . number_format($xpCurrent) . TF::DARK_GRAY . "/" . TF::GRAY . number_format($xpForNext) . "\n" .
            TF::DARK_GRAY . "[" . TF::GREEN . $bar . TF::DARK_GRAY . "] " . TF::WHITE . $progress . "%\n\n" .
            TF::DARK_GRAY . "━━━━━━━━━━━━━━━━━━━━";

        $form = new SimpleForm(function(Player $p, ?int $d): void {
            // بستن فرم – کاری نمی‌کنیم
        });
        $form->setTitle(TF::BOLD . TF::WHITE . "📊 My Statistics");
        $form->setContent($content);
        $form->addButton(TF::RED . "Close");
        $form->sendToPlayer($player);  // ← تفاوت اصلی
    }

    private function makeBar(int $percent, int $length = 20): string {
        $filled = (int)($percent / 100 * $length);
        return str_repeat("█", $filled) . TF::DARK_GRAY . str_repeat("█", $length - $filled);
    }
}