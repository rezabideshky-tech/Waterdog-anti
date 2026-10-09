<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\gui;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;

class LeaderboardGui {

    private const CATEGORIES = [
        "kills"      => ["Kills", "kills"],
        "wins"       => ["Wins", "wins"],
        "finalkills" => ["Final Kills", "final_kills"],
        "deaths"     => ["Deaths", "deaths"],
        "bedbroken"  => ["Beds Broken", "beds_broken"],
        "level"      => ["Level", "level"],
        "coins"      => ["Coins", "coins"],
        "winstreak"  => ["Win Streak", "win_streak"],
    ];

    public function openMain(Player $player): void {
        $form = new SimpleForm(function(Player $player, ?int $data): void {
            if ($data === null) return;
            $keys = array_keys(self::CATEGORIES);
            if (isset($keys[$data])) {
                $this->openCategory($player, $keys[$data]);
            }
        });
        $form->setTitle(TF::BOLD . TF::GOLD . "Leaderboards");
        $form->setContent("§7Choose a leaderboard to view:");
        foreach (self::CATEGORIES as [$label, ]) {
            $form->addButton($label);
        }
        $player->sendForm($form);
    }

    public function openCategory(Player $player, string $stat): void {
        if (!isset(self::CATEGORIES[$stat])) {
            $player->sendMessage(TF::RED . "Unknown leaderboard: $stat. Valid: " . implode(", ", array_keys(self::CATEGORIES)));
            return;
        }
        [$label, $column] = self::CATEGORIES[$stat];

        BedWarsCore::getInstance()->getProvider()->getLeaderboard($column, 10, function(array $rows) use ($player, $label, $column): void {
            $content = "";
            if (empty($rows)) {
                $content = "§7No data yet.";
            } else {
                foreach ($rows as $i => $row) {
                    $content .= "§b" . ($i + 1) . ". §f" . $row["username"] . " §7- §e" . number_format((int) $row[$column]) . "\n";
                }
            }

            $form = new SimpleForm(function(Player $player, ?int $data): void {});
            $form->setTitle(TF::BOLD . TF::GOLD . "Top " . $label);
            $form->setContent($content);
            $form->addButton(TF::RED . "Close");
            $player->sendForm($form);
        });
    }

}
