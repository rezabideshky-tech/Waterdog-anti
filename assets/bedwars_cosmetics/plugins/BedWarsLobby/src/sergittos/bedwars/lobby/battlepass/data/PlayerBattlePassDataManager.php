<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\data;

use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\api\CoinsAPI;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\cosmetics\registry\CosmeticsRegistry;
use sergittos\bedwars\lobby\battlepass\model\BattlePassTier;
use sergittos\bedwars\lobby\battlepass\model\RewardType;
use sergittos\bedwars\lobby\battlepass\registry\BattlePassRegistry;
use function is_array;
use function max;
use function min;
use function str_replace;
use function time;

final class PlayerBattlePassDataManager{

    public const PREMIUM_PERMISSION = "bedwars.battlepass.premium";

    private const XP_PER_KILL = 10;
    private const XP_PER_FINAL_KILL = 25;
    private const XP_PER_BED_BROKEN = 40;
    private const XP_PER_WIN = 100;

    public function load(Player $player, \Closure $cb): void{
        BedWarsCore::getInstance()->getProvider()->getBattlePassState($player->getName(), function(array $row, array $state) use ($player, $cb): void{
            if(!$player->isConnected()){
                return;
            }
            $cb($row, $state);
        });
    }

    public function save(Player $player, array $row, array $state): void{
        BedWarsCore::getInstance()->getProvider()->setBattlePassState($player->getName(), $row, $state);
    }

    /**
     * Goes through the provider's race-safe mutate path instead of the
     * plain load()/save() pair above. This is the auto-fire sync that runs
     * on every player join right alongside PlayerChallengeDataManager's own
     * syncNow() (see LobbyListener::onJoin()) - both used to read/write the
     * same shared quest_v2 JSON blob independently, so whichever one's
     * write landed second would silently overwrite the other's progress
     * with its own stale copy of the blob. Routing this one through the
     * same per-player queue as challenges closes that race for good.
     */
    public function syncNow(Player $player, BattlePassRegistry $registry): void{
        BedWarsCore::getInstance()->getProvider()->mutateBattlePassState($player->getName(), function(array $row) use ($player, $registry): array{
            $row = $this->applySeasonResetIfNeeded($row, $registry);
            return $this->applyXpDiffIfNeeded($player, $registry, $row);
        });
    }

    public function applySeasonResetIfNeeded(array $row, BattlePassRegistry $registry): array{
        $current = (string) ($row["season_id"] ?? "");
        if($current === $registry->getSeasonId()){
            return $row;
        }

        $row["season_id"] = $registry->getSeasonId();
        $row["xp"] = 0;
        $row["claimed_free"] = [];
        $row["claimed_premium"] = [];
        $row["last"] = null;
        // premium_until intentionally kept - premium status carries across seasons

        return $row;
    }

    public function applyXpDiffIfNeeded(Player $player, BattlePassRegistry $registry, array $row): array{
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session === null){
            return $row;
        }

        $current = [
            "kills" => $session->getKills(),
            "final_kills" => $session->getFinalKills(),
            "beds_broken" => $session->getBedsBroken(),
            "wins" => $session->getWins(),
        ];

        $last = $row["last"] ?? null;
        if(!is_array($last)){
            $row["last"] = $current;
            return $row;
        }

        if(!$registry->isActive()){
            $row["last"] = $current;
            return $row;
        }

        $diffKills = max(0, (int) $current["kills"] - (int) ($last["kills"] ?? 0));
        $diffFinalKills = max(0, (int) $current["final_kills"] - (int) ($last["final_kills"] ?? 0));
        $diffBeds = max(0, (int) $current["beds_broken"] - (int) ($last["beds_broken"] ?? 0));
        $diffWins = max(0, (int) $current["wins"] - (int) ($last["wins"] ?? 0));

        $gainedXp = ($diffKills * self::XP_PER_KILL)
            + ($diffFinalKills * self::XP_PER_FINAL_KILL)
            + ($diffBeds * self::XP_PER_BED_BROKEN)
            + ($diffWins * self::XP_PER_WIN);

        if($gainedXp > 0){
            $oldXp = (int) ($row["xp"] ?? 0);
            $newXp = min($registry->getMaxXp(), $oldXp + $gainedXp);

            $oldTier = $registry->tierForXp($oldXp);
            $newTier = $registry->tierForXp($newXp);

            $row["xp"] = $newXp;

            if($newTier > $oldTier && $player->isConnected()){
                $player->sendMessage("§d§l§oBattle Pass §r§7- §a+" . $gainedXp . " XP §7| Advanced to §d§lTier " . $newTier . "§r§7!");
                $player->sendTitle("§d§lTIER UP", "§fYou reached §d§lTier " . $newTier, 5, 40, 10);
            }
        }

        $row["last"] = $current;

        return $row;
    }

    public function getXp(array $row): int{ return (int) ($row["xp"] ?? 0); }

    public function getTier(array $row, BattlePassRegistry $registry): int{
        return $registry->tierForXp($this->getXp($row));
    }

    public function getTierProgress(array $row, BattlePassRegistry $registry): array{
        $xp = $this->getXp($row);
        $tier = $registry->tierForXp($xp);
        $into = $xp - ($tier * $registry->getXpPerTier());
        return [$into, $registry->getXpPerTier()];
    }

    public function isPremium(Player $player, array $row): bool{
        if($player->hasPermission(self::PREMIUM_PERMISSION)){
            return true;
        }
        $until = $row["premium_until"] ?? null;
        if($until === null){
            return false;
        }
        return (int) $until === -1 || (int) $until > time();
    }

    public function getPremiumStatusLine(Player $player, array $row): string{
        if($player->hasPermission(self::PREMIUM_PERMISSION)){
            return "§d§lPremium §r§7(§bRank Perk§7)";
        }
        $until = $row["premium_until"] ?? null;
        if($until === null){
            return "§7Free Pass";
        }
        if((int) $until === -1){
            return "§d§lPremium §r§7(§6Lifetime§7)";
        }
        if((int) $until > time()){
            return "§d§lPremium §r§7(" . \sergittos\bedwars\lobby\battlepass\util\TimeFormatter::left((int) $until) . " left)";
        }
        return "§7Free Pass §8(§cPremium Expired§8)";
    }

    public function isClaimedFree(array $row, int $tier): bool{
        $c = $row["claimed_free"] ?? [];
        return is_array($c) ? (bool) ($c[$tier] ?? false) : false;
    }

    public function isClaimedPremium(array $row, int $tier): bool{
        $c = $row["claimed_premium"] ?? [];
        return is_array($c) ? (bool) ($c[$tier] ?? false) : false;
    }

    public function claimFree(Player $player, BattlePassRegistry $registry, array &$row, int $tier): bool{
        $def = $registry->getTier($tier);
        if($def === null || $tier > $this->getTier($row, $registry) || $this->isClaimedFree($row, $tier)){
            return false;
        }

        $this->applyRewards($player, $def->getFreeRewards());

        $claimed = is_array($row["claimed_free"] ?? null) ? $row["claimed_free"] : [];
        $claimed[$tier] = true;
        $row["claimed_free"] = $claimed;

        return true;
    }

    public function claimPremium(Player $player, BattlePassRegistry $registry, array &$row, int $tier): bool{
        $def = $registry->getTier($tier);
        if($def === null || !$def->hasPremiumReward()){
            return false;
        }
        if($tier > $this->getTier($row, $registry) || $this->isClaimedPremium($row, $tier)){
            return false;
        }
        if(!$this->isPremium($player, $row)){
            return false;
        }

        $this->applyRewards($player, $def->getPremiumRewards());

        $claimed = is_array($row["claimed_premium"] ?? null) ? $row["claimed_premium"] : [];
        $claimed[$tier] = true;
        $row["claimed_premium"] = $claimed;

        return true;
    }

    public function claimAllReady(Player $player, BattlePassRegistry $registry, array &$row): int{
        $unlockedTier = $this->getTier($row, $registry);
        $premium = $this->isPremium($player, $row);
        $count = 0;

        for($t = 1; $t <= $unlockedTier; $t++){
            if($this->claimFree($player, $registry, $row, $t)){
                $count++;
            }
            if($premium && $this->claimPremium($player, $registry, $row, $t)){
                $count++;
            }
        }

        return $count;
    }

    public function countReady(Player $player, BattlePassRegistry $registry, array $row): int{
        $unlockedTier = $this->getTier($row, $registry);
        $premium = $this->isPremium($player, $row);
        $count = 0;

        for($t = 1; $t <= $unlockedTier; $t++){
            $def = $registry->getTier($t);
            if($def === null){
                continue;
            }
            if(!$this->isClaimedFree($row, $t)){
                $count++;
            }
            if($premium && $def->hasPremiumReward() && !$this->isClaimedPremium($row, $t)){
                $count++;
            }
        }

        return $count;
    }

    public function grantPremium(array $row, ?int $days): array{
        $row["premium_until"] = $days === null ? -1 : (time() + ($days * 86400));
        return $row;
    }

    public function addTestXp(BattlePassRegistry $registry, array $row, int $amount): array{
        $row["xp"] = min($registry->getMaxXp(), max(0, $this->getXp($row) + $amount));
        return $row;
    }

    private function applyRewards(Player $player, array $rewards): void{
        foreach($rewards as $r){
            if(!is_array($r)){
                continue;
            }

            $type = (string) ($r["type"] ?? "");

            if($type === RewardType::COINS){
                $amount = (float) ($r["amount"] ?? 0);
                if($amount > 0){
                    CoinsAPI::addCoins($player, $amount);
                    $player->sendMessage("§d§l§oBattle Pass §r§7- §e+" . (int) $amount . " §6coins");
                }
                continue;
            }

            if($type === RewardType::COMMAND){
                $cmd = (string) ($r["command"] ?? "");
                if($cmd !== ""){
                    $cmd = str_replace("{player}", $player->getName(), $cmd);
                    Server::getInstance()->dispatchCommand(Server::getInstance()->getConsoleSender(), $cmd);
                    $player->sendMessage("§d§l§oBattle Pass §r§7- §fSpecial reward delivered");
                }
                continue;
            }

            if($type === RewardType::COSMETIC){
                $categoryRaw = (string) ($r["category"] ?? "");
                $key = (string) ($r["key"] ?? "");
                $equip = (bool) ($r["equip"] ?? false);

                if($categoryRaw === "" || $key === ""){
                    continue;
                }

                $cat = CosmeticCategory::tryFrom($categoryRaw);
                if($cat === null){
                    continue;
                }

                $def = CosmeticsRegistry::getInstance()->get($cat, $key);
                if($def === null){
                    continue;
                }

                $mgr = PlayerCosmeticsManager::getInstance();
                $mgr->purchase($player, $cat, $key);
                if($equip){
                    $mgr->equip($player, $cat, $key);
                }

                $player->sendMessage("§d§l§oBattle Pass §r§7- Unlocked " . $def->getRarity()->color() . $def->getDisplayName());
            }
        }
    }
}
