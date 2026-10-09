<?php

declare(strict_types=1);

namespace sergittos\bedwars\rejoin;

use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use function strtolower;
use function time;

/**
 * Owns the "Rejoin Ticket" item lifecycle for players sitting on a lobby
 * server with a still-valid pending rejoin (see CoreListener::onJoin() ->
 * trySendRejoinTicket()).
 *
 * Why this exists as its own class instead of living inline in CoreListener:
 * LobbyItems::give() (BedWarsLobby) rebuilds the player's ENTIRE hotbar -
 * including a clearAll() - every time it runs (fresh join, respawn after
 * dying in the lobby, etc.). BedWarsLobby depends on BedWarsCore (not the
 * other way around), so LobbyItems can safely call back into this manager
 * right after it repopulates the hotbar to re-place the ticket if one is
 * active - see reapplyIfActive(). Without that hook, a death/respawn in the
 * lobby while a rejoin countdown was showing would silently wipe it early,
 * with nothing to bring it back until the next countdown tick (up to
 * TICK_INTERVAL_TICKS later) - or not at all if the countdown had already
 * fired its last tick before expiring.
 *
 * @var array<string, array{targetServer: string, mode: string, team: string, expiresAt: int}>
 */
final class RejoinTicketManager{

    /** How often the countdown lore refreshes and the expiry is re-checked. 1s: cheap (only players with an active ticket pay for it) and keeps the on-screen countdown feeling live. */
    private const TICK_INTERVAL_TICKS = 20;

    /** @var array<string, array{targetServer: string, mode: string, team: string, expiresAt: int}> lowercase username => ticket state */
    private static array $active = [];

    public static function give(Player $player, string $targetServer, string $mode, string $team, int $secondsLeft): void{
        if(!$player->isOnline()){
            return;
        }

        $key = strtolower($player->getName());
        $expiresAt = time() + $secondsLeft;
        $isNew = !isset(self::$active[$key]);

        self::$active[$key] = [
            "targetServer" => $targetServer,
            "mode"         => $mode,
            "team"         => $team,
            "expiresAt"    => $expiresAt,
        ];

        self::place($player, $mode, $team, $secondsLeft, $targetServer);

        if($isNew){
            $player->sendMessage(
                TF::AQUA . TF::BOLD . "UNFINISHED MATCH" . TF::RESET . TF::GRAY . " » " .
                TF::WHITE . "Check your hotbar - right-click the clock to jump back into your match!"
            );

            self::scheduleTick($player, $key);
        }
    }

    /**
     * Re-places the ticket item for this player if (and only if) they
     * currently have an active, unexpired ticket - called by LobbyItems
     * (BedWarsLobby) right after it rebuilds the hotbar, so a lobby-side
     * inventory reset (fresh join, respawn, ...) never permanently loses
     * the ticket out from under an active countdown.
     */
    public static function reapplyIfActive(Player $player): void{
        $key = strtolower($player->getName());
        $state = self::$active[$key] ?? null;
        if($state === null){
            return;
        }

        $left = $state["expiresAt"] - time();
        if($left <= 0){
            // Already expired - let the running countdown tick (or the next
            // one, if it hasn't fired yet) handle the cleanup/message/clearRejoin,
            // rather than duplicating that logic here.
            return;
        }

        self::place($player, $state["mode"], $state["team"], $left, $state["targetServer"]);
    }

    public static function isActive(Player $player): bool{
        return isset(self::$active[strtolower($player->getName())]);
    }

    public static function clear(Player $player): void{
        unset(self::$active[strtolower($player->getName())]);
    }

    private static function place(Player $player, string $mode, string $team, int $secondsLeft, string $targetServer): void{
        if(!$player->isOnline()){
            return;
        }
        $player->getInventory()->setItem(RejoinTicketItem::HOTBAR_SLOT, RejoinTicketItem::build($targetServer, $mode, $team, $secondsLeft));
    }

    private static function scheduleTick(Player $player, string $key): void{
        BedWarsCore::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $key): void{
            self::tick($player, $key);
        }), self::TICK_INTERVAL_TICKS);
    }

    private static function tick(Player $player, string $key): void{
        $state = self::$active[$key] ?? null;
        if($state === null){
            // Cleared elsewhere (successful rejoin transfer, manual clear, ...) - nothing left to do.
            return;
        }

        if(!$player->isConnected()){
            // Player disconnected with the ticket still pending - the DB
            // record itself is left untouched (still valid for whichever
            // server they reconnect to next), only this in-memory state is
            // dropped so it doesn't keep ticking forever for an offline player.
            unset(self::$active[$key]);
            return;
        }

        $left = $state["expiresAt"] - time();
        if($left <= 0){
            unset(self::$active[$key]);

            BedWarsCore::getInstance()->getProvider()->clearRejoin($player->getName());

            if($player->isOnline()){
                $inv = $player->getInventory();
                if(RejoinTicketItem::isTicket($inv->getItem(RejoinTicketItem::HOTBAR_SLOT))){
                    $inv->clear(RejoinTicketItem::HOTBAR_SLOT);
                }

                $player->sendMessage(
                    TF::RED . TF::BOLD . "TIME'S UP" . TF::RESET . TF::GRAY . " » " .
                    TF::WHITE . "Your rejoin window has expired - that match spot is gone."
                );
            }

            return;
        }

        self::place($player, $state["mode"], $state["team"], $left, $state["targetServer"]);
        self::scheduleTick($player, $key);
    }
}
