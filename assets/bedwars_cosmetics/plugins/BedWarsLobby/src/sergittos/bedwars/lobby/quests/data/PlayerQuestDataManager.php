<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\quests\data;

use DateTimeImmutable;
use DateTimeZone;
use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\api\CoinsAPI;
use sergittos\bedwars\lobby\quests\quest\QuestDefinition;
use sergittos\bedwars\lobby\quests\quest\QuestRegistry;
use sergittos\bedwars\lobby\quests\quest\QuestStat;
use sergittos\bedwars\lobby\quests\quest\QuestType;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function strtolower;
use function time;

final class PlayerQuestDataManager{

    private string $folder;

    /** @var array<string, array> */
    private array $cache = [];

    /** @var array<string, int> */
    private array $dirtySince = [];

    /** @var array<string, int> */
    private array $lastSave = [];

    public function __construct(string $dataFolder){
        $this->folder = rtrim($dataFolder, "/") . "/quests/players/";
        if(!is_dir($this->folder)){
            mkdir($this->folder, 0777, true);
        }
    }

    public function tick(): void{
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            $this->syncPlayer($player);
            $this->flushIfNeeded($player);
        }
    }

    public function syncPlayer(Player $player): void{
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session === null){
            return;
        }

        $data = $this->getData($player);
        $data = $this->applyResets($player, $data);

        $current = [
            QuestStat::KILLS->value => $session->getKills(),
            QuestStat::FINAL_KILLS->value => $session->getFinalKills(),
            QuestStat::BEDS_BROKEN->value => $session->getBedsBroken(),
            QuestStat::WINS->value => $session->getWins(),
        ];

        $last = $data["last_stats"] ?? null;
        if(!is_array($last)){
            $data["last_stats"] = $current;
            $this->setData($player, $data, true);
            return;
        }

        $diff = [];
        foreach($current as $k => $v){
            $prev = (int) ($last[$k] ?? $v);
            $now = (int) $v;
            $diff[$k] = $now > $prev ? ($now - $prev) : 0;
        }

        if(($diff[QuestStat::KILLS->value] ?? 0) === 0 &&
            ($diff[QuestStat::FINAL_KILLS->value] ?? 0) === 0 &&
            ($diff[QuestStat::BEDS_BROKEN->value] ?? 0) === 0 &&
            ($diff[QuestStat::WINS->value] ?? 0) === 0){
            $data["last_stats"] = $current;
            $this->setData($player, $data, false);
            return;
        }

        $changed = false;

        foreach(QuestRegistry::getInstance()->allByType(QuestType::DAILY) as $quest){
            $changed = $this->applyQuestProgress($player, $data, $quest, $diff) || $changed;
        }
        foreach(QuestRegistry::getInstance()->allByType(QuestType::WEEKLY) as $quest){
            $changed = $this->applyQuestProgress($player, $data, $quest, $diff) || $changed;
        }

        $data["last_stats"] = $current;
        if($changed){
            $this->setData($player, $data, true);
            $this->saveNow($player);
        }else{
            $this->setData($player, $data, false);
        }
    }

    /**
     * Updates a quest's progress and, the moment it hits its goal, grants
     * the reward immediately and marks it claimed - the player never has to
     * open the Quests menu and press a claim button.
     */
    private function applyQuestProgress(Player $player, array &$data, QuestDefinition $quest, array $diff): bool{
        $statKey = $quest->getStat()->value;
        $add = (int) ($diff[$statKey] ?? 0);
        if($add <= 0){
            return false;
        }

        $id = $quest->getId();
        $progress = (int) ($data["progress"][$id] ?? 0);
        $goal = $quest->getGoal();

        if($progress >= $goal){
            return false;
        }

        $new = $progress + $add;
        if($new > $goal){
            $new = $goal;
        }

        $data["progress"][$id] = $new;

        if($new >= $goal && !(bool) ($data["claimed"][$id] ?? false)){
            $this->autoClaim($player, $data, $quest);
        }

        return true;
    }

    /**
     * Grants a quest's reward (coins + XP) and marks it claimed. Called the
     * instant a quest's progress reaches its goal - see applyQuestProgress().
     */
    private function autoClaim(Player $player, array &$data, QuestDefinition $quest): void{
        $id = $quest->getId();

        CoinsAPI::addCoins($player, (float) $quest->getRewardCoins());

        if($quest->getRewardExp() > 0){
            $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
            $session?->addXp($quest->getRewardExp(), "Quest: " . $quest->getName());
        }

        $data["claimed"][$id] = true;

        $player->sendMessage(
            "§a§l\xE2\x9C\x94 Quest Completed §r§7- §f" . $quest->getName() . "\n" .
            "§7Rewards: §6+" . $quest->getRewardCoins() . " Coins §7| §b+" . $quest->getRewardExp() . " EXP"
        );
    }

    public function claimQuest(Player $player, string $questId): bool{
        $quest = QuestRegistry::getInstance()->get($questId);
        if($quest === null){
            return false;
        }

        $data = $this->getData($player);
        $data = $this->applyResets($player, $data);

        $goal = $quest->getGoal();
        $progress = (int) ($data["progress"][$questId] ?? 0);
        $claimed = (bool) ($data["claimed"][$questId] ?? false);

        if($claimed || $progress < $goal){
            return false;
        }

        CoinsAPI::addCoins($player, (float) $quest->getRewardCoins());

        $data["claimed"][$questId] = true;
        $this->setData($player, $data, true);
        $this->saveNow($player);

        return true;
    }

    public function claimAll(Player $player): int{
        $data = $this->getData($player);
        $data = $this->applyResets($player, $data);

        $ready = $this->getReadyCoins($player, null);
        if($ready <= 0){
            return 0;
        }

        foreach(QuestRegistry::getInstance()->allByType(QuestType::DAILY) as $quest){
            $this->claimIfReadySilent($data, $quest);
        }
        foreach(QuestRegistry::getInstance()->allByType(QuestType::WEEKLY) as $quest){
            $this->claimIfReadySilent($data, $quest);
        }

        CoinsAPI::addCoins($player, (float) $ready);

        $this->setData($player, $data, true);
        $this->saveNow($player);

        return $ready;
    }

    private function claimIfReadySilent(array &$data, QuestDefinition $quest): void{
        $id = $quest->getId();
        $goal = $quest->getGoal();
        $progress = (int) ($data["progress"][$id] ?? 0);
        $claimed = (bool) ($data["claimed"][$id] ?? false);

        if($claimed || $progress < $goal){
            return;
        }

        $data["claimed"][$id] = true;
    }

    public function getReadyCoins(Player $player, ?QuestType $type): int{
        $data = $this->getData($player);
        $data = $this->applyResets($player, $data);

        $sum = 0;

        $lists = [];
        if($type === null){
            $lists[] = QuestRegistry::getInstance()->allByType(QuestType::DAILY);
            $lists[] = QuestRegistry::getInstance()->allByType(QuestType::WEEKLY);
        }else{
            $lists[] = QuestRegistry::getInstance()->allByType($type);
        }

        foreach($lists as $quests){
            foreach($quests as $quest){
                $id = $quest->getId();
                $goal = $quest->getGoal();
                $progress = (int) ($data["progress"][$id] ?? 0);
                $claimed = (bool) ($data["claimed"][$id] ?? false);

                if(!$claimed && $progress >= $goal){
                    $sum += $quest->getRewardCoins();
                }
            }
        }

        return $sum;
    }

    public function getCompletedCount(Player $player, QuestType $type): int{
        $data = $this->getData($player);
        $data = $this->applyResets($player, $data);

        $count = 0;
        foreach(QuestRegistry::getInstance()->allByType($type) as $quest){
            $id = $quest->getId();
            $goal = $quest->getGoal();
            $progress = (int) ($data["progress"][$id] ?? 0);
            if($progress >= $goal){
                $count++;
            }
        }
        return $count;
    }

    public function getProgress(Player $player, string $questId): int{
        $data = $this->getData($player);
        $data = $this->applyResets($player, $data);
        return (int) ($data["progress"][$questId] ?? 0);
    }

    public function isClaimed(Player $player, string $questId): bool{
        $data = $this->getData($player);
        $data = $this->applyResets($player, $data);
        return (bool) ($data["claimed"][$questId] ?? false);
    }

    private function applyResets(Player $player, array $data): array{
        $tz = new DateTimeZone("Asia/Tehran");
        $now = new DateTimeImmutable("now", $tz);

        $dailyStamp = $now->format("Y-m-d");
        $weeklyStamp = $now->format("o-\\WW");

        $storedDaily = (string) ($data["daily_stamp"] ?? "");
        $storedWeekly = (string) ($data["weekly_stamp"] ?? "");

        $resetDaily = $storedDaily !== $dailyStamp;
        $resetWeekly = $storedWeekly !== $weeklyStamp;

        if(!$resetDaily && !$resetWeekly){
            return $data;
        }

        if($resetDaily){
            $data["daily_stamp"] = $dailyStamp;
            foreach(QuestRegistry::getInstance()->allByType(QuestType::DAILY) as $q){
                unset($data["progress"][$q->getId()], $data["claimed"][$q->getId()]);
            }
        }

        if($resetWeekly){
            $data["weekly_stamp"] = $weeklyStamp;
            foreach(QuestRegistry::getInstance()->allByType(QuestType::WEEKLY) as $q){
                unset($data["progress"][$q->getId()], $data["claimed"][$q->getId()]);
            }
        }

        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session !== null){
            $data["last_stats"] = [
                QuestStat::KILLS->value => $session->getKills(),
                QuestStat::FINAL_KILLS->value => $session->getFinalKills(),
                QuestStat::BEDS_BROKEN->value => $session->getBedsBroken(),
                QuestStat::WINS->value => $session->getWins(),
            ];
        }

        $this->setData($player, $data, true);
        $this->saveNow($player);

        return $data;
    }

    private function id(Player $player): string{
        $xuid = $player->getXuid();
        return $xuid !== "" ? $xuid : strtolower($player->getName());
    }

    private function path(Player $player): string{
        return $this->folder . $this->id($player) . ".json";
    }

    public function getData(Player $player): array{
        $id = $this->id($player);
        if(isset($this->cache[$id])){
            return $this->cache[$id];
        }

        $data = [
            "daily_stamp" => "",
            "weekly_stamp" => "",
            "progress" => [],
            "claimed" => [],
            "last_stats" => []
        ];

        $file = $this->path($player);
        if(is_file($file)){
            $decoded = json_decode((string) file_get_contents($file), true);
            if(is_array($decoded)){
                $data["daily_stamp"] = (string) ($decoded["daily_stamp"] ?? "");
                $data["weekly_stamp"] = (string) ($decoded["weekly_stamp"] ?? "");
                $data["progress"] = is_array($decoded["progress"] ?? null) ? $decoded["progress"] : [];
                $data["claimed"] = is_array($decoded["claimed"] ?? null) ? $decoded["claimed"] : [];
                $data["last_stats"] = is_array($decoded["last_stats"] ?? null) ? $decoded["last_stats"] : [];
            }
        }

        $this->cache[$id] = $data;
        $this->dirtySince[$id] = 0;
        $this->lastSave[$id] = time();

        return $data;
    }

    private function setData(Player $player, array $data, bool $dirty): void{
        $id = $this->id($player);
        $this->cache[$id] = $data;

        if($dirty){
            if(($this->dirtySince[$id] ?? 0) === 0){
                $this->dirtySince[$id] = time();
            }
        }
    }

    private function flushIfNeeded(Player $player): void{
        $id = $this->id($player);
        $dirtySince = (int) ($this->dirtySince[$id] ?? 0);
        if($dirtySince === 0){
            return;
        }

        $now = time();
        $lastSave = (int) ($this->lastSave[$id] ?? 0);

        if($now - $lastSave < 20){
            return;
        }

        $this->saveNow($player);
    }

    public function saveNow(Player $player): void{
        $id = $this->id($player);
        $data = $this->cache[$id] ?? null;
        if(!is_array($data)){
            return;
        }

        file_put_contents($this->path($player), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->dirtySince[$id] = 0;
        $this->lastSave[$id] = time();
    }
}