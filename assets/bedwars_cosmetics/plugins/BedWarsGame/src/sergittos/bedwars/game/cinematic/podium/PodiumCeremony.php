<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\podium;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\StopSoundPacket;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\World;
use sergittos\bedwars\cosmetics\api\CosmeticsAPI;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\cinematic\camera\CinematicCamera;
use sergittos\bedwars\utils\ColorUtils;
use function array_filter;
use function array_keys;
use function array_values;
use function cos;
use function count;
use function intdiv;
use function max;
use function mt_rand;
use function round;
use function sin;
use function spl_object_id;

/**
 * Drives the full "Podium Ceremony" that plays instead of a plain victory
 * countdown whenever a solo player or a team wins the match:
 *
 *  1. FADE   - a short screen fade (the "scene cut") right as the last bed
 *              breaks, during which the podium is silently built and the
 *              top-3 winners are teleported onto it.
 *  2. SPOT   - the camera holds on 3rd place, then 2nd place (whichever of
 *              the two actually exist), each one starting their Victory
 *              Dance and getting a floating "#3 - 7 Kills, ..." label the
 *              instant the camera lands on them.
 *  3. MVP    - the camera holds a little longer on 1st place, with a big
 *              particle wave, a louder sound, and a "MVP OF THE GAME"
 *              title shown to everyone except the MVP themselves.
 *  4. FINALE - the camera pulls back into a single wide orbit around the
 *              whole podium, all winners dancing together, and stays
 *              there on a loop until EndingStage's countdown ends and
 *              calls stop().
 *
 * Fully mode-agnostic: Solo/Doubles/Triples/Squads all just mean the
 * winning Team has 1-4 members, so however many of them (up to 3) end up
 * ranked ends up with however many podium spots actually exist - a Solo
 * win skips straight from FADE to a MVP-only ceremony, a Doubles win only
 * ever shows 2nd + 1st, etc. Nothing here special-cases team size.
 *
 * A custom "podium.theme" music track (see MUSIC_SOUND_ID and the bundled
 * PodiumMusic resource pack) plays underneath all four phases above, for
 * every current viewer - winners and the rest of the podium occupants,
 * and every spectator - equally, from the moment start() is called until
 * stop() cuts it off. It's independent of team size the same way the rest
 * of the ceremony is: nothing about it changes between Solo and Squads.
 */
final class PodiumCeremony{

    private const TICKS_PER_SECOND = 20;
    private const DEFAULT_EYE_HEIGHT = 1.62;
    /** How far above eye height the "#1 - PlayerName" / stats floating text hovers - kept close to the head instead of a block+ above it. */
    private const FLOATING_TEXT_HEIGHT = 0.35;

    private const PHASE_FADE = 0;
    private const PHASE_SPOT = 1;
    private const PHASE_MVP = 2;
    private const PHASE_FINALE = 3;

    private const FADE_DURATION_TICKS = 13;      // ~0.65s scene-cut fade
    private const SPOT_DURATION_TICKS = 90;       // 4.5s per non-MVP rank
    private const MVP_DURATION_TICKS = 130;       // 6.5s on the MVP
    /** Minimum time the FINALE wide shot should get before stop() is allowed to cut it short. */
    public const FINALE_MIN_DURATION_TICKS = 100; // 5s

    private const SPOT_ORBIT_RADIUS = 3.2;
    private const SPOT_ORBIT_HEIGHT = 1.1;
    private const SPOT_ORBIT_REVOLUTIONS_PER_SEC = 0.09;

    private const FINALE_ORBIT_RADIUS = 11.0;
    private const FINALE_ORBIT_HEIGHT = 6.5;
    private const FINALE_ORBIT_REVOLUTIONS_PER_SEC = 0.045;

    /** Real-body offset ring radius for spectators, same idea as VictoryCinematicController. */
    private const BODY_OFFSET_RADIUS = 5.0;

    /**
     * Custom sound event defined in the bundled PodiumMusic resource pack
     * (see PodiumMusicResourcePackInstaller / resources/PodiumMusic.zip),
     * played to everyone watching the ceremony - winners, podium
     * occupants and spectators alike - for its entire duration.
     *
     * Uses the "music" sound category and is3D:false in the pack's
     * sound_definitions.json, so every viewer hears it at the same flat
     * volume regardless of where they are relative to the podium (no
     * distance falloff), and it respects each player's own in-game Music
     * volume slider instead of the Sound one.
     */
    private const MUSIC_SOUND_ID = "podium.theme";
    private const MUSIC_VOLUME = 1.0;
    private const MUSIC_PITCH = 1.0;

    /**
     * Length (in ticks) of the theme.ogg loop bundled in the resource
     * pack. PlaySoundPacket has no built-in "loop" flag, so - same idea
     * as the per-rank Victory Dance re-looping in tickDanceLoops() below -
     * the track is simply re-triggered for every viewer right as it would
     * otherwise end, giving a seamless loop for as long as the ceremony
     * (FADE through FINALE) keeps running. Must match the actual encoded
     * duration of resources/PodiumMusic.zip's sounds/podium/theme.ogg, or
     * the loop will audibly stutter/overlap.
     */
    private const MUSIC_LOOP_DURATION_TICKS = 320; // 16s @ 20 ticks/sec

    /**
     * Root cause of "music sometimes just doesn't play" (equally for
     * podium occupants and spectators - both go through the exact same
     * startMusic() call, so this was never a viewer-type-specific bug):
     * the only per-viewer delivery attempt was the single PlaySoundPacket
     * sent by ensureMusicStarted() the moment a viewer is first seen, and
     * PlaySoundPacket has no ack - if that one packet is ever lost/no-op
     * for a given client (a session that isn't fully ready yet, a brief
     * hiccup, anything), nothing ever resent it, because the only retry
     * mechanism (tickMusicLoop(), every MUSIC_LOOP_DURATION_TICKS) is
     * tied to the actual encoded length of theme.ogg and can't be shortened.
     * For a Solo win (no SPOT phases at all) the whole ceremony is over in
     * ~12s - *less* than this 16s loop period - so that safety net could
     * mathematically never fire even once before stop() cut the audio for
     * good; Duos sits right on the edge. Triples/Squads had enough runway
     * for one natural loop retrigger, which is exactly why this always
     * looked like a Solo/Duos-specific issue rather than a general one.
     *
     * Fixed below with a second, independent one-shot retry (see
     * $musicRetryDeadline / guaranteeMusicRetry()) timed to always land
     * partway through *any* ceremony length, Solo included, instead of
     * relying solely on a loop period that can outlast the ceremony.
     */
    private const MUSIC_RETRY_MIN_TICKS = 40; // 2s - long enough for any transient send hiccup to have cleared

    /** @var array<int, PodiumSpot> rank => spot, only for ranks that actually exist */
    private array $spots;

    private Vector3 $podiumCenter;

    /** @var int[] queue of non-MVP ranks (3 then 2) still waiting to be revealed, highest number first */
    private array $revealQueue = [];

    /** @var array<int, true> ranks whose spotlight/MVP moment has already started - used to sync late joiners */
    private array $revealed = [];

    /** @var array<int, array{key: string, nextTick: int}> per-rank Victory Dance state, for the individual re-loop */
    private array $danceState = [];

    /**
     * @var array<int, array{particles: FloatingTextParticle[], positions: Vector3[], targets: Player[]}[]>
     * rank => list of every sendFloatingText() call made for that rank
     * (the initial reveal broadcast, plus one more per late joiner caught
     * up via resyncViewer() - each with its own fresh particle instances
     * and its own target list), so removeFloatingText() can despawn every
     * one of them, to everyone who actually received it, later - see
     * removeFloatingText()'s doc comment.
     */
    private array $floatingTextByRank = [];

    private int $phase = self::PHASE_FADE;
    private int $phaseElapsed = 0;
    private int $elapsedTicks = 0;
    private ?int $currentFocusRank = null;

    private int $mvpBurstElapsed = 0;

    /**
     * The elapsedTicks() value at which the podium theme needs to be
     * re-triggered for every current viewer to keep the loop seamless -
     * see MUSIC_LOOP_DURATION_TICKS. Left at 0 (never reached, since
     * elapsedTicks starts at 0 too and only increments after start()
     * schedules the first tick) until start() sets the real first
     * target.
     */
    private int $musicNextTick = 0;

    /**
     * elapsedTicks() value for the guaranteed one-shot retry described
     * above at MUSIC_RETRY_MIN_TICKS - always set in start() to a value
     * that fits inside *this* ceremony's own runtime (see
     * getMinimumSeconds()), so unlike MUSIC_LOOP_DURATION_TICKS it can
     * never outlast a short ceremony.
     */
    private int $musicRetryDeadline = 0;
    private bool $musicRetryDone = false;

    /** @var \WeakMap<Player, true> viewers already sent the camera preset + (if applicable) body-synced */
    private \WeakMap $initialized;

    /**
     * @var \WeakMap<Player, true> viewers who have already had the podium
     * theme started for them at least once - tracked completely separately
     * from $initialized (see ensureMusicStarted()) so that a failure in
     * the camera/body-sync work below can never take the music down with
     * it for that viewer.
     */
    private \WeakMap $musicStarted;

    private ?TaskHandler $task = null;
    private bool $stopped = false;

    /**
     * Snapshot of each occupant's real nametag text (e.g. "§b[12★] §c[RED] §rToji"),
     * taken right before we blank it out for the ceremony, so
     * restoreOccupantNametags() can put back the exact original string
     * instead of trying to recompute it (level/team/rank can all change a
     * player's nametag text, so recomputing it later could easily drift
     * from what they actually had).
     *
     * @var \WeakMap<Player, string>
     */
    private \WeakMap $savedNametags;

    /**
     * @param array<int, PodiumSpot> $spots rank => spot, 1-3 entries
     */
    public function __construct(
        private readonly Plugin $plugin,
        private readonly Game $game,
        private readonly World $world,
        array $spots
    ){
        $this->spots = $spots;
        $this->initialized = new \WeakMap();
        $this->musicStarted = new \WeakMap();
        $this->savedNametags = new \WeakMap();
        $this->podiumCenter = $this->computeCenter();

        foreach([3, 2] as $rank){
            if(isset($this->spots[$rank])){
                $this->revealQueue[] = $rank;
            }
        }
    }

    /**
     * @return array<int, Vector3> rank => stand position, handed back to
     *                              EndingStage so it can teleport each
     *                              winner onto their pedestal *before*
     *                              freezing them there.
     */
    public function getStandPositions() : array{
        $out = [];
        foreach($this->spots as $rank => $spot){
            $out[$rank] = $spot->standPosition;
        }
        return $out;
    }

    /**
     * The pedestal position the given session should stand on, or null if
     * they didn't place top-3 on their team (e.g. the 4th member of a
     * Squads team). Used by EndingStage to teleport podium winners onto
     * their pedestal right before freezing them there.
     */
    public function getStandPositionFor(\sergittos\bedwars\session\Session $session) : ?Vector3{
        foreach($this->spots as $spot){
            if($spot->session === $session){
                return $spot->standPosition;
            }
        }
        return null;
    }

    /**
     * How many seconds EndingStage's countdown needs to be, at minimum,
     * for this ceremony to play out in full (fade + every reveal + MVP +
     * a decent finale) before the arena resets from under it. Scales
     * naturally with how many podium spots exist - a Solo win's ceremony
     * is shorter than a Squads win's.
     */
    public function getMinimumSeconds() : int{
        $ticks = self::FADE_DURATION_TICKS
            + (count($this->revealQueue) * self::SPOT_DURATION_TICKS)
            + self::MVP_DURATION_TICKS
            + self::FINALE_MIN_DURATION_TICKS;

        return (int) max(1, (int) round($ticks / self::TICKS_PER_SECOND) + 1);
    }

    private function computeCenter() : Vector3{
        $x = 0.0;
        $y = 0.0;
        $z = 0.0;
        $count = count($this->spots);
        if($count === 0){
            // Should never happen (EndingStage only builds a ceremony when
            // there's at least one ranked winner), but never divide by zero.
            return new Vector3(0, 0, 0);
        }

        foreach($this->spots as $spot){
            $x += $spot->standPosition->x;
            $y += $spot->standPosition->y;
            $z += $spot->standPosition->z;
        }

        return new Vector3($x / $count, $y / $count, $z / $count);
    }

    public function start() : void{
        if($this->stopped){
            return;
        }

        $this->phase = self::PHASE_FADE;
        $this->phaseElapsed = 0;
        $this->elapsedTicks = 0;
        $this->currentFocusRank = null;

        $this->hideOccupantNametags();

        $this->musicNextTick = self::MUSIC_LOOP_DURATION_TICKS;

        // Guaranteed retry always lands inside this specific ceremony's
        // own runtime (half of it, floored at MUSIC_RETRY_MIN_TICKS)
        // instead of at a fixed 16s that a short Solo ceremony never
        // reaches - see the MUSIC_LOOP_DURATION_TICKS doc comment above.
        $ceremonyTicks = self::FADE_DURATION_TICKS
            + (count($this->revealQueue) * self::SPOT_DURATION_TICKS)
            + self::MVP_DURATION_TICKS
            + self::FINALE_MIN_DURATION_TICKS;
        $this->musicRetryDeadline = max(self::MUSIC_RETRY_MIN_TICKS, intdiv($ceremonyTicks, 2));
        $this->musicRetryDone = false;

        $this->ensureMusicStarted($this->currentViewers());

        $this->task = $this->plugin->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() : void{
                $this->tick();
            }),
            1
        );
    }

    public function stop() : void{
        $this->stopped = true;
        $this->task?->cancel();
        $this->task = null;

        foreach($this->currentViewers() as $player){
            try{
                CinematicCamera::clear($player);
            }catch(\Throwable){
                // Best-effort - a stuck camera clear should never block the game from resetting.
            }
        }

        $this->stopMusic($this->currentViewers());
        $this->removeAllFloatingText();
        $this->restoreOccupantNametags();
    }

    /** @return Player[] every currently connected player/spectator in the match */
    private function currentViewers() : array{
        $viewers = [];
        foreach($this->game->getPlayersAndSpectators() as $session){
            $player = $session->getPlayer();
            if($player->isConnected()){
                $viewers[] = $player;
            }
        }
        return $viewers;
    }

    private function isPodiumOccupant(Player $player) : bool{
        foreach($this->spots as $spot){
            $mp = $spot->session->getPlayer();
            if($mp === $player){
                return true;
            }
        }
        return false;
    }

    /**
     * Hides the level/team/name nametag that normally floats above each
     * podium occupant's head for the duration of the ceremony - it's
     * redundant with (and visually clutters) the "#1 - PlayerName" style
     * floating text this ceremony shows instead, right above their head.
     *
     * Toggling setNameTagVisible(false) alone isn't enough here: it flips
     * the CAN_SHOW_NAMETAG entity flag, but that flag only suppresses the
     * tag while still leaving the actual "[12★] [RED] Name" text set on the
     * entity - any spawn/resync of the podium occupant to a viewer during
     * the ceremony (a spectator's camera cutting to a new angle, a late
     * joiner, etc.) re-sends that text with fresh default metadata and the
     * name reappears, which is exactly what was showing up on-screen. So on
     * top of the flag, we also blank the nametag text itself and restore
     * the exact original string afterwards - with no text to show, nothing
     * can leak through regardless of when/how the entity gets resynced.
     */
    private function hideOccupantNametags() : void{
        foreach($this->spots as $spot){
            $mp = $spot->session->getPlayer();
            if(!$mp->isConnected()){
                continue;
            }
            try{
                $this->savedNametags[$mp] = $mp->getNameTag();
                $mp->setNameTag("");
                $mp->setNameTagVisible(false);
            }catch(\Throwable){
            }
            $this->setHealthTagHidden($mp, true);
        }
    }

    /**
     * Hides/restores the HealthTag plugin's health bar (the score tag
     * under the nametag) for a podium occupant, so it doesn't show up
     * during the ceremony either. Purely optional: if HealthTag isn't
     * installed/enabled this silently does nothing, and any failure here
     * must never affect the ceremony itself.
     */
    private function setHealthTagHidden(Player $player, bool $hidden) : void{
        try{
            $healthTag = $this->plugin->getServer()->getPluginManager()->getPlugin("HealthTag");
            if($healthTag !== null && $healthTag->isEnabled() && method_exists($healthTag, "setHidden")){
                $healthTag->setHidden($player, $hidden);
            }
        }catch(\Throwable){
        }
    }

    /**
     * Undoes hideOccupantNametags() the moment the ceremony ends, instead
     * of relying on the arena reset (unloadWorld + transfer to hub) to
     * implicitly fix it. That assumption doesn't hold for every exit path -
     * a podium occupant who mashes "Play Again" the instant EndingStage's
     * countdown finishes gets instantly requeued onto a brand-new match on
     * the SAME connection (see Matchmaker::queue()/PlayAgainItem), so their
     * Player object, and the invisible-nametag flag on it, carries straight
     * over. JoinableTrait::onJoin() now also re-enables it defensively for
     * that specific path, but restoring it right here - at the source of
     * the hide - is what actually closes the leak instead of only patching
     * one downstream symptom of it.
     */
    private function restoreOccupantNametags() : void{
        foreach($this->spots as $spot){
            $mp = $spot->session->getPlayer();
            if(!$mp->isConnected()){
                continue;
            }
            try{
                $original = $this->savedNametags[$mp] ?? null;
                if($original !== null && $original !== ""){
                    $mp->setNameTag($original);
                }
                $mp->setNameTagVisible(true);
                $mp->setNameTagAlwaysVisible(true);
            }catch(\Throwable){
            }
            $this->setHealthTagHidden($mp, false);
        }
    }

    private function tick() : void{
        if($this->stopped){
            return;
        }

        $viewers = $this->currentViewers();
        if($viewers === []){
            // Nobody left to show the ceremony to - stop cleanly instead of ticking forever.
            $this->stop();
            return;
        }

        $this->ensureInitialized($viewers);
        // Independent of ensureInitialized() above - see ensureMusicStarted()
        // for why this must never be gated behind camera/body-sync success.
        $this->ensureMusicStarted($viewers);
        $this->guaranteeMusicRetry($viewers);
        $this->advancePhase();
        $this->broadcastCamera($viewers);
        $this->tickDanceLoops($viewers);
        $this->tickMusicLoop($viewers);

        $this->phaseElapsed++;
        $this->elapsedTicks++;
    }

    /**
     * Sends the one-time setup (camera preset, and for outside spectators
     * only, a single body sync near the podium so the winners' entities
     * are actually sent to their client - see VictoryCinematicController
     * for the full explanation of why that's needed) to any viewer who
     * just joined mid-ceremony, then catches them up on whatever's
     * already been revealed.
     *
     * @param Player[] $viewers
     */
    private function ensureInitialized(array $viewers) : void{
        foreach($viewers as $player){
            if(isset($this->initialized[$player])){
                continue;
            }

            try{
                CinematicCamera::sendPreset($player);

                if($this->isPodiumOccupant($player)){
                    $this->resyncOccupantBody($player);
                }else{
                    $this->syncSpectatorBody($player);
                }

                $this->resyncViewer($player);

                // NOTE: starting the podium theme for this viewer is
                // intentionally NOT done here anymore - see
                // ensureMusicStarted(), called separately every tick from
                // tick(). Keeping it in this same try block used to mean a
                // single exception thrown anywhere above (camera preset,
                // occupant/spectator body sync, dance/label resync) left
                // $initialized unset for that viewer, which retried (and
                // could keep failing) every tick for the rest of the
                // ceremony - and since startMusic() lived in that same
                // block, it never ran for them either. A spectator whose
                // syncSpectatorBody() teleport got rejected/threw (map
                // border, an unloaded chunk near the podium, a stale
                // connection, etc.) would then never hear the theme at
                // all, while podium occupants - whose resync path rarely
                // fails - always did. Decoupling the two means a
                // visual-sync failure can no longer silently take the
                // audio down with it.
                $this->initialized[$player] = true;
            }catch(\Throwable $e){
                $this->plugin->getLogger()->warning("Podium ceremony failed to initialize viewer " . $player->getName() . ": " . $e->getMessage());
            }
        }
    }

    /**
     * Makes sure every current viewer - podium occupant or spectator,
     * initial audience or late joiner, doesn't matter - has had the
     * podium theme started for them at least once, using its own
     * $musicStarted tracking instead of piggybacking on $initialized.
     *
     * This has to stay completely independent of ensureInitialized()'s
     * camera-preset/body-sync work: that work involves teleports and
     * entity resyncs that can legitimately fail for an individual viewer
     * without the rest of the ceremony breaking, and coupling the two
     * (as a single try block gating a single flag) previously meant one
     * viewer's visual-sync failure silently and permanently muted the
     * music for them too, since it retried and failed the exact same way
     * every tick. Spectators go through the more failure-prone
     * syncSpectatorBody() teleport, which is why this bug consistently
     * showed up as "music works for the winners on the podium but not
     * for spectators" rather than affecting everyone equally.
     *
     * Called once from start() for the initial audience (so they're
     * marked as started immediately and don't get a duplicate copy from
     * this same method on the very first tick), then every tick from
     * tick() for anyone new.
     *
     * @param Player[] $viewers
     */
    private function ensureMusicStarted(array $viewers) : void{
        foreach($viewers as $player){
            if(isset($this->musicStarted[$player])){
                continue;
            }

            try{
                $this->startMusic([$player]);
            }catch(\Throwable $e){
                $this->plugin->getLogger()->warning("Podium ceremony failed to start music for viewer " . $player->getName() . ": " . $e->getMessage());
            }

            // Marked regardless of success - startMusic() already has its
            // own best-effort try/catch per player (see startMusic()), so
            // a failure here means the underlying sendDataPacket() call
            // itself failed (e.g. a connection that's already dying), not
            // something retrying will fix. tickMusicLoop() will still
            // naturally re-trigger the theme for this viewer, along with
            // everyone else, the next time the loop comes around.
            $this->musicStarted[$player] = true;
        }
    }

    /**
     * Teleports a spectator's real (already invisible/noclip) body once
     * to a point on a small ring around the podium so chunk-based entity
     * visibility actually sends them the winners' player entities. Unlike
     * VictoryCinematicController's winner (who can walk around during a
     * plain victory countdown), the podium itself never moves for the
     * rest of the ceremony, so this only ever needs to happen once per
     * viewer instead of being re-synced every shot change.
     */
    private function syncSpectatorBody(Player $player) : void{
        $angle = (spl_object_id($player) % 360) * (M_PI / 180.0);
        $offset = new Vector3(
            $this->podiumCenter->x + cos($angle) * self::BODY_OFFSET_RADIUS,
            $this->podiumCenter->y,
            $this->podiumCenter->z + sin($angle) * self::BODY_OFFSET_RADIUS
        );
        $player->teleport($offset);
    }

    /**
     * Forces a fresh position resync for a podium occupant (a top-3
     * winner standing on their own pedestal) at the exact moment their
     * detached "free" camera preset is registered.
     *
     * Their real body is already exactly where it needs to be (their
     * pedestal was set by EndingStage before this ceremony even started),
     * so unlike syncSpectatorBody() this isn't about chunk-based entity
     * visibility. It's about the client actually picking up the winner's
     * own player model for the newly-detached camera to render: without
     * a teleport sent *after* the free preset is registered, the client
     * keeps using whatever stale position sync it already had from
     * before the cinematic camera took over, and never renders the
     * winner's own body from the detached viewpoint - so every viewer
     * sees their Victory Dance except the winner performing it.
     *
     * A re-teleport is what forces that resync - but re-teleporting to
     * the *exact same* coordinates the entity already has is a no-op:
     * teleport() only actually broadcasts a movement packet when the
     * target position differs from the current one, so a same-spot call
     * never reaches the client at all and silently fixes nothing. Instead
     * this nudges the player by a sub-visible epsilon first (a genuinely
     * different position, guaranteed to send), then snaps them back to
     * their exact pedestal spot one tick later (also a genuinely
     * different position from the nudge, also guaranteed to send). Two
     * real teleports, zero perceptible movement, and the client is left
     * exactly on its pedestal mark with the resync it needed.
     */
    private function resyncOccupantBody(Player $player) : void{
        if(!$player->isConnected()){
            return;
        }

        $exact = $player->getPosition()->asVector3();
        $player->teleport($exact->add(0, 0.01, 0));

        $this->plugin->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($player, $exact) : void{
                if($player->isConnected()){
                    $player->teleport($exact);
                }
            }),
            1
        );
    }

    /**
     * Catches a single (usually late-joining) viewer up on everything
     * that's already happened: floating text + a fresh Victory Dance
     * broadcast for every rank already revealed, so they don't just see
     * static players mid-ceremony.
     */
    private function resyncViewer(Player $player) : void{
        foreach($this->spots as $rank => $spot){
            if(!isset($this->revealed[$rank])){
                continue;
            }
            $this->sendFloatingText($spot, [$player]);

            $mp = $spot->session->getPlayer();
            if($mp->isConnected() && isset($this->danceState[$rank])){
                CosmeticsAPI::triggerVictoryDance($mp, [$player], $this->danceState[$rank]["key"]);
            }
        }
    }

    private function advancePhase() : void{
        switch($this->phase){
            case self::PHASE_FADE:
                if($this->phaseElapsed === 0){
                    $this->onFadeStart();
                }
                if($this->phaseElapsed >= self::FADE_DURATION_TICKS){
                    $this->enterNextRevealOrMvp();
                }
                break;

            case self::PHASE_SPOT:
                if($this->phaseElapsed >= self::SPOT_DURATION_TICKS){
                    $this->enterNextRevealOrMvp();
                }
                break;

            case self::PHASE_MVP:
                $this->tickMvpFlourish();
                if($this->phaseElapsed >= self::MVP_DURATION_TICKS){
                    $this->enterFinale();
                }
                break;

            case self::PHASE_FINALE:
                // Stays here until EndingStage calls stop() once its own countdown finishes.
                break;
        }
    }

    private function onFadeStart() : void{
        foreach($this->currentViewers() as $player){
            try{
                CinematicCamera::fade($player, 0.15, 0.4, 0.15);
            }catch(\Throwable){
            }
        }
    }

    private function enterNextRevealOrMvp() : void{
        if($this->revealQueue !== []){
            $rank = array_values($this->revealQueue)[0];
            unset($this->revealQueue[0]);
            $this->revealQueue = array_values($this->revealQueue);

            $this->phase = self::PHASE_SPOT;
            $this->phaseElapsed = 0;
            $this->currentFocusRank = $rank;
            $this->revealSpot($rank);
            return;
        }

        if(isset($this->spots[1])){
            $this->phase = self::PHASE_MVP;
            $this->phaseElapsed = 0;
            $this->mvpBurstElapsed = 0;
            $this->currentFocusRank = 1;
            $this->revealSpot(1, true);
            return;
        }

        $this->enterFinale();
    }

    private function enterFinale() : void{
        $this->phase = self::PHASE_FINALE;
        $this->phaseElapsed = 0;
        $this->currentFocusRank = null;
    }

    /**
     * Kicks off a rank's spotlight moment: crossfade blip, Victory Dance,
     * floating stat label, and (MVP only) the big flourish.
     */
    private function revealSpot(int $rank, bool $isMvp = false) : void{
        $spot = $this->spots[$rank] ?? null;
        if($spot === null){
            return;
        }

        $this->revealed[$rank] = true;
        $viewers = $this->currentViewers();

        foreach($viewers as $player){
            try{
                CinematicCamera::fade($player, 0.12, 0.0, 0.12);
            }catch(\Throwable){
            }
        }

        $this->startDance($spot, $viewers);
        $this->sendFloatingText($spot, $viewers);

        $mp = $spot->session->getPlayer();
        $label = ColorUtils::translate($spot->rankLabel());

        foreach($viewers as $player){
            if($player === $mp){
                continue;
            }
            $player->sendActionBarMessage(ColorUtils::translate(
                "{YELLOW}{BOLD}" . $label . " {RESET}{GRAY}- " . TF::WHITE . $mp->getName()
            ));
        }

        if(!$isMvp && $mp->isConnected()){
            $mp->sendTitle(
                ColorUtils::translate("{AQUA}{BOLD}" . $label),
                ColorUtils::translate("{GRAY}" . $spot->describeStats()),
                5, 40, 10
            );
        }
    }

    /**
     * Resolves and starts the given podium spot's own equipped Victory
     * Dance (falling back to the default dance if they never bought/
     * equipped one), and remembers it so tickDanceLoops() can keep
     * re-triggering the same one for the rest of the ceremony.
     *
     * @param Player[] $viewers
     */
    private function startDance(PodiumSpot $spot, array $viewers) : void{
        $mp = $spot->session->getPlayer();
        if(!$mp->isConnected()){
            return;
        }

        $key = PlayerCosmeticsManager::getInstance()->getEquipped($mp, CosmeticCategory::VICTORY_DANCE)
            ?? CosmeticsAPI::DEFAULT_VICTORY_DANCE_KEY;

        CosmeticsAPI::triggerVictoryDance($mp, $viewers, $key);

        $durationTicks = (int) round(CosmeticsAPI::danceDurationSeconds($key) * self::TICKS_PER_SECOND);
        $this->danceState[$spot->rank] = [
            "key" => $key,
            "nextTick" => $this->elapsedTicks + max(1, $durationTicks)
        ];
    }

    /**
     * @param Player[] $viewers
     */
    private function tickDanceLoops(array $viewers) : void{
        foreach($this->danceState as $rank => $state){
            if($this->elapsedTicks < $state["nextTick"]){
                continue;
            }

            $spot = $this->spots[$rank] ?? null;
            if($spot === null){
                unset($this->danceState[$rank]);
                continue;
            }

            $mp = $spot->session->getPlayer();
            if(!$mp->isConnected()){
                unset($this->danceState[$rank]);
                continue;
            }

            CosmeticsAPI::triggerVictoryDance($mp, $viewers, $state["key"]);

            $durationTicks = (int) round(CosmeticsAPI::danceDurationSeconds($state["key"]) * self::TICKS_PER_SECOND);
            $this->danceState[$rank]["nextTick"] = $this->elapsedTicks + max(1, $durationTicks);
        }
    }

    /**
     * Starts (or restarts) the podium theme for exactly the given players -
     * used both for the whole-audience broadcast in start() and for a
     * single late joiner in ensureInitialized(). Position is irrelevant
     * (the sound is defined is3D:false in the resource pack, see
     * MUSIC_SOUND_ID), so podiumCenter is just a convenient fixed point,
     * the same way mvpParticleWave() below already uses it for its sound.
     *
     * @param Player[] $players
     */
    private function startMusic(array $players) : void{
        foreach($players as $player){
            if(!$player->isConnected()){
                continue;
            }
            try{
                $player->getNetworkSession()->sendDataPacket(
                    PlaySoundPacket::create(self::MUSIC_SOUND_ID, $this->podiumCenter->x, $this->podiumCenter->y, $this->podiumCenter->z, self::MUSIC_VOLUME, self::MUSIC_PITCH)
                );
            }catch(\Throwable){
                // Best-effort - a player who never hears the theme should never block the ceremony.
            }
        }
    }

    /**
     * Stops the podium theme for exactly the given players - called once
     * from stop() so the music cuts out cleanly with the rest of the
     * ceremony instead of ringing on into the post-ceremony countdown or,
     * worse, following a "Play Again" rejoin onto a fresh match.
     *
     * @param Player[] $players
     */
    private function stopMusic(array $players) : void{
        foreach($players as $player){
            if(!$player->isConnected()){
                continue;
            }
            try{
                $player->getNetworkSession()->sendDataPacket(StopSoundPacket::create(self::MUSIC_SOUND_ID, false));
            }catch(\Throwable){
                // Best-effort - see startMusic().
            }
        }
    }

    /**
     * Re-triggers the podium theme for every current viewer once the
     * bundled loop is about to run out, exactly the same re-looping idea
     * tickDanceLoops() above already uses for Victory Dances - PlaySoundPacket
     * has no native loop flag, so staying seamless just means re-sending it
     * slightly before/at the point the previous play-through ends.
     *
     * @param Player[] $viewers
     */
    private function tickMusicLoop(array $viewers) : void{
        if($this->elapsedTicks < $this->musicNextTick){
            return;
        }

        $this->startMusic($viewers);
        $this->musicNextTick = $this->elapsedTicks + self::MUSIC_LOOP_DURATION_TICKS;
    }

    /**
     * The actual fix for the intermittent "no music" report: a single
     * extra, unconditional re-send to every current viewer partway
     * through the ceremony (see $musicRetryDeadline), completely
     * independent of tickMusicLoop()'s MUSIC_LOOP_DURATION_TICKS cadence.
     *
     * Firing unconditionally to everyone (not just viewers we suspect
     * missed it) is intentional: PlaySoundPacket has no delivery ack, so
     * the server has no way to know which individual viewers actually
     * heard the first attempt and which didn't. Re-sending to a viewer
     * who already has it playing just restarts their loop a little
     * early/in sync with everyone else - inaudible as a problem, unlike
     * the alternative of a viewer hearing nothing for the entire
     * ceremony because their one and only attempt silently failed.
     */
    private function guaranteeMusicRetry(array $viewers) : void{
        if($this->musicRetryDone || $this->elapsedTicks < $this->musicRetryDeadline){
            return;
        }
        $this->musicRetryDone = true;
        $this->startMusic($viewers);
    }

    /**
     * @param Player[] $viewers
     */
    private function sendFloatingText(PodiumSpot $spot, array $viewers) : void{
        $mp = $spot->session->getPlayer();
        if(!$mp->isConnected()){
            return;
        }

        $targets = array_values(array_filter($viewers, static fn(Player $p) : bool => $p->isConnected()));
        if($targets === []){
            return;
        }

        $color = match($spot->rank){
            1 => TF::GOLD,
            2 => TF::GRAY,
            default => TF::DARK_RED
        };

        $title = new FloatingTextParticle(
            $color . TF::BOLD . $spot->rankLabel() . TF::RESET . TF::WHITE . " - " . $mp->getName()
        );
        $stats = new FloatingTextParticle(TF::YELLOW . $spot->describeStats());

        $headPos = $spot->standPosition->add(0, self::DEFAULT_EYE_HEIGHT + self::FLOATING_TEXT_HEIGHT, 0);
        $titlePos = $headPos->add(0, 0.25, 0);
        $this->world->addParticle($titlePos, $title, $targets);
        $this->world->addParticle($headPos, $stats, $targets);

        // Remembered so removeFloatingText() can despawn these exact
        // instances, to these exact targets, at these exact positions,
        // later - see its doc comment. Appended rather than overwritten:
        // resyncViewer() calls this again per late joiner with brand-new
        // particle instances and a different (single-player) target list,
        // and all of those batches need to be cleaned up independently.
        $this->floatingTextByRank[$spot->rank][] = [
            "particles" => [$title, $stats],
            "positions" => [$titlePos, $headPos],
            "targets" => $targets
        ];
    }

    /**
     * Undoes sendFloatingText() for a single rank: despawns the two
     * invisible hologram entities (the "#1 - PlayerName" title and the
     * stats line underneath it) it created above that spot's pedestal.
     *
     * This has to exist and actually be called from stop(), because
     * FloatingTextParticle's entities aren't tracked as real server-side
     * Entity objects - they're pure client-side actors that only go away
     * when the *same particle instance*, now flipped invisible via
     * setInvisible(true), is re-sent at the *same position* it was
     * originally spawned at (the same despawn pattern already used by
     * Hologram.php / HologramManager.php elsewhere in this codebase).
     * sendFloatingText() never did that, so the hologram just kept
     * sitting there on the client - normally hidden again the moment the
     * player's world truly unloads, but not on the "Play Again"/
     * instant-requeue path (see hideOccupantNametags()'s own doc comment
     * above) where the same Player connection carries straight into a
     * brand-new match on the same map without a full disconnect: nothing
     * ever told that specific client's already-open session to remove the
     * old actor, so the stale "#1 - PlayerName" / stats text from the
     * previous ceremony was still floating there above the (now empty)
     * pedestal.
     *
     */
    private function removeFloatingText(int $rank) : void{
        $batches = $this->floatingTextByRank[$rank] ?? [];

        foreach($batches as $batch){
            $targets = array_values(array_filter($batch["targets"], static fn(Player $p) : bool => $p->isConnected()));
            if($targets === []){
                continue;
            }
            foreach($batch["particles"] as $i => $particle){
                $particle->setInvisible(true);
                $this->world->addParticle($batch["positions"][$i], $particle, $targets);
            }
        }

        unset($this->floatingTextByRank[$rank]);
    }

    /**
     * Removes every rank's floating hologram that actually got shown this
     * ceremony (only ranks with something recorded in
     * $floatingTextByRank - a rank whose reveal never happened, e.g. the
     * ceremony got cut short, never had anything to clean up). Called
     * once from stop().
     */
    private function removeAllFloatingText() : void{
        foreach(array_keys($this->floatingTextByRank) as $rank){
            $this->removeFloatingText($rank);
        }
    }

    /**
     * MVP-only flourish: a large ring particle wave plus a louder sound
     * repeated a couple of times through the MVP hold, and once, right
     * at the start, the big "MVP OF THE GAME" title for everyone except
     * the MVP themselves (who gets their own personal line instead,
     * since they're already watching their own moment play out).
     */
    private function tickMvpFlourish() : void{
        $spot = $this->spots[1] ?? null;
        if($spot === null){
            return;
        }

        if($this->phaseElapsed === 0){
            $mp = $spot->session->getPlayer();
            $viewers = $this->currentViewers();

            foreach($viewers as $player){
                if($player === $mp){
                    if($player->isConnected()){
                        $player->sendTitle(
                            ColorUtils::translate("{GOLD}{BOLD}YOU ARE THE MVP!"),
                            ColorUtils::translate("{YELLOW}" . $spot->describeStats()),
                            10, 60, 20
                        );
                    }
                    continue;
                }
                $player->sendTitle(
                    ColorUtils::translate("{GOLD}{BOLD}MVP OF THE GAME"),
                    ColorUtils::translate("{YELLOW}" . $mp->getName() . " {GRAY}- " . $spot->describeStats()),
                    10, 60, 20
                );
            }

            $this->mvpParticleWave($spot);
        }

        $this->mvpBurstElapsed++;
        if($this->mvpBurstElapsed >= 3 * self::TICKS_PER_SECOND){
            $this->mvpBurstElapsed = 0;
            $this->mvpParticleWave($spot);
        }
    }

    private function mvpParticleWave(PodiumSpot $spot) : void{
        $mp = $spot->session->getPlayer();
        if(!$mp->isConnected()){
            return;
        }

        $viewers = $this->currentViewers();
        $center = $spot->standPosition->add(0, 1.0, 0);

        foreach($viewers as $player){
            if(!$player->isConnected()){
                continue;
            }
            try{
                $player->getNetworkSession()->sendDataPacket(
                    PlaySoundPacket::create("random.levelup", $center->x, $center->y, $center->z, 1.2, 0.9)
                );
            }catch(\Throwable){
            }
        }

        $ring = [new EnchantmentTableParticle(), new HappyVillagerParticle(), new PortalParticle(), new CriticalParticle()];
        $particle = $ring[mt_rand(0, count($ring) - 1)];

        $vcount = count($viewers);
        if($vcount <= 0){
            return;
        }
        $mult = $vcount > 8 ? max(0.35, 8.0 / $vcount) : 1.0;
        $count = max(1, (int) round(50 * $mult));

        for($i = 0; $i < $count; $i++){
            $angle = ($i / $count) * 2 * M_PI;
            $radius = 1.6 + (mt_rand(0, 40) / 100);
            $pos = new Vector3(
                $center->x + cos($angle) * $radius,
                $center->y + (mt_rand(0, 140) / 100),
                $center->z + sin($angle) * $radius
            );
            $this->world->addParticle($pos, $particle, $viewers);
        }

        foreach($viewers as $player){
            if($player !== $mp){
                try{
                    CinematicCamera::shake($player, 0.15, 0.5);
                }catch(\Throwable){
                }
            }
        }
    }

    /**
     * @param Player[] $viewers
     */
    private function broadcastCamera(array $viewers) : void{
        $pose = $this->computePose();
        if($pose === null){
            return;
        }

        foreach($viewers as $player){
            try{
                CinematicCamera::setPosition($player, $pose["position"], $pose["lookAt"]);
            }catch(\Throwable){
            }
        }
    }

    /**
     * @return array{position: Vector3, lookAt: Vector3}|null
     */
    private function computePose() : ?array{
        return match($this->phase){
            self::PHASE_FADE => $this->focusPose($this->initialFocusCenter(), self::SPOT_ORBIT_RADIUS, self::SPOT_ORBIT_HEIGHT, 0.0),
            self::PHASE_SPOT, self::PHASE_MVP => $this->focusOrbitPose(),
            self::PHASE_FINALE => $this->finaleOrbitPose(),
            default => null
        };
    }

    private function initialFocusCenter() : Vector3{
        if($this->revealQueue !== []){
            $rank = array_values($this->revealQueue)[0];
            return $this->spots[$rank]->standPosition;
        }
        if(isset($this->spots[1])){
            return $this->spots[1]->standPosition;
        }
        return $this->podiumCenter;
    }

    private function focusOrbitPose() : ?array{
        if($this->currentFocusRank === null || !isset($this->spots[$this->currentFocusRank])){
            return null;
        }

        $center = $this->spots[$this->currentFocusRank]->standPosition;
        $seconds = $this->phaseElapsed / self::TICKS_PER_SECOND;
        $angle = $seconds * self::SPOT_ORBIT_REVOLUTIONS_PER_SEC * 2 * M_PI;

        return $this->focusPose($center, self::SPOT_ORBIT_RADIUS, self::SPOT_ORBIT_HEIGHT, $angle);
    }

    private function finaleOrbitPose() : array{
        $seconds = $this->phaseElapsed / self::TICKS_PER_SECOND;
        $angle = $seconds * self::FINALE_ORBIT_REVOLUTIONS_PER_SEC * 2 * M_PI;

        return $this->focusPose($this->podiumCenter, self::FINALE_ORBIT_RADIUS, self::FINALE_ORBIT_HEIGHT, $angle);
    }

    /**
     * @return array{position: Vector3, lookAt: Vector3}
     */
    private function focusPose(Vector3 $center, float $radius, float $height, float $angle) : array{
        $eye = $center->add(0, self::DEFAULT_EYE_HEIGHT, 0);

        $position = new Vector3(
            $center->x + $radius * cos($angle),
            $center->y + self::DEFAULT_EYE_HEIGHT + $height,
            $center->z + $radius * sin($angle)
        );

        return ["position" => $position, "lookAt" => $eye];
    }

}
