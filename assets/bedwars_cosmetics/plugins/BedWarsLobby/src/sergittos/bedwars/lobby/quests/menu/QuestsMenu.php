<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\quests\menu;

use pocketmine\player\Player;
use sergittos\bedwars\lobby\quests\data\PlayerQuestDataManager;
use sergittos\bedwars\lobby\quests\form\SimpleForm;
use sergittos\bedwars\lobby\quests\quest\QuestDefinition;
use sergittos\bedwars\lobby\quests\quest\QuestRegistry;
use sergittos\bedwars\lobby\quests\quest\QuestType;
use function count;
use function number_format;

/**
 * Daily Quests menu.
 *
 * Rewards are granted automatically the instant a quest's progress hits its
 * goal (see PlayerQuestDataManager::applyQuestProgress()/autoClaim()) - the
 * player never needs to open this menu to collect anything.
 *
 * This is intentionally a single screen: there is no per-quest "details"
 * page anymore. Every quest's status (name, mode, progress, reward) is
 * shown directly as its button in this one menu, and tapping a button does
 * not navigate anywhere - it just closes the form, since there is nothing
 * further to show or claim.
 */
final class QuestsMenu{

    /**
     * Entry point. Kept as openMain() so QuestsCommand and every other
     * caller keeps working unchanged - it just goes straight to Daily now.
     */
    public static function openMain(Player $player, PlayerQuestDataManager $data): void{
        self::openDaily($player, $data);
    }

    public static function openDaily(Player $player, PlayerQuestDataManager $data): void{
        $data->syncPlayer($player);

        $quests = QuestRegistry::getInstance()->allByType(QuestType::DAILY);
        $completed = $data->getCompletedCount($player, QuestType::DAILY);
        $total = count($quests);

        $content = "§d§lCompleted: §f" . $completed . "§8/§f" . $total;

        // No-op on selection - this is a status view only, so a click just
        // closes the form instead of opening a details page.
        $form = new SimpleForm("§l§6DAILY QUESTS", $content, function(Player $p, int $index): void{});

        foreach($quests as $quest){
            // No icon on purpose - addButton()'s $iconPath is optional and
            // simply omitted here so the Daily Quests menu renders as a
            // plain text list of buttons.
            $form->addButton(self::buildQuestButton($player, $data, $quest));
        }

        $player->sendForm($form);
    }

    private static function buildQuestButton(Player $player, PlayerQuestDataManager $data, QuestDefinition $quest): string{
        $qid = $quest->getId();
        $progress = $data->getProgress($player, $qid);
        $goal = $quest->getGoal();
        $claimed = $data->isClaimed($player, $qid);

        $nameColor = $claimed ? "§a§l" : "§e§l";

        return
            $nameColor . $quest->getName() . "\n" .
            "§7Progress: §f" . number_format($progress) . "§8/§f" . number_format($goal) . "\n" .
            "§6+" . number_format($quest->getRewardCoins()) . " Coins §7  §b+" . number_format($quest->getRewardExp()) . " EXP";
    }
}
