<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\menu;

use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\battlepass\data\PlayerBattlePassDataManager;
use sergittos\bedwars\lobby\battlepass\form\SimpleForm;
use sergittos\bedwars\lobby\battlepass\registry\BattlePassRegistry;
use sergittos\bedwars\lobby\battlepass\ui\BattlePassForm;
use sergittos\bedwars\lobby\battlepass\ui\BattlePassLayout;
use sergittos\bedwars\lobby\battlepass\ui\BattlePassUi;
use sergittos\bedwars\lobby\battlepass\util\TimeFormatter;
use function ceil;
use function count;
use function max;
use function min;
use function number_format;
use function round;

/**
 * Battle Pass screen: premium row on top, tier track in the middle, free row below (5 tiers per page).
 * Clicking a reward card claims it, "Claim All" claims everything that is ready, "Activate" shows how to get
 * the Golden (premium) pass. Pass $page = 0 to open on the page of the player's current tier.
 */
final class BattlePassMenu{

    private const PREFIX = "§d§l§oBattle Pass §r§7- ";

    public static function openMain(Player $player, BattlePassRegistry $registry, PlayerBattlePassDataManager $data, int $page = 0): void{
        $data->load($player, function(array $row, array $state) use ($player, $registry, $data, $page): void{
            if(!$player->isConnected()){
                return;
            }

            $row = $data->applySeasonResetIfNeeded($row, $registry);
            $row = $data->applyXpDiffIfNeeded($player, $registry, $row);
            $data->save($player, $row, $state);

            $perPage = BattlePassLayout::PER_PAGE;
            $tierCount = $registry->getTierCount();
            $totalPages = max(1, (int) ceil($tierCount / $perPage));
            $currentTier = $data->getTier($row, $registry);

            if($page <= 0){
                $page = (int) ceil(max(1, $currentTier) / $perPage);
            }
            $page = max(1, min($totalPages, $page));
            $first = ($page - 1) * $perPage + 1;

            [$into, $need] = $data->getTierProgress($row, $registry);
            $maxed = $currentTier >= $tierCount;
            $fraction = $maxed ? 0.0 : ($need > 0 ? $into / $need : 0.0);
            $xpPercent = $maxed ? 100 : (int) round($fraction * 100);
            $premium = $data->isPremium($player, $row);
            $ready = $data->countReady($player, $registry, $row);

            // gold line between the tier nodes: node k sits at k/4 of the line
            $position = $currentTier + $fraction - $first;
            $trackPercent = (int) round(max(0.0, min(1.0, $position / ($perPage - 1))) * 100);

            $buttons = BattlePassUi::blankButtons();

            $buttons[BattlePassLayout::TIME] = BattlePassUi::text(BattlePassLayout::TIME, "§l§e" . ($registry->isActive() ? TimeFormatter::season($registry->getEnd()) : "Ended"));
            $buttons[BattlePassLayout::SEASON] = BattlePassUi::text(BattlePassLayout::SEASON, BattlePassUi::seasonName($registry->getSeasonName()));
            $buttons[BattlePassLayout::PAGE] = BattlePassUi::text(BattlePassLayout::PAGE, "§7PAGE §f§l" . $page . "§r§7/§f" . $totalPages);
            $buttons[BattlePassLayout::LVL] = BattlePassUi::text(BattlePassLayout::LVL, "§l§f" . $currentTier);
            $buttons[BattlePassLayout::XPBAR] = BattlePassUi::image(BattlePassLayout::XPBAR, BattlePassUi::xpBar($xpPercent));
            $buttons[BattlePassLayout::XPTEXT] = BattlePassUi::text(BattlePassLayout::XPTEXT, $maxed
                ? "§6§lMAX LEVEL"
                : "§f" . number_format($into) . " §7/ §f" . number_format($need) . " §7XP");
            $buttons[BattlePassLayout::TRACK] = BattlePassUi::image(BattlePassLayout::TRACK, BattlePassUi::track($trackPercent));

            for($k = 0; $k < $perPage; ++$k){
                $tier = $first + $k;
                $def = $registry->getTier($tier);
                if($tier > $tierCount || $def === null){
                    continue;
                }

                $token = $tier === $currentTier ? BattlePassLayout::N_CURRENT : ($tier < $currentTier ? BattlePassLayout::N_REACHED : BattlePassLayout::N_OFF);
                $buttons[BattlePassLayout::NODE + $k] = ["text" => BattlePassLayout::marker(BattlePassLayout::NODE + $k) . $token . "§l§f" . $tier];

                $unlocked = $tier <= $currentTier;

                // free card
                $freeRewards = $def->getFreeRewards();
                if($freeRewards === []){
                    $buttons[BattlePassLayout::FREE + $k] = BattlePassUi::card(BattlePassLayout::FREE + $k, BattlePassLayout::T_EMPTY, "", null);
                }else{
                    $freeToken = $data->isClaimedFree($row, $tier) ? BattlePassLayout::T_DONE : ($unlocked ? BattlePassLayout::T_READY : BattlePassLayout::T_NORMAL);
                    $buttons[BattlePassLayout::FREE + $k] = BattlePassUi::card(BattlePassLayout::FREE + $k, $freeToken, BattlePassUi::rewardLabel($freeRewards), BattlePassUi::rewardIcon($freeRewards));
                }

                // premium card
                if(!$def->hasPremiumReward()){
                    $buttons[BattlePassLayout::PREM + $k] = BattlePassUi::card(BattlePassLayout::PREM + $k, BattlePassLayout::T_EMPTY, "", null);
                }else{
                    $premRewards = $def->getPremiumRewards();
                    $claimed = $data->isClaimedPremium($row, $tier);
                    $premToken = $claimed ? BattlePassLayout::T_DONE : ($unlocked && $premium ? BattlePassLayout::T_READY : BattlePassLayout::T_NORMAL);
                    if(!$premium && !$claimed){
                        $premToken .= BattlePassLayout::T_LOCK;
                    }
                    $buttons[BattlePassLayout::PREM + $k] = BattlePassUi::card(BattlePassLayout::PREM + $k, $premToken, BattlePassUi::rewardLabel($premRewards), BattlePassUi::rewardIcon($premRewards));
                }
            }

            $buttons[BattlePassLayout::CLAIM] = ["text" => BattlePassLayout::marker(BattlePassLayout::CLAIM)
                . ($ready > 0 ? BattlePassLayout::B_ACTIVE : BattlePassLayout::B_OFF)
                . "§l§fClaim All" . ($ready > 0 ? " §e(" . $ready . ")" : "")];
            $buttons[BattlePassLayout::ACTIVATE] = ["text" => BattlePassLayout::marker(BattlePassLayout::ACTIVATE)
                . ($premium ? BattlePassLayout::B_PURPLE . "§l§fActive" : BattlePassLayout::B_ACTIVE . "§l§fActivate")];
            if($page > 1){
                $buttons[BattlePassLayout::PREV] = ["text" => BattlePassLayout::marker(BattlePassLayout::PREV)];
            }
            if($page < $totalPages){
                $buttons[BattlePassLayout::NEXT] = ["text" => BattlePassLayout::marker(BattlePassLayout::NEXT)];
            }
            $buttons[BattlePassLayout::PSTATUS] = BattlePassUi::text(BattlePassLayout::PSTATUS, $premium ? "§a§lACTIVE" : "§c§lLOCKED");

            $player->sendForm(new BattlePassForm($buttons, function(Player $player, int $index) use ($registry, $data, $page, $first, $perPage, $totalPages, $tierCount, $premium): void{
                if(!$player->isConnected()){
                    return;
                }

                if($index >= BattlePassLayout::FREE && $index < BattlePassLayout::FREE + $perPage){
                    self::claimTier($player, $registry, $data, $first + ($index - BattlePassLayout::FREE), false, $page);
                    return;
                }
                if($index >= BattlePassLayout::PREM && $index < BattlePassLayout::PREM + $perPage){
                    self::claimTier($player, $registry, $data, $first + ($index - BattlePassLayout::PREM), true, $page);
                    return;
                }

                switch($index){
                    case BattlePassLayout::CLAIM:
                        self::claimAll($player, $registry, $data, $page);
                        return;
                    case BattlePassLayout::ACTIVATE:
                        if($premium){
                            self::reopen($player, $registry, $data, $page);
                        }else{
                            self::openPremiumInfo($player, $registry, $data, $page);
                        }
                        return;
                    case BattlePassLayout::PREV:
                        self::reopen($player, $registry, $data, max(1, $page - 1));
                        return;
                    case BattlePassLayout::NEXT:
                        self::reopen($player, $registry, $data, min($totalPages, $page + 1));
                        return;
                }
            }));
        });
    }

    /** one tick later so the closing form and the next one never overlap */
    private static function reopen(Player $player, BattlePassRegistry $registry, PlayerBattlePassDataManager $data, int $page): void{
        BedWarsLobby::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player, $registry, $data, $page): void{
            if($player->isConnected()){
                self::openMain($player, $registry, $data, $page);
            }
        }), 2);
    }

    private static function claimTier(Player $player, BattlePassRegistry $registry, PlayerBattlePassDataManager $data, int $tier, bool $premiumTrack, int $page): void{
        $data->load($player, function(array $row, array $state) use ($player, $registry, $data, $tier, $premiumTrack, $page): void{
            if(!$player->isConnected()){
                return;
            }

            $row = $data->applySeasonResetIfNeeded($row, $registry);
            $row = $data->applyXpDiffIfNeeded($player, $registry, $row);

            $def = $registry->getTier($tier);
            if($def === null){
                $data->save($player, $row, $state);
                self::reopen($player, $registry, $data, $page);
                return;
            }

            if($tier > $data->getTier($row, $registry)){
                $player->sendMessage(self::PREFIX . "§cReach §dTier " . $tier . " §cfirst.");
            }elseif(!$premiumTrack){
                $player->sendMessage(self::PREFIX . ($data->claimFree($player, $registry, $row, $tier) ? "§aFree reward claimed!" : "§7Nothing to claim there."));
            }elseif(!$def->hasPremiumReward()){
                $player->sendMessage(self::PREFIX . "§7No premium reward on this tier.");
            }elseif(!$data->isPremium($player, $row)){
                $player->sendMessage(self::PREFIX . "§cRequires the §6Golden Pass§c.");
            }else{
                $player->sendMessage(self::PREFIX . ($data->claimPremium($player, $registry, $row, $tier) ? "§dPremium reward claimed!" : "§7Nothing to claim there."));
            }

            $data->save($player, $row, $state);
            self::reopen($player, $registry, $data, $page);
        });
    }

    private static function claimAll(Player $player, BattlePassRegistry $registry, PlayerBattlePassDataManager $data, int $page): void{
        $data->load($player, function(array $row, array $state) use ($player, $registry, $data, $page): void{
            if(!$player->isConnected()){
                return;
            }

            $row = $data->applySeasonResetIfNeeded($row, $registry);
            $row = $data->applyXpDiffIfNeeded($player, $registry, $row);

            $claimed = $data->claimAllReady($player, $registry, $row);
            $player->sendMessage(self::PREFIX . ($claimed > 0 ? "§aClaimed §f" . $claimed . " §areward(s)!" : "§7Nothing ready to claim right now."));

            $data->save($player, $row, $state);
            self::reopen($player, $registry, $data, $page);
        });
    }

    public static function openPremiumInfo(Player $player, BattlePassRegistry $registry, PlayerBattlePassDataManager $data, int $returnPage = 1): void{
        $content =
            "§l§6Golden Pass\n\n" .
            "§7Unlock the §6Golden Pass §7to claim the premium reward on every tier - exclusive coins, cosmetics and special rewards alongside the free track.\n\n" .
            "§7Ask a server admin or visit the store to unlock §6Golden Pass§7.";

        $form = new SimpleForm("§l§6Golden Pass", $content, function(Player $player, int $index) use ($registry, $data, $returnPage): void{
            if(!$player->isConnected()){
                return;
            }
            self::reopen($player, $registry, $data, $returnPage);
        });

        $form->addButton("§l§7Back");
        $player->sendForm($form);
    }
}
