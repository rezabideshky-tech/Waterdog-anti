<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\form\PlayOrManageForm;
use sergittos\bedwars\form\PlayOrSetupForm;
use sergittos\bedwars\game\BedWarsGame;
use sergittos\bedwars\game\Matchmaker;
use sergittos\bedwars\game\shop\item\editor\ShopEditorManager;
use sergittos\bedwars\session\SessionFactory;
use function json_encode;
use function time;

final class JoinListener implements Listener{

    private const PARTY_FOLLOW_RETRIES = 6;
    private const PARTY_FOLLOW_MAX_AGE = 60;

    // Must always match RejoinDisconnectListener::REJOIN_TTL - that listener is the
    // one that stamps the database record and the local per-session countdown when
    // a player disconnects, this one just has to agree on how long that record is
    // considered valid. Previously this was hardcoded to a different value (60s)
    // than the disconnect side (45s/120s), which was the source of the "rejoin
    // works sometimes" bug: the DB row could still look valid here after the local
    // team slot had already been purged, or vice-versa.
    private const REJOIN_WINDOW_SECONDS = RejoinDisconnectListener::REJOIN_TTL;
    private const REJOIN_TRY_RETRIES = 10;
    private const REJOIN_TRY_DELAY_TICKS = 10;

    // How many times (and how far apart) to re-check the database for a
    // pending rejoin row before concluding there really isn't one. setRejoin()
    // on disconnect and this lookup on (re)join are two separate database
    // round-trips - a player who leaves and comes straight back (lobby, then
    // the same mode server) can easily have their join reach this check
    // before their own disconnect's write has finished landing. Without
    // retrying, that race made a perfectly valid, in-window rejoin look like
    // "no pending rejoin" and silently dropped the player into fresh
    // matchmaking instead of showing the rejoin prompt - even with time left
    // and their bed intact. Mirrors CoreListener::REJOIN_LOOKUP_RETRIES on
    // the lobby side, which already retried for exactly this reason.
    private const REJOIN_LOOKUP_RETRIES = 10;
    private const REJOIN_LOOKUP_DELAY_TICKS = 10;

    public function onJoin(PlayerJoinEvent $event): void{
        $player = $event->getPlayer();

        // Fire-and-forget: fetches this player's saved Item Shop "Main" tab
        // layout and applies it once it lands. Independent of the rejoin
        // flow below - the shop editor's own persistence has nothing to do
        // with mid-match reconnects, it just needs to happen once per join.
        ShopEditorManager::load($player);

        BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void{
            if(!$player->isConnected() || !SessionFactory::hasSession($player)){
                return;
            }

            $this->tryPromptRejoin($player, 0, function() use ($player): void{
                if(!$player->isConnected()){
                    return;
                }

                if($player->hasPermission("bedwars.admin") || $player->hasPermission("bedwars.setup")){
                    $player->sendForm(new PlayOrSetupForm(function() use ($player): void{
                        $this->checkPartyFollow($player);
                    }));
                    return;
                }

                if($player->hasPermission("bedwars.moderator")){
                    $player->sendForm(new PlayOrManageForm(function() use ($player): void{
                        $this->checkPartyFollow($player);
                    }));
                    return;
                }

                $this->checkPartyFollow($player);
            });
        }), 5);
    }

    private function tryPromptRejoin(Player $player, int $attempt, \Closure $fallback): void{
        BedWarsCore::getInstance()->getProvider()->getRejoin($player->getName(), function(?array $row) use ($player, $attempt, $fallback): void{
            if(!$player->isConnected() || !SessionFactory::hasSession($player)){
                return;
            }

            // DIAGNOSTIC LOGGING - disabled (console log silenced by request).
            // Shows exactly what the DB returned for this player's rejoin lookup and
            // which branch is about to be taken, so a "rejoin does nothing" report can
            // be root-caused from the console log instead of re-reading this file.
            // BedWarsCore::getInstance()->getLogger()->info(
            //     "[Rejoin] tryPromptRejoin(" . $player->getName() . ", attempt=" . $attempt . "): row=" . ($row === null ? "null" : json_encode($row))
            // );

            if($row === null){
                // Don't give up on the first empty result IF there's actual
                // reason to suspect a race - setRejoin() on the disconnect side
                // is an async database write that can still be in flight when a
                // fast reconnect (lobby, then straight back to the same mode
                // server) lands this SELECT before it. RejoinDisconnectListener
                // leaves an in-memory hint for exactly that window, so re-check
                // a few times, a short delay apart, before actually concluding
                // there's nothing to rejoin - see REJOIN_LOOKUP_RETRIES.
                //
                // For everyone else - the overwhelming majority of joins, since
                // most players never had a mid-match disconnect at all - there
                // is no hint, so we know right away there's nothing to wait for
                // and can fall through immediately. This used to retry blindly
                // on every null result regardless of the hint, which meant every
                // single join (including fresh players and staff opening setup)
                // paid a flat ~5 second delay before ever reaching the waiting
                // lobby / setup form.
                if($attempt < self::REJOIN_LOOKUP_RETRIES && RejoinDisconnectListener::hasRecentWrite($player->getName())){
                    BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(
                        new ClosureTask(function() use ($player, $attempt, $fallback): void{
                            $this->tryPromptRejoin($player, $attempt + 1, $fallback);
                        }),
                        self::REJOIN_LOOKUP_DELAY_TICKS
                    );
                    return;
                }

                $fallback();
                return;
            }

            $network = BedWarsCore::getInstance()->getNetworkManager();
            $targetServer = (string) ($row["target_server"] ?? "");
            $created = (int) ($row["created_at"] ?? 0);
            $left = self::REJOIN_WINDOW_SECONDS - (time() - $created);

            if($targetServer === "" || $left <= 0){
                // BedWarsCore::getInstance()->getLogger()->info("[Rejoin] " . $player->getName() . ": record found but expired/invalid (targetServer='" . $targetServer . "', left=" . $left . "s) - clearing and falling back.");
                BedWarsCore::getInstance()->getProvider()->clearRejoin($player->getName());
                $fallback();
                return;
            }

            if($targetServer !== $network->getServerName()){
                // Expected/harmless case: the record points at a different server in the
                // network (e.g. this player disconnected from bedwars-game-3 but just
                // joined bedwars-game-1) - CoreListener's lobby-side prompt is what's
                // supposed to route them back, not this listener.
                // BedWarsCore::getInstance()->getLogger()->info("[Rejoin] " . $player->getName() . ": record targets '" . $targetServer . "' but this server is '" . $network->getServerName() . "' - not for this server, falling back to normal join.");
                $fallback();
                return;
            }

            // Only auto-rejoin when the player actually asked for it by
            // right-clicking their Rejoin Ticket on the lobby (which stamps
            // this flag via AsyncMysqlProvider::confirmRejoin() right before
            // transferring them here - see CoreListener::onItemUse()).
            //
            // Without this check, a player who instead ignores the ticket
            // and queues into a brand new match through normal matchmaking
            // (Matchmaker::queue()) could land on the very server that's
            // still running their old, still-valid-in-the-DB match and get
            // silently pulled back into it instead of the fresh one they
            // just chose - overriding their own decision with no way to
            // opt out short of waiting for the record to expire. If it
            // isn't confirmed, this is just an ordinary join: leave the DB
            // record completely untouched (it may still be legitimately
            // picked up later, from the lobby or another retry) and fall
            // through to normal matchmaking/menus.
            $confirmed = (int) ($row["confirmed"] ?? 0) === 1;
            if(!$confirmed){
                // BedWarsCore::getInstance()->getLogger()->info(
                //     "[Rejoin] " . $player->getName() . ": record found for this server but not confirmed by the player yet - not auto-rejoining, falling back to normal join."
                // );
                $fallback();
                return;
            }

            $gameId = (int) ($row["game_id"] ?? -1);
            $team = (string) ($row["team"] ?? "");

            // The player already actively chose to come back by
            // right-clicking their Rejoin Ticket on the lobby server
            // (confirmed above), so asking again here would just be a
            // second, redundant prompt. Auto-rejoin now.
            $this->tryRejoinNow($player, $gameId, $team, 0, $fallback);
        });
    }

    private function tryRejoinNow(Player $player, int $gameId, string $team, int $attempt, \Closure $fallback): void{
        if(!$player->isConnected() || !SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $game = BedWarsGame::getInstance()->getGameManager()->getGameById($gameId);

        if($game === null){
            // BedWarsCore::getInstance()->getLogger()->info("[Rejoin] tryRejoinNow(" . $player->getName() . ", attempt=" . $attempt . "): game #" . $gameId . " not found on this server (already ended/reset?).");
        }

        if($game !== null && $game->rejoinPlayer($session, $team)){
            BedWarsCore::getInstance()->getProvider()->clearRejoin($player->getName());
            $player->sendMessage("§a§lWELCOME BACK §r§8» §fYou've rejoined your match §7- §agood luck!");
            return;
        }

        if($attempt < self::REJOIN_TRY_RETRIES){
            $player->sendActionBarMessage(TF::AQUA . TF::BOLD . "Reconnecting you to your match" . TF::RESET . TF::GRAY . "...");
            BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player, $gameId, $team, $attempt, $fallback): void{
                    $this->tryRejoinNow($player, $gameId, $team, $attempt + 1, $fallback);
                }),
                self::REJOIN_TRY_DELAY_TICKS
            );
            return;
        }

        BedWarsCore::getInstance()->getProvider()->clearRejoin($player->getName());
        $player->sendMessage("§c§lMATCH UNAVAILABLE §r§8» §7Your previous match could no longer be restored. §eFinding you a new one...");
        $fallback();
    }

    private function checkPartyFollow(Player $player): void{
        if(!$player->isConnected()){
            return;
        }

        BedWarsCore::getInstance()->getProvider()->getPartyFollow($player->getName(), function(?array $row) use ($player): void{
            if(!$player->isConnected()){
                return;
            }

            $network = BedWarsCore::getInstance()->getNetworkManager();
            $valid = $row !== null
                && $row["target_server"] === $network->getServerName()
                && (time() - (int) $row["created_at"]) <= self::PARTY_FOLLOW_MAX_AGE;

            if(!$valid){
                Matchmaker::queue($player, 0);
                return;
            }

            BedWarsCore::getInstance()->getProvider()->clearPartyFollow($player->getName());
            $this->tryFollowLeader($player, (string) $row["leader"], 0);
        });
    }

    private function tryFollowLeader(Player $player, string $leader, int $attempt): void{
        if(!$player->isConnected() || !SessionFactory::hasSession($player)){
            return;
        }

        $game = BedWarsGame::getInstance()->getGameManager()->findGameByMemberUsername($leader);
        if($game !== null){
            $game->addPlayer(SessionFactory::getSession($player));
            $player->sendMessage(TF::AQUA . "Joined your party leader's match!");
            return;
        }

        if($attempt < self::PARTY_FOLLOW_RETRIES){
            $player->sendActionBarMessage(TF::YELLOW . "Waiting for your party leader...");
            BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player, $leader, $attempt): void{
                    $this->tryFollowLeader($player, $leader, $attempt + 1);
                }),
                10
            );
            return;
        }

        $player->sendMessage(TF::YELLOW . "Couldn't find your party leader's match. Joining normally...");
        Matchmaker::queue($player, 0);
    }
}