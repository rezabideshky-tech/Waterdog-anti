<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\leaderboard;

use pocketmine\math\Vector3;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use sergittos\bedwars\BedWarsCore;

/**
 * LeaderboardManager — مدیریت هولوگرام‌های leaderboard
 * موقعیت‌ها در leaderboards.yml ذخیره می‌شن
 */
class LeaderboardManager {

    /** @var array<string, array{world:string,x:float,y:float,z:float}> */
    private array $positions = [];
    private Config $config;

    public function __construct(private Plugin $plugin) {
        $this->config = new Config($plugin->getDataFolder() . "leaderboards.yml", Config::YAML);
        $this->positions = $this->config->getAll();
        $this->spawnAll();
    }

    public function setPosition(string $stat, Vector3 $pos, string $worldName): void {
        $this->positions[$stat] = ["world" => $worldName, "x" => $pos->x, "y" => $pos->y, "z" => $pos->z];
        $this->config->setAll($this->positions);
        $this->config->save();
        $this->spawn($stat);
    }

    public function removePosition(string $stat): void {
        $removed = $this->positions[$stat] ?? null;   // قبل از unset لازمه
        unset($this->positions[$stat]);
        $this->config->setAll($this->positions);
        $this->config->save();
        BedWarsCore::getInstance()->getHologramManager()->remove("leaderboard_$stat");

        if ($removed !== null) {
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($removed["world"]);
            $lobby = \sergittos\bedwars\lobby\BedWarsLobby::getInstance()->getLobbyManagerOrNull();
            if ($world !== null && $lobby !== null) {
                $lobby->getPedestals()->remove(
                    $stat, $world, new Vector3((float) $removed["x"], (float) $removed["y"], (float) $removed["z"])
                );
            }
        }
    }

    public function spawnAll(): void {
        foreach (array_keys($this->positions) as $stat) {
            $this->spawn($stat);
        }
    }

    public function spawn(string $stat): void {
        if (!isset($this->positions[$stat])) return;
        $data  = $this->positions[$stat];
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($data["world"]);
        if ($world === null) return;

        $pos = new Vector3($data["x"], $data["y"], $data["z"]);
        $displayName = match($stat) {
            "kills"       => "Kills",
            "wins"        => "Wins",
            "beds_broken" => "Beds Broken",
            "final_kills" => "Final Kills",
            "level"       => "Level",
            default       => ucfirst($stat),
        };

        BedWarsCore::getInstance()->getProvider()->getLeaderboard($stat, 10, function(array $rows) use ($stat, $displayName, $world, $pos): void {
            BedWarsCore::getInstance()->getHologramManager()->createLeaderboard($stat, $displayName, $world, $pos, $rows);

            // پایه‌ی هالووینی همراه همون هولوگرام بالا/پایین می‌شه.
            // نکته: این manager داخل constructor خودش spawnAll() صدا می‌زنه و ممکنه
            // هنوز LobbyManager ساخته نشده باشه؛ پس فقط از راه accessor امن استفاده می‌کنیم.
            $lobby = \sergittos\bedwars\lobby\BedWarsLobby::getInstance()->getLobbyManagerOrNull();
            $lobby?->getPedestals()->summon($stat, $world, $pos);
        });
    }

    public function refreshAll(): void {
        $this->spawnAll();
    }
}
