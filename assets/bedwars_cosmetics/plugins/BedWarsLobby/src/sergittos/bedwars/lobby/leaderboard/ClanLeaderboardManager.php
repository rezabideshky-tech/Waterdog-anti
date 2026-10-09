<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\leaderboard;

use pocketmine\math\Vector3;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use sergittos\bedwars\BedWarsCore;

/**
 * Manages the single clan weekly-leaderboard hologram, reusing the exact
 * same HologramManager::createLeaderboard() the per-player stat holograms
 * already use (see LeaderboardManager for the sibling implementation).
 *
 * Unlike LeaderboardManager, this never queries the database at refresh
 * time - ClanManager already keeps every clan cached in memory (refreshed
 * periodically from MySQL by ClanManager itself), so the leaderboard is
 * just a re-sort of that existing cache.
 */
final class ClanLeaderboardManager{

    private const HOLOGRAM_STAT = "clan_score";

    /** @var array{world:string,x:float,y:float,z:float}|null */
    private ?array $position = null;
    private Config $config;

    public function __construct(private Plugin $plugin){
        $this->config = new Config($plugin->getDataFolder() . "clan_leaderboard.yml", Config::YAML);
        $data = $this->config->getAll();
        $this->position = isset($data["world"], $data["x"], $data["y"], $data["z"]) ? $data : null;
        $this->spawn();
    }

    public function setPosition(Vector3 $pos, string $worldName): void{
        $this->position = ["world" => $worldName, "x" => $pos->x, "y" => $pos->y, "z" => $pos->z];
        $this->config->set("world", $worldName);
        $this->config->set("x", $pos->x);
        $this->config->set("y", $pos->y);
        $this->config->set("z", $pos->z);
        $this->config->save();
        $this->spawn();
    }

    public function removePosition(): void{
        $this->position = null;
        $this->config->set("world", null);
        $this->config->set("x", null);
        $this->config->set("y", null);
        $this->config->set("z", null);
        $this->config->save();
        BedWarsCore::getInstance()->getHologramManager()->remove("leaderboard_" . self::HOLOGRAM_STAT);
    }

    public function hasPosition(): bool{
        return $this->position !== null;
    }

    public function spawn(): void{
        if($this->position === null){
            return;
        }

        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->position["world"]);
        if($world === null){
            return;
        }

        $pos = new Vector3($this->position["x"], $this->position["y"], $this->position["z"]);
        $clanManager = BedWarsCore::getInstance()->getClanManager();
        $config = $clanManager->getConfig();

        $top = [];
        foreach($clanManager->getWeeklyLeaderboard(10) as $clan){
            $top[] = [
                "username"          => $clan->getColoredName(),
                self::HOLOGRAM_STAT => $clan->computeWeeklyScore($config),
            ];
        }

        BedWarsCore::getInstance()->getHologramManager()->createLeaderboard(self::HOLOGRAM_STAT, "Clan Score", $world, $pos, $top);
    }

    public function refresh(): void{
        $this->spawn();
    }
}
