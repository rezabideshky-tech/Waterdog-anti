<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\bounty;

use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\session\Session;
use function array_values;
use function count;
use function spl_object_id;

/**
 * In-match, in-memory Bounty system.
 *
 * Bounty status is driven entirely by performance inside the *current*
 * match (kills / final kills / bed breaks racked up since a player's last
 * death), lives only in this object's own arrays, and is never read from or
 * written to any database or player profile - it is wiped the instant the
 * match ends (see reset(), called from Game::setStage() on the transition
 * into EndingStage and again from Game::reset() when the world/Game
 * instance is recycled for its next match). The only thing that survives a
 * Bounty beyond the moment it ends is the normal coin/XP reward handed to
 * whoever claims it - exactly like any other in-match reward (a kill, a
 * bed break), and no different from those in terms of persistence; the
 * Bounty *flag* itself, and the momentum score that leads up to it, are
 * pure runtime state.
 *
 * One instance of this class belongs to exactly one Game (see
 * Game::$bountyManager / Game::getBountyManager()).
 */
final class BountyManager{

    /** Momentum points awarded for a normal kill. */
    private const POINTS_KILL = 2;

    /** Momentum points awarded for a final kill (bed already destroyed). */
    private const POINTS_FINAL_KILL = 4;

    /** Momentum points awarded for destroying an enemy bed. */
    private const POINTS_BED_BREAK = 5;

    /**
     * Momentum needed to become a Bounty from kills/final kills alone
     * (e.g. three normal kills, or one normal + one final kill, in the
     * same life). Breaking a bed always triggers a Bounty by itself,
     * regardless of this threshold - see registerBedBreak().
     */
    private const TRIGGER_THRESHOLD = 5;

    /** At most this many players can be a Bounty at the same time, match-wide. */
    public const MAX_ACTIVE_BOUNTIES = 2;

    /** Coin reward for whoever kills a Bounty target. */
    public const REWARD_COINS = 25;

    /** XP reward for whoever kills a Bounty target. */
    public const REWARD_XP = 20;

    /** @var array<int, int> spl_object_id(Session) => in-match momentum score since their last death */
    private array $momentum = [];

    /** @var array<int, Session> spl_object_id(Session) => Session, currently active Bounty targets */
    private array $active = [];

    public function __construct(
        private Game $game
    ){}

    public function isBounty(Session $session): bool{
        return isset($this->active[spl_object_id($session)]);
    }

    /** @return Session[] */
    public function getActiveBounties(): array{
        return array_values($this->active);
    }

    /**
     * Call whenever a kill or final kill is credited to $killer.
     */
    public function registerKill(Session $killer, bool $isFinalKill): void{
        $this->addMomentum($killer, $isFinalKill ? self::POINTS_FINAL_KILL : self::POINTS_KILL);
    }

    /**
     * Call whenever $destroyer breaks an enemy bed. Unlike a normal kill,
     * breaking a bed is treated as "excessive" on its own merit and always
     * attempts to trigger a Bounty immediately, independent of the usual
     * momentum threshold (still subject to the MAX_ACTIVE_BOUNTIES cap and
     * to the destroyer needing to still be on a team).
     */
    public function registerBedBreak(Session $destroyer): void{
        $id = spl_object_id($destroyer);
        $this->momentum[$id] = ($this->momentum[$id] ?? 0) + self::POINTS_BED_BREAK;
        $this->tryActivate($destroyer);
    }

    private function addMomentum(Session $session, int $points): void{
        $id = spl_object_id($session);
        $score = ($this->momentum[$id] ?? 0) + $points;
        $this->momentum[$id] = $score;

        if($score >= self::TRIGGER_THRESHOLD){
            $this->tryActivate($session);
        }
    }

    private function tryActivate(Session $session): void{
        if($this->isBounty($session)){
            return;
        }

        if(count($this->active) >= self::MAX_ACTIVE_BOUNTIES){
            return;
        }

        if(!$session->hasTeam()){
            return;
        }

        $this->active[spl_object_id($session)] = $session;
        $session->setBounty(true);

        // Instant nametag refresh instead of waiting for the once-a-second
        // tick in PlayingStage::tickPlayersAndSpectators() - a Bounty
        // should be visibly marked the moment it triggers.
        $session->getTeam()?->refreshNametag($session);

        $this->game->broadcastMessage(
            "{DARK_RED}{BOLD}BOUNTY ALERT > {RESET}" . $session->getColoredUsername() .
            " {GRAY}is dominating this match! {YELLOW}Kill them for a bonus reward!"
        );

        // Client-side toast card, top-right above the scoreboard - see the
        // "bounty_toast" control in the Arvan UI resource pack's
        // ui/hud_screen.json. That UI recognizes any chat line starting
        // with the literal 7-character prefix "bounty." and renders it as
        // that card instead of a normal chat line; hud_screen.json's
        // "chat_label" $condition also hides any such line from the
        // regular chat log, so this never doubles up with the broadcast
        // above or clutters chat for anyone. Deliberately sent only here,
        // on activation - not from onDeath()/reset() - since the person
        // asked for this popup strictly for "someone just became a
        // Bounty", nothing else. Uses a fixed colour rather than the
        // target's team colour so the card reads the same regardless of
        // which team is hunted.
        $this->game->broadcastMessage("bounty.{BLUE}{BOLD}" . $session->getUsername());
    }

    /**
     * Call for the victim of every single death in the match while it is
     * still running, Bounty or not - Session::kill() is the single call
     * site. Momentum always resets to zero on death (a fresh climb back up
     * is required to become a Bounty again - see the class doc comment),
     * and an active Bounty always ends on death: if $killer is set, they
     * claim the reward; otherwise (void, self-inflicted, disconnect-as-
     * death, etc.) the Bounty simply expires with no reward.
     *
     * If $killer happens to be a Bounty target themselves, their own
     * Bounty status is left completely untouched here - only $victim's
     * state is read or modified - so a Bounty killing another Bounty keeps
     * their own flag and additionally claims this reward (the "double
     * reward for high risk" case).
     */
    public function onDeath(Session $victim, ?Session $killer): void{
        $id = spl_object_id($victim);
        unset($this->momentum[$id]);

        if(!isset($this->active[$id])){
            return;
        }

        unset($this->active[$id]);
        $victim->setBounty(false);
        $victim->getTeam()?->refreshNametag($victim);

        if($killer !== null && $killer !== $victim){
            $killer->addCoins(self::REWARD_COINS);
            $killer->addXp(self::REWARD_XP, "Bounty Claimed");

            $this->game->broadcastMessage(
                "{GOLD}{BOLD}BOUNTY CLAIMED > {RESET}" . $killer->getColoredUsername() .
                " {GRAY}took down the Bounty target " . $victim->getColoredUsername() .
                "{GRAY}! {GREEN}+" . self::REWARD_COINS . " Coins"
            );
        }else{
            $this->game->broadcastMessage(
                "{GRAY}{BOLD}BOUNTY EXPIRED > {RESET}" . $victim->getColoredUsername() .
                " {GRAY}is no longer being hunted."
            );
        }
    }

    /**
     * Drops the Bounty (silently - the "TEAM ELIMINATED" broadcast that
     * triggered this already covers the announcement) for anyone still
     * flagged on a team that just got wiped. Covers the one path a Bounty
     * could otherwise outlive its target's team: the last member of a team
     * disconnecting/leaving without ever dying through Session::kill()
     * (see PlayingStage::onQuit()) - every other elimination path already
     * runs through onDeath() first.
     */
    public function onTeamEliminated(Team $team): void{
        foreach($team->getRoster() as $session){
            $id = spl_object_id($session);
            if(!isset($this->active[$id])){
                continue;
            }

            unset($this->active[$id]);
            unset($this->momentum[$id]);
            $session->setBounty(false);
        }
    }

    /**
     * Wipes every bit of Bounty/momentum state. Called once the match ends
     * (Game::setStage() transitioning into EndingStage) and again when the
     * Game instance is recycled for its next match (Game::reset()) so
     * nothing from this match can ever leak into the next one hosted by
     * the same instance. Nothing here is persisted anywhere, so clearing
     * the in-memory arrays is the entire cleanup.
     */
    public function reset(): void{
        foreach($this->active as $session){
            $session->setBounty(false);
        }

        $this->active = [];
        $this->momentum = [];
    }
}
