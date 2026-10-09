<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\challenges\menu;

use pocketmine\player\Player;
use sergittos\bedwars\api\CoinsAPI;
use sergittos\bedwars\lobby\challenges\data\PlayerChallengeDataManager;
use sergittos\bedwars\lobby\challenges\form\SimpleForm;
use sergittos\bedwars\lobby\challenges\registry\ChallengeRegistry;
use function count;
use function floor;
use function number_format;
use function str_repeat;
use function time;

/**
 * Challenges menu.
 *
 * Progress is tracked automatically, but the reward is only granted once
 * the player opens the challenge and presses the "Claim Reward" button -
 * see PlayerChallengeDataManager::claimOne().
 */
final class ChallengesMenu{

    private const BAR_LENGTH = 10;

    private static function progressBar(int $progress, int $goal): string{
        $goal = $goal > 0 ? $goal : 1;
        $ratio = $progress >= $goal ? 1.0 : $progress / $goal;
        $filled = (int) floor($ratio * self::BAR_LENGTH);
        if($filled > self::BAR_LENGTH){
            $filled = self::BAR_LENGTH;
        }
        $empty = self::BAR_LENGTH - $filled;
        $color = $ratio >= 1.0 ? "§a" : ($ratio >= 0.5 ? "§e" : "§c");
        return $color . str_repeat("\xE2\x96\xA0", $filled) . "§8" . str_repeat("\xE2\x96\xA0", $empty);
    }

    public static function openMain(Player $player, ChallengeRegistry $registry, PlayerChallengeDataManager $data): void{
        $data->load($player, $registry, function(array $row) use ($player, $registry, $data): void{
            if(!$player->isConnected()){
                return;
            }

            $list = $registry->getVisibleChallenges();
            $coins = (int) CoinsAPI::getCoins($player);

            $readyCount = 0;
            foreach($list as $ch){
                $progress = $data->getProgress($row, $ch->getId());
                if($progress >= $ch->getGoal() && !$data->isClaimed($row, $ch->getId())){
                    $readyCount++;
                }
            }

            $content =
                "§8§m                                   §r\n" .
                "§b§lCHALLENGES §r§8| §7Limited-Time Rewards\n" .
                "§8§m                                   §r\n\n" .
                "§7Complete limited-time challenges, then open one and press\n" .
                "§7§lClaim Reward§r§7 to collect it.\n\n" .
                "§7Coins: §6§l" . number_format($coins) . "\n" .
                "§7Active Challenges: §b" . count($list) .
                ($readyCount > 0 ? "\n§e§lReady to Claim: §a" . $readyCount : "");

            $form = new SimpleForm("§l§bChallenges", $content, function(Player $player, int $index) use ($registry, $data, $list): void{
                if(!$player->isConnected()){
                    return;
                }

                if(!isset($list[$index])){
                    return;
                }

                self::openChallenge($player, $registry, $data, $list[$index]->getId());
            });

            foreach($list as $ch){
                $status = $data->statusLine($row, $ch);
                $progress = $data->getProgress($row, $ch->getId());
                $goal = $ch->getGoal();
                $bar = self::progressBar($progress, $goal);
                $ready = $progress >= $goal && !$data->isClaimed($row, $ch->getId());
                $form->addButton(
                    $ch->getColoredName() . ($ready ? " §a\xE2\x9C\x94" : "") . "\n" .
                    $bar . " §7" . number_format($progress) . "§8/§7" . number_format($goal) . "\n" .
                    $status,
                    $ch->getIcon()
                );
            }

            $player->sendForm($form);
        });
    }

    public static function openChallenge(Player $player, ChallengeRegistry $registry, PlayerChallengeDataManager $data, string $id): void{
        $ch = $registry->get($id);
        if($ch === null){
            return;
        }

        $data->load($player, $registry, function(array $row) use ($player, $registry, $data, $ch, $id): void{
            if(!$player->isConnected()){
                return;
            }

            $progress = $data->getProgress($row, $id);
            $goal = $ch->getGoal();
            $status = $data->statusLine($row, $ch);
            $claimed = $data->isClaimed($row, $id);
            $ready = $progress >= $goal && !$claimed;

            $timeLeft = $ch->isRunning(time())
                ? \sergittos\bedwars\lobby\challenges\util\TimeFormatter::left($ch->getEnd())
                : "§cEnded";

            $content =
                "§8§m                                   §r\n" .
                "§b§l" . $ch->getNamePlain() . "\n" .
                "§8§m                                   §r\n\n" .
                "§7Time Left: §f" . $timeLeft . "\n" .
                "§7Reward: " . $ch->getRewardPreview() . "\n" .
                "§7Status: " . $status . "\n\n" .
                "§7Progress: §b" . number_format($progress) . "§7/§b" . number_format($goal) . "\n" .
                self::progressBar($progress, $goal) . "\n\n" .
                ($ready
                    ? "§a§lThis challenge is complete! §r§7Press §aClaim Reward §7below."
                    : ($claimed
                        ? "§7You already claimed this reward."
                        : "§7Keep going - your reward unlocks at §b" . number_format($goal) . "§7."));

            $form = new SimpleForm("§l§b" . $ch->getNamePlain(), $content, function(Player $player, int $index) use ($registry, $data, $id, $ready): void{
                if(!$player->isConnected()){
                    return;
                }

                if($ready && $index === 0){
                    $data->claimOne($player, $registry, $id, function(array $row, bool $claimed) use ($player, $registry, $data): void{
                        if(!$player->isConnected()){
                            return;
                        }
                        if($claimed){
                            $player->sendMessage("§a§l\xE2\x9C\x94 Reward Claimed§r §7- enjoy!");
                        }
                        self::openMain($player, $registry, $data);
                    });
                    return;
                }

                self::openMain($player, $registry, $data);
            });

            if($ready){
                $form->addButton("§a§lClaim Reward", "textures/ui/free_download_symbol");
            }
            $form->addButton("§l§7\xC2\xAB Back", "textures/ui/undo");
            $player->sendForm($form);
        });
    }
}
