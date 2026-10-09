<?php

declare(strict_types=1);

namespace sergittos\bedwars\game;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Utils;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;
use pocketmine\world\WorldException;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\entity\PlayBedwarsEntity;
use sergittos\bedwars\game\bounty\BountyManager;
use sergittos\bedwars\game\BedWarsGame;
use sergittos\bedwars\game\cinematic\camera\CinematicCamera;
use sergittos\bedwars\game\entity\shop\ItemShopVillager;
use sergittos\bedwars\game\entity\shop\UpgradesShopVillager;
use sergittos\bedwars\game\entity\shop\Villager;
use sergittos\bedwars\game\generator\Generator;
use sergittos\bedwars\game\generator\presets\TextGenerator;
use sergittos\bedwars\game\map\Map;
use sergittos\bedwars\game\stage\EndingStage;
use sergittos\bedwars\game\stage\PlayingStage;
use sergittos\bedwars\game\stage\Stage;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\game\stage\WaitingStage;
use sergittos\bedwars\game\task\SafeRemoveGameTask;
use sergittos\bedwars\game\task\SpawnFallSafetyTask;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\game\team\TeamSelectionManager;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use sergittos\bedwars\world\GameChunkLoader;
use function array_merge;
use function array_search;
use function count;
use function date;
use function in_array;
use function is_string;
use function microtime;
use function round;
use function strtolower;
use function time;

class Game{

    private int $id;
    private Map $map;
    private Stage $stage;

    private ?World $world = null;

    /** @var Generator[] */
    private array $generators;

    /** @var Team[] */
    private array $teams;

    /** @var Position[] */
    private array $blocks = [];

    /**
     * Mirrors $blocks but keyed by "worldId:x:y:z" so checkBlock() can do an
     * O(1) lookup instead of scanning the whole array with Position::equals()
     * for every single block in every explosion (this was the source of the
     * multi-tick lag spikes on TNT explosions - checkBlock() is called once
     * per exploded block, and a linear scan against every block ever placed
     * in the match made that O(exploded_blocks * placed_blocks)).
     *
     * @var array<string, int> block key => index into $blocks
     */
    private array $blockIndex = [];

    /** @var Session[] */
    private array $players = [];

    /** @var Session[] */
    private array $spectators = [];

    private bool $worldClosing = false;

    private ?GameChunkLoader $chunkLoader = null;

    /** @var array<string,bool> */
    private array $lockedChunks = [];

    /**
     * Set true once every team spawn's chunks have been force-loaded and
     * populated (see prepareTeamSpawnChunks()). Not currently required by
     * any caller directly - teleportToTeamSpawn() falls back to its own
     * isChunkLoaded() check/wait regardless of this flag - but kept
     * available for anything (e.g. diagnostics/telemetry) that wants to
     * know whether the warm-up has finished.
     */
    private bool $spawnChunksReady = false;

    /** @var array<string,bool> chunk keys already requested by prepareTeamSpawnChunks(), so a reset()+re-setup doesn't skip re-requesting them for the new world instance */
    private array $preparedSpawnChunks = [];

    /**
     * Where the most recently destroyed bed was, used as the reference
     * point the Podium Ceremony builds its podium next to - the "right
     * next to where the last bed broke" spot from the ceremony's design.
     * Falls back to the winning team's own bed position (see EndingStage)
     * if no bed was ever actually destroyed this match (e.g. everyone on
     * the other team(s) disconnected before their bed broke).
     */
    private ?Vector3 $lastBedBreakPosition = null;

    /**
     * The Team whose bed was destroyed most recently, kept alongside
     * $lastBedBreakPosition so the Podium Ceremony can orient itself
     * using that team's own spawn->bed facing (see
     * EndingStage::preparePodiumCeremony() / PodiumBuilder::build())
     * instead of assuming every map's beds face the same way.
     */
    private ?Team $lastBedBreakTeam = null;

    /**
     * In-match, in-memory Bounty system for this Game - see BountyManager's
     * own doc comment. One instance per Game, reset (never rebuilt) each
     * time this Game is recycled for a new match - see reset() below.
     */
    private BountyManager $bountyManager;

    public function __construct(Map $map, int $id){
        $this->id = $id;
        $this->map = $map;
        $this->teams = Utils::cloneObjectArray($map->getTeams());
        $this->generators = Utils::cloneObjectArray($map->getGenerators());
        $this->bountyManager = new BountyManager($this);
        $this->setStage(new WaitingStage());
    }

    public function getBountyManager(): BountyManager{ return $this->bountyManager; }

    public function isWorldClosing(): bool{
        return $this->worldClosing;
    }

    public function getId(): int{ return $this->id; }
    public function getStage(): Stage{ return $this->stage; }
    public function getMap(): Map{ return $this->map; }
    public function getWorld(): ?World{ return $this->world; }

    /**
     * Human-readable mode name (Solo/Doubles/Triples/Squads) derived from
     * this game's map's team size - same 1/2/3/4 mapping Matchmaker uses to
     * pick which queue a server feeds into. Used to label the Rejoin Ticket
     * a disconnected player gets handed on a lobby server (see
     * RejoinDisconnectListener / RejoinTicketItem) so they can see at a
     * glance which match they'd be jumping back into.
     */
    public function getModeName(): string{
        return match($this->map->getPlayersPerTeam()){
            1 => "Solo",
            2 => "Doubles",
            3 => "Triples",
            4 => "Squads",
            default => "Solo",
        };
    }

    public function getLastBedBreakPosition(): ?Vector3{ return $this->lastBedBreakPosition; }
    public function setLastBedBreakPosition(Vector3 $position): void{ $this->lastBedBreakPosition = $position->asVector3(); }

    public function getLastBedBreakTeam(): ?Team{ return $this->lastBedBreakTeam; }
    public function setLastBedBreakTeam(Team $team): void{ $this->lastBedBreakTeam = $team; }

    /** @return Generator[] */
    public function getGenerators(): array{ return $this->generators; }

    /** @return Team[] */
    public function getTeams(): array{ return $this->teams; }

    /** @return Team[] */
    public function getAliveTeams(): array{
        $teams = [];
        foreach($this->teams as $team){
            if($team->isAlive()){
                $teams[] = $team;
            }
        }
        return $teams;
    }

    /** @return Session[] */
    public function getPlayers(): array{ return $this->players; }

    /** @return Session[] */
    public function getSpectators(): array{ return $this->spectators; }

    /** @return Session[] */
    public function getPlayersAndSpectators(): array{
        return array_merge($this->players, $this->spectators);
    }

    public function getPlayersCount(): int{
        return count($this->players);
    }

    public function isFull(): bool{
        return $this->getPlayersCount() >= $this->map->getMaxCapacity();
    }

    public function checkBlock(Position $position): bool{
        $key = $this->blockKey($position);
        if(!isset($this->blockIndex[$key])){
            return false;
        }

        $index = $this->blockIndex[$key];
        unset($this->blocks[$index], $this->blockIndex[$key]);
        return true;
    }

    private function blockKey(Position $position): string{
        $world = $position->getWorld();
        return ($world !== null ? spl_object_id($world) : 0) . ":" . $position->getFloorX() . ":" . $position->getFloorY() . ":" . $position->getFloorZ();
    }

    /**
     * Removes every currently-tracked player-placed block (see addBlock()/
     * $blocks above - map terrain, generator blocks, the Podium Ceremony's
     * own gold/iron/clay columns, etc. were never added to that list, so
     * this can never touch anything the player didn't personally place)
     * within $radius blocks horizontally of $center, resetting each one
     * back to air and forgetting it from tracking.
     *
     * Used to clear bridges/towers/blocks players built around the
     * winners' podium before the Podium Ceremony camera cuts in, so the
     * celebration isn't cluttered by leftover builds. Distance is checked
     * on the X/Z plane only (height is ignored) so a tall bridge or tower
     * built near the podium gets cleared along its whole height, not just
     * the layer level with $center.
     *
     * Safe to call with no world loaded / nothing tracked - it's a no-op
     * in that case instead of throwing, since this always runs from
     * inside a try/catch in EndingStage but should never need to rely on
     * that as its only safety net.
     */
    public function clearPlacedBlocksNear(Vector3 $center, float $radius): void{
        if($this->world === null || $this->blocks === []){
            return;
        }

        $radiusSquared = $radius * $radius;
        $toClear = [];

        foreach($this->blocks as $position){
            $dx = $position->x - $center->x;
            $dz = $position->z - $center->z;
            if((($dx * $dx) + ($dz * $dz)) <= $radiusSquared){
                $toClear[] = $position;
            }
        }

        foreach($toClear as $position){
            // Untrack first: setBlock() below doesn't fire a BlockBreakEvent
            // (that's the point - this is an admin-style cleanup, not a
            // player breaking their own block), so checkBlock() would never
            // otherwise get called for these positions and they'd stay
            // "tracked" pointing at a now-air block forever.
            $this->checkBlock($position);
            $this->world->setBlock($position, VanillaBlocks::AIR(), false);
        }
    }

    public function isPlaying(Session $session): bool{
        return in_array($session, $this->players, true);
    }

    public function isSpectator(Session $session): bool{
        return in_array($session, $this->spectators, true);
    }

    public function setStage(Stage $stage): void{
        // Two independent triggers can both notice "only one team left
        // alive" around the same tick and each try to end the match on
        // their own: a normal team-wipe detected right inside
        // PlayingStage::onQuit(), and the periodic rejoin-timeout sweep in
        // purgeInvalidSessions(). Without this guard, whichever one lost
        // the race still went ahead and built a second, brand new
        // EndingStage on top of the first - EndingStage::onStart() ran
        // twice, which is why "TEAM ELIMINATED"/"TOP PLAYERS" (and every
        // reward, podium and spectator-promotion side effect that comes
        // with it) could show up broadcast twice for the exact same match
        // end. Scoped to EndingStage only - every other stage transition
        // (Waiting -> Starting -> Playing) still behaves exactly as before.
        if($stage instanceof EndingStage && $this->stage instanceof EndingStage){
            return;
        }

        $this->stage = $stage;
        $this->stage->start($this);

        if($stage instanceof EndingStage){
            // Bounty state is entirely match-scoped (see BountyManager's
            // doc comment) - wipe it the instant the match ends, so no
            // Bounty/momentum ever carries into whatever this Game
            // instance hosts next.
            $this->bountyManager->reset();
        }
    }

    public function addBlock(Position $position): void{
        $key = $this->blockKey($position);
        if(isset($this->blockIndex[$key])){
            // already tracked at this position (e.g. re-placed on the same spot) -
            // keep the index pointing at a single, current entry instead of letting
            // $blocks grow with stale duplicates.
            $this->blocks[$this->blockIndex[$key]] = $position;
            return;
        }

        $this->blocks[] = $position;
        $this->blockIndex[$key] = array_key_last($this->blocks);
    }

    public function addPlayer(Session $session): void{
        if($this->isPlaying($session)){
            return;
        }

        if($this->isSpectator($session)){
            $this->leaveSpectating($session);
        }

        $session->setPendingRejoinUntil(null);
        $session->setGame($this);
        $this->players[] = $session;

        $this->stage->onJoin($session);

        $this->updateScoreboards();
        $this->updatePlayEntities();
    }

    public function rejoinPlayer(Session $session, string $teamName): bool{
        // Look up the target team BEFORE detachSameUsername() below runs.
        // That call removes the stale Session object left behind by the
        // player's own earlier disconnect (GameListener intentionally
        // leaves it in $this->players/$team so the rest of the match keeps
        // treating the team as present) - looking the team up first just
        // avoids any ordering surprises from that detach.
        //
        // Eligibility to come back as an active player is gated purely on
        // the team's BED, via isBedDestroyed() - not Team::isAlive() (which
        // only means "has at least one living member"). isAlive() used to
        // gate this instead, which was wrong: the player's own disconnect
        // placeholder keeps a solo/last-member team "alive" by that
        // definition even after their bed has already been destroyed while
        // they were away, which let them rejoin as a full player again and
        // again with no bed to back it up. Bed status is the only thing
        // that should decide this - if the bed still stands they're
        // restored to their team, otherwise they come back as a spectator
        // exactly like a team that was truly eliminated.
        $team = $this->stage instanceof PlayingStage ? $this->findTeamByName($teamName) : null;
        $hasBed = $team !== null && !$team->isBedDestroyed();

        // Capture the earned-so-far in-game stats off the stale (pre-
        // disconnect) session BEFORE detachSameUsername() below discards it -
        // that stale session is the only place this number still lives (see
        // Session::carryOverGameStats()'s doc-comment for why the brand new
        // session about to replace it can't compute this on its own).
        $stale = $this->findSessionByUsername($session->getUsername());
        $carriedKills = $stale?->getGameKills() ?? 0;
        $carriedFinalKills = $stale?->getGameFinalKills() ?? 0;
        $carriedBedsBroken = $stale?->getGameBedsBroken() ?? 0;

        $this->detachSameUsername($session->getUsername());

        $session->carryOverGameStats($carriedKills, $carriedFinalKills, $carriedBedsBroken);

        $session->setPendingRejoinUntil(null);
        $session->setGame($this);

        // Outside PlayingStage there's no player/spectator branch below to
        // decide this for us - a rejoin during Waiting/Starting/Ending is
        // always a full participant, so add to $this->players right away
        // (isPlaying() guards against ever inserting the same Session twice).
        // Inside PlayingStage this is intentionally deferred to the branches
        // below instead of happening unconditionally here - see the comment
        // on the $team !== null && $hasBed branch for why.
        if(!($this->stage instanceof PlayingStage) && !$this->isPlaying($session)){
            $this->players[] = $session;
        }

        $this->stage->onJoin($session);

        if($this->stage instanceof PlayingStage){
            if($team !== null && $hasBed){
                // Only added to $this->players in THIS branch - restored as
                // an ACTIVE player - instead of unconditionally above.
                // Adding unconditionally used to run before we knew whether
                // this rejoin would end up here or in the spectator branch
                // below, so a player coming back with their bed already
                // destroyed got pushed into $this->players AND then into
                // $this->spectators via addSpectator(). getPlayersAndSpectators()
                // merges both arrays with no de-duplication, so every single
                // broadcastMessage() call (all match chat and system
                // messages) delivered that one player's message packet to
                // them twice - the "everything shows up doubled" symptom -
                // and TeleporterForm (built from getPlayers() alone) listed
                // them as a live teleport target even though they were
                // actually spectating.
                if(!$this->isPlaying($session)){
                    $this->players[] = $session;
                }
                $team->addMember($session);
                $this->broadcastMessage("§a§lREJOINED §r§8» §e§l" . $session->getUsername() . "§r§7 is back in the match!");
                $session->message("§a§lWELCOME BACK §r§8» §fYou've been restored to " . $team->getColoredName() . "§f.");
                $this->updateScoreboards();
                $this->updatePlayEntities();
                return true;
            }

            // Team unavailable (bed already gone) - spectator only.
            // Deliberately never also added to $this->players - see the
            // comment on the branch above.
            $this->addSpectator($session);
            $session->message("§e§lTEAM UNAVAILABLE §r§8» §7Your team was eliminated while you were away. §fYou are now spectating.");
            $this->updateScoreboards();
            $this->updatePlayEntities();
            return true;
        }

        $this->updateScoreboards();
        $this->updatePlayEntities();
        return true;
    }

    /**
     * Looks up a currently-tracked Session (player OR spectator) by
     * username, without removing it - unlike detachSameUsername() below.
     * Used by rejoinPlayer() to read the stale session's earned game stats
     * before that session gets discarded.
     */
    private function findSessionByUsername(string $username): ?Session{
        $u = strtolower($username);

        foreach($this->players as $s){
            if(strtolower($s->getUsername()) === $u){
                return $s;
            }
        }

        foreach($this->spectators as $s){
            if(strtolower($s->getUsername()) === $u){
                return $s;
            }
        }

        return null;
    }

    private function detachSameUsername(string $username): void{
        $u = strtolower($username);

        foreach($this->players as $k => $s){
            if(strtolower($s->getUsername()) !== $u){
                continue;
            }

            if($s->hasTeam()){
                $s->getTeam()?->removeMember($s, false);
            }

            unset($this->players[$k]);
            $this->despawnGeneratorsFrom($s);
            $s->setTrackingSession(null);
            $s->setPendingRejoinUntil(null);
            $s->setTeam(null);
            $s->setGame(null);
        }

        foreach($this->spectators as $k => $s){
            if(strtolower($s->getUsername()) !== $u){
                continue;
            }

            unset($this->spectators[$k]);
            $this->despawnGeneratorsFrom($s);
            $s->setTrackingSession(null);
            $s->setPendingRejoinUntil(null);
            $s->setTeam(null);
            $s->setGame(null);
        }
    }

    private function findTeamByName(string $name): ?Team{
        foreach($this->teams as $t){
            if(strtolower($t->getName()) === strtolower($name)){
                return $t;
            }
        }
        return null;
    }

    /**
     * @param bool $deferUpdates When true, skips the updateScoreboards()/
     *        updatePlayEntities() refresh at the end of this call. Both of
     *        those are O(remaining players) and O(every entity in the hub
     *        world) respectively, so calling them once per player inside a
     *        bulk removal loop (see reset()) turns an already-linear
     *        teardown into an effectively quadratic one and is exactly
     *        what showed up as a 140-170ms blocking spike on match end in
     *        timings. Bulk callers should pass true here and call
     *        updateScoreboards()/updatePlayEntities() themselves ONCE after
     *        their loop finishes. Every other (single-player) call site
     *        keeps the old default of refreshing immediately.
     */
    public function removePlayer(Session $session, bool $teleport_to_hub = true, bool $set_spectator = false, bool $deferUpdates = false): void{
        $idx = array_search($session, $this->players, true);
        if($idx !== false){
            unset($this->players[$idx]);
        }

        $this->stage->onQuit($session);

        $session->setPendingRejoinUntil(null);

        if($teleport_to_hub){
            $session->teleportToHub();
        }

        if($set_spectator){
            $this->addSpectator($session);
        }else{
            $session->setTeam(null);
            $session->setGame(null);
        }

        if(!$deferUpdates){
            $this->updateScoreboards();
            $this->updatePlayEntities();
        }
    }

    public function addSpectator(Session $session): void{
        if($this->isSpectator($session)){
            return;
        }

        $session->setPendingRejoinUntil(null);
        $session->setGame($this);
        $this->spectators[] = $session;

        $session->giveSpectatorItems();
        $session->getSpectatorSettings()?->apply();
    }

    /**
     * @param bool $deferUpdates See removePlayer()'s doc-comment - same
     *        reasoning applies here since EndingStage::onStart() calls this
     *        once per losing player in a loop.
     */
    public function promoteToSpectator(Session $session, bool $deferUpdates = false): void{
        // promoteToSpectator() is only ever called from EndingStage - i.e.
        // this is specifically the end-of-match ("game over") spectator
        // promotion, so it must use the real GameMode::SPECTATOR() rather
        // than the fake ADVENTURE-based spectator used for mid-match
        // elimination (see addSpectator() and Session::giveSpectatorItems()).
        if($this->isSpectator($session)){
            $session->giveSpectatorItems(true);
            $session->getSpectatorSettings()?->apply();
            if(!$deferUpdates){
                $this->updateScoreboards();
                $this->updatePlayEntities();
            }
            return;
        }

        $idx = array_search($session, $this->players, true);
        if($idx !== false){
            unset($this->players[$idx]);
        }

        $session->setPendingRejoinUntil(null);
        $session->setGame($this);
        $this->spectators[] = $session;

        $session->giveSpectatorItems(true);
        $session->getSpectatorSettings()?->apply();

        if(!$deferUpdates){
            $this->updateScoreboards();
            $this->updatePlayEntities();
        }
    }

    public function removeSpectator(Session $session): void{
        $this->leaveSpectating($session);
        $session->teleportToHub();
    }

    public function leaveSpectating(Session $session): void{
        $idx = array_search($session, $this->spectators, true);
        if($idx !== false){
            unset($this->spectators[$idx]);
        }

        // Every path out of spectating goes through here first (Return to
        // Lobby, Play Again, disconnect cleanup, etc). Return to Lobby never
        // showed this bug because it also transfers the player to a whole
        // different server right after - a fresh connection that resets
        // the client's camera implicitly. Play Again instead requeues the
        // player straight into a new match on this same server/connection
        // (see Matchmaker::queue()/PlayAgainItem), so nothing ever resets
        // a cinematic camera left over from a Podium Ceremony they were
        // spectating - their body moves into the new match but their
        // screen stays locked in the old detached camera view. Clearing it
        // here, unconditionally, covers every exit path the same way and
        // is a safe no-op for a spectator who never had one active.
        $player = $session->getPlayer();
        self::clearCinematicCamera($player);

        $this->despawnGeneratorsFrom($session);
        $session->setPendingRejoinUntil(null);
        $session->setTeam(null);
        $session->setGame(null);
    }

    /**
     * Clears the player's cinematic camera and guarantees it stays cleared,
     * instead of trusting a single immediate packet to win a race it can
     * sometimes lose.
     *
     * While a Podium Ceremony is running, its own repeating task is still
     * broadcasting a fresh (non-immediate, per-tick-buffered) camera
     * position to every remaining viewer once a tick - including this
     * player, right up until the exact moment they're removed from
     * $this->spectators above. If that broadcast already got queued into
     * this player's send buffer earlier in the SAME tick, sending our
     * clear with $immediate = true doesn't help: an immediate packet is
     * written straight to the wire right away, while the buffered
     * position update it was meant to override is still sitting in the
     * per-tick buffer and only goes out when the tick's normal flush
     * happens - which can land AFTER our clear and silently re-lock the
     * client back into the detached cinematic view. That race depends on
     * exactly when the click was processed relative to the ceremony's own
     * per-tick broadcast, which is why this only ever showed up
     * intermittently instead of every time.
     *
     * A single clear can't rule that out by itself, so this re-sends it a
     * couple more times a few ticks later - by then the ceremony's task
     * has had multiple full ticks to finish flushing anything it had
     * already queued for this player, so the last clear is guaranteed to
     * be the last camera instruction the client ever sees. Re-clearing an
     * already-normal camera is a harmless no-op, so this is safe to run
     * unconditionally for every exit path, including ones that never had
     * an active cinematic camera to begin with.
     */
    private static function clearCinematicCamera(Player $player): void{
        if($player->isConnected()){
            try{
                CinematicCamera::clear($player);
            }catch(\Throwable){
            }
        }

        foreach([2, 5] as $delayTicks){
            BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(static function() use ($player): void{
                    if($player->isConnected()){
                        try{
                            CinematicCamera::clear($player);
                        }catch(\Throwable){
                        }
                    }
                }),
                $delayTicks
            );
        }
    }

    public function despawnGeneratorsFrom(Session $session): void{
        foreach($this->getGenerators() as $generator){
            if($generator instanceof TextGenerator){
                $generator->getText()->despawnFrom($session);
            }
        }
    }

    public function updatePlayEntities(): void{
        $default = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if($default === null){
            return;
        }

        foreach($default->getEntities() as $entity){
            if($entity instanceof PlayBedwarsEntity && $entity->getPlayersPerTeam() === $this->map->getPlayersPerTeam()){
                $entity->updateNameTag();
            }
        }
    }

    private function spawnVillager(Villager $villager): void{
        if($this->world === null){
            return;
        }

        $position = $villager->getPosition()->floor();
        $this->world->requestChunkPopulation(
            $position->getFloorX() >> Chunk::COORD_BIT_SIZE,
            $position->getFloorZ() >> Chunk::COORD_BIT_SIZE,
            null
        )->onCompletion(
            function() use ($villager): void{
                $villager->spawnToAll();
            },
            function(): void{}
        );
    }

    public function tickGenerators(): void{
        foreach($this->generators as $generator){
            $generator->tick($this);
        }
        foreach($this->teams as $team){
            $team->tickGenerators($this);
        }
    }

    public function updateScoreboards(): void{
        foreach($this->getPlayersAndSpectators() as $session){
            $session->updateScoreboard();
        }
    }

    public function broadcastTitle(string $title): void{
        foreach($this->players as $session){
            $session->title($title);
        }
    }

    public function broadcastMessage(string $message): void{
        foreach($this->getPlayersAndSpectators() as $session){
            $session->message($message);
        }
    }

    public function broadcastSound(Sound|string $sound): void{
        foreach($this->getPlayersAndSpectators() as $session){
            if(is_string($sound)){
                $session->playSound($sound);
            }else{
                $player = $session->getPlayer();
                $player->broadcastSound($sound, [$player]);
            }
        }
    }

    public function setupWorld(): void{
        $name = $this->map->getName() . "-" . $this->id;

        $world_manager = Server::getInstance()->getWorldManager();
        if(!$world_manager->loadWorld($name)){
            throw new WorldException("Failed to load world");
        }

        $this->world = $world_manager->getWorldByName($name);
        if($this->world === null){
            throw new WorldException("World unavailable");
        }

        $this->world->setAutoSave(false);
        $this->world->setTime(World::TIME_DAY);
        $this->world->stopTime();

        BedWarsGame::getInstance()->getGameManager()->bindWorld($this->world, $this);

        $spawn = $this->world->getSafeSpawn();
        $this->lockChunk($spawn->getFloorX() >> Chunk::COORD_BIT_SIZE, $spawn->getFloorZ() >> Chunk::COORD_BIT_SIZE);

        // Warm up every team island's spawn chunks in the background as
        // early as possible - this world has just been loaded/copied and
        // is about to sit through the whole waiting-room + starting
        // countdown before anyone actually gets teleported to a team
        // spawn, which is easily enough time for this to finish quietly
        // in the background. See prepareTeamSpawnChunks() for why this
        // matters.
        $this->prepareTeamSpawnChunks();

        $this->despawnExistingVillagers();

        foreach($this->map->getShopPositions() as $position){
            $yaw = $this->map->getShopYaw($position);
            $this->spawnVillager(new ItemShopVillager(new Location($position->x, $position->y, $position->z, $this->world, $yaw, 0.0)));
        }

        foreach($this->map->getUpgradesPositions() as $position){
            $yaw = $this->map->getUpgradesYaw($position);
            $this->spawnVillager(new UpgradesShopVillager(new Location($position->x, $position->y, $position->z, $this->world, $yaw, 0.0)));
        }
    }

    private function despawnExistingVillagers(): void{
        if($this->world === null){
            return;
        }

        foreach($this->world->getEntities() as $entity){
            if($entity instanceof Villager){
                $entity->close();
            }
        }

        $positions = array_merge($this->map->getShopPositions(), $this->map->getUpgradesPositions());
        foreach($positions as $pos){
            $cx = $pos->getFloorX() >> Chunk::COORD_BIT_SIZE;
            $cz = $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE;

            $this->world->loadChunk($cx, $cz);

            $r = 4.0;
            $bb = new AxisAlignedBB(
                $pos->x - $r, $pos->y - 6.0, $pos->z - $r,
                $pos->x + $r, $pos->y + 6.0, $pos->z + $r
            );

            foreach($this->world->getNearbyEntities($bb) as $e){
                if($e instanceof Villager){
                    $e->close();
                }
            }
        }
    }

    public function getChunkLoader(): GameChunkLoader{
        return $this->chunkLoader ??= new GameChunkLoader(0x5B000000 + $this->id);
    }

    /**
     * Force-loads and requests population for a 3x3 grid of chunks around
     * every team's spawn point, as soon as this game's arena world exists -
     * long before the starting countdown even ends, let alone before any
     * player is actually teleported to a team spawn.
     *
     * This is the real fix for the classic "some players fall through
     * their island and take fall/void damage right as the match starts"
     * bug. The old flow teleported a player to Team::getSpawnPoint() the
     * instant the match began without ever having asked the world to make
     * sure that spot was actually loaded - chunk loading/population in
     * PocketMine is asynchronous (World::requestChunkPopulation() returns
     * a Promise, see the onCompletion() callback below and the identical
     * pattern already used for shop villagers in this file), so a
     * synchronous teleport right after a fresh copy of the world is
     * loaded can land a player over a chunk that hasn't actually finished
     * loading/populating yet - nothing but air with no collision, so they
     * fall straight through until it pops in underneath them (or hit void
     * damage first). Because it's a timing race against server/disk load
     * at that exact moment, it only ever hit some players on some
     * matches - never reproducible on demand, exactly as reported.
     *
     * setupWorld() runs this the moment the world is ready, which for a
     * normal match is a full waiting-room + 20s starting countdown ahead
     * of the actual team-spawn teleports in Team::addMember() - more than
     * enough time for every spawn chunk to be fully ready with zero
     * perceptible delay to anyone. teleportToTeamSpawn() still double
     * checks and, only as a defensive fallback, waits on the same promise
     * if a teleport is ever requested before this finished.
     *
     * A 3x3 grid (not just the single chunk under the spawn point) is
     * requested per spawn so a spawn point sitting close to a chunk
     * border can't still expose an unloaded neighbouring chunk. Every
     * chunk is also locked (registered under this game's own
     * GameChunkLoader, the same mechanism already used for the shared
     * spawn/shop chunks) so none of them can be unloaded again before the
     * match starts, and they're released like the rest in unloadWorld().
     */
    private function prepareTeamSpawnChunks(): void{
        if($this->world === null){
            return;
        }

        $chunks = [];
        foreach($this->teams as $team){
            $spawn = $team->getSpawnPoint();
            $centerX = $spawn->getFloorX() >> Chunk::COORD_BIT_SIZE;
            $centerZ = $spawn->getFloorZ() >> Chunk::COORD_BIT_SIZE;

            for($dx = -1; $dx <= 1; $dx++){
                for($dz = -1; $dz <= 1; $dz++){
                    $chunkKey = ($centerX + $dx) . ":" . ($centerZ + $dz);
                    $chunks[$chunkKey] = [$centerX + $dx, $centerZ + $dz];
                }
            }
        }

        // Drop anything we've already requested before (defensive - in
        // practice this only ever runs once per world instance, right at
        // the top of setupWorld()).
        foreach($chunks as $key => $_){
            if(isset($this->preparedSpawnChunks[$key])){
                unset($chunks[$key]);
            }else{
                $this->preparedSpawnChunks[$key] = true;
            }
        }

        if($chunks === []){
            $this->spawnChunksReady = true;
            return;
        }

        // Computed up front and only ever decremented from inside the
        // completion callbacks below - never re-derived mid-loop - so it
        // can't be misread as "done" while chunks requested later in this
        // same loop are still outstanding, even if some promises happen
        // to resolve synchronously.
        $remaining = count($chunks);

        foreach($chunks as [$cx, $cz]){
            $this->lockChunk($cx, $cz);

            $onSettled = function() use (&$remaining): void{
                $remaining--;
                if($remaining <= 0){
                    $this->spawnChunksReady = true;
                }
            };

            $this->world->requestChunkPopulation($cx, $cz, $this->getChunkLoader())->onCompletion($onSettled, $onSettled);
        }
    }

    /**
     * Safely teleports a session's player to a team spawn (initial match
     * start via Team::addMember(), or a later rejoin/team-switch). See
     * prepareTeamSpawnChunks() for the full explanation of the bug this
     * fixes.
     *
     * In the overwhelmingly common case the target chunk is already
     * loaded (prepareTeamSpawnChunks() warmed it during the waiting-room
     * + starting countdown) and this teleports immediately with no added
     * delay whatsoever. Only if that isn't true yet - e.g. an unusually
     * short countdown, or a very slow disk - does this wait on the
     * chunk's population promise before teleporting, instead of
     * teleporting the player in blind like before.
     *
     * A short spawn-protection window (Session::grantSpawnProtection(),
     * enforced by GameListener::onReceiveDamage()) plus
     * SpawnFallSafetyTask are kept underneath all of this regardless, as
     * a last-resort net: even if some still-unknown edge case slips past
     * the chunk check itself, the player can't take fall/void damage or
     * die from this specific teleport.
     */
    public function teleportToTeamSpawn(Session $session, Vector3 $spawnPoint): void{
        $world = $this->world;
        $player = $session->getPlayer();
        if($world === null || !$player->isConnected()){
            return;
        }

        $chunkX = $spawnPoint->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $chunkZ = $spawnPoint->getFloorZ() >> Chunk::COORD_BIT_SIZE;

        if($world->isChunkLoaded($chunkX, $chunkZ)){
            $this->finishTeamSpawnTeleport($session, $spawnPoint, $world);
            return;
        }

        // Defensive fallback only - see doc comment above.
        $this->lockChunk($chunkX, $chunkZ);
        $onReady = function() use ($session, $player, $spawnPoint, $world): void{
            // Guard against the player leaving, dying, or being moved to
            // a different world entirely while this promise was pending.
            if($player->isConnected() && $player->getWorld() === $world){
                $this->finishTeamSpawnTeleport($session, $spawnPoint, $world);
            }
        };
        $this->world->requestChunkPopulation($chunkX, $chunkZ, $this->getChunkLoader())->onCompletion($onReady, $onReady);
    }

    private function finishTeamSpawnTeleport(Session $session, Vector3 $spawnPoint, World $world): void{
        $player = $session->getPlayer();
        $player->teleport(Position::fromObject($spawnPoint, $world));

        // Belt-and-suspenders: guarantees zero fall/void damage for the
        // brief window right after this teleport no matter what.
        // SpawnFallSafetyTask clears this once it's done watching.
        $session->grantSpawnProtection();

        SpawnFallSafetyTask::start($session, $spawnPoint, $world);
    }


    public function areSpawnChunksReady(): bool{
        return $this->spawnChunksReady;
    }

    public function lockChunk(int $chunkX, int $chunkZ): void{
        if($this->world === null){
            return;
        }

        $key = $chunkX . ":" . $chunkZ;
        if(isset($this->lockedChunks[$key])){
            return;
        }

        $this->world->registerChunkLoader($this->getChunkLoader(), $chunkX, $chunkZ, true);
        $this->lockedChunks[$key] = true;
    }

    private function unlockAllChunks(): void{
        if($this->world === null || $this->chunkLoader === null || $this->lockedChunks === []){
            $this->lockedChunks = [];
            return;
        }

        foreach($this->lockedChunks as $key => $_){
            $parts = explode(":", $key, 2);
            if(count($parts) !== 2){
                continue;
            }
            $this->world->unregisterChunkLoader($this->chunkLoader, (int) $parts[0], (int) $parts[1]);
        }

        $this->lockedChunks = [];
    }

    public function unloadWorld(): void{
        if($this->world === null){
            return;
        }

        $this->worldClosing = true;

        $w = $this->world;
        $w->setAutoSave(false);

        $this->unlockAllChunks();

        $debug = (bool) BedWarsGame::getInstance()->getConfig()->get("debug-match-end", false);
        $started = $debug ? microtime(true) : 0.0;

        $ok = Server::getInstance()->getWorldManager()->unloadWorld($w, true);

        if($debug){
            $ms = (int) round((microtime(true) - $started) * 1000);
            BedWarsGame::getInstance()->getLogger()->debug(
                "[Game#" . $this->id . "] " . date("H:i:s") . " unloadWorld() took {$ms}ms (ok=" . ($ok ? "true" : "false") . ")" .
                ($ms >= 50 ? " - this is on the main thread and blocks the whole server for its duration" : "")
            );
        }

        if($ok){
            BedWarsGame::getInstance()->getGameManager()->unbindWorld($w);
            $this->world = null;
            $this->blocks = [];
            $this->blockIndex = [];

            // The next setupWorld() call loads a brand new world instance
            // (a fresh copy from the map template) for the next match in
            // this slot, so the spawn-chunk warm-up has to run again from
            // scratch for it too.
            $this->spawnChunksReady = false;
            $this->preparedSpawnChunks = [];
        }

        $this->worldClosing = false;
    }

    public function reset(): void{
        TeamSelectionManager::clearGame($this);
        \sergittos\bedwars\profile\ProfileHooks::reset($this);

        // deferUpdates=true on every iteration: removePlayer() would
        // otherwise rebuild every remaining player's scoreboard AND rescan
        // every entity in the whole hub world once per player removed here
        // (see removePlayer()'s doc-comment) - for an 8-16 player match
        // that's the exact quadratic-ish blocking spike timings caught on
        // this closure. Refreshed once, in bulk, right after both loops
        // instead - the end result (everyone's scoreboard/name tags
        // correctly reflecting the now-empty game) is identical.
        //
        // Every step below is isolated on purpose. This used to be one
        // straight run of unguarded calls, so a single throwing player
        // (a disconnected one still inside their rejoin window has a
        // closed Player object -> Living::$effectManager "must not be
        // accessed before initialization" inside Session::teleportToHub())
        // aborted the whole method half way: the remaining players were
        // never sent to the lobby, the world never unloaded and the stage
        // never went back to Waiting - EndingStage just kept counting
        // down into negative numbers with everyone stuck on the podium.
        foreach($this->players as $session){
            if(!$this->isSessionOnline($session)){
                $this->dropOfflineSession($session);
                continue;
            }

            try{
                $this->removePlayer($session, true, false, true);
            }catch(\Throwable $e){
                $this->logResetFailure("removePlayer(" . $this->safeUsername($session) . ")", $e);
                $this->dropOfflineSession($session);
            }
        }
        foreach($this->spectators as $spectator){
            if(!$this->isSessionOnline($spectator)){
                $this->dropOfflineSession($spectator);
                continue;
            }

            try{
                $this->removeSpectator($spectator);
            }catch(\Throwable $e){
                $this->logResetFailure("removeSpectator(" . $this->safeUsername($spectator) . ")", $e);
                $this->dropOfflineSession($spectator);
            }
        }

        try{
            $this->updateScoreboards();
            $this->updatePlayEntities();
        }catch(\Throwable $e){
            $this->logResetFailure("scoreboard/entity refresh", $e);
        }

        foreach($this->generators as $generator){
            try{
                $generator->reset();
            }catch(\Throwable $e){
                $this->logResetFailure("generator reset", $e);
            }
        }

        try{
            $this->unloadWorld();
        }catch(\Throwable $e){
            $this->worldClosing = false;
            $this->logResetFailure("unloadWorld()", $e);
        }

        // Was hardcoded to "> 5", disconnected from the actual pool
        // target (GameManager::INSTANCES_PER_MAP) - a map could end up
        // keeping up to 5 idle world-instance copies around at once
        // regardless of how many are actually meant to exist. Now this
        // only keeps as many spare instances as the pool is configured
        // to top up to, and deletes the rest instead of letting them
        // pile up.
        $instances_per_map = BedWarsGame::getInstance()->getGameManager()->getInstancesPerMap();
        if(BedWarsGame::getInstance()->getGameManager()->getGamesCount($this->map) > $instances_per_map){
            BedWarsGame::getInstance()->getScheduler()->scheduleRepeatingTask(
                new SafeRemoveGameTask($this->id, $this->map->getName() . "-" . $this->id),
                20
            );
            return;
        }

        foreach($this->teams as $team){
            $team->reset();
        }

        $this->players = [];
        $this->spectators = [];
        $this->lastBedBreakPosition = null;
        $this->lastBedBreakTeam = null;
        $this->bountyManager->reset();

        $this->setStage(new WaitingStage());
    }

    private function isSessionOnline(Session $session): bool{
        try{
            return $session->getPlayer()->isConnected();
        }catch(\Throwable){
            return false;
        }
    }

    private function safeUsername(Session $session): string{
        try{
            return $session->getUsername();
        }catch(\Throwable){
            return "?";
        }
    }

    private function logResetFailure(string $step, \Throwable $e): void{
        BedWarsGame::getInstance()->getLogger()->warning(
            "[Game#" . $this->id . "] reset(): " . $step . " failed (continuing with the rest of the reset): " . $e->getMessage()
        );
        BedWarsGame::getInstance()->getLogger()->warning($e->getTraceAsString());
    }

    /**
     * Cleans up a session during reset() WITHOUT touching its Player at all
     * - for players who are already disconnected (typically still inside
     * their rejoin window when the match ended) or whose normal removal
     * just threw. There is no one to teleport/transfer, so only the
     * server-side bookkeeping is released. A pending rejoin record is also
     * cleared here: the match it pointed at is over, so leaving it in the
     * database would only offer that player a Rejoin Ticket to an arena
     * that has already been reset.
     */
    private function dropOfflineSession(Session $session): void{
        $idx = array_search($session, $this->players, true);
        if($idx !== false){
            unset($this->players[$idx]);
        }
        $idx = array_search($session, $this->spectators, true);
        if($idx !== false){
            unset($this->spectators[$idx]);
        }

        try{
            if($session->getPendingRejoinUntil() !== null){
                BedWarsCore::getInstance()->getProvider()->clearRejoin($this->safeUsername($session));
            }
        }catch(\Throwable){
        }

        try{
            $this->despawnGeneratorsFrom($session);
        }catch(\Throwable){
        }

        try{
            $session->setTrackingSession(null);
            $session->setPendingRejoinUntil(null);
            $session->setTeam(null);
            $session->setGame(null);
        }catch(\Throwable){
        }
    }

    public function addToTeam(\pocketmine\player\Player $player, Team $team): void{
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        if(!$this->isPlaying($session)){
            return;
        }

        if($team->isFull()){
            $session->message("{RED}{BOLD}Team Full{RESET}{GRAY} - Choose another team.");
            return;
        }

        if($session->hasTeam()){
            $session->message("{YELLOW}{BOLD}Already Assigned{RESET}{GRAY} - Your team is already set.");
            return;
        }

        $team->addMember($session);
        $session->message("{GREEN}{BOLD}Team Joined{RESET}{GRAY} - You joined " . $team->getColoredName() . "{GRAY}.");
    }

    public function purgeInvalidSessions(): void{
        $changed = false;

        foreach($this->players as $k => $session){
            $player = $session->getPlayer();

            if($session->getGame() !== $this){
                if($session->hasTeam()){
                    $session->getTeam()?->removeMember($session);
                }
                unset($this->players[$k]);
                $this->despawnGeneratorsFrom($session);
                $session->setTrackingSession(null);
                $session->setPendingRejoinUntil(null);
                $session->setTeam(null);
                $session->setGame(null);
                $changed = true;
                continue;
            }

            if($player->isConnected()){
                continue;
            }

            $until = $session->getPendingRejoinUntil();
            if($until !== null && time() <= $until){
                continue;
            }

            $team = $session->getTeam();
            $hadPending = $until !== null;

            if($hadPending){
                BedWarsCore::getInstance()->getProvider()->clearRejoin($session->getUsername());
                $this->broadcastMessage("§c§lFORFEITED §r§8» §e§l" . $session->getUsername() . "§r§7 didn't rejoin in time and was removed from the match.");
            }

            if($team !== null){
                $team->removeMember($session);
                if(!$team->isAlive()){
                    $this->broadcastMessage("§f§lTEAM ELIMINATED §r§8» " . $team->getColoredName() . " §7has been eliminated from the match!");
                }
            }

            unset($this->players[$k]);
            $this->despawnGeneratorsFrom($session);
            $session->setTrackingSession(null);
            $session->setPendingRejoinUntil(null);
            $session->setTeam(null);
            $session->setGame(null);
            $changed = true;
        }

        foreach($this->spectators as $k => $session){
            $player = $session->getPlayer();
            if(!$player->isConnected() || $session->getGame() !== $this){
                unset($this->spectators[$k]);
                $this->despawnGeneratorsFrom($session);
                $session->setTrackingSession(null);
                $session->setPendingRejoinUntil(null);
                $session->setTeam(null);
                $session->setGame(null);
                $changed = true;
            }
        }

        if($changed){
            $this->updateScoreboards();
            $this->updatePlayEntities();
        }

        if($this->stage instanceof PlayingStage && count($this->getAliveTeams()) === 1){
            $this->setStage(new EndingStage());
        }
    }
}