<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use poggit\libasynql\DataConnector;
use poggit\libasynql\SqlError;
use function max;
use function strtolower;
use function time;

/** All database access of the profile module. Every callback is also fired (with a failure value) when a query errors. */
final class ProfileRepository{

    public function __construct(private DataConnector $db){}

    // ------------------------------------------------------------------ seasons

    public function seedSeason(int $ends) : void{
        $this->db->executeChange("bedwars.profile.seed_season", ["now" => time(), "ends" => $ends], null, static function(SqlError $e) : void{});
    }

    /** @param callable(?array{id: int, name: string, started: int, ends: int}): void $done */
    public function getSeason(callable $done) : void{
        $this->db->executeSelect("bedwars.profile.get_season", [], static function(array $rows) use ($done) : void{
            $r = $rows[0] ?? null;
            $done($r === null ? null : ["id" => (int) $r["id"], "name" => (string) $r["name"], "started" => (int) $r["started_at"], "ends" => (int) $r["ends_at"]]);
        }, static function(SqlError $e) use ($done) : void{
            $done(null);
        });
    }

    /** @param callable(bool): void $done true when THIS call created the season row */
    public function newSeason(int $id, string $name, int $ends, callable $done) : void{
        $this->db->executeChange("bedwars.profile.new_season", ["id" => $id, "name" => $name, "now" => time(), "ends" => $ends], static function(int $affected) use ($done) : void{
            $done($affected > 0);
        }, static function(SqlError $e) use ($done) : void{
            $done(false);
        });
    }

    /** @param callable(): void $done */
    public function archiveAndReset(int $oldSeason, callable $done) : void{
        $this->db->executeChange("bedwars.profile.archive_season", ["season" => $oldSeason], function() use ($done) : void{
            $this->db->executeChange("bedwars.profile.reset_season", [], static function() use ($done) : void{
                $done();
            }, static function(SqlError $e) use ($done) : void{
                $done();
            });
        }, function(SqlError $e) use ($done) : void{
            $done();
        });
    }

    // ------------------------------------------------------------------ profile rows

    public function ensure(string $username, string $display) : void{
        $this->db->executeChange("bedwars.profile.ensure", ["username" => strtolower($username), "display" => $display, "now" => time()], null, static function(SqlError $e) : void{});
    }

    public function addPlaytime(string $username, string $display, int $secs) : void{
        if($secs <= 0){
            return;
        }
        $this->db->executeChange("bedwars.profile.add_playtime", ["username" => strtolower($username), "display" => $display, "secs" => $secs, "now" => time()], null, static function(SqlError $e) : void{});
    }

    /** @param callable(?array<string, mixed>): void $done row of bw_profile or null (missing/error) */
    public function getProfileRow(string $username, callable $done) : void{
        $this->db->executeSelect("bedwars.profile.get_profile", ["username" => strtolower($username)], static function(array $rows) use ($done) : void{
            $done($rows[0] ?? null);
        }, static function(SqlError $e) use ($done) : void{
            $done(null);
        });
    }

    /** @param callable(?PlayerProfile): void $done null when the database failed */
    public function loadProfile(string $username, callable $done) : void{
        $lower = strtolower($username);
        $p = new PlayerProfile($lower);
        $fail = static function(SqlError $e) use ($done) : void{
            $done(null);
        };

        $this->db->executeSelect("bedwars.profile.get_stats", ["username" => $lower], function(array $rows) use ($p, $lower, $done, $fail) : void{
            $hasStats = $rows !== [];
            if($hasStats){
                $r = $rows[0];
                $p->kills = (int) $r["kills"];
                $p->wins = (int) $r["wins"];
                $p->finals = (int) $r["final_kills"];
                $p->beds = (int) $r["beds_broken"];
                $p->deaths = (int) $r["deaths"];
                $p->xp = (int) $r["xp"];
                $p->level = max(1, (int) $r["level"]);
                $p->winStreak = (int) $r["win_streak"];
                $p->bestStreak = (int) $r["best_win_streak"];
            }
            $this->db->executeSelect("bedwars.profile.get_profile", ["username" => $lower], function(array $rows2) use ($p, $hasStats, $done, $fail) : void{
                if($rows2 !== []){
                    $r = $rows2[0];
                    $name = (string) $r["display_name"];
                    if($name !== ""){
                        $p->displayName = $name;
                    }
                    $p->games = (int) $r["games"];
                    $p->losses = (int) $r["losses"];
                    $p->playtime = (int) $r["playtime"];
                    $p->mvps = (int) $r["mvps"];
                    $p->rp = (int) $r["rp"];
                    $p->peakRp = (int) $r["peak_rp"];
                    $p->peakTier = (int) $r["peak_tier"];
                }
                $p->exists = $hasStats || $rows2 !== [];
                if(!$p->exists){
                    $done($p);
                    return;
                }
                $this->db->executeSelect("bedwars.profile.rank_position", ["rp" => $p->rp], static function(array $rows3) use ($p, $done) : void{
                    if($p->rp > 0 && isset($rows3[0]["higher"])){
                        $p->rankPosition = ((int) $rows3[0]["higher"]) + 1;
                    }
                    $done($p);
                }, static function(SqlError $e) use ($p, $done) : void{
                    $done($p);
                });
            }, $fail);
        }, $fail);
    }

    /** @param callable(?array<string, int>): void $done medal id => count */
    public function loadMedals(string $username, callable $done) : void{
        $this->db->executeSelect("bedwars.profile.get_medals", ["username" => strtolower($username)], static function(array $rows) use ($done) : void{
            $out = [];
            foreach($rows as $r){
                $out[(string) $r["medal"]] = (int) $r["cnt"];
            }
            $done($out);
        }, static function(SqlError $e) use ($done) : void{
            $done(null);
        });
    }

    public function addMedal(string $username, string $medal, int $count) : void{
        $this->db->executeChange("bedwars.profile.add_medal", ["username" => strtolower($username), "medal" => $medal, "cnt" => $count], null, static function(SqlError $e) : void{});
    }

    /** @param callable(bool): void $done true when the achievement was newly stored */
    public function unlockAchievement(string $username, string $id, callable $done) : void{
        $this->db->executeChange("bedwars.profile.unlock_achievement", ["username" => strtolower($username), "achievement" => $id, "now" => time()], static function(int $affected) use ($done) : void{
            $done($affected > 0);
        }, static function(SqlError $e) use ($done) : void{
            $done(false);
        });
    }

    public function applyMatch(string $username, string $display, bool $loss, bool $mvp, int $rp, int $tier) : void{
        $this->db->executeChange("bedwars.profile.apply_match", [
            "username" => strtolower($username), "display" => $display, "loss" => $loss ? 1 : 0, "mvp" => $mvp ? 1 : 0,
            "rp" => max(0, $rp), "tier" => $tier, "now" => time(),
        ], null, static function(SqlError $e) : void{});
    }

    public function insertHistory(string $username, MatchResult $r, int $rpDelta, int $season) : void{
        $this->db->executeChange("bedwars.profile.insert_history", [
            "username" => strtolower($username), "ts" => time(), "mode" => $r->mode, "map" => $r->map, "result" => $r->result,
            "kills" => $r->kills, "finals" => $r->finals, "beds" => $r->beds, "deaths" => $r->deaths,
            "rp_delta" => $rpDelta, "duration" => $r->duration, "season" => $season,
        ], null, static function(SqlError $e) : void{});
    }

    /** @param callable(?array{rows: list<array<string, mixed>>, total: int}): void $done */
    public function loadHistory(string $username, int $limit, int $offset, callable $done) : void{
        $lower = strtolower($username);
        $this->db->executeSelect("bedwars.profile.count_history", ["username" => $lower], function(array $c) use ($lower, $limit, $offset, $done) : void{
            $total = (int) ($c[0]["total"] ?? 0);
            if($total === 0){
                $done(["rows" => [], "total" => 0]);
                return;
            }
            $this->db->executeSelect("bedwars.profile.get_history", ["username" => $lower, "limit" => $limit, "offset" => max(0, $offset)], static function(array $rows) use ($total, $done) : void{
                $done(["rows" => $rows, "total" => $total]);
            }, static function(SqlError $e) use ($done) : void{
                $done(null);
            });
        }, static function(SqlError $e) use ($done) : void{
            $done(null);
        });
    }

    /** @param callable(?list<array<string, mixed>>): void $done */
    public function loadSeasonResults(string $username, int $limit, callable $done) : void{
        $this->db->executeSelect("bedwars.profile.season_results", ["username" => strtolower($username), "limit" => $limit], static function(array $rows) use ($done) : void{
            $done($rows);
        }, static function(SqlError $e) use ($done) : void{
            $done(null);
        });
    }

    /** @param callable(?list<array<string, mixed>>): void $done */
    public function loadTop(int $limit, callable $done) : void{
        $this->db->executeSelect("bedwars.profile.top", ["limit" => $limit], static function(array $rows) use ($done) : void{
            $done($rows);
        }, static function(SqlError $e) use ($done) : void{
            $done(null);
        });
    }
}
