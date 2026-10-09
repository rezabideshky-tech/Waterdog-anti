<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\stage;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\player\GameMode;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\cosmetics\api\CosmeticsAPI;
use sergittos\bedwars\game\BedWarsGame;
use sergittos\bedwars\game\cinematic\podium\PodiumBuilder;
use sergittos\bedwars\game\cinematic\podium\PodiumCeremony;
use sergittos\bedwars\game\cinematic\podium\PodiumSpot;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\session\Session;
use function array_filter;
use function array_merge;
use function array_slice;
use function array_values;
use function count;
use function date;
use function implode;
use function microtime;
use function round;
use function spl_object_id;
use function usort;

class EndingStage extends Stage{

    /**
     * How many ticks to wait after clearing everyone's cinematic camera
     * before actually resetting the game (which teleports/transfers
     * players to the hub). Even with an immediate/unbuffered send (see
     * CinematicCamera::clear()), giving the client a full extra tick to
     * receive and apply the clear instruction before it also has to
     * process a teleport/transfer is a cheap, safe margin against the
     * camera staying locked into the detached cinematic view once
     * players land back in the lobby.
     */
    private const RESET_DELAY_TICKS = 4;

    /** Countdown used only for ties (no Podium Ceremony to size the timer around). */
    private const TIE_TIME_SECONDS = 15;

    /**
     * Horizontal radius (blocks, X/Z plane only - height is ignored so a
     * whole bridge/tower gets cleared, not just the layer level with the
     * reference point) around the last-bed-break reference point that
     * clearPlacedBlocksNear() clears before the podium goes up. Comfortably
     * covers the widest podium footprint (a Squads win's gold/iron/clay
     * columns span up to 4 blocks either side of center and up to ~5
     * blocks in front of the reference point - see PodiumBuilder) plus a
     * margin, without reaching so far it starts eating into a team's whole
     * base build.
     */
    private const PODIUM_BLOCK_CLEAR_RADIUS = 10.0;

    private int $time = self::TIE_TIME_SECONDS;
    private bool $tie = false;
    private bool $resetScheduled = false;
    private bool $resetRetried = false;
    /** Set once Game::reset() has returned without throwing (see scheduleReset()). */
    private bool $resetFinished = false;
    private ?Team $winnerTeam = null;

    /**
     * Everyone who counts as a winner of this match, keyed by
     * spl_object_id(session): the winning team's current members PLUS -
     * unless "podium-include-eliminated-teammates" is turned off in
     * config.yml - teammates who were eliminated earlier in the match but
     * are still online and still in this game (now spectating). Before
     * this existed only Team::getMembers() was used, and that list loses
     * every player the moment they get final-killed, so a Triples win
     * where two teammates had died earlier ended up with a single
     * pedestal (and only that one player getting the win) instead of
     * three.
     *
     * @var array<int, Session>
     */
    private array $winners = [];

    /**
     * The Podium Ceremony running for this match's winner(s), or null on
     * a tie / if there was nobody left online on the winning team to
     * actually put on a podium. One instance belongs to a single
     * EndingStage, so it (and every task/state it owns) naturally goes
     * away with this stage once the arena resets for the next match.
     */
    private ?PodiumCeremony $podiumCeremony = null;

    public function getTime(): int{
        return $this->time;
    }

    public function isTie(): bool{
        return $this->tie;
    }

    public function getWinnerTeam(): ?Team{
        return $this->winnerTeam;
    }

    protected function onStart(): void{
        $this->game->purgeInvalidSessions();

        $alive = $this->game->getAliveTeams();
        $this->tie = count($alive) !== 1;
        $this->winnerTeam = $this->tie ? null : ($alive[0] ?? null);

        $this->winners = [];
        if(!$this->tie && $this->winnerTeam !== null){
            try{
                $this->winners = $this->collectWinners($this->winnerTeam);
            }catch(\Throwable $e){
                BedWarsGame::getInstance()->getLogger()->warning("[EndingStage] failed to collect winners: " . $e->getMessage());
                foreach($this->winnerTeam->getMembers() as $member){
                    $this->winners[spl_object_id($member)] = $member;
                }
            }
        }

        if(!$this->tie && $this->winnerTeam !== null){
            // Wrapped here too (not just around the individual risky calls
            // inside preparePodiumCeremony()) - this used to be an
            // unprotected direct call, so ANY unexpected failure while
            // building the ceremony (a bad block name, a missing class
            // from an incomplete deploy, anything) threw straight out of
            // onStart() and silently skipped EVERYTHING after it: reward
            // distribution, promoting losers to spectator, even starting
            // the ceremony itself - while EndingStage::tick() kept the
            // countdown/scoreboard ticking along completely normally
            // (it doesn't depend on onStart() finishing), which is
            // exactly what made the bug look like "the ceremony just
            // never showed up" with everything else looking fine.
            try{
                $this->preparePodiumCeremony($this->winnerTeam);
            }catch(\Throwable $e){
                BedWarsGame::getInstance()->getLogger()->warning(
                    "Podium ceremony failed to prepare (match ending will continue without it): " . $e->getMessage()
                );
                BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
                $this->podiumCeremony = null;
            }
        }

        // deferUpdates=true: handleSessionEnding() promotes every losing
        // player to spectator, and each promotion would otherwise rebuild
        // every player's scoreboard AND rescan the whole hub world's
        // entities on its own (see Game::promoteToSpectator()'s doc-
        // comment) - for a full lobby that repeats the same expensive
        // O(n) + O(hub entities) work once per player instead of once
        // total. Refreshed a single time below instead.
        //
        // Wrapped per-session (not just around broadcastTopPlayers() below) -
        // this used to be an unprotected call, so if handleSessionEnding()
        // threw for ANY single player (a stale session, a mid-teleport
        // disconnect, anything) it stopped the foreach dead and everything
        // scheduled after the loop - including broadcastTopPlayers() - never
        // ran at all. That silently killed the "TOP PLAYERS" message for
        // EVERYONE even though only one player's handling actually failed,
        // which matches a report of "the top players message just stopped
        // sending". Logged at warning level so it shows up in the console
        // by default if it happens again.
        foreach($this->game->getPlayersAndSpectators() as $session){
            try{
                $this->handleSessionEnding($session, $this->winnerTeam, true);
            }catch(\Throwable $e){
                BedWarsGame::getInstance()->getLogger()->warning(
                    "[EndingStage] handleSessionEnding failed for " . $session->getUsername() . ": " . $e->getMessage()
                );
                BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
            }
        }

        // ranked points / history / medals / achievements (never throws - see ProfileHooks)
        \sergittos\bedwars\profile\ProfileHooks::finish($this->game, $this->winnerTeam, $this->tie);

        try{
            $this->broadcastTopPlayers();
        }catch(\Throwable $e){
            BedWarsGame::getInstance()->getLogger()->warning("[EndingStage] broadcastTopPlayers failed: " . $e->getMessage());
            BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
        }

        $this->startPodiumCeremony();
        $this->game->updateScoreboards();
        $this->game->updatePlayEntities();
    }

    /**
     * Builds the winner list for this match (see $winners).
     *
     * Current team members are always included - exactly the same set the
     * old code used, offline-but-still-in-rejoin-window members included,
     * so rewards behave exactly as before for them. On top of that, when
     * enabled, any roster member who was eliminated earlier but is still
     * connected and still inside THIS game (i.e. spectating, not someone
     * who left to the lobby or disconnected) is added too.
     *
     * @return array<int, Session>
     */
    private function collectWinners(Team $team): array{
        $winners = [];
        foreach($team->getMembers() as $member){
            $winners[spl_object_id($member)] = $member;
        }

        $includeEliminated = (bool) BedWarsGame::getInstance()->getConfig()->get("podium-include-eliminated-teammates", true);
        if(!$includeEliminated){
            return $winners;
        }

        foreach($team->getRoster() as $member){
            $id = spl_object_id($member);
            if(isset($winners[$id])){
                continue;
            }
            if($member->getGame() !== $this->game){
                continue;
            }
            if(!$member->getPlayer()->isConnected()){
                continue;
            }
            if(!$this->game->isSpectator($member)){
                continue;
            }
            $winners[$id] = $member;
        }

        return $winners;
    }

    /**
     * Ranks the winning team's online members by how they actually
     * performed this match (kills, final kills, beds broken - the exact
     * same weighting as the "TOP PLAYERS" broadcast, so the podium and
     * the chat board never disagree), takes the top 3, builds the
     * physical podium structure next to where the last bed broke, and
     * wraps it all up into a PodiumCeremony ready for startPodiumCeremony()
     * to kick off.
     *
     * Fully mode-agnostic: a Solo team only ever has 1 member so this
     * naturally produces a single MVP-only spot, Doubles produces up to
     * 2, and Triples/Squads produce up to 3 (any 4th Squads member simply
     * isn't ranked onto the podium, same as a real medal ceremony).
     */
    private function preparePodiumCeremony(Team $winnerTeam): void{
        $world = $this->game->getWorld();
        if($world === null){
            return;
        }

        $ranked = [];
        foreach($this->winners as $member){
            if(!$member->getPlayer()->isConnected()){
                continue;
            }

            $kills = $member->getGameKills();
            $finalKills = $member->getGameFinalKills();
            $bedsBroken = $member->getGameBedsBroken();
            $points = ($kills * 2) + ($finalKills * 4) + ($bedsBroken * 6);

            $ranked[] = [$member, $points, $kills, $finalKills, $bedsBroken];
        }

        if($ranked === []){
            // Everyone on the winning team disconnected before the
            // ceremony could start - nothing to put on a podium.
            return;
        }

        usort($ranked, static function(array $a, array $b): int{
            return ($b[1] <=> $a[1])
                ?: ($b[3] <=> $a[3])
                ?: ($b[2] <=> $a[2])
                ?: ($b[4] <=> $a[4]);
        });

        $top = array_slice($ranked, 0, 3);

        $ranksPresent = [];
        foreach($top as $index => $_){
            $ranksPresent[] = $index + 1;
        }

        $reference = $this->game->getLastBedBreakPosition() ?? $winnerTeam->getBedPosition();

        // Which way is actually "in front of" that bed. Every team's own
        // spawn point sits behind their bed, facing out into the arena -
        // so spawn->bed already is that team's outward-facing direction,
        // without needing any new per-map/per-bed facing data. Falls back
        // to the winning team's own spawn->bed if the last-broken bed's
        // team is unknown (e.g. an admin-forced win with no real bed
        // break), and to a fixed direction only if even that degenerates
        // (spawn and bed on the exact same spot) - see PodiumBuilder.
        $referenceTeam = $this->game->getLastBedBreakTeam() ?? $winnerTeam;
        $forwardDirection = $referenceTeam->getBedPosition()->subtractVector($referenceTeam->getSpawnPoint());

        // Clear player-placed clutter (bridges, towers, defensive blocks,
        // whatever) from around where the podium is about to go up, so the
        // ceremony doesn't play out next to a messy build - only ever
        // touches blocks a player actually placed this match (see
        // Game::clearPlacedBlocksNear()), never map terrain. This has to
        // run *before* PodiumBuilder::build() below, not after: the podium
        // columns are placed with a plain setBlock() overwrite, so if a
        // player had built on the exact spot a column goes, clearing
        // afterwards would blow away the freshly-placed podium block right
        // along with it. A failure here should never cancel the ceremony
        // itself - worst case the area just doesn't get tidied up.
        try{
            $this->game->clearPlacedBlocksNear($reference, self::PODIUM_BLOCK_CLEAR_RADIUS);
        }catch(\Throwable $e){
            BedWarsGame::getInstance()->getLogger()->warning("Podium ceremony failed to clear player-placed blocks: " . $e->getMessage());
        }

        try{
            $standPositions = PodiumBuilder::build($world, $reference, $ranksPresent, $forwardDirection);
        }catch(\Throwable $e){
            BedWarsGame::getInstance()->getLogger()->warning("Podium ceremony failed to build the podium: " . $e->getMessage());
            BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
            return;
        }

        $spots = [];
        foreach($top as $index => $entry){
            [$member, $points, $kills, $finalKills, $bedsBroken] = $entry;
            $rank = $index + 1;
            if(!isset($standPositions[$rank])){
                continue;
            }
            $spots[$rank] = new PodiumSpot($rank, $member, $points, $kills, $finalKills, $bedsBroken, $standPositions[$rank]);
        }

        if($spots === []){
            return;
        }

        $this->podiumCeremony = new PodiumCeremony(BedWarsGame::getInstance(), $this->game, $world, $spots);

        // Give the ending countdown as much time as the ceremony actually
        // needs instead of letting the arena reset out from under it
        // mid-celebration for the bigger team sizes.
        $this->time = max($this->time, $this->podiumCeremony->getMinimumSeconds());
    }

    /**
     * Defensive wrapper - a bug in the ceremony code should never be able
     * to break the BedWars ending stage itself.
     */
    private function startPodiumCeremony(): void{
        if($this->podiumCeremony === null){
            return;
        }

        try{
            $this->podiumCeremony->start();
        }catch(\Throwable $e){
            BedWarsGame::getInstance()->getLogger()->warning("Podium ceremony failed to start: " . $e->getMessage());
            BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
        }
    }

    private function stopPodiumCeremony(): void{
        if($this->podiumCeremony === null){
            return;
        }

        try{
            $this->podiumCeremony->stop();
        }catch(\Throwable $e){
            BedWarsGame::getInstance()->getLogger()->warning("Podium ceremony failed to stop: " . $e->getMessage());
            BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
        }
    }

    /** Dashed divider width for the TOP PLAYERS board - see broadcastTopPlayers(). */
    private const BOARD_DIVIDER = "{GRAY}------------------------------";

    /**
     * End-of-match "TOP PLAYERS" board. Sent as one multi-line block per
     * viewer (instead of one broadcastMessage() call per line, like the
     * old version did) so the whole thing always renders as a single tight
     * block in chat with no risk of another plugin's message landing in
     * the middle of it, and so every viewer's own personal result line at
     * the bottom can be personalized to them without rebuilding the top-3
     * portion per player.
     *
     * Layout is a plain "#rank name - points" list between two dashed
     * dividers, deliberately kept to that same simple shape - this only
     * changes the presentation, never the ranking: points/kills/final
     * kills/beds are still tracked and formula is intentionally identical
     * to preparePodiumCeremony()'s so this board and the Podium Ceremony
     * always agree on who ranked where.
     */
    private function broadcastTopPlayers(): void{
        $scored = [];
        // Keyed by spl_object_id(session) so the per-viewer loop below can
        // look up "my own entry" and "am I top 3" in O(1) instead of
        // re-scanning $scored/$top with a nested foreach per viewer.
        $byId = [];

        foreach($this->game->getPlayersAndSpectators() as $session){
            $kills = $session->getGameKills();
            $finalKills = $session->getGameFinalKills();
            $bedsBroken = $session->getGameBedsBroken();

            $points = ($kills * 2) + ($finalKills * 4) + ($bedsBroken * 6);

            $entry = [
                "session" => $session,
                "points" => $points,
                "kills" => $kills,
                "finalKills" => $finalKills,
                "bedsBroken" => $bedsBroken
            ];

            $scored[] = $entry;
            $byId[spl_object_id($session)] = $entry;
        }

        if($scored === []){
            // No one left to send anything to (purgeInvalidSessions() ran
            // right before this in onStart()) - genuinely nothing to do.
            return;
        }

        $ranked = array_values(array_filter($scored, static fn(array $e): bool => $e["points"] > 0));

        // Previously this returned here whenever every single player scored
        // 0 points (nobody got a kill/final kill/bed this match), which
        // meant NOBODY got any end-of-match message at all - not even their
        // own personal result line - even though the match ended normally.
        // Now $top just ends up empty in that case: the header still goes
        // out, the rank loop below simply contributes no lines, and every
        // viewer still gets their personal "0 pts" line.
        usort($ranked, static fn(array $a, array $b) => $b["points"] <=> $a["points"]);
        $top = array_slice($ranked, 0, 3);

        $topIds = [];
        foreach($top as $entry){
            $topIds[spl_object_id($entry["session"])] = true;
        }

        $lines = [];
        $lines[] = self::BOARD_DIVIDER;
        $lines[] = "{GOLD}>>    {YELLOW}{BOLD}TOP PLAYERS{RESET}    {GOLD}<<";
        $lines[] = self::BOARD_DIVIDER;
        $lines[] = "";

        foreach($top as $index => $entry){
            /** @var Session $s */
            $s = $entry["session"];
            $rank = $index + 1;

            $lines[] = "{WHITE}#{GOLD}{$rank} {GREEN}{$s->getUsername()} {GRAY}- {WHITE}{$entry["points"]}";
        }

        $lines[] = "";
        $lines[] = self::BOARD_DIVIDER;

        $header = implode("\n", $lines);

        foreach($this->game->getPlayersAndSpectators() as $session){
            $entry = $byId[spl_object_id($session)] ?? ["points" => 0];
            $isTop3 = isset($topIds[spl_object_id($session)]);

            $personal = $isTop3
                ? "{AQUA}YOUR SCORE {WHITE}: {GREEN}{$entry["points"]} {GRAY}(Top Players!)"
                : "{AQUA}YOUR SCORE {WHITE}: {GREEN}{$entry["points"]}";

            $session->message($header . "\n\n" . $personal);
        }
    }

    public function onJoin(Session $session): void{
        $this->handleSessionEnding($session, $this->winnerTeam);
        $this->game->updateScoreboards();
    }

    private function handleSessionEnding(Session $session, ?Team $winnerTeam, bool $deferUpdates = false): void{
        $player = $session->getPlayer();
        if(!$player->isConnected()){
            return;
        }

        $viewers = [];
        foreach($this->game->getPlayersAndSpectators() as $s){
            $p = $s->getPlayer();
            if($p->isConnected()){
                $viewers[] = $p;
            }
        }

        $isWinner = !$this->tie && $winnerTeam !== null
            && ($winnerTeam->hasMember($session) || isset($this->winners[spl_object_id($session)]));

        if(!$this->tie && $winnerTeam !== null){
            if(!$winnerTeam->hasDistributedRewards()){
                $winnerTeam->setDistributedRewards(true);

                // Current members first (unchanged behaviour), then the
                // eliminated-but-still-here teammates from collectWinners().
                // setReceivedVictoryRewards(true) below de-duplicates the
                // two lists.
                foreach(array_merge($winnerTeam->getMembers(), array_values($this->winners)) as $member){
                    if($member->hasReceivedVictoryRewards()){
                        continue;
                    }

                    $member->addWin();
                    $member->addXp(100, "Winner");
                    $member->addCoins(140);
                    $member->registerWinStreak();
                    $member->setReceivedVictoryRewards(true);
                    $member->save();

                    $mp = $member->getPlayer();
                    if($mp->isConnected()){
                        CosmeticsAPI::triggerWinEffect($mp, $mp->getPosition()->asVector3(), $viewers);
                    }
                }
            }

            if(!$session->hasReceivedVictoryRewards()){
                $session->resetWinStreak();
            }
        }

        try{ $player->getEffects()->clear(); }catch(\Throwable){}
        $session->setTrackingSession(null);

        if($isWinner){
            // Winners stay fully visible and in their normal gamemode
            // instead of being turned into an invisible noclip spectator
            // - that would hide them from the Podium Ceremony camera and
            // from their own Victory Dance. They're just locked in place
            // (see onMove freeze handling) until the countdown finishes.
            // Their roster entry doesn't change (still in $this->players),
            // so there's nothing to resync there.
            //
            // Their combat inventory (sword, blocks, tools, armor, etc.)
            // is still cleared here though - unlike the gamemode/
            // visibility, there's no reason for the leftover game items
            // to still be sitting in their hotbar during the victory
            // celebration, and leaving that clear out was letting those
            // items linger even after the match fully ends.
            $session->clearCommonInventories();
        }else{
            $this->game->promoteToSpectator($session, $deferUpdates);
        }

        if($this->tie){
            $session->title("{ORANGE}TIE!", "", 0, $this->time * 20);
        }else{
            $player->sendTitle("", "", 0, 1, 0);
            $this->applyVillageHeroEffect($session);
        }

        $session->resetSettings();

        if($isWinner){
            // Undo whatever real-Spectator/invisible state kill() may have
            // left this winner in - covers the case where they died in the
            // very same exchange that ended the match (e.g. both the last
            // enemy and the winner knocked into the void together on a
            // bridge). Without this, that winner's skin never renders on
            // their own podium - see Session::restoreForVictoryCeremony().
            $session->restoreForVictoryCeremony();

            // If this winner placed top-3 on their team, stand them on
            // their podium pedestal *before* freezing them - freeze()
            // locks in whatever position the player is at right at the
            // moment it's called, so the teleport has to happen first or
            // they'd just get frozen wherever they were standing when the
            // bed broke instead of on the podium.
            $standPosition = $this->podiumCeremony?->getStandPositionFor($session);
            if($standPosition instanceof Vector3 && $player->isConnected()){
                try{
                    $player->teleport($standPosition);
                }catch(\Throwable $e){
                    BedWarsGame::getInstance()->getLogger()->warning("Podium ceremony failed to place a winner on their pedestal: " . $e->getMessage());
                }
            }

            $session->freeze();
        }
    }

    private function applyVillageHeroEffect(Session $session): void{
        $player = $session->getPlayer();
        if(!$player->isConnected()){
            return;
        }

        $ticks = $this->time * 20;

        $parser = StringToEffectParser::getInstance();
        $type = $parser->parse("village_hero_effect")
            ?? $parser->parse("village_hero");

        if($type !== null){
            $player->getEffects()->add(new EffectInstance($type, $ticks, 0, false));
            return;
        }

        $pk = new MobEffectPacket();
        $pk->actorRuntimeId = $player->getId();
        $pk->eventId = defined(MobEffectPacket::class . "::EVENT_ADD") ? MobEffectPacket::EVENT_ADD : 1;
        $pk->effectId = 29;
        $pk->amplifier = 0;
        $pk->particles = false;
        $pk->duration = $ticks;
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    public function onQuit(Session $session): void{
        $this->game->despawnGeneratorsFrom($session);
        $this->game->purgeInvalidSessions();
    }

    public function tick(): void{
        $this->time--;
        $this->game->purgeInvalidSessions();
        $this->game->updateScoreboards();

        if($this->time <= 0 && !$this->resetScheduled){
            $this->resetScheduled = true;
            $this->stopPodiumCeremony();
            $this->scheduleReset();
            return;
        }

        // Safety net: if the scheduled reset() somehow did not take the game
        // out of this stage (it would keep ticking here with an ever more
        // negative countdown - "Returning in -26s"), try once more instead
        // of leaving every player stuck on the podium forever. reset() is
        // now exception-safe step by step, so this should never actually be
        // needed, but a stuck arena is much worse than one extra attempt.
        if($this->resetScheduled && !$this->resetFinished && !$this->resetRetried && $this->time <= -5){
            $this->resetRetried = true;
            BedWarsGame::getInstance()->getLogger()->warning(
                "[EndingStage] game #" . $this->game->getId() . " is still in EndingStage " . (-$this->time) . "s after its reset was scheduled - retrying reset()"
            );
            $this->scheduleReset();
        }
    }

    /**
     * Don't reset (and transfer/teleport everyone to the hub) in this exact
     * same tick - see RESET_DELAY_TICKS and the note on
     * CinematicCamera::clear() for why. This is the fix for players landing
     * back in the lobby with their camera still locked into the cinematic
     * view.
     */
    private function scheduleReset(): void{
        $debug = (bool) BedWarsGame::getInstance()->getConfig()->get("debug-match-end", false);
        $gameId = $this->game->getId();
        $game = $this->game;

        BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($game, $debug, $gameId) : void{
                if($debug){
                    BedWarsGame::getInstance()->getLogger()->debug(
                        "[EndingStage] " . date("H:i:s") . " game #$gameId reset() starting"
                    );
                }
                $started = microtime(true);
                try{
                    $game->reset();
                }catch(\Throwable $e){
                    // reset() runs inside a delayed ClosureTask, so an
                    // uncaught exception here would otherwise only show
                    // up as a generic scheduler error with no game
                    // context - logged explicitly instead.
                    BedWarsGame::getInstance()->getLogger()->warning(
                        "[EndingStage] game #$gameId reset() threw: " . $e->getMessage()
                    );
                    BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
                    return;
                }
                $this->resetFinished = true;
                if($debug){
                    $ms = (int) round((microtime(true) - $started) * 1000);
                    BedWarsGame::getInstance()->getLogger()->debug(
                        "[EndingStage] " . date("H:i:s") . " game #$gameId reset() finished in {$ms}ms"
                    );
                }
            }),
            self::RESET_DELAY_TICKS
        );
    }
}
