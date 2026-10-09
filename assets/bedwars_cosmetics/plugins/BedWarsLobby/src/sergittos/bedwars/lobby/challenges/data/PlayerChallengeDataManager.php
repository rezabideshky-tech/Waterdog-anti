<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\challenges\data;

use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\api\CoinsAPI;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\cosmetics\registry\CosmeticsRegistry;
use sergittos\bedwars\lobby\challenges\model\ChallengeDefinition;
use sergittos\bedwars\lobby\challenges\model\RewardType;
use sergittos\bedwars\lobby\challenges\registry\ChallengeRegistry;
use function is_array;
use function max;
use function min;
use function str_replace;
use function time;

final class PlayerChallengeDataManager{

    /**
     * Refreshes progress from the player's session and persists it. Goes
     * through the provider's race-safe mutate path (see
     * AsyncMysqlProvider::mutateChallengesState()) so this can never
     * collide with a battlepass sync (or another challenges sync) firing
     * for the same player around the same time - that collision used to
     * silently revert progress, making challenges look like they "reset"
     * mid-day for no reason.
     */
    public function syncNow(Player $player, ChallengeRegistry $registry): void{
        BedWarsCore::getInstance()->getProvider()->mutateChallengesState($player->getName(), function(array $row) use ($player, $registry): array{
            $row = $this->applyResetIfNeeded($row);
            return $this->applyProgressDiffIfNeeded($player, $registry, $row);
        });
    }

    /**
     * Loads a fresh, synced snapshot for read/display purposes (menus).
     * Internally this still runs through the mutate queue - progress needs
     * to be refreshed and saved before it can be shown - but callers get a
     * plain read-only row back via $cb.
     */
    public function load(Player $player, ChallengeRegistry $registry, \Closure $cb): void{
        BedWarsCore::getInstance()->getProvider()->mutateChallengesState($player->getName(), function(array $row) use ($player, $registry): array{
            $row = $this->applyResetIfNeeded($row);
            return $this->applyProgressDiffIfNeeded($player, $registry, $row);
        }, function(array $row) use ($player, $cb): void{
            if(!$player->isConnected()){
                return;
            }
            $cb($row);
        });
    }

    /**
     * Claims a single challenge's reward. Safe to call concurrently with
     * syncNow()/load() for the same player - it's just another mutation
     * queued on the same per-player queue.
     */
    public function claimOne(Player $player, ChallengeRegistry $registry, string $challengeId, \Closure $cb): void{
        BedWarsCore::getInstance()->getProvider()->mutateChallengesState($player->getName(), function(array $row) use ($player, $registry, $challengeId): array{
            $row = $this->applyResetIfNeeded($row);
            $row = $this->applyProgressDiffIfNeeded($player, $registry, $row);
            $this->claim($player, $registry, $row, $challengeId);
            return $row;
        }, function(array $row) use ($player, $challengeId, $cb): void{
            if(!$player->isConnected()){
                return;
            }
            $cb($row, $this->isClaimed($row, $challengeId));
        });
    }

    public function applyResetIfNeeded(array $row): array{
        $stamp = date("Y-m-d");
        $stored = (string)($row["reset_stamp"] ?? "");
        if($stored === $stamp){
            return $row;
        }

        $row["reset_stamp"] = $stamp;
        $row["progress"] = [];
        $row["claimed"] = [];
        $row["last"] = null;

        return $row;
    }

    public function applyProgressDiffIfNeeded(Player $player, ChallengeRegistry $registry, array $row): array{
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session === null){
            return $row;
        }

        $current = [
            "coins" => $session->getCoins(),
            "kills" => $session->getKills(),
            "wins" => $session->getWins(),
            "final_kills" => $session->getFinalKills(),
            "beds_broken" => $session->getBedsBroken(),
        ];

        $last = $row["last"] ?? null;
        if(!is_array($last)){
            $row["last"] = $current;
            return $row;
        }

        $diff = [
            "coins_earned" => max(0, (int)$current["coins"] - (int)($last["coins"] ?? 0)),
            "kills" => max(0, (int)$current["kills"] - (int)($last["kills"] ?? 0)),
            "wins" => max(0, (int)$current["wins"] - (int)($last["wins"] ?? 0)),
            "final_kills" => max(0, (int)$current["final_kills"] - (int)($last["final_kills"] ?? 0)),
            "beds_broken" => max(0, (int)$current["beds_broken"] - (int)($last["beds_broken"] ?? 0)),
        ];

        $now = time();
        $progress = is_array($row["progress"] ?? null) ? $row["progress"] : [];
        $claimed = is_array($row["claimed"] ?? null) ? $row["claimed"] : [];

        foreach($registry->getVisibleChallenges() as $ch){
            if(!$ch->isRunning($now)){
                continue;
            }

            $id = $ch->getId();
            $inc = $ch->getProgressIncrement($diff);
            if($inc <= 0){
                continue;
            }

            $old = (int)($progress[$id] ?? 0);
            $wasComplete = $old >= $ch->getGoal();
            $new = min($ch->getGoal(), $old + $inc);
            $progress[$id] = $new;

            // Progress is saved here, but the reward is NOT granted
            // automatically anymore - the player has to open the challenge
            // in the Challenges menu and press the "Claim Reward" button
            // (see ChallengesMenu::openChallenge() / claimOne() above).
            if($new >= $ch->getGoal() && !$wasComplete && !(bool)($claimed[$id] ?? false)){
                $player->sendMessage("§e§l\xE2\x9C\x94 Challenge Ready §r§7- " . $ch->getNamePlain() . " §7- open §fChallenges §7to claim your reward");
            }
        }

        $row["progress"] = $progress;
        $row["claimed"] = $claimed;
        $row["last"] = $current;

        return $row;
    }

    public function getProgress(array $row, string $id): int{
        $p = $row["progress"] ?? [];
        return is_array($p) ? (int)($p[$id] ?? 0) : 0;
    }

    public function isClaimed(array $row, string $id): bool{
        $c = $row["claimed"] ?? [];
        return is_array($c) ? (bool)($c[$id] ?? false) : false;
    }

    public function statusLine(array $row, ChallengeDefinition $ch): string{
        $now = time();
        $id = $ch->getId();

        if($ch->isExpired($now)){
            return "§cExpired";
        }
        if($now < $ch->getStart()){
            return "§7Starts in §b" . \sergittos\bedwars\lobby\challenges\util\TimeFormatter::left($ch->getStart());
        }

        $progress = $this->getProgress($row, $id);
        $goal = $ch->getGoal();
        $claimed = $this->isClaimed($row, $id);

        if($claimed){
            return "§aClaimed";
        }
        if($progress >= $goal){
            return "§eCompleted §8| §aClaim Ready";
        }
        if($ch->isRunning($now)){
            return "§7In Progress §8| §b" . \sergittos\bedwars\lobby\challenges\util\TimeFormatter::left($ch->getEnd());
        }

        return "§cEnded §8| §7Claim Window";
    }

    public function readyCoins(ChallengeRegistry $registry, array $row, ?string $onlyId): int{
        $sum = 0;
        $now = time();

        foreach($registry->getVisibleChallenges() as $ch){
            if($onlyId !== null && $ch->getId() !== $onlyId){
                continue;
            }
            if($ch->isExpired($now)){
                continue;
            }

            $id = $ch->getId();
            $progress = $this->getProgress($row, $id);
            $claimed = $this->isClaimed($row, $id);

            if($claimed || $progress < $ch->getGoal()){
                continue;
            }

            foreach($ch->getRewards() as $r){
                if(is_array($r) && ($r["type"] ?? "") === RewardType::COINS){
                    $sum += (int)($r["amount"] ?? 0);
                }
            }
        }

        return $sum;
    }

    public function claim(Player $player, ChallengeRegistry $registry, array &$row, string $challengeId): bool{
        $ch = $registry->get($challengeId);
        if($ch === null){
            return false;
        }

        $now = time();
        if($ch->isExpired($now)){
            return false;
        }

        $progress = $this->getProgress($row, $challengeId);
        if($progress < $ch->getGoal()){
            return false;
        }

        if($this->isClaimed($row, $challengeId)){
            return false;
        }

        $this->applyRewards($player, $ch->getRewards());

        $claimed = is_array($row["claimed"] ?? null) ? $row["claimed"] : [];
        $claimed[$challengeId] = true;
        $row["claimed"] = $claimed;

        return true;
    }

    public function claimAll(Player $player, ChallengeRegistry $registry, array &$row): int{
        $now = time();
        $totalCoins = 0;

        foreach($registry->getVisibleChallenges() as $ch){
            if($ch->isExpired($now)){
                continue;
            }

            $id = $ch->getId();
            if($this->isClaimed($row, $id)){
                continue;
            }

            $progress = $this->getProgress($row, $id);
            if($progress < $ch->getGoal()){
                continue;
            }

            foreach($ch->getRewards() as $r){
                if(is_array($r) && ($r["type"] ?? "") === RewardType::COINS){
                    $totalCoins += (int)($r["amount"] ?? 0);
                }
            }

            $this->applyRewards($player, $ch->getRewards());

            $claimed = is_array($row["claimed"] ?? null) ? $row["claimed"] : [];
            $claimed[$id] = true;
            $row["claimed"] = $claimed;
        }

        return $totalCoins;
    }

    private function applyRewards(Player $player, array $rewards): void{
        foreach($rewards as $r){
            if(!is_array($r)){
                continue;
            }

            $type = (string)($r["type"] ?? "");
            if($type === RewardType::COINS){
                $amount = (float)($r["amount"] ?? 0);
                if($amount > 0){
                    CoinsAPI::addCoins($player, $amount);
                    $player->sendMessage("§a§lCoins Added§r §7- §e+" . (int)$amount . " §6coins");
                }
                continue;
            }

            if($type === RewardType::COMMAND){
                $cmd = (string)($r["command"] ?? "");
                if($cmd !== ""){
                    $cmd = str_replace("{player}", $player->getName(), $cmd);
                    Server::getInstance()->dispatchCommand(Server::getInstance()->getConsoleSender(), $cmd);
                    $player->sendMessage("§d§lReward Granted§r §7- §fSpecial reward delivered");
                }
                continue;
            }

            if($type === RewardType::COSMETIC){
                $categoryRaw = (string)($r["category"] ?? "");
                $key = (string)($r["key"] ?? "");
                $equip = (bool)($r["equip"] ?? true);

                if($categoryRaw === "" || $key === ""){
                    continue;
                }

                $cat = CosmeticCategory::tryFrom($categoryRaw);
                if($cat === null){
                    continue;
                }

                $def = \sergittos\bedwars\cosmetics\registry\CosmeticsRegistry::getInstance()->get($cat, $key);
                if($def === null){
                    continue;
                }

                $mgr = PlayerCosmeticsManager::getInstance();
                $mgr->purchase($player, $cat, $key);
                if($equip){
                    $mgr->equip($player, $cat, $key);
                }

                $player->sendMessage("§b§lCosmetic Unlocked§r §7- " . $def->getRarity()->color() . $def->getDisplayName());
            }
        }
    }
}