<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\manager;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\World;
use pocketmine\utils\Config;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\task\HologramUpdateTask;

class LobbyManager {

    private BedWarsLobby $plugin;
    private Config $lobbyConfig;
    private Config $hologramConfig;

    private ?World $lobbyWorld = null;
    private ?Vector3 $lobbySpawn = null;

    /** @var array<string, array{world: string, x: float, y: float, z: float}> */
    private array $statsHologramPositions = [];

    /** @var array<string, array{world: string, x: float, y: float, z: float, stat: string}> */
    private array $leaderboardHologramPositions = [];

    public function __construct(BedWarsLobby $plugin) {
        $this->plugin = $plugin;
        $this->lobbyConfig = new Config($plugin->getDataFolder() . "lobby.yml", Config::YAML);
        $this->hologramConfig = new Config($plugin->getDataFolder() . "holograms.yml", Config::YAML);

        $this->loadLobbyData();
        $this->loadHologramData();

        $plugin->getScheduler()->scheduleRepeatingTask(new HologramUpdateTask($this), 100);
        $plugin->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(): void {
            $this->updateLeaderboards();
        }), 6000); // هر ۵ دقیقه
        $this->updateLeaderboards(); // یه بار همون اول، که منتظر ۵ دقیقه نمونیم
    }

    private function loadLobbyData(): void {
        $data = $this->lobbyConfig->getAll();
        if (isset($data["world"], $data["x"], $data["y"], $data["z"])) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
            if ($world !== null) {
                $this->lobbyWorld = $world;
                $this->lobbySpawn = new Vector3((float)$data["x"], (float)$data["y"], (float)$data["z"]);
            }
        }
    }

    private function loadHologramData(): void {
        $stats = $this->hologramConfig->get("stats", []);
        foreach ($stats as $posKey => $data) {
            $this->statsHologramPositions[$posKey] = $data;
        }

        $leaderboards = $this->hologramConfig->get("leaderboards", []);
        foreach ($leaderboards as $posKey => $data) {
            $this->leaderboardHologramPositions[$posKey] = $data;
        }
    }

    public function saveLobbyData(): void {
        if ($this->lobbyWorld !== null && $this->lobbySpawn !== null) {
            $this->lobbyConfig->set("world", $this->lobbyWorld->getFolderName());
            $this->lobbyConfig->set("x", $this->lobbySpawn->getX());
            $this->lobbyConfig->set("y", $this->lobbySpawn->getY());
            $this->lobbyConfig->set("z", $this->lobbySpawn->getZ());
            $this->lobbyConfig->save();
        }
    }

    /** Extra height (in blocks) added above the exact position /setlobby was run at, so players spawn a bit elevated instead of right at foot level. */
    private const LOBBY_SPAWN_HEIGHT_OFFSET = 1.5;

    public function setLobby(World $world, Vector3 $pos): void {
        $this->lobbyWorld = $world;
        $this->lobbySpawn = $pos->add(0, self::LOBBY_SPAWN_HEIGHT_OFFSET, 0);
        $this->saveLobbyData();
    }

    public function getLobbySpawn(): ?Vector3 {
        return $this->lobbySpawn;
    }

    public function getLobbyWorld(): ?World {
        return $this->lobbyWorld;
    }

    // ─── Stats Hologram ──────────────────────────

    public function addStatsHologram(Player $player): void {
        $pos = $player->getPosition();
        $world = $player->getWorld();
        $key = $world->getFolderName() . ":" . round($pos->getX(), 1) . ":" . round($pos->getY(), 1) . ":" . round($pos->getZ(), 1);
        $this->statsHologramPositions[$key] = [
            "world" => $world->getFolderName(),
            "x" => $pos->getX(),
            "y" => $pos->getY(),
            "z" => $pos->getZ()
        ];
        $this->hologramConfig->set("stats", $this->statsHologramPositions);
        $this->hologramConfig->save();
        $player->sendMessage("§aStats hologram spawned at your position.");
    }

    public function getStatsHologramPositions(): array {
        return $this->statsHologramPositions;
    }

    // ─── Leaderboard Hologram ────────────────────

    public function addLeaderboardHologram(Player $player, string $stat): void {
        $pos = $player->getPosition();
        $world = $player->getWorld();
        $key = $world->getFolderName() . ":" . round($pos->getX(), 1) . ":" . round($pos->getY(), 1) . ":" . round($pos->getZ(), 1);
        $this->leaderboardHologramPositions[$key] = [
            "world" => $world->getFolderName(),
            "x" => $pos->getX(),
            "y" => $pos->getY(),
            "z" => $pos->getZ(),
            "stat" => $stat
        ];
        $this->hologramConfig->set("leaderboards", $this->leaderboardHologramPositions);
        $this->hologramConfig->save();
        $player->sendMessage("§aLeaderboard hologram for '$stat' spawned.");
    }

    public function getLeaderboardHologramPositions(): array {
        return $this->leaderboardHologramPositions;
    }

    // ─── Update ──────────────────────────────────

    public function updateAllHolograms(): void {
        $this->updateStatsHolograms();
    }

    public function updateLeaderboards(): void {
        $this->updateLeaderboardHolograms();
    }

    private function updateStatsHolograms(): void {
        $hologramManager = BedWarsCore::getInstance()->getHologramManager();
        $sessionManager = BedWarsCore::getInstance()->getSessionManager();

        foreach ($this->statsHologramPositions as $posKey => $data) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
            if ($world === null) continue;
            $pos = new Vector3($data["x"], $data["y"], $data["z"]);

            foreach ($world->getPlayers() as $player) {
                $session = $sessionManager->get($player);
                if ($session === null) continue;

                $lines = [
                    "§l§b§m     §r §l§e★ §l§b§lSTATUS §l§e★§r §l§b§m     ",
                    $session->getRankPrefixDisplay() . " §f§l" . $session->getUsername(),
                    "§8§m                         ",
                    "§b➤ §7Level: " . $session->getLevelColor() . "§l" . $session->getLevel(),
                    "§b➤ §7Progress: §a" . $session->getXp() . " §8/ §a" . $session->getRequiredXPForNextLevel(),
                    "§8§m                         ",
                    "§6➤ §7Coins: §6§l" . number_format($session->getCoins()),
                    "§a➤ §7Wins: §a§l" . number_format($session->getWins()),
                    "§6➤ §7Win Streak: §6§l" . number_format($session->getWinStreak()) . " §7(Best: §e" . number_format($session->getBestWinStreak()) . "§7)",
                    "§e➤ §7Kills: §e§l" . number_format($session->getKills()),
                    "§d➤ §7Final Kills: §d§l" . number_format($session->getFinalKills()),
                    "§c➤ §7Deaths: §c§l" . number_format($session->getDeaths()),
                    "§b➤ §7Beds Broken: §b§l" . number_format($session->getBedsBroken()),
                    "§8§m                         ",
                ];

                $hologramManager->sendStatsHologramToPlayer($player, $pos, $lines);
            }
        }
    }

    private function updateLeaderboardHolograms(): void {
        $hologramManager = BedWarsCore::getInstance()->getHologramManager();
        $provider = BedWarsCore::getInstance()->getProvider();

        foreach ($this->leaderboardHologramPositions as $posKey => $data) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
            if ($world === null) continue;
            $pos = new Vector3($data["x"], $data["y"], $data["z"]);
            $stat = $data["stat"];

            $displayName = match($stat) {
                "kills"       => "Kills",
                "wins"        => "Wins",
                "beds_broken" => "Beds Broken",
                "final_kills" => "Final Kills",
                "deaths"      => "Deaths",
                "level"       => "Level",
                "coins"       => "Coins",
                "win_streak"  => "Win Streak",
                default       => ucfirst($stat),
            };

            $idSuffix = "_lm_" . substr(md5((string) $posKey), 0, 8);
            $provider->getLeaderboard($stat, 10, function(array $rows) use ($hologramManager, $world, $pos, $stat, $displayName, $idSuffix) {
                $hologramManager->createLeaderboard($stat, $displayName, $world, $pos, $rows, $idSuffix);
            });
        }
    }
}