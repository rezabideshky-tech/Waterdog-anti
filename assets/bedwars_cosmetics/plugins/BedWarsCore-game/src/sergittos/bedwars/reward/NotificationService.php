<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use function array_shift;
use function count;
use function strtolower;

/**
 * Central, unified "boxed" notification system used by every reward feature
 * (playtime rewards, daily login streak, and anything added later) across
 * both the lobby and the game servers.
 *
 * Why this exists instead of every feature calling Player::sendMessage()
 * directly:
 *  - a single, consistent, no-emoji visual style everywhere
 *  - a per-player FIFO queue + minimum gap between boxes, so two rewards
 *    firing on the same tick (e.g. join-time streak + playtime tick) never
 *    get interleaved or flood the chat
 *  - one place to silence/mute a player or change the style later
 *
 * Nothing here touches the database or does any per-tick heavy work; it is
 * just an array queue drained a little at a time by RewardHeartbeatTask.
 */
final class NotificationService{

    /** Minimum ticks between two boxes shown to the *same* player. */
    private const MIN_GAP_TICKS = 30;

    /** Safety cap so a bug elsewhere can't pile up unbounded memory. */
    private const MAX_QUEUE_PER_PLAYER = 6;

    /** @var array<string, list<list<string>>> lowercase username => queue of pre-rendered message blocks */
    private array $queues = [];

    /** @var array<string, int> lowercase username => tick of the last box actually sent */
    private array $lastSentTick = [];

    private int $tick = 0;

    /**
     * Queue a styled notification box for a player. Safe to call from any
     * reward manager at any time - this never sends anything synchronously,
     * it only enqueues, so callers never need to worry about spamming chat.
     *
     * @param string[] $lines body lines, already colored (no leading accent
     *                        pipe) - pass an empty string "" for a blank
     *                        spacer line inside the box (still shows the
     *                        left-hand accent pipe with nothing after it).
     */
    public function queue(Player $player, string $accent, string $title, array $lines): void{
        if(!$player->isConnected()){
            return;
        }

        $key = strtolower($player->getName());
        $this->queues[$key] ??= [];

        if(count($this->queues[$key]) >= self::MAX_QUEUE_PER_PLAYER){
            // drop the oldest queued box rather than the newest - the newest
            // is the one the player actually just earned.
            array_shift($this->queues[$key]);
        }

        $this->queues[$key][] = $this->render($accent, $title, $lines);
    }

    /**
     * Called once per server tick by RewardHeartbeatTask. Drains at most one
     * box per player per MIN_GAP_TICKS, so this is O(online players) and
     * extremely cheap - no allocation happens unless a box is actually due.
     */
    public function tick(): void{
        $this->tick++;

        if($this->queues === []){
            return;
        }

        $server = Server::getInstance();

        foreach($this->queues as $key => $queue){
            if($queue === []){
                unset($this->queues[$key]);
                continue;
            }

            $last = $this->lastSentTick[$key] ?? -self::MIN_GAP_TICKS;
            if($this->tick - $last < self::MIN_GAP_TICKS){
                continue;
            }

            $player = $server->getPlayerExact($key);
            if($player === null || !$player->isConnected()){
                unset($this->queues[$key], $this->lastSentTick[$key]);
                continue;
            }

            $block = array_shift($this->queues[$key]);
            foreach($block as $line){
                $player->sendMessage($line);
            }
            $this->lastSentTick[$key] = $this->tick;
        }
    }

    public function clear(Player $player): void{
        $key = strtolower($player->getName());
        unset($this->queues[$key], $this->lastSentTick[$key]);
    }

    /**
     * Left-accent-pipe box style, colorful and emoji-free, matching every
     * other boxed panel across the network:
     *
     *   §6| §6§lDAILY LOGIN STREAK
     *   §6|
     *   §6| §7Player §f» §d{player}
     *   ...
     *
     * @param string[] $lines body lines, already colored - "" renders as a
     *                        bare accent pipe used as a spacer between
     *                        sections.
     * @return string[]
     */
    private function render(string $accent, string $title, array $lines): array{
        $out = [];
        $out[] = $accent . "| " . $accent . TF::BOLD . $title . TF::RESET;
        $out[] = $accent . "|";
        foreach($lines as $line){
            $out[] = $line === "" ? $accent . "|" : $accent . "| " . $line;
        }

        return $out;
    }
}
