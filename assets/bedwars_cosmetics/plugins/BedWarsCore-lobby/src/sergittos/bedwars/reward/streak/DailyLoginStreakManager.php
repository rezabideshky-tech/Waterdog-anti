<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward\streak;

use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\api\CoinsAPI;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\reward\NotificationService;
use sergittos\bedwars\reward\RewardXp;
use function date;
use function is_array;
use function ksort;
use function max;
use function min;
use function strtolower;
use function strtotime;

/**
 * Daily login streak with a one-day "freeze" safety net and a
 * prestige cycle, closely modeled on the reference screenshot
 * ("Daily Login Streak (N Days)" / "Welcome back! You are on a N-day
 * streak!" / "<Perk> unlocks at day X (Y more day(s))").
 *
 * Persistence is a single small JSON row per player (see
 * AsyncMysqlProvider::getRewardState/mergeRewardState) - there is no
 * per-tick work at all for this feature, it only runs once, on join.
 *
 * Concurrency: the async DB read/write for a given player is guarded by
 * $pending so a very fast double-join (e.g. a proxy hiccup) can never
 * process the same login twice and double-grant a milestone.
 */
final class DailyLoginStreakManager{

    /** @var array<int, StreakMilestone> keyed by streak day */
    private array $milestones;

    private int $cycleLength;
    private int $maxFreezes;

    /** @var array<string, bool> lowercase username => an onJoin() lookup is in flight */
    private array $pending = [];

    /** @var array<string, array<string, bool>> lowercase username => unlocked perk flags, cached from the last processed login */
    private array $perkCache = [];

    public function __construct(private BedWarsCore $plugin, private NotificationService $notifications){
        $cfg = $plugin->getConfig()->get("daily-streak", []);
        if(!is_array($cfg)){
            $cfg = [];
        }

        $milestones = [];
        foreach((is_array($cfg["milestones"] ?? null) ? $cfg["milestones"] : []) as $row){
            if(!is_array($row)){
                continue;
            }
            $day = (int) ($row["day"] ?? 0);
            if($day <= 0){
                continue;
            }
            $milestones[$day] = new StreakMilestone(
                $day,
                (string) ($row["label"] ?? ("Day " . $day . " Bonus")),
                max(0, (int) ($row["coins"] ?? 0)),
                max(0, (int) ($row["xp"] ?? 0)),
                (bool) ($row["grants-freeze"] ?? false),
                isset($row["perk"]) ? (string) $row["perk"] : null
            );
        }

        if($milestones === []){
            $milestones = [
                7 => new StreakMilestone(7, "Weekly Bonus", 150, 500, true),
                11 => new StreakMilestone(11, "XP Boost", 50, 250, false, "xp_boost"),
                14 => new StreakMilestone(14, "Weekly Bonus", 250, 800, true),
                21 => new StreakMilestone(21, "Weekly Bonus", 350, 1100, true),
                30 => new StreakMilestone(30, "Monthly Bonus", 1000, 3000, true),
            ];
        }
        ksort($milestones);
        $this->milestones = $milestones;

        $this->cycleLength = max(1, (int) ($cfg["cycle-length-days"] ?? 30));
        $this->maxFreezes = max(0, (int) ($cfg["max-streak-freezes"] ?? 2));
    }

    /** Cheap in-memory lookup - safe to call every heartbeat tick from other reward features. */
    public function hasPerk(Player $player, string $perk): bool{
        return (bool) ($this->perkCache[strtolower($player->getName())][$perk] ?? false);
    }

    public function onJoin(Player $player): void{
        $username = $player->getName();
        $lower = strtolower($username);

        if(isset($this->pending[$lower])){
            return;
        }
        $this->pending[$lower] = true;

        $this->plugin->getProvider()->getRewardState($username, function(array $saved) use ($player, $username, $lower): void{
            unset($this->pending[$lower]);

            if(!$player->isConnected()){
                return;
            }

            $streak = is_array($saved["streak"] ?? null) ? $saved["streak"] : [];

            $perks = is_array($streak["perks"] ?? null) ? $streak["perks"] : [];
            $this->perkCache[$lower] = $perks;

            $this->process($player, $username, $streak);
        });
    }

    private function process(Player $player, string $username, array $streak): void{
        $today = date("Y-m-d");
        $lastDate = (string) ($streak["last-date"] ?? "");

        if($lastDate === $today){
            // already processed today (e.g. relog) - do not re-grant, do
            // not spam a box, but do let the player know their status if
            // they explicitly reconnect a while after their last join.
            return;
        }

        $count = max(0, (int) ($streak["count"] ?? 0));
        $best = max(0, (int) ($streak["best"] ?? 0));
        $freezes = max(0, (int) ($streak["freezes"] ?? 0));
        $prestige = max(0, (int) ($streak["prestige"] ?? 0));
        $perks = is_array($streak["perks"] ?? null) ? $streak["perks"] : [];

        $yesterday = date("Y-m-d", strtotime("-1 day"));
        $usedFreeze = false;

        if($lastDate === ""){
            $count = 1;
        }elseif($lastDate === $yesterday){
            $count++;
        }elseif($freezes > 0){
            $freezes--;
            $usedFreeze = true;
            $count++;
        }else{
            $count = 1;
        }

        $best = max($best, $count);

        $prestiged = false;
        if($count > $this->cycleLength){
            $prestige++;
            $count = 1;
            $prestiged = true;
        }

        $prestigeMultiplier = 1.0 + (0.1 * $prestige);

        $milestone = $this->milestones[$count] ?? null;
        $coinsGranted = 0;
        $xpGranted = 0;

        if($milestone !== null){
            $coinsGranted = (int) round($milestone->coins * $prestigeMultiplier);
            $xpGranted = (int) round($milestone->xp * $prestigeMultiplier);

            if($coinsGranted > 0){
                CoinsAPI::addCoins($player, (float) $coinsGranted);
            }
            if($xpGranted > 0){
                $this->grantXp($player, $xpGranted);
            }
            if($milestone->grantsFreeze){
                $freezes = min($this->maxFreezes, $freezes + 1);
            }
            if($milestone->perk !== null){
                $perks[$milestone->perk] = true;
            }
        }

        $newState = [
            "count" => $count,
            "best" => $best,
            "last-date" => $today,
            "freezes" => $freezes,
            "prestige" => $prestige,
            "perks" => $perks,
        ];

        $this->perkCache[strtolower($username)] = $perks;

        $this->plugin->getProvider()->mergeRewardState($username, ["streak" => $newState]);

        $this->notify($player, $count, $best, $usedFreeze, $prestiged, $milestone, $coinsGranted, $xpGranted, $prestige);
    }

    public function onQuit(Player $player): void{
        unset($this->perkCache[strtolower($player->getName())], $this->pending[strtolower($player->getName())]);
    }

    private function nextMilestone(int $count): ?StreakMilestone{
        foreach($this->milestones as $day => $milestone){
            if($day > $count){
                return $milestone;
            }
        }
        return null;
    }

    private function notify(Player $player, int $count, int $best, bool $usedFreeze, bool $prestiged, ?StreakMilestone $milestone, int $coinsGranted, int $xpGranted, int $prestige): void{
        $lines = [];

        $lines[] = TF::GRAY . "Player " . TF::WHITE . "» " . TF::LIGHT_PURPLE . $player->getName();
        $lines[] = TF::GRAY . "Current Streak " . TF::WHITE . "» " . TF::YELLOW . TF::BOLD . $count . TF::RESET . TF::WHITE . TF::BOLD . " Days" . TF::RESET . TF::GRAY . " (Best: " . TF::AQUA . $best . TF::GRAY . ")";

        if($prestiged){
            $lines[] = "";
            $lines[] = TF::LIGHT_PURPLE . "Streak Prestige! " . TF::WHITE . "You reached Prestige " . TF::GOLD . $prestige . TF::WHITE . " - rewards now scale up.";
        }

        if($usedFreeze){
            $lines[] = "";
            $lines[] = TF::AQUA . "A Streak Freeze protected your progress after a missed day.";
        }

        if($milestone !== null && ($coinsGranted > 0 || $xpGranted > 0)){
            $reward = "";
            if($coinsGranted > 0){
                $reward .= TF::GOLD . "+" . $coinsGranted . " Coins";
            }
            if($xpGranted > 0){
                $reward .= ($reward !== "" ? TF::WHITE . "  " : "") . TF::GREEN . "+" . $xpGranted . " XP";
            }
            $lines[] = "";
            $lines[] = $reward;
        }

        $next = $this->nextMilestone($count);
        $lines[] = "";
        if($next !== null){
            $away = $next->day - $count;
            $lines[] = TF::GRAY . "Next Reward " . TF::WHITE . "» " . TF::GOLD . "Day " . $next->day
                . TF::GRAY . " in " . TF::YELLOW . $away . " day" . ($away === 1 ? "" : "s");
        }else{
            $lines[] = TF::GRAY . "Next Reward " . TF::WHITE . "» " . TF::GOLD . "All milestones reached this cycle";
        }

        $this->notifications->queue(
            $player,
            TF::GOLD,
            "DAILY LOGIN STREAK",
            $lines
        );
    }

    /** Retries shortly after join if the session hasn't finished loading yet, rather than dropping the reward. */
    private function grantXp(Player $player, int $amount): void{
        if($amount <= 0){
            return;
        }

        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session === null){
            return;
        }

        if(!$session->isLoaded()){
            BedWarsCore::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player, $amount): void{
                    $this->grantXp($player, $amount);
                }),
                20
            );
            return;
        }

        RewardXp::grantSilently($session, $amount);
    }
}
