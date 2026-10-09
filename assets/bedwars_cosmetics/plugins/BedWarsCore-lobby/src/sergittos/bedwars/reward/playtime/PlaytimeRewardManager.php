<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward\playtime;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\api\CoinsAPI;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\reward\NotificationService;
use sergittos\bedwars\reward\RewardXp;
use sergittos\bedwars\reward\streak\DailyLoginStreakManager;
use sergittos\bedwars\session\Session;
use function array_slice;
use function count;
use function date;
use function intdiv;
use function is_array;
use function max;
use function min;
use function round;
use function strtolower;
use function time;
use function usort;

/**
 * Tracks how long each player has been actively (non-AFK) online today and
 * grants a stepped ladder of coin/XP rewards as they cross each threshold.
 *
 * Anti-abuse:
 *  - the per-player "seconds today" counter only advances while the player
 *    has produced real input recently (see recordActivity()); an AFK player
 *    (or an AFK-machine/bot standing still) simply stops accruing playtime,
 *    so it cannot be farmed passively.
 *  - the counter resets on the server's local calendar day, independently
 *    of session length, so relogging cannot re-trigger already-claimed
 *    tiers - "claimed" tier indices are stored right alongside the day
 *    stamp and are wiped together on rollover.
 *  - once the configured daily cap is reached no further reward tiers are
 *    evaluated at all for that player until the next day, protecting the
 *    server economy from unbounded inflation.
 *
 * All DB reads/writes are batched (loaded once on join, flushed on a slow
 * interval + on quit) rather than per-second, keeping this feature's actual
 * per-tick cost to a handful of integer comparisons per online player.
 */
final class PlaytimeRewardManager{

    private const FLUSH_INTERVAL_SECONDS = 60;

    /** @var PlaytimeTier[] sorted ascending by minutes */
    private array $tiers;

    private int $capSeconds;
    private int $afkThresholdSeconds;

    /**
     * @var array<string, array{
     *     day: string,
     *     seconds: int,
     *     claimed: array<int, bool>,
     *     lastActivity: int,
     *     lastFlush: int,
     *     dirty: bool,
     *     loaded: bool
     * }> lowercase username => runtime state
     */
    private array $state = [];

    public function __construct(private BedWarsCore $plugin, private NotificationService $notifications, private ?DailyLoginStreakManager $streaks = null){
        $cfg = $plugin->getConfig()->get("playtime-rewards", []);
        if(!is_array($cfg)){
            $cfg = [];
        }

        $tiers = [];
        foreach((is_array($cfg["tiers"] ?? null) ? $cfg["tiers"] : []) as $row){
            if(!is_array($row)){
                continue;
            }
            $minutes = (int) ($row["minutes"] ?? 0);
            if($minutes <= 0){
                continue;
            }
            $tiers[] = new PlaytimeTier($minutes, max(0, (int) ($row["coins"] ?? 0)), max(0, (int) ($row["xp"] ?? 0)));
        }

        if($tiers === []){
            // sane defaults if the config section is missing/empty, mirroring
            // a Hypixel-style stepped ladder: bigger gaps + bigger rewards
            // the longer the player stays online.
            $tiers = [
                new PlaytimeTier(5, 10, 15),
                new PlaytimeTier(15, 25, 25),
                new PlaytimeTier(30, 50, 35),
                new PlaytimeTier(60, 120, 55),
                new PlaytimeTier(90, 150, 70),
                new PlaytimeTier(120, 200, 90),
            ];
        }

        usort($tiers, fn(PlaytimeTier $a, PlaytimeTier $b) => $a->minutes <=> $b->minutes);
        $this->tiers = $tiers;

        $capMinutes = (int) ($cfg["daily-cap-minutes"] ?? 120);
        $this->capSeconds = max(0, $capMinutes) * 60;

        $this->afkThresholdSeconds = max(5, (int) ($cfg["afk-threshold-seconds"] ?? 60));
    }

    private function today(): string{
        return date("Y-m-d");
    }

    public function loadPlayer(Player $player): void{
        $key = strtolower($player->getName());
        $now = time();

        $this->state[$key] = [
            "day" => $this->today(),
            "seconds" => 0,
            "claimed" => [],
            "lastActivity" => $now,
            "lastFlush" => $now,
            "dirty" => false,
            "loaded" => false,
        ];

        $this->plugin->getProvider()->getRewardState($player->getName(), function(array $saved) use ($key): void{
            if(!isset($this->state[$key])){
                // player already left before the async load returned
                return;
            }

            $playtime = is_array($saved["playtime"] ?? null) ? $saved["playtime"] : [];
            $day = (string) ($playtime["day"] ?? "");

            if($day === $this->today()){
                $this->state[$key]["seconds"] = max(0, (int) ($playtime["seconds"] ?? 0));
                $claimed = is_array($playtime["claimed"] ?? null) ? $playtime["claimed"] : [];
                foreach($claimed as $index){
                    $this->state[$key]["claimed"][(int) $index] = true;
                }
            }
            // else: stale/previous day - keep the fresh zeroed state, which
            // is exactly the "daily reset" behaviour.

            $this->state[$key]["loaded"] = true;
        });
    }

    public function unloadPlayer(Player $player): void{
        $key = strtolower($player->getName());
        if(!isset($this->state[$key])){
            return;
        }

        $this->flush($player->getName(), $this->state[$key]);
        unset($this->state[$key]);
    }

    /** Called from any listener that observed real player input this tick. */
    public function recordActivity(Player $player): void{
        $key = strtolower($player->getName());
        if(isset($this->state[$key])){
            $this->state[$key]["lastActivity"] = time();
        }
    }

    /**
     * Advances every online, loaded player's counter by $elapsedSeconds
     * (the heartbeat interval) unless they are AFK, and grants any reward
     * tier crossed. Cheap: just array reads/writes, no DB/network calls.
     */
    public function tick(int $elapsedSeconds, iterable $sessions): void{
        // Playtime rewards are only meant to run on the actual game servers
        // (solo/double/triple/squad) - they used to also tick on the lobby
        // server, letting players farm the daily coin/XP ladder just by
        // idling in the hub. This plugin build (BedWarsCore-lobby) only
        // ever runs on the lobby server, so this is a hard, permanent
        // no-op here rather than a runtime check: the identical
        // PlaytimeRewardManager in BedWarsCore-game (bundled into the
        // solo/double/triple/squad servers) is the one that actually does
        // the accounting.
    }

    /**
     * @param array<int, bool> $claimed
     */
    private function grant(Player $player, Session $session, PlaytimeTier $tier, int $secondsToday, array $claimed): void{
        if($tier->coins > 0){
            CoinsAPI::addCoins($player, (float) $tier->coins);
        }

        $xp = $tier->xp;
        if($xp > 0 && $this->streaks !== null && $this->streaks->hasPerk($player, "xp_boost")){
            $xp = (int) round($xp * 1.1);
        }
        if($xp > 0){
            RewardXp::grantSilently($session, $xp);
        }

        $rewardLine = "";
        if($tier->coins > 0){
            $rewardLine .= TF::GOLD . "+" . $tier->coins . " Coins";
        }
        if($xp > 0){
            $rewardLine .= ($rewardLine !== "" ? TF::WHITE . "  " : "") . TF::GREEN . "+" . $xp . " XP";
        }

        $minutesToday = intdiv($secondsToday, 60);
        $next = $this->nextTier($claimed);

        $lines = [
            TF::GRAY . "Tier " . TF::WHITE . "» " . TF::AQUA . $tier->label(),
            TF::GRAY . "Online Today " . TF::WHITE . "» " . TF::YELLOW . $minutesToday . TF::GRAY . " minutes",
            "",
            $rewardLine,
            "",
        ];
        $lines[] = $next !== null
            ? TF::GRAY . "Next Tier " . TF::WHITE . "» " . TF::AQUA . $next->label()
            : TF::GRAY . "Next Tier " . TF::WHITE . "» " . TF::AQUA . "All tiers reached today";

        $this->notifications->queue(
            $player,
            TF::AQUA,
            "PLAYTIME REWARD",
            $lines
        );
    }

    /**
     * @param array<int, bool> $claimed
     */
    private function nextTier(array $claimed): ?PlaytimeTier{
        foreach($this->tiers as $index => $tier){
            if(!isset($claimed[$index])){
                return $tier;
            }
        }
        return null;
    }

    private function flush(string $username, array $s): void{
        $this->plugin->getProvider()->mergeRewardState($username, [
            "playtime" => [
                "day" => $s["day"],
                "seconds" => $s["seconds"],
                "claimed" => array_slice(array_keys($s["claimed"]), 0, count($this->tiers)),
            ],
        ]);
    }
}
