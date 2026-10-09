<?php

declare(strict_types=1);

namespace sergittos\bedwars\provider\mysql;

use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;
use pocketmine\plugin\Plugin;
use sergittos\bedwars\session\Session;
use function array_shift;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function strtolower;
use function time;

class AsyncMysqlProvider{

    private DataConnector $db;

    // Kept only so getRejoin()/getPartyFollow() can log through the plugin's
    // own logger from their onError safety-net (added below) without a
    // second constructor argument - see those methods for why.
    private Plugin $plugin;

    /**
     * quest_v2 is a single JSON blob per player shared by several unrelated
     * features (challenges, battlepass, ...), each owning one key inside it.
     * A naive get-full-blob -> modify-my-key -> set-full-blob roundtrip is
     * NOT safe when two of those features sync for the same player around
     * the same tick (e.g. challenges + battlepass both syncing on join):
     * both read the same snapshot, and whichever write lands second clobbers
     * the other's update with its own (stale) copy of the blob, silently
     * reverting progress that was just saved - this is what caused
     * challenge progress to randomly "reset" mid-day.
     *
     * The queue below serializes every quest_v2 read+write for a given
     * player so that each mutation always starts from the state left by the
     * previous one, and only releases the next one once its own write has
     * actually completed on the DB thread.
     *
     * @var array<string, \Closure[]>
     */
    private array $questStateQueue = [];

    /** @var array<string, bool> */
    private array $questStateBusy = [];

    public function __construct(Plugin $plugin){
        $this->plugin = $plugin;

        $cfg = $plugin->getConfig()->get("database");
        if(!is_array($cfg)){
            $cfg = [];
        }

        // Everything in this plugin that needs the database - rejoin included -
        // depends on this connector coming up cleanly. libasynql::create() throws
        // on a bad/missing "database" config section, which (unlike the
        // safeGeneric()-wrapped calls below) was previously uncaught here: that
        // exception aborts BedWarsCore::onEnable() partway through, PocketMine
        // disables the whole plugin, and every symptom looks identical to "rejoin
        // does nothing" with no obvious error tying the two together unless you
        // scroll back to the startup log. Fail loudly and specifically instead.
        try{
            $this->db = libasynql::create($plugin, $cfg, [
                "mysql" => "mysql.sql",
            ]);
        }catch(\Throwable $e){
            $plugin->getLogger()->critical(
                "Could not connect to the database using the 'database' section of config.yml - " .
                "ALL database-backed features are disabled, including rejoin, sessions and stats: " . $e->getMessage()
            );
            throw $e;
        }

        $this->safeGeneric($plugin, "bedwars.init");
        $this->safeGeneric($plugin, "bedwars.party_follow.create_table");
        $this->safeGeneric($plugin, "bedwars.rejoin.create_table");
        $this->safeGeneric($plugin, "bedwars.quest.create_table");
        $this->safeGeneric($plugin, "bedwars.quest.create_progress_table");
        $this->safeGeneric($plugin, "bedwars.quest_v2.create_table");
        $this->safeGeneric($plugin, "bedwars.reward.create_table");
        $this->safeGeneric($plugin, "bedwars.clan.create_table");
        $this->safeGeneric($plugin, "bedwars.clan.members_create_table");
        $this->safeGeneric($plugin, "bedwars.clan.applications_create_table");
        $this->safeGeneric($plugin, "bedwars.clan.creation_requests_create_table");
        $this->safeGeneric($plugin, "bedwars.clan.bank_log_create_table");
        $this->safeGeneric($plugin, "bedwars.clan.levelups_create_table");
        $this->safeGeneric($plugin, "bedwars.report.create_table");
        foreach(["create_profile", "create_medals", "create_achievements", "create_history", "create_seasons", "create_season_results"] as $profileTable){
            $this->safeGeneric($plugin, "bedwars.profile." . $profileTable);
        }

        $onMigrateFail = function(SqlError $error) use ($plugin): void{
            $plugin->getLogger()->warning("Could not auto-add a column: " . $error->getMessage());
        };

        $this->db->executeGeneric("bedwars.migrate_deaths", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_rejoin_mode", [], null, $onMigrateFail);
        // ROOT-CAUSE FIX: mirrors the same fix in BedWarsCore-game's
        // AsyncMysqlProvider - see the comment there for the full story. This
        // migration query already existed in mysql.sql but was never called
        // here either, so any database created before the "confirmed" column
        // existed made every bedwars.rejoin.get select fail at PREPARE time on
        // both the lobby and game side alike.
        $this->db->executeGeneric("bedwars.migrate_rejoin_confirmed", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_win_streak", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_best_win_streak", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_dance", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_wearable_pet", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_wearable_wing", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_wearable_cape", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_wearable_hat", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_kill_message", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_level_default", [], null, $onMigrateFail);
        $this->db->executeGeneric("bedwars.migrate_level_min", [], null, $onMigrateFail);
    }

    /** Raw connector - used by the profile / ranked module (sergittos\bedwars\profile) for its own queries. */
    public function getConnector(): DataConnector{
        return $this->db;
    }

    private function safeGeneric(Plugin $plugin, string $query): void{
        try{
            $this->db->executeGeneric($query);
        }catch(\Throwable $e){
            $plugin->getLogger()->error("Missing/invalid libasynql query: " . $query);
            $plugin->getLogger()->error($e->getMessage());
        }
    }

    public function setPartyFollow(string $username, string $leader, string $targetServer): void{
        $this->db->executeChange("bedwars.party_follow.set", [
            "username"      => strtolower($username),
            "leader"        => strtolower($leader),
            "target_server" => $targetServer,
            "created_at"    => time(),
        ]);
    }

    public function getPartyFollow(string $username, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.party_follow.get",
            ["username" => strtolower($username)],
            function(array $rows) use ($callback): void{
                $callback($rows[0] ?? null);
            },
            // Safety net - see the matching comment on getRejoin() below.
            function(SqlError $error) use ($username, $callback): void{
                $this->plugin->getLogger()->error(
                    "[PartyFollow] getPartyFollow(" . $username . ") failed: " . $error->getMessage() . " - falling back to normal join."
                );
                $callback(null);
            }
        );
    }

    public function clearPartyFollow(string $username): void{
        $this->db->executeChange("bedwars.party_follow.clear", ["username" => strtolower($username)]);
    }

    public function setRejoin(string $username, string $targetServer, int $gameId, string $team, string $mode = "Solo"): void{
        $this->db->executeChange("bedwars.rejoin.set", [
            "username"      => strtolower($username),
            "target_server" => $targetServer,
            "game_id"       => $gameId,
            "team"          => $team,
            "mode"          => $mode,
            "created_at"    => time(),
        ]);
    }

    public function getRejoin(string $username, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.rejoin.get",
            ["username" => strtolower($username)],
            function(array $rows) use ($callback): void{
                $callback($rows[0] ?? null);
            },
            // Safety net for the exact failure mode that caused this bug
            // report: this query used to fail at PREPARE time on any
            // database missing the "confirmed" column (see the
            // migrate_rejoin_confirmed fix above), and a failed query never
            // invokes executeSelect()'s success callback at all - which left
            // the lobby-side rejoin prompt waiting forever, for every player,
            // on every join. With the migration fixed this specific cause is
            // gone, but this handler makes any future lookup failure degrade
            // to "treat it as no pending rejoin" instead of stranding the
            // player with no way forward.
            function(SqlError $error) use ($username, $callback): void{
                // $this->plugin->getLogger()->error(
                //     "[Rejoin] getRejoin(" . $username . ") failed: " . $error->getMessage() . " - treating as no pending rejoin so the join can proceed."
                // );
                $callback(null);
            }
        );
    }

    /**
     * Marks a pending rejoin as explicitly confirmed by the player. Called
     * by CoreListener the moment a player right-clicks their Rejoin Ticket
     * here on the Lobby, right before transferring them - the Game
     * server's JoinListener only auto-rejoins a landing player when this
     * flag is set, so ignoring the ticket and queueing into a fresh match
     * instead can never get silently overridden by the old one.
     */
    public function confirmRejoin(string $username): void{
        $this->db->executeChange("bedwars.rejoin.confirm", ["username" => strtolower($username)]);
    }

    public function clearRejoin(string $username): void{
        $this->db->executeChange("bedwars.rejoin.clear", ["username" => strtolower($username)]);
    }

    public function loadSession(Session $session): void{
        $this->db->executeSelect(
            "bedwars.player.load",
            ["username" => strtolower($session->getUsername())],
            function(array $rows) use ($session): void{
                if(!empty($rows)){
                    $r = $rows[0];

                    $session->setCoins((int) ($r["coins"] ?? 0));
                    $session->setKills((int) ($r["kills"] ?? 0));
                    $session->setWins((int) ($r["wins"] ?? 0));
                    $session->setBedsBroken((int) ($r["beds_broken"] ?? 0));
                    $session->setFinalKills((int) ($r["final_kills"] ?? 0));
                    $session->setDeaths((int) ($r["deaths"] ?? 0));
                    $session->setXp((int) ($r["xp"] ?? 0));
                    $session->setLevel((int) ($r["level"] ?? 1));

                    $session->setWinStreak((int) ($r["win_streak"] ?? 0));
                    $session->setBestWinStreak((int) ($r["best_win_streak"] ?? 0));

                    $session->setSelectedKillEffect((string) ($r["kill_effect"] ?? "none"));
                    $session->setSelectedKillSound((string) ($r["kill_sound"] ?? "none"));
                    $session->setSelectedKillMessage((string) ($r["kill_message"] ?? "none"));

                    $session->setSelectedCape((string) ($r["cape"] ?? "none"));
                    $session->setSelectedWing((string) ($r["wing"] ?? "none"));
                    $session->setSelectedHat((string) ($r["hat"] ?? "none"));
                    $session->setSelectedParticle((string) ($r["particle"] ?? "none"));
                    $session->setSelectedDance((string) ($r["dance"] ?? "none"));

                    $session->setUnlockedCosmetics(
                        json_decode((string) ($r["unlocked_cosmetics"] ?? "[]"), true) ?? []
                    );

                    $session->setWearablePet((string) ($r["wearable_pet"] ?? "none"));
                    $session->setWearableWing((string) ($r["wearable_wing"] ?? "none"));
                    $session->setWearableCape((string) ($r["wearable_cape"] ?? "none"));
                    $session->setWearableHat((string) ($r["wearable_hat"] ?? "none"));
                }else{
                    $this->createPlayer($session->getUsername());
                }

                $session->setLoaded(true);
                if($session->getPlayer()->isOnline()){
                    $session->syncXpBar();
                }
            },
            function(SqlError $e): void{
                throw $e;
            }
        );
    }

    public function saveSession(Session $session): void{
        $this->db->executeChange(
            "bedwars.player.save",
            [
                "username"           => strtolower($session->getUsername()),
                "coins"              => $session->getCoins(),
                "kills"              => $session->getKills(),
                "wins"               => $session->getWins(),
                "beds_broken"        => $session->getBedsBroken(),
                "final_kills"        => $session->getFinalKills(),
                "deaths"             => $session->getDeaths(),
                "xp"                 => $session->getXp(),
                "level"              => $session->getLevel(),

                "win_streak"         => $session->getWinStreak(),
                "best_win_streak"    => $session->getBestWinStreak(),

                "kill_effect"        => $session->getSelectedKillEffect(),
                "kill_sound"         => $session->getSelectedKillSound(),
                "kill_message"       => $session->getSelectedKillMessage(),

                "cape"               => $session->getSelectedCape(),
                "wing"               => $session->getSelectedWing(),
                "hat"                => $session->getSelectedHat(),
                "particle"           => $session->getSelectedParticle(),
                "dance"              => $session->getSelectedDance(),

                "unlocked_cosmetics" => json_encode($session->getUnlockedCosmetics()),

                "wearable_pet"       => $session->getWearablePet(),
                "wearable_wing"      => $session->getWearableWing(),
                "wearable_cape"      => $session->getWearableCape(),
                "wearable_hat"       => $session->getWearableHat(),
            ],
            null,
            function(SqlError $e): void{
                throw $e;
            }
        );
    }

    private function createPlayer(string $username): void{
        $this->db->executeChange("bedwars.player.create", ["username" => strtolower($username)]);
    }

    public function getLeaderboard(string $stat, int $limit, callable $callback): void{
        $allowed = ["kills", "wins", "beds_broken", "final_kills", "deaths", "level", "coins", "win_streak"];
        if(!in_array($stat, $allowed, true)){
            return;
        }

        $this->db->executeSelect(
            "bedwars.leaderboard.$stat",
            ["limit" => $limit],
            $callback,
            function(SqlError $e): void{
                throw $e;
            }
        );
    }

    public function addQuest(string $name, string $description, array $requirements, int $rewardCoins, int $rewardXp, ?callable $onDone = null): void{
        $this->db->executeChange(
            "bedwars.quest.add",
            [
                "name"         => $name,
                "description"  => $description,
                "requirements" => json_encode($requirements),
                "reward_coins" => $rewardCoins,
                "reward_xp"    => $rewardXp,
                "created_at"   => time(),
            ],
            function() use ($onDone): void{
                if($onDone !== null){
                    $onDone();
                }
            }
        );
    }

    public function removeQuest(int $id, ?callable $onDone = null): void{
        $this->db->executeChange(
            "bedwars.quest.remove",
            ["id" => $id],
            function() use ($onDone): void{
                if($onDone !== null){
                    $onDone();
                }
            }
        );

        $this->db->executeChange("bedwars.quest.reset_progress_for_quest", ["quest_id" => $id]);
    }

    public function setQuestEnabled(int $id, bool $enabled, ?callable $onDone = null): void{
        $this->db->executeChange(
            "bedwars.quest.set_enabled",
            ["id" => $id, "enabled" => $enabled ? 1 : 0],
            function() use ($onDone): void{
                if($onDone !== null){
                    $onDone();
                }
            }
        );
    }

    public function getAllQuests(callable $callback): void{
        $this->db->executeSelect("bedwars.quest.get_all", [], $callback);
    }

    public function getEnabledQuests(callable $callback): void{
        $this->db->executeSelect("bedwars.quest.get_enabled", [], $callback);
    }

    public function getQuestProgress(string $username, callable $callback): void{
        $this->db->executeSelect("bedwars.quest.get_progress", ["username" => strtolower($username)], $callback);
    }

    public function setQuestProgress(string $username, int $questId, array $progress, bool $completed): void{
        $this->db->executeChange(
            "bedwars.quest.set_progress",
            [
                "username"  => strtolower($username),
                "quest_id"  => $questId,
                "progress"  => json_encode($progress),
                "completed" => $completed ? 1 : 0,
            ]
        );
    }

    public function getQuestStateV2(string $username, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.quest_v2.get",
            ["username" => strtolower($username)],
            function(array $rows) use ($callback): void{
                if(empty($rows)){
                    $callback(null);
                    return;
                }
                $row = $rows[0];
                $state = json_decode((string) ($row["state"] ?? "{}"), true);
                $callback(is_array($state) ? $state : null);
            }
        );
    }

    public function setQuestStateV2(string $username, array $state): void{
        $this->db->executeChange(
            "bedwars.quest_v2.set",
            [
                "username" => strtolower($username),
                "state"    => json_encode($state),
                "updated"  => time(),
            ]
        );
    }

    public function getChallengesState(string $username, callable $callback): void{
        $this->getQuestStateV2($username, function(?array $state) use ($callback): void{
            $state = is_array($state) ? $state : [];
            $row = $state["lobby_challenges_v1"] ?? null;

            if(!is_array($row)){
                $row = [
                    "reset_stamp" => "",
                    "progress" => [],
                    "claimed" => [],
                    "last" => null
                ];
            }

            $callback($row, $state);
        });
    }

    public function setChallengesState(string $username, array $row, array $state): void{
        $state["lobby_challenges_v1"] = $row;
        $this->setQuestStateV2($username, $state);
    }

    /**
     * Race-safe read-modify-write for a player's challenges state. Prefer
     * this over getChallengesState()/setChallengesState() for anything that
     * mutates progress - see the $questStateQueue doc comment above for why.
     *
     * $mutator receives the current row (never null) and must return the
     * new row to persist. $onDone (optional) receives that same new row
     * once the write has actually completed on the DB thread.
     */
    public function mutateChallengesState(string $username, callable $mutator, ?callable $onDone = null): void{
        $this->mutateQuestStateKey($username, "lobby_challenges_v1", [
            "reset_stamp" => "",
            "progress" => [],
            "claimed" => [],
            "last" => null
        ], $mutator, $onDone);
    }

    public function getBattlePassState(string $username, callable $callback): void{
        $this->getQuestStateV2($username, function(?array $state) use ($callback): void{
            $state = is_array($state) ? $state : [];
            $row = $state["lobby_battlepass_v1"] ?? null;

            if(!is_array($row)){
                $row = [
                    "season_id" => "",
                    "xp" => 0,
                    "claimed_free" => [],
                    "claimed_premium" => [],
                    "premium_until" => null,
                    "last" => null
                ];
            }

            $callback($row, $state);
        });
    }

    public function setBattlePassState(string $username, array $row, array $state): void{
        $state["lobby_battlepass_v1"] = $row;
        $this->setQuestStateV2($username, $state);
    }

    /** Race-safe equivalent of setBattlePassState() - see mutateChallengesState(). */
    public function mutateBattlePassState(string $username, callable $mutator, ?callable $onDone = null): void{
        $this->mutateQuestStateKey($username, "lobby_battlepass_v1", [
            "season_id" => "",
            "xp" => 0,
            "claimed_free" => [],
            "claimed_premium" => [],
            "premium_until" => null,
            "last" => null
        ], $mutator, $onDone);
    }

    /** Per-player clan data that doesn't belong on the shared bw_clans/bw_clan_members rows: notification toggles, past-clan history, join cooldown. Stored under the existing quest_v2 blob, same mechanism as battlepass/challenges state above. */
    public function getClanPlayerState(string $username, callable $callback): void{
        $this->getQuestStateV2($username, function(?array $state) use ($callback): void{
            $state = is_array($state) ? $state : [];
            $row = $state["lobby_clan_v1"] ?? null;

            if(!is_array($row)){
                $row = [
                    "notifications" => [],
                    "history" => [],
                    "cooldown_until" => 0
                ];
            }

            $callback($row);
        });
    }

    /** Race-safe equivalent of a hand-rolled get+set - see mutateChallengesState(). */
    public function mutateClanPlayerState(string $username, callable $mutator, ?callable $onDone = null): void{
        $this->mutateQuestStateKey($username, "lobby_clan_v1", [
            "notifications" => [],
            "history" => [],
            "cooldown_until" => 0
        ], $mutator, $onDone);
    }

    /**
     * Core of the race-safe quest_v2 mutation: queues the whole
     * get -> mutate -> set sequence per player so that concurrent callers
     * (different features, or the same feature firing twice in a row) are
     * processed one at a time instead of racing on the same JSON blob.
     */
    private function mutateQuestStateKey(string $username, string $stateKey, array $default, callable $mutator, ?callable $onDone): void{
        $this->enqueueQuestStateOp($username, function(\Closure $release) use ($username, $stateKey, $default, $mutator, $onDone): void{
            $this->getQuestStateV2($username, function(?array $state) use ($username, $stateKey, $default, $mutator, $onDone, $release): void{
                $state = is_array($state) ? $state : [];
                $row = is_array($state[$stateKey] ?? null) ? $state[$stateKey] : $default;

                $row = $mutator($row);

                $state[$stateKey] = $row;

                $this->db->executeChange(
                    "bedwars.quest_v2.set",
                    [
                        "username" => strtolower($username),
                        "state"    => json_encode($state),
                        "updated"  => time(),
                    ],
                    function() use ($row, $onDone, $release): void{
                        // Only release the next queued mutation once this
                        // write has actually landed, otherwise it could read
                        // the pre-write blob and clobber this update anyway.
                        if($onDone !== null){
                            $onDone($row);
                        }
                        $release();
                    },
                    function() use ($release): void{
                        // Even on error, release the queue so a single
                        // failed write can't stall every future sync for
                        // this player.
                        $release();
                    }
                );
            });
        });
    }

    private function enqueueQuestStateOp(string $username, \Closure $op): void{
        $key = strtolower($username);
        $this->questStateQueue[$key][] = $op;
        $this->pumpQuestStateQueue($key);
    }

    private function pumpQuestStateQueue(string $key): void{
        if(($this->questStateBusy[$key] ?? false) === true){
            return;
        }

        $queue = $this->questStateQueue[$key] ?? [];
        if(empty($queue)){
            unset($this->questStateQueue[$key], $this->questStateBusy[$key]);
            return;
        }

        $op = array_shift($queue);
        $this->questStateQueue[$key] = $queue;
        $this->questStateBusy[$key] = true;

        $op(function() use ($key): void{
            $this->questStateBusy[$key] = false;
            $this->pumpQuestStateQueue($key);
        });
    }

    /**
     * Loads the combined reward-system state blob for a player: currently
     * ["playtime" => [...], "streak" => [...]], each written/owned by its
     * own manager. Stored in its own dedicated table (bw_reward_state)
     * rather than piggy-backing on the quest_v2 blob, so this feature can
     * never collide with or slow down quest/challenge/battlepass data.
     *
     * @param callable(array):void $callback always called with an array,
     *        empty if the player has no reward row yet (brand new player).
     */
    public function getRewardState(string $username, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.reward.get",
            ["username" => strtolower($username)],
            function(array $rows) use ($callback): void{
                if(empty($rows)){
                    $callback([]);
                    return;
                }
                $state = json_decode((string) ($rows[0]["state"] ?? "{}"), true);
                $callback(is_array($state) ? $state : []);
            }
        );
    }

    public function setRewardState(string $username, array $state): void{
        $this->db->executeChange(
            "bedwars.reward.set",
            [
                "username" => strtolower($username),
                "state"    => json_encode($state),
                "updated"  => time(),
            ]
        );
    }

    /**
     * Read-modify-write helper: merges only the given top-level keys (e.g.
     * just "playtime" or just "streak") into whatever is currently stored,
     * so the playtime manager flushing its counter can never clobber a
     * streak update (or vice versa) even if both happen to save around the
     * same moment.
     */
    public function mergeRewardState(string $username, array $partial): void{
        $this->getRewardState($username, function(array $current) use ($username, $partial): void{
            foreach($partial as $key => $value){
                $current[$key] = $value;
            }
            $this->setRewardState($username, $current);
        });
    }

    // ==========================================================================
    // Clan system — every method below is a thin wrapper around the
    // "bedwars.clan.*" queries in mysql.sql. All XP/bank mutations use atomic
    // "column = column + :amount" UPDATEs (not read-modify-write), so the
    // Lobby server and every Game server can safely credit the same clan
    // concurrently without a shared cache or a locking mechanism — the
    // database is the single source of truth, exactly like bw_players already
    // is for individual stats.
    // ==========================================================================

    public function createClan(array $data, ?callable $onSuccess = null, ?callable $onError = null): void{
        $this->db->executeChange(
            "bedwars.clan.insert",
            [
                "id"              => $data["id"],
                "name"            => $data["name"],
                "tag"             => $data["tag"],
                "color"           => $data["color"],
                "description"     => $data["description"],
                "crest_id"        => $data["crest_id"],
                "owner_username"  => strtolower($data["owner_username"]),
                "visibility"      => $data["visibility"],
                "required_level"  => $data["required_level"],
                "created_at"      => time(),
            ],
            $onSuccess,
            $onError
        );
    }

    public function getClanById(string $id, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.get_by_id", ["id" => $id], function(array $rows) use ($callback): void{
            $callback($rows[0] ?? null);
        });
    }

    public function getClanByTag(string $tag, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.get_by_tag", ["tag" => $tag], function(array $rows) use ($callback): void{
            $callback($rows[0] ?? null);
        });
    }

    public function getClanByName(string $name, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.get_by_name", ["name" => $name], function(array $rows) use ($callback): void{
            $callback($rows[0] ?? null);
        });
    }

    public function getAllClans(callable $callback): void{
        $this->db->executeSelect("bedwars.clan.get_all", [], $callback);
    }

    public function updateClanMeta(string $id, array $data, ?callable $onSuccess = null, ?callable $onError = null): void{
        $this->db->executeChange(
            "bedwars.clan.update_meta",
            [
                "id"              => $id,
                "name"            => $data["name"],
                "tag"             => $data["tag"],
                "color"           => $data["color"],
                "description"     => $data["description"],
                "crest_id"        => $data["crest_id"],
                "visibility"      => $data["visibility"],
                "required_level"  => $data["required_level"],
            ],
            $onSuccess,
            $onError
        );
    }

    public function updateClanOwner(string $id, string $ownerUsername): void{
        $this->db->executeChange("bedwars.clan.update_owner", [
            "id"             => $id,
            "owner_username" => strtolower($ownerUsername),
        ]);
    }

    public function addClanXp(string $id, int $amount): void{
        if($amount <= 0) return;
        $this->db->executeChange("bedwars.clan.add_xp", ["id" => $id, "amount" => $amount]);
    }

    public function addClanWeeklyCombat(string $id, int $winsDelta, int $finalKillsDelta): void{
        if($winsDelta === 0 && $finalKillsDelta === 0) return;
        $this->db->executeChange("bedwars.clan.add_weekly_combat", [
            "id"                => $id,
            "wins_delta"        => $winsDelta,
            "final_kills_delta" => $finalKillsDelta,
        ]);
    }

    public function setClanLevel(string $id, int $level): void{
        $this->db->executeChange("bedwars.clan.set_level", ["id" => $id, "level" => $level]);
    }

    public function addClanBank(string $id, int $amount, ?callable $onSuccess = null): void{
        $this->db->executeChange("bedwars.clan.add_bank", ["id" => $id, "amount" => $amount], $onSuccess);
    }

    /** @param callable(bool):void $callback true if the withdrawal succeeded (sufficient balance) */
    public function withdrawClanBank(string $id, int $amount, callable $callback): void{
        $this->db->executeChange("bedwars.clan.withdraw_bank", ["id" => $id, "amount" => $amount], function(int $affected) use ($callback): void{
            $callback($affected > 0);
        });
    }

    public function resetClanWeekly(string $id): void{
        $this->db->executeChange("bedwars.clan.reset_weekly", ["id" => $id]);
    }

    public function setClanLastWeek(string $id, int $score, int $rank, int $bestRank): void{
        $this->db->executeChange("bedwars.clan.set_last_week", [
            "id" => $id, "score" => $score, "rank" => $rank, "best_rank" => $bestRank,
        ]);
    }

    public function deleteClan(string $id): void{
        $this->db->executeChange("bedwars.clan.delete", ["id" => $id]);
    }

    public function upsertClanMember(string $username, string $clanId, string $role): void{
        $this->db->executeChange("bedwars.clan.member_insert", [
            "username"  => strtolower($username),
            "clan_id"   => $clanId,
            "role"      => $role,
            "joined_at" => time(),
        ]);
    }

    public function deleteClanMember(string $username): void{
        $this->db->executeChange("bedwars.clan.member_delete", ["username" => strtolower($username)]);
    }

    public function getClanMember(string $username, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.member_get_by_username", ["username" => strtolower($username)], function(array $rows) use ($callback): void{
            $callback($rows[0] ?? null);
        });
    }

    public function getClanRoster(string $clanId, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.member_get_roster", ["clan_id" => $clanId], $callback);
    }

    public function getAllClanMembers(callable $callback): void{
        $this->db->executeSelect("bedwars.clan.member_get_all", [], $callback);
    }

    public function setClanMemberRole(string $username, string $role): void{
        $this->db->executeChange("bedwars.clan.member_set_role", ["username" => strtolower($username), "role" => $role]);
    }

    public function createClanApplication(string $id, string $clanId, string $username, string $message, ?callable $onSuccess = null, ?callable $onError = null): void{
        // Used to be fire-and-forget: the GUI told the player "Application
        // sent!" the instant this was called, without ever knowing whether
        // the INSERT actually reached MySQL. Any hiccup (a slow/failed
        // write, a dropped connection) meant the player saw a success
        // message while the applications table - and therefore the clan's
        // "Applications" screen - stayed completely empty, with nothing in
        // the logs pointing at why. Waiting for executeChange()'s own
        // success/error callback closes that gap: the player is only told
        // it worked once the row is actually confirmed written.
        $this->db->executeChange("bedwars.clan.application_insert", [
            "id"         => $id,
            "clan_id"    => $clanId,
            "username"   => strtolower($username),
            "message"    => $message,
            "created_at" => time(),
        ], $onSuccess !== null ? fn(int $affectedRows) => $onSuccess() : null, $onError);
    }

    public function getPendingApplicationsForClan(string $clanId, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.application_get_pending_for_clan", ["clan_id" => $clanId], $callback);
    }

    public function getPendingApplicationForUser(string $clanId, string $username, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.clan.application_get_pending_for_user",
            ["clan_id" => $clanId, "username" => strtolower($username)],
            function(array $rows) use ($callback): void{
                $callback($rows[0] ?? null);
            }
        );
    }

    public function getClanApplicationById(string $id, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.application_get_by_id", ["id" => $id], function(array $rows) use ($callback): void{
            $callback($rows[0] ?? null);
        });
    }

    public function setClanApplicationStatus(string $id, string $status, string $reason = ""): void{
        $this->db->executeChange("bedwars.clan.application_set_status", ["id" => $id, "status" => $status, "reason" => $reason]);
    }

    public function createClanCreationRequest(array $data, ?callable $onSuccess = null): void{
        $this->db->executeChange("bedwars.clan.creation_request_insert", [
            "id"              => $data["id"],
            "username"        => strtolower($data["username"]),
            "name"            => $data["name"],
            "tag"             => $data["tag"],
            "color"           => $data["color"],
            "description"     => $data["description"],
            "crest_id"        => $data["crest_id"],
            "visibility"      => $data["visibility"],
            "required_level"  => $data["required_level"],
            "cost"            => $data["cost"],
            "created_at"      => time(),
        ], $onSuccess);
    }

    public function getAllPendingCreationRequests(callable $callback): void{
        $this->db->executeSelect("bedwars.clan.creation_request_get_all_pending", [], $callback);
    }

    public function getPendingCreationRequestForUser(string $username, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.clan.creation_request_get_pending_for_user",
            ["username" => strtolower($username)],
            function(array $rows) use ($callback): void{
                $callback($rows[0] ?? null);
            }
        );
    }

    public function getClanCreationRequestById(string $id, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.creation_request_get_by_id", ["id" => $id], function(array $rows) use ($callback): void{
            $callback($rows[0] ?? null);
        });
    }

    public function setClanCreationRequestStatus(string $id, string $status, string $reason = ""): void{
        $this->db->executeChange("bedwars.clan.creation_request_set_status", ["id" => $id, "status" => $status, "reason" => $reason]);
    }

    public function addClanBankLog(string $clanId, string $username, string $type, int $amount): void{
        $this->db->executeChange("bedwars.clan.bank_log_insert", [
            "id"         => uniqid("cbl_", true),
            "clan_id"    => $clanId,
            "username"   => strtolower($username),
            "type"       => $type,
            "amount"     => $amount,
            "created_at" => time(),
        ]);
    }

    public function getClanBankLogRecent(string $clanId, int $limit, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.bank_log_get_recent", ["clan_id" => $clanId, "limit" => $limit], $callback);
    }

    public function getClanDailyDepositTotal(string $clanId, string $username, int $since, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.clan.bank_log_get_daily_deposit_total",
            ["clan_id" => $clanId, "username" => strtolower($username), "since" => $since],
            function(array $rows) use ($callback): void{
                $callback((int) ($rows[0]["total"] ?? 0));
            }
        );
    }

    public function addClanLevelup(string $clanId, int $level): void{
        $this->db->executeChange("bedwars.clan.levelup_insert", [
            "id"          => uniqid("clv_", true),
            "clan_id"     => $clanId,
            "level"       => $level,
            "achieved_at" => time(),
        ]);
    }

    public function getClanLevelupsRecent(string $clanId, int $limit, callable $callback): void{
        $this->db->executeSelect("bedwars.clan.levelup_get_recent", ["clan_id" => $clanId, "limit" => $limit], $callback);
    }


    /** Raw, session-independent coin read — used only when the clan creation queue must be judged for an OFFLINE requester (op approval can happen while the player is offline). */
    public function getPlayerCoinsRaw(string $username, callable $callback): void{
        $this->db->executeSelect("bedwars.player.get_coins", ["username" => strtolower($username)], function(array $rows) use ($callback): void{
            $callback(isset($rows[0]) ? (int) $rows[0]["coins"] : null);
        });
    }

    /** Atomic, race-safe coin deduction for an offline player. @param callable(bool):void $callback true if the balance was sufficient and the deduction happened. */
    public function deductPlayerCoinsRaw(string $username, int $amount, callable $callback): void{
        $this->db->executeChange("bedwars.player.deduct_coins", ["username" => strtolower($username), "amount" => $amount], function(int $affected) use ($callback): void{
            $callback($affected > 0);
        });
    }

    // ==========================================================================
    // Report system
    //
    // Every filed report is persisted here regardless of which server it was
    // filed from (Game or Lobby both share this table through the same
    // AsyncMysqlProvider pattern used by the clan system above), so a report
    // filed mid-match is never lost even if the match's world/game instance
    // is gone by the time staff review it.
    // ==========================================================================

    public function insertReport(array $data, ?callable $onSuccess = null, ?callable $onError = null): void{
        $this->db->executeChange(
            "bedwars.report.insert",
            [
                "id"          => $data["id"],
                "reporter"    => strtolower($data["reporter"]),
                "reported"    => strtolower($data["reported"]),
                "reason_id"   => $data["reason_id"],
                "description" => $data["description"],
                "server_name" => $data["server_name"],
                "game_id"     => $data["game_id"],
                "created_at"  => time(),
            ],
            $onSuccess,
            $onError
        );
    }

    /** @param callable(array):void $callback */
    public function getRecentReports(int $limit, callable $callback): void{
        $this->db->executeSelect("bedwars.report.get_recent", ["limit" => $limit], $callback);
    }

    /** @param callable(array):void $callback */
    public function getRecentReportsAgainst(string $reported, int $limit, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.report.get_recent_against",
            ["reported" => strtolower($reported), "limit" => $limit],
            $callback
        );
    }

    /** @param callable(int):void $callback */
    public function countReportsAgainstSince(string $reported, int $since, callable $callback): void{
        $this->db->executeSelect(
            "bedwars.report.count_against_since",
            ["reported" => strtolower($reported), "since" => $since],
            function(array $rows) use ($callback): void{
                $callback(isset($rows[0]) ? (int) $rows[0]["total"] : 0);
            }
        );
    }

    public function close(): void{
        if(isset($this->db)){
            $this->db->close();
        }
    }
}