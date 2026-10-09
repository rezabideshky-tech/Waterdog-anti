<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\map\Map;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\game\Game;
use function basename;
use function dirname;
use function is_dir;
use function is_file;
use function str_replace;

class GenerateGameTask extends AsyncTask {

    private int $id;

    private string $map_id;
    private string $map_name;

    private string $world_path;
    private string $destination_path;

    private bool $success = true;

    public function __construct(int $id, Map $map) {
        $this->id = $id;

        $this->map_id = $map->getId();
        $this->map_name = $map->getName();

        $this->world_path = BedWars::getInstance()->getDataFolder() . "worlds/" . $this->map_name;
        $this->destination_path = Server::getInstance()->getDataPath() . "worlds/" . $this->map_name . "-" . $id;
    }

    public function onRun(): void {
        if (!is_dir($this->world_path)) {
            $this->success = false;
            return;
        }

        if (is_dir($this->destination_path)) {
            try {
                Filesystem::recursiveUnlink($this->destination_path);
            } catch (\Throwable) {
                $this->success = false;
                return;
            }
        }

        $this->success = $this->copyWorld($this->world_path, $this->destination_path);
        if (!$this->success) {
            try {
                if (is_dir($this->destination_path)) {
                    Filesystem::recursiveUnlink($this->destination_path);
                }
            } catch (\Throwable) {
            }
        }
    }

    private function copyWorld(string $src, string $dst): bool {
        if (is_dir($src)) {
            if (!is_dir($dst) && !@mkdir($dst, 0777, true)) {
                return false;
            }

            $list = @scandir($src);
            if ($list === false) {
                return false;
            }

            foreach ($list as $name) {
                if ($name === "." || $name === "..") continue;

                if (!$this->copyWorld($src . DIRECTORY_SEPARATOR . $name, $dst . DIRECTORY_SEPARATOR . $name)) {
                    return false;
                }
            }

            return true;
        }

        if (!is_file($src)) {
            return true;
        }

        if ($this->shouldSkip($src)) {
            return true;
        }

        $dir = dirname($dst);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            return false;
        }

        return @copy($src, $dst);
    }

    private function shouldSkip(string $path): bool {
        $n = str_replace("\\", "/", $path);

        if (str_ends_with($n, "/db/LOCK")) return true;
        if (str_ends_with($n, "/db/LOG")) return true;
        if (str_ends_with($n, "/db/LOG.old")) return true;

        return false;
    }

    public function onCompletion(): void {
        // Either the copy failed, or it succeeded but the map was
        // deleted/replaced while the async copy was running - either
        // way this in-flight generation is done and must stop counting
        // as "pending" for this map, or GameManager::generateGames()
        // would permanently think this map is short one instance that
        // will never actually arrive. Uses the map id directly rather
        // than requiring the Map object, since MapFactory may no longer
        // have it (map deleted) in either of these cases.
        if (!$this->success) {
            BedWars::getInstance()->getGameManager()->generationFailed($this->map_id);
            return;
        }

        $map = MapFactory::getMapById($this->map_id);
        if ($map === null) {
            BedWars::getInstance()->getGameManager()->generationFailed($this->map_id);
            return;
        }

        BedWars::getInstance()->getGameManager()->addGame(new Game($map, $this->id));
    }
}