<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\game\stage\PlayingStage;
use sergittos\bedwars\session\SessionFactory;
use function floor;
use function time;

/**
 * Sole owner of the "rejoin" grace-period flow triggered by a mid-match disconnect.
 *
 * This is the ONLY listener allowed to create/refresh a pending rejoin for a
 * playing session that still has a team during PlayingStage. Keeping this in a
 * single place (instead of duplicating it in GameListener, as the plugin used
 * to do) is what keeps the database record, the in-memory countdown and the
 * chat announcement perfectly in sync with the window JoinListener checks
 * when the player comes back.
 */
final class RejoinDisconnectListener implements Listener{

    /** Grace window, in seconds, a disconnected player has to rejoin their match. */
    public const REJOIN_TTL = 120;

    // How long a "write in flight" hint stays valid, in seconds. setRejoin()
    // below is a fire-and-forget async database write with no completion
    // callback, so JoinListener can't know for certain when it has landed.
    // This just needs to comfortably outlast a real DB round trip (well
    // under a second in practice) so JoinListener::tryPromptRejoin() knows
    // whether it's worth retrying its lookup at all - see hasRecentWrite().
    private const PENDING_WRITE_HINT_SECONDS = 5;

    /**
     * In-memory hint for JoinListener: lowercase username => unix timestamp
     * the matching setRejoin() write was fired off at. This lets a (re)join
     * skip straight to the "no pending rejoin" fallback instead of blindly
     * retrying the database lookup for every single join - the retries only
     * ever matter for the rare case where a player disconnects mid-match and
     * reconnects to this exact same server process before the async write
     * above has landed. Every other join (the overwhelming majority: fresh
     * players, players entering setup, players with no rejoin at all) used
     * to pay for that race's fix with a flat multi-second delay before ever
     * reaching the waiting lobby / setup form, since the old code retried a
     * fixed number of times on ANY null result, not just a suspected race.
     *
     * @var array<string,int>
     */
    private static array $pendingWrites = [];

    public static function hasRecentWrite(string $username): bool{
        $key = strtolower($username);
        $stamp = self::$pendingWrites[$key] ?? null;
        if($stamp === null){
            return false;
        }

        if(time() - $stamp > self::PENDING_WRITE_HINT_SECONDS){
            // Stale - either the write already landed a while ago (in which
            // case the DB lookup above already found it and this hint is no
            // longer needed) or something went wrong. Either way, don't let
            // future joins retry forever off a leftover flag.
            unset(self::$pendingWrites[$key]);
            return false;
        }

        return true;
    }

    public function onQuit(PlayerQuitEvent $event): void{
        $player = $event->getPlayer();

        // DIAGNOSTIC LOGGING (disabled - console output silenced by request):
        // every early return below is a reason a rejoin record does NOT get
        // created for this disconnect. Lines kept commented out, not removed,
        // so they can be quickly re-enabled if this ever needs debugging again.
        if(!SessionFactory::hasSession($player)){
            // $logger->info("[Rejoin] onQuit: no session for " . $player->getName() . " - skipping (nothing to persist).");
            return;
        }

        $session = SessionFactory::getSession($player);
        $game = $session->getGame();
        if($game === null){
            // $logger->info("[Rejoin] onQuit: " . $player->getName() . " has a session but session->getGame() is null - not in a match, skipping.");
            return;
        }

        if(!$session->isPlaying() || !$session->hasTeam()){
            // $logger->info("[Rejoin] onQuit: " . $player->getName() . " - isPlaying=" . ($session->isPlaying() ? "true" : "false") . " hasTeam=" . ($session->hasTeam() ? "true" : "false") . " - skipping (not an active team member, e.g. spectator).");
            return;
        }

        if(!$game->getStage() instanceof PlayingStage){
            // $logger->info("[Rejoin] onQuit: " . $player->getName() . " - game stage is " . get_class($game->getStage()) . ", not PlayingStage - skipping.");
            return;
        }

        if($session->hasPendingRejoin()){
            // $logger->info("[Rejoin] onQuit: " . $player->getName() . " already has a pending rejoin window open - not overwriting it.");
            return;
        }

        $team = $session->getTeam();
        $aliveTeams = $game->getAliveTeams();

        // Decisive-duel case: this player is the LAST member of their team,
        // and only one other team is still alive. Their disconnect eliminates
        // this team right now and hands the win to the other one - exactly
        // like a normal death/quit elimination would. Granting a rejoin grace
        // period here would instead leave a disconnected "ghost" member
        // sitting in the team roster (Team::isAlive() still true) for up to
        // REJOIN_TTL seconds, blocking the match from ending even though the
        // outcome is already decided. No rejoin record is written, so there
        // is no lobby prompt and no auto-rejoin on the next game-server join
        // for this disconnect.
        if($team !== null && $team->getMembersCount() === 1 && count($aliveTeams) === 2){
            // $logger->info(
            //     "[Rejoin] onQuit: " . $player->getName() . " was the last member of team " . $team->getName() .
            //     " with only one other team alive - finishing the match now instead of granting a rejoin window."
            // );
            $game->broadcastMessage($team->getColor() . $session->getUsername() . " §cdisconnected");
            $game->removePlayer($session, false);
            return;
        }

        // $logger->info("[Rejoin] onQuit: " . $player->getName() . " qualifies - writing rejoin record (game=" . $game->getId() . ", team=" . $session->getTeam()?->getName() . ") and broadcasting.");

        self::$pendingWrites[strtolower($session->getUsername())] = time();

        $core = BedWarsCore::getInstance();
        $core->getProvider()->setRejoin(
            $session->getUsername(),
            $core->getNetworkManager()->getServerName(),
            $game->getId(),
            $session->getTeam()->getName(),
            $game->getModeName()
        );

        $session->setPendingRejoinUntil(time() + self::REJOIN_TTL);

        $game->broadcastMessage($session->getTeam()->getColor() . $session->getUsername() . " §cdisconnected");
    }

    public static function formatWindow(): string{
        $minutes = (int) floor(self::REJOIN_TTL / 60);
        if($minutes > 0 && self::REJOIN_TTL % 60 === 0){
            return $minutes . " minute" . ($minutes === 1 ? "" : "s");
        }
        return self::REJOIN_TTL . "s";
    }
}
