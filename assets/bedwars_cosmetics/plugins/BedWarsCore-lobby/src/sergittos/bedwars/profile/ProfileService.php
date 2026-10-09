<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use sergittos\bedwars\provider\mysql\AsyncMysqlProvider;
use sergittos\bedwars\session\Session;
use Throwable;
use function array_keys;
use function array_sum;
use function implode;
use function max;
use function min;
use function number_format;
use function str_pad;
use function strtolower;
use function strtoupper;
use function time;
use const STR_PAD_LEFT;

/**
 * Heart of the BedWars profile / ranked-season / medals / achievements module.
 *
 * Shared by the Lobby core (read side: playtime, seasons, the profile menu) and the Game core (write side:
 * ranked points, match history, medals, achievements - fed by ProfileHooks). Everything lives in MySQL, so
 * every server of the network always sees the same season and the same numbers.
 */
final class ProfileService{

    private ProfileConfig $config;
    private ProfileRepository $repo;

    /** @var array{id: int, name: string, started: int, ends: int} */
    private array $season = ["id" => 1, "name" => "", "started" => 0, "ends" => 0];

    /** @var list<int>|null */
    private ?array $sizesCache = null;
    private int $sizesSeason = 0;

    /** @var array<string, int> lowercase name => unix time playtime was last flushed */
    private array $tracked = [];

    private bool $rolling = false;

    public function __construct(private PluginBase $plugin, AsyncMysqlProvider $provider){
        $file = $plugin->getDataFolder() . "profile.yml";
        $data = [];
        try{
            $data = (new Config($file, Config::YAML))->getAll();
        }catch(Throwable $e){
            $plugin->getLogger()->warning("[Profile] could not read profile.yml (" . $e->getMessage() . ") - using defaults.");
        }
        $this->config = new ProfileConfig($data);
        $this->repo = new ProfileRepository($provider->getConnector());

        $days = $this->config->int("season.length-days", 0);
        $this->repo->seedSeason($days > 0 ? time() + $days * 86400 : 0);
        $this->refreshSeason();

        $plugin->getServer()->getPluginManager()->registerEvents(new ProfileListener($this), $plugin);
        $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            $this->refreshSeason();
        }), 20 * 60);
        $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            $this->flushAllPlaytime();
        }), 20 * 300);

        foreach($plugin->getServer()->getOnlinePlayers() as $p){
            $this->onJoin($p);
        }
    }

    public function getConfig() : ProfileConfig{
        return $this->config;
    }

    public function getRepository() : ProfileRepository{
        return $this->repo;
    }

    // ------------------------------------------------------------------ seasons

    public function getSeasonId() : int{
        return $this->season["id"];
    }

    public function getSeasonEnds() : int{
        return $this->season["ends"];
    }

    /** "SERIES 01" (or the custom name given to /bwseason new). */
    public function getSeasonLabel() : string{
        $name = $this->season["name"];
        return $name !== "" ? strtoupper($name) : "SERIES " . str_pad((string) $this->season["id"], 2, "0", STR_PAD_LEFT);
    }

    public function refreshSeason() : void{
        $this->repo->getSeason(function(?array $s) : void{
            if($s === null){
                return;
            }
            $this->season = $s;
            if($s["ends"] > 0 && time() >= $s["ends"]){
                $this->startNewSeason(null, static function(bool $ok, int $id) : void{});
            }
        });
    }

    /**
     * Ends the current season (results archived, every RP reset) and opens the next one.
     * Safe to call from several servers at once: only the server that wins the insert performs the reset.
     *
     * @param callable(bool, int): void $done
     */
    public function startNewSeason(?string $name, callable $done) : void{
        if($this->rolling){
            $done(false, $this->season["id"]);
            return;
        }
        $this->rolling = true;
        $old = $this->season["id"];
        $new = $old + 1;
        $days = $this->config->int("season.length-days", 0);
        $ends = $days > 0 ? time() + $days * 86400 : 0;
        $this->repo->newSeason($new, $name ?? "", $ends, function(bool $created) use ($old, $new, $done) : void{
            if(!$created){
                $this->rolling = false;
                $this->refreshSeason();
                $done(false, $old);
                return;
            }
            $this->repo->archiveAndReset($old, function() use ($new, $done) : void{
                $this->rolling = false;
                $this->sizesCache = null;
                $this->refreshSeason();
                $this->plugin->getLogger()->notice("[Profile] Season " . $new . " started - ranks were reset.");
                $done(true, $new);
            });
        });
    }

    /** @return list<int> */
    public function sizes() : array{
        if($this->sizesCache === null || $this->sizesSeason !== $this->season["id"]){
            $this->sizesCache = RankedTiers::sizes($this->config, $this->season["id"]);
            $this->sizesSeason = $this->season["id"];
        }
        return $this->sizesCache;
    }

    /** @return array{tier: int, division: int, floor: int, next: ?int, tierFloor: int, into: int, span: int} */
    public function locate(int $rp) : array{
        return RankedTiers::locate($this->sizes(), $rp);
    }

    // ------------------------------------------------------------------ playtime / presence

    public function onJoin(Player $player) : void{
        $this->tracked[strtolower($player->getName())] = time();
        $this->repo->ensure($player->getName(), $player->getName());
    }

    public function onQuit(Player $player) : void{
        $this->flushPlaytime($player->getName(), true);
    }

    private function flushPlaytime(string $name, bool $remove) : void{
        $key = strtolower($name);
        if(!isset($this->tracked[$key])){
            return;
        }
        $secs = time() - $this->tracked[$key];
        if($remove){
            unset($this->tracked[$key]);
        }else{
            $this->tracked[$key] = time();
        }
        $display = $this->plugin->getServer()->getPlayerExact($name)?->getName() ?? $name;
        $this->repo->addPlaytime($key, $display, $secs);
    }

    private function flushAllPlaytime() : void{
        foreach(array_keys($this->tracked) as $key){
            $this->flushPlaytime($key, false);
        }
    }

    public function shutdown() : void{
        foreach(array_keys($this->tracked) as $key){
            $this->flushPlaytime($key, true);
        }
    }

    /** Seconds the player has been online since the last flush (used for achievement progress). */
    public function unflushedSeconds(string $name) : int{
        $t = $this->tracked[strtolower($name)] ?? null;
        return $t === null ? 0 : max(0, time() - $t);
    }

    // ------------------------------------------------------------------ ranked match recording

    private function delta(MatchResult $r) : int{
        $c = $this->config;
        $base = match($r->result){
            "WIN" => $c->int("ranked.win", 25),
            "TIE" => $c->int("ranked.tie", 0),
            default => $c->int("ranked.loss", -12),
        };
        $perf = $r->kills * $c->int("ranked.kill", 1) + $r->finals * $c->int("ranked.final-kill", 2) + $r->beds * $c->int("ranked.bed", 3);
        $delta = $base + min(max(0, $c->int("ranked.performance-cap", 12)), max(0, $perf));
        if($r->mvp){
            $delta += $c->int("ranked.mvp-bonus", 5);
        }
        if($r->result === "LEFT"){
            $delta -= max(0, $c->int("ranked.quit-penalty", 8));
        }
        return $delta;
    }

    public function isRanked(MatchResult $r) : bool{
        return $r->duration >= $this->config->int("ranked.min-match-seconds", 90);
    }

    /**
     * Stores one finished match: RP, history, medals and achievements. Never throws; all database work is async.
     * $session is only used to talk to the player and to hand out achievement rewards (may be null/offline).
     */
    public function recordMatch(MatchResult $r, ?Session $session) : void{
        if(!$this->isRanked($r)){
            return;
        }
        $lower = strtolower($r->username);
        $seasonId = $this->season["id"];

        $this->repo->getProfileRow($lower, function(?array $row) use ($r, $session, $lower, $seasonId) : void{
            try{
                $old = $row !== null ? (int) $row["rp"] : 0;
                $games = ($row !== null ? (int) $row["games"] : 0) + 1;
                $peakTier0 = $row !== null ? (int) $row["peak_tier"] : 0;
                $playtime0 = $row !== null ? (int) $row["playtime"] : 0;

                $delta = $this->delta($r);
                $new = max(0, $old + $delta);
                $oldLoc = $this->locate($old);
                if($delta < 0 && !$this->config->bool("ranked.tier-demotion", false)){
                    $new = max($new, $oldLoc["tierFloor"]);
                }
                $newLoc = $this->locate($new);
                $applied = $new - $old;

                $this->repo->applyMatch($lower, $r->displayName, $r->result === "LOSS" || $r->result === "LEFT", $r->mvp, $new, $newLoc["tier"]);
                $this->repo->insertHistory($lower, $r, $applied, $seasonId);
                foreach($r->medals as $id => $count){
                    if($count > 0 && isset(ProfileCatalog::MEDALS[$id])){
                        $this->repo->addMedal($lower, $id, $count);
                    }
                }

                $this->announce($session, $r, $applied, $new, $oldLoc, $newLoc);

                if($session !== null){
                    $this->repo->loadMedals($lower, function(?array $medals) use ($r, $session, $lower, $games, $peakTier0, $newLoc, $playtime0) : void{
                        try{
                            $this->evaluateAchievements($r, $session, $lower, $medals ?? [], $games, max($peakTier0, $newLoc["tier"]), $playtime0);
                        }catch(Throwable $e){
                            $this->plugin->getLogger()->warning("[Profile] achievement check failed for " . $lower . ": " . $e->getMessage());
                        }
                    });
                }
            }catch(Throwable $e){
                $this->plugin->getLogger()->warning("[Profile] recordMatch failed for " . $lower . ": " . $e->getMessage());
            }
        });
    }

    /**
     * @param array{tier: int, division: int} $oldLoc
     * @param array{tier: int, division: int} $newLoc
     */
    private function announce(?Session $session, MatchResult $r, int $applied, int $newRp, array $oldLoc, array $newLoc) : void{
        if($session === null || !$session->getPlayer()->isConnected()){
            return;
        }
        $sign = $applied >= 0 ? "§a+" . $applied : "§c" . $applied;
        $label = match($r->result){"WIN" => "§aVictory", "TIE" => "§eDraw", "LEFT" => "§6Left early", default => "§cDefeat"};
        $session->message("§d§lRANKED §r§8| " . $label . " §8| " . $sign . " RP §8| " . RankedTiers::coloredLabel($newLoc) . " §7(" . number_format($newRp) . " RP)");
        if($r->medals !== [] && $this->config->bool("medals.announce", true)){
            $names = [];
            foreach($r->medals as $id => $count){
                if(isset(ProfileCatalog::MEDALS[$id])){
                    $names[] = "§f" . ProfileCatalog::MEDALS[$id]["name"] . ($count > 1 ? " §7x" . $count : "");
                }
            }
            if($names !== []){
                $session->message("§d§lMEDALS §r§8| " . implode("§7, ", $names));
            }
        }
        // (division numbers count UP inside a tier: I -> II -> III, so a higher division is a promotion)
        $promoted = $newLoc["tier"] > $oldLoc["tier"] || ($newLoc["tier"] === $oldLoc["tier"] && $newLoc["division"] > $oldLoc["division"]);
        $demoted = $newLoc["tier"] < $oldLoc["tier"] || ($newLoc["tier"] === $oldLoc["tier"] && $newLoc["division"] < $oldLoc["division"]);
        if($promoted){
            $session->title("§d§lRANK UP", RankedTiers::coloredLabel($newLoc), 5, 50, 15);
            $session->playSound("random.levelup");
        }elseif($demoted){
            $session->message("§c§lDEMOTED §r§8| §7You dropped to " . RankedTiers::coloredLabel($newLoc) . "§7.");
        }
    }

    /** @param array<string, int> $medals */
    private function evaluateAchievements(MatchResult $r, Session $session, string $lower, array $medals, int $games, int $peakTier, int $playtime0) : void{
        $won = $r->result === "WIN";
        $medalNow = array_sum($medals);
        $streak = $session->getBestWinStreak();
        $now = [
            "wins" => (float) $session->getWins(),
            "games" => (float) $games,
            "kills" => (float) $session->getKills(),
            "finals" => (float) $session->getFinalKills(),
            "beds" => (float) $session->getBedsBroken(),
            "streak" => (float) $streak,
            "medals" => (float) $medalNow,
            "playtime" => ($playtime0 + $this->unflushedSeconds($lower)) / 3600.0,
            "peak_tier" => (float) $peakTier,
        ];
        $before = $now;
        $before["wins"] = max(0.0, $now["wins"] - ($won ? 1 : 0));
        $before["games"] = max(0.0, $now["games"] - 1);
        $before["kills"] = max(0.0, $now["kills"] - $r->kills);
        $before["finals"] = max(0.0, $now["finals"] - $r->finals);
        $before["beds"] = max(0.0, $now["beds"] - $r->beds);
        $before["medals"] = max(0.0, $now["medals"] - array_sum($r->medals));
        $before["playtime"] = max(0.0, $now["playtime"] - $r->duration / 3600.0);
        if($won && $session->getWinStreak() >= $streak){
            $before["streak"] = max(0.0, $now["streak"] - 1);
        }

        $retro = $this->config->bool("achievements.retroactive-rewards", false);
        $rewards = $this->config->bool("achievements.give-rewards", true);

        foreach(ProfileCatalog::ACHIEVEMENTS as $id => $def){
            $stat = $def["stat"];
            if(!isset($now[$stat]) || $now[$stat] < $def["goal"]){
                continue;
            }
            $fresh = $before[$stat] < $def["goal"];
            $this->repo->unlockAchievement($lower, $id, function(bool $stored) use ($session, $def, $fresh, $retro, $rewards) : void{
                if(!$stored || !($fresh || $retro)){
                    return;
                }
                try{
                    if(!$session->getPlayer()->isConnected()){
                        return;
                    }
                    $extra = "";
                    if($rewards){
                        if($def["coins"] > 0){
                            $session->addCoins($def["coins"]);
                            $extra .= " §6+" . number_format($def["coins"]) . " coins";
                        }
                        if($def["xp"] > 0){
                            $session->addXp($def["xp"], "Achievement");
                        }
                    }
                    $session->message("§d§lACHIEVEMENT §r§8| §f" . $def["name"] . " §7- " . $def["desc"] . $extra);
                    $session->title("§d§lACHIEVEMENT", "§f" . $def["name"], 5, 45, 15);
                    $session->playSound("random.levelup");
                }catch(Throwable){
                }
            });
        }
    }
}
