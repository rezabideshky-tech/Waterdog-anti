<?php

declare(strict_types=1);

namespace sergittos\bedwars\network;

use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use function array_filter;
use function array_values;
use function ceil;
use function count;
use function date;
use function implode;
use function in_array;
use function is_array;
use function max;
use function time;

class NetworkManager {

    private string $serverName;
    private string $serverType;

    /** @var array<string, ServerInfo> */
    private array $servers = [];

    /** @var array<string, array{ip:string, queryIp:string, port:int, type:string}> */
    private array $cfgServers = [];

    /** @var string[] */
    private array $queryQueue = [];

    private int $rrIndex = 0;

    /** @var array<string, int> */
    private array $nextQueryAt = [];

    /**
     * Wall-clock second (time()) at which $servers[$name] was last actually
     * written - whether the write was a successful query, a failed/timed
     * out query (marked offline), or registerSelf(). pickBestServer() uses
     * this to distinguish "we just confirmed this server is online" from
     * "we haven't heard from this server in a while and are just trusting
     * old cached data" - see the staleness check below and the
     * investigation notes on the post-ceremony kick bug.
     *
     * @var array<string, int>
     */
    private array $lastSeenAt = [];

    private int $timeoutSeconds;
    private int $refreshAllSeconds;
    private int $offlineRetrySeconds;

    /**
     * How old (seconds) a server's cached ServerInfo is allowed to be
     * before pickBestServer() stops trusting its "online" status and skips
     * it instead of handing it out as a transfer target. Defaults to
     * 3x the refresh cycle - long enough that a normal round-robin gap
     * doesn't cause false skips, short enough to catch a server that
     * actually went offline/unreachable between refreshes.
     */
    private int $staleAfterSeconds;

    public function __construct(private Plugin $plugin) {
        $this->serverName = (string) $plugin->getConfig()->get("server-name", "server-1");
        $this->serverType = (string) $plugin->getConfig()->get("server-type", "lobby");

        $this->timeoutSeconds = (int) $plugin->getConfig()->get("network-query-timeout", 1);
        $this->refreshAllSeconds = (int) $plugin->getConfig()->get("network-refresh-all-seconds", 5);
        $this->offlineRetrySeconds = (int) $plugin->getConfig()->get("network-offline-retry-seconds", 10);
        $this->staleAfterSeconds = (int) $plugin->getConfig()->get("network-stale-seconds", max(15, $this->refreshAllSeconds * 3));

        $this->loadConfigServers();
        $this->registerSelf();
        $this->startHeartbeat();
    }

    private function loadConfigServers(): void {
        $cfg = (array) $this->plugin->getConfig()->get("servers", []);

        $this->cfgServers = [];
        foreach ($cfg as $name => $data) {
            if (!is_array($data)) {
                continue;
            }

            $ip = (string) ($data["ip"] ?? "");
            $queryIp = (string) ($data["query-ip"] ?? $ip);
            $port = (int) ($data["port"] ?? 0);
            $type = (string) ($data["type"] ?? "unknown");

            if ($name === "" || $queryIp === "" || $port <= 0) {
                continue;
            }

            $this->cfgServers[$name] = [
                "ip" => $ip,
                "queryIp" => $queryIp,
                "port" => $port,
                "type" => $type
            ];
        }

        $this->queryQueue = [];
        foreach ($this->cfgServers as $name => $_) {
            if ($name !== $this->serverName) {
                $this->queryQueue[] = $name;
            }
        }
    }

    private function registerSelf(): void {
        $this->servers[$this->serverName] = new ServerInfo(
            $this->serverName,
            $this->serverType,
            count($this->plugin->getServer()->getOnlinePlayers()),
            $this->plugin->getServer()->getMaxPlayers(),
            "online"
        );
        $this->lastSeenAt[$this->serverName] = time();
    }

    private function startHeartbeat(): void {
        $this->plugin->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function(): void {
                $this->registerSelf();
                $this->queryBatch();
            }),
            20
        );
    }

    private function queryBatch(): void {
        $debug = (bool) $this->plugin->getConfig()->get("debug-network", false);

        $total = count($this->queryQueue);
        if ($total === 0) {
            return;
        }

        $refresh = $this->refreshAllSeconds <= 0 ? 5 : $this->refreshAllSeconds;
        $perRun = (int) ceil($total / $refresh);
        if ($perRun < 1) $perRun = 1;

        $now = time();

        for ($i = 0; $i < $perRun; $i++) {
            if ($this->rrIndex >= $total) {
                $this->rrIndex = 0;
            }

            $name = $this->queryQueue[$this->rrIndex++];
            $due = $this->nextQueryAt[$name] ?? 0;
            if ($due > $now) {
                continue;
            }

            $cfg = $this->cfgServers[$name] ?? null;
            if ($cfg === null) {
                continue;
            }

            $queryIp = $cfg["queryIp"];
            $port = $cfg["port"];
            $type = $cfg["type"];

            // Mark as pending immediately so a second batch run before the async
            // query comes back doesn't queue duplicate queries for the same server.
            $this->nextQueryAt[$name] = $now + max(1, $this->timeoutSeconds);

            $plugin = $this->plugin;
            $timeoutSeconds = $this->timeoutSeconds;
            $offlineRetrySeconds = $this->offlineRetrySeconds;

            $this->plugin->getServer()->getAsyncPool()->submitTask(new ServerQueryTask(
                $name,
                $queryIp,
                $port,
                $timeoutSeconds,
                function(string $name, array $result) use ($plugin, $type, $debug, $queryIp, $port, $offlineRetrySeconds): void {
                    if (!$plugin->isEnabled()) {
                        return;
                    }

                    $now = time();

                    if ($result["success"] ?? false) {
                        $this->servers[$name] = new ServerInfo($name, $type, (int) $result["online"], (int) $result["max"], "online");
                        $this->nextQueryAt[$name] = $now + 1;
                        $this->lastSeenAt[$name] = $now;
                    } else {
                        $this->servers[$name] = new ServerInfo($name, $type, 0, 0, "offline");
                        $this->nextQueryAt[$name] = $now + ($offlineRetrySeconds <= 0 ? 10 : $offlineRetrySeconds);
                        // A failed/timed-out query is still a confirmed
                        // observation (we know it's offline right now), so
                        // this counts as "seen" too - only the total silence
                        // case (server never queried at all, e.g. it was
                        // added to config after startup) should be treated
                        // as stale by pickBestServer().
                        $this->lastSeenAt[$name] = $now;

                        if ($debug) {
                            $plugin->getLogger()->debug("[NetworkManager] query to $name ($queryIp:$port) failed at " . date("H:i:s", $now));
                        }
                    }
                }
            ));
        }
    }

    public function transferToServer(Player $player, string $serverName): bool {
        $debug = (bool) $this->plugin->getConfig()->get("debug-network", false);
        $now = time();

        if ($serverName === $this->serverName) {
            // Transferring a player to the server they're already on would
            // still send them through a real disconnect/reconnect handshake
            // client-side - from the player's perspective that's exactly
            // the unexplained "kicked but server didn't crash" symptom,
            // just self-inflicted. Refuse it outright and log it, since it
            // can only happen from a config mistake (e.g. this server's own
            // name also listed under "servers" with type: lobby) or a stale
            // pickBestServer() result.
            $this->plugin->getLogger()->warning(
                "[NetworkManager] " . date("H:i:s", $now) . " refused to transfer " . $player->getName() .
                " to \"$serverName\" - that is this server's own name."
            );
            return false;
        }

        $info = $this->servers[$serverName] ?? null;
        if ($info === null || !$info->isOnline()) {
            if ($debug) {
                $this->plugin->getLogger()->warning(
                    "[NetworkManager] " . date("H:i:s", $now) . " transfer of " . $player->getName() .
                    " to \"$serverName\" blocked - last known status: " . ($info === null ? "never queried" : $info->status)
                );
            }
            $player->sendMessage("§cThat server is currently offline, please try again shortly.");
            return false;
        }

        $port = (int) (($this->cfgServers[$serverName]["port"] ?? 19132));

        if ($debug) {
            $lastSeen = $this->lastSeenAt[$serverName] ?? null;
            $age = $lastSeen !== null ? ($now - $lastSeen) : -1;
            $ip = (string) ($this->cfgServers[$serverName]["ip"] ?? "?");
            $this->plugin->getLogger()->debug(
                "[NetworkManager] " . date("H:i:s", $now) . " transferring " . $player->getName() .
                " -> \"$serverName\" ($ip:$port, info age {$age}s)"
            );
        }

        try {
            $player->transfer($serverName, $port);
        } catch (\Throwable $e) {
            // player->transfer() itself is expected to be safe, but this
            // whole call path runs right as EndingStage::reset() is tearing
            // the match down (world unload, session cleanup) - wrapping it
            // means a race there surfaces as a logged warning instead of an
            // unexplained disconnect with nothing in the console to explain
            // it. This is exactly the visibility item 4 of the optimization
            // pass asked for.
            $this->plugin->getLogger()->warning(
                "[NetworkManager] " . date("H:i:s", $now) . " transfer of " . $player->getName() .
                " to \"$serverName\" threw: " . $e->getMessage()
            );
            $this->plugin->getLogger()->warning($e->getTraceAsString());
            return false;
        }

        return true;
    }

    public function transferToLobby(Player $player): void {
        $debug = (bool) $this->plugin->getConfig()->get("debug-network", false);
        $now = time();

        $best = $this->pickBestServer("lobby", [$this->serverName]);
        $default = (string) $this->plugin->getConfig()->get("default-lobby", "lobby-1");
        $target = $best?->name ?? $default;

        if ($best === null && $debug) {
            // No lobby currently passes the online+not-full+not-stale check
            // in pickBestServer() - falling back to the configured
            // default-lobby blind. Logged with a timestamp and a snapshot
            // of every known lobby's cached status so a kick reported at a
            // given time can be matched against exactly what NetworkManager
            // believed at that moment (see item 4 of the optimization
            // request: comparing the kick timestamp against ServerQueryTask
            // timing).
            $snapshot = [];
            foreach ($this->getServersByType("lobby") as $info) {
                $age = $now - ($this->lastSeenAt[$info->name] ?? 0);
                $snapshot[] = "{$info->name}={$info->status}({$info->online}/{$info->maxPlayers}, age {$age}s)";
            }
            $this->plugin->getLogger()->warning(
                "[NetworkManager] " . date("H:i:s", $now) . " pickBestServer(lobby) found nothing usable for " .
                $player->getName() . " - falling back to default-lobby \"$default\". Known lobbies: " .
                (implode(", ", $snapshot) ?: "(none registered)")
            );
        }

        $this->transferToServer($player, $target);
    }

    public function pickBestServer(string $type, array $exclude = []): ?ServerInfo {
        $best = null;
        $bestFree = -1;
        $now = time();

        foreach ($this->getServersByType($type) as $info) {
            if (!$info->isOnline() || $info->isFull()) continue;
            if (in_array($info->name, $exclude, true)) continue;

            // Don't hand out a server we haven't actually heard from in a
            // while as if it were a fresh, confirmed-online result - see
            // $lastSeenAt's doc-comment. A stale "online" entry is worse
            // than no entry at all, since it gets used exactly once (right
            // here) to send a real player through a real transfer.
            $age = $now - ($this->lastSeenAt[$info->name] ?? 0);
            if ($age > $this->staleAfterSeconds) {
                continue;
            }

            $free = $info->maxPlayers - $info->online;
            if ($free > $bestFree) {
                $bestFree = $free;
                $best = $info;
            }
        }

        return $best;
    }

    /** @return ServerInfo[] */
    public function getServers(): array { return $this->servers; }

    /** @return ServerInfo[] */
    public function getServersByType(string $type): array {
        return array_values(array_filter($this->servers, fn(ServerInfo $s) => $s->type === $type));
    }

    public function getServerName(): string { return $this->serverName; }
    public function getServerType(): string { return $this->serverType; }

    public function close(): void {}
}