<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\session\Session;
use Throwable;
use function count;
use function max;
use function microtime;
use function spl_object_id;
use function strtolower;
use function time;

/**
 * The only door between the running game and the profile module. Every entry point swallows its own errors:
 * a problem in medals / ranked points must never be able to break a match.
 */
final class ProfileHooks{

    /** @var array<int, MatchContext> spl_object_id(Game) => context */
    private static array $contexts = [];

    private static function service() : ?ProfileService{
        try{
            return BedWarsCore::getInstance()->getProfileService();
        }catch(Throwable){
            return null;
        }
    }

    private static function fail(string $where, Throwable $e) : void{
        try{
            BedWarsCore::getInstance()->getLogger()->warning("[Profile] " . $where . " failed: " . $e->getMessage());
        }catch(Throwable){
        }
    }

    private static function participant(Session $s) : ?array{
        $game = $s->getGame();
        if($game === null){
            return null;
        }
        $ctx = self::$contexts[spl_object_id($game)] ?? null;
        if($ctx === null){
            return null;
        }
        $key = strtolower($s->getUsername());
        $p = $ctx->players[$key] ?? null;
        if($p === null){
            return null;
        }
        $p->session = $s; // a reconnected player has a fresh session object
        if($p->team === null && $s->getTeam() !== null){
            $p->team = $s->getTeam();
            $p->teamName = $p->team->getName();
        }
        return [$ctx, $p];
    }

    // ------------------------------------------------------------------ lifecycle

    /** Called at the very end of PlayingStage::onStart(), when every player already has a team. */
    public static function start(Game $game) : void{
        try{
            $ctx = new MatchContext(time(), $game->getModeName(), $game->getMap()->getName());
            foreach($game->getPlayers() as $s){
                $p = new Participant($s);
                $ctx->players[$p->key] = $p;
            }
            self::$contexts[spl_object_id($game)] = $ctx;
        }catch(Throwable $e){
            self::fail("start", $e);
        }
    }

    /** Called when a game is reset - drops the context if the match never reached the ending stage. */
    public static function reset(Game $game) : void{
        unset(self::$contexts[spl_object_id($game)]);
    }

    // ------------------------------------------------------------------ live events

    public static function frag(Session $s, bool $final) : void{
        try{
            $found = self::participant($s);
            $service = self::service();
            if($found === null || $service === null){
                return;
            }
            [$ctx, $p] = $found;
            if($final){
                $p->finals++;
            }else{
                $p->kills++;
            }

            $cfg = $service->getConfig();
            $now = microtime(true);
            $window = max(1, $cfg->int("medals.multi-kill-window", 10));
            $p->stamps[] = $now;
            $recent = [];
            foreach($p->stamps as $t){
                if($now - $t <= $window){
                    $recent[] = $t;
                }
            }
            $p->stamps = $recent;

            if(!$ctx->firstBlood){
                $ctx->firstBlood = true;
                self::award($p, "first_blood");
            }
            switch(count($recent)){
                case 2: self::award($p, "double_kill"); break;
                case 3: self::award($p, "triple_kill"); break;
                case 4: self::award($p, "fury_kill"); break;
                case 5: self::award($p, "rampage"); break;
            }
            if($p->kills + $p->finals === max(1, $cfg->int("medals.slayer-kills", 8))){
                self::award($p, "slayer");
            }
            if($final){
                if($p->finals === 1){
                    self::award($p, "final_blow");
                }
                if($p->finals === 3){
                    self::award($p, "executioner");
                }
            }
        }catch(Throwable $e){
            self::fail("frag", $e);
        }
    }

    public static function bed(Session $s) : void{
        try{
            $found = self::participant($s);
            if($found === null){
                return;
            }
            [$ctx, $p] = $found;
            $p->beds++;
            if(!$ctx->firstBed){
                $ctx->firstBed = true;
                self::award($p, "trailblazer");
            }
            match($p->beds){
                1 => self::award($p, "bed_breaker"),
                2 => self::award($p, "bed_wrecker"),
                3 => self::award($p, "bed_hunter"),
                default => null,
            };
        }catch(Throwable $e){
            self::fail("bed", $e);
        }
    }

    public static function death(Session $s) : void{
        try{
            $found = self::participant($s);
            if($found === null){
                return;
            }
            [, $p] = $found;
            $p->deaths++;
            $team = $s->getTeam();
            if($team !== null && $team->isBedDestroyed()){
                $p->eliminated = true;
            }
        }catch(Throwable $e){
            self::fail("death", $e);
        }
    }

    private static function award(Participant $p, string $id) : void{
        $p->medals[$id] = ($p->medals[$id] ?? 0) + 1;
        $service = self::service();
        if($service === null || !$service->getConfig()->bool("medals.announce", true) || !isset(ProfileCatalog::MEDALS[$id])){
            return;
        }
        try{
            $pl = $p->session->getPlayer();
            if($pl->isConnected()){
                $p->session->message("§d§lMEDAL §r§8| §f" . ProfileCatalog::MEDALS[$id]["name"] . " §7- " . ProfileCatalog::MEDALS[$id]["desc"]);
                $p->session->playSound("note.pling");
            }
        }catch(Throwable){
        }
    }

    // ------------------------------------------------------------------ match end

    /** Called from EndingStage::onStart(), after wins / win streaks were handed out. */
    public static function finish(Game $game, ?Team $winner, bool $tie) : void{
        $id = spl_object_id($game);
        $ctx = self::$contexts[$id] ?? null;
        unset(self::$contexts[$id]);
        $service = self::service();
        if($ctx === null || $service === null){
            return;
        }
        try{
            $cfg = $service->getConfig();
            $duration = max(0, time() - $ctx->startedAt);
            $winName = (!$tie && $winner !== null) ? $winner->getName() : null;

            /** @var array<string, Session> $present */
            $present = [];
            foreach($game->getPlayersAndSpectators() as $s){
                $present[strtolower($s->getUsername())] = $s;
            }

            // decide result + winners first (MVP needs the whole winning team)
            $results = [];
            $winners = [];
            foreach($ctx->players as $key => $p){
                $isPresent = isset($present[$key]);
                if($winName !== null && $p->teamName === $winName && ($isPresent || $p->eliminated)){
                    $results[$key] = "WIN";
                    $winners[$key] = $p;
                }elseif($tie){
                    $results[$key] = "TIE";
                }elseif(!$isPresent && !$p->eliminated){
                    $results[$key] = "LEFT";
                }else{
                    $results[$key] = "LOSS";
                }
            }

            $mvpKey = null;
            $best = max(1, $cfg->int("ranked.mvp-min-points", 8)) - 1;
            foreach($winners as $key => $p){
                if($p->points() > $best){
                    $best = $p->points();
                    $mvpKey = $key;
                }
            }

            $marathon = $cfg->int("medals.marathon-seconds", 1200);
            $blitz = $cfg->int("medals.blitz-seconds", 600);

            foreach($ctx->players as $key => $p){
                $result = $results[$key];
                $won = $result === "WIN";
                $mvp = $mvpKey === $key;
                $intact = $p->team !== null && !$p->team->isBedDestroyed();
                $medals = $p->medals;
                $add = static function(string $mid) use (&$medals) : void{
                    $medals[$mid] = ($medals[$mid] ?? 0) + 1;
                };

                if($won){
                    $add("victor");
                    if($intact){
                        $add("flawless");
                    }
                    if($p->deaths === 0){
                        $add("untouchable");
                    }
                    if($p->team !== null && $p->team->isBedDestroyed()){
                        $add("comeback");
                    }
                    $streak = $p->session->getWinStreak();
                    if($streak === 3){
                        $add("hot_streak");
                    }
                    if($streak === 5){
                        $add("unstoppable");
                    }
                    if($p->kills + $p->finals + $p->beds >= 10){
                        $add("dominator");
                    }
                    if($p->team !== null && count($p->team->getRoster()) >= 2 && $p->team->getMembersCount() === 1 && $p->team->hasMember($p->session)){
                        $add("clutch");
                    }
                    if($mvp){
                        $add("match_mvp");
                    }
                    if($duration < $blitz){
                        $add("blitz");
                    }
                    if($mvp && $intact && $p->deaths === 0){
                        $add("perfectionist");
                    }
                }
                if($result !== "LEFT" && $duration >= $marathon){
                    $add("marathon");
                }

                $session = $present[$key] ?? null;
                if($session === null && $p->session->getPlayer()->isConnected()){
                    $session = $p->session;
                }
                $service->recordMatch(new MatchResult(
                    $p->key, $p->name, $ctx->mode, $ctx->map, $result,
                    $p->kills, $p->finals, $p->beds, $p->deaths, $duration, $mvp, $medals
                ), $session);
            }
        }catch(Throwable $e){
            self::fail("finish", $e);
        }
    }
}
