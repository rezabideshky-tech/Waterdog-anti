<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\map\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\game\task\SafeRemoveGameTask;
use sergittos\bedwars\listener\SetupListener;
use sergittos\bedwars\session\setup\builder\MapBuilder;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function strtolower;

final class UpdateMapTask extends AsyncTask{

    private string $worldFolder;
    private string $worldPath;
    private string $destinationPath;
    private string $playerName;

    public function __construct(MapBuilder $map, string $playerName){
        $this->storeLocal("map", $map);
        $this->playerName = $playerName;

        $this->worldFolder = $map->getPlayingWorld();
        $this->worldPath = Server::getInstance()->getDataPath() . "worlds/" . $this->worldFolder;
        $this->destinationPath = BedWars::getInstance()->getDataFolder() . "worlds/" . $map->getName();

        $wm = Server::getInstance()->getWorldManager();
        $world = $wm->getWorldByName($this->worldFolder);

        if($world !== null){
            $default = $wm->getDefaultWorld();
            foreach($world->getPlayers() as $p){
                if($default !== null && $default !== $world){
                    $p->teleport($default->getSafeSpawn());
                }
            }
            $world->setAutoSave(false);
            $wm->unloadWorld($world, true);
        }
    }

    public function onRun() : void{
        if(is_dir($this->destinationPath)){
            Filesystem::recursiveUnlink($this->destinationPath);
        }

        Filesystem::recursiveCopy($this->worldPath, $this->destinationPath);
    }

    public function onCompletion() : void{
        SetupListener::endSave($this->playerName);

        $plugin = BedWars::getInstance();
        if(!$plugin->isEnabled()){
            return;
        }

        $map = $this->fetchLocal("map")->build();

        $path = $plugin->getDataFolder() . "maps.json";
        $data = json_decode((string) file_get_contents($path), true);
        if(!is_array($data)){
            $data = [];
        }

        $replaced = false;
        foreach($data as $i => $row){
            if(isset($row["name"]) && strtolower((string) $row["name"]) === strtolower($map->getName())){
                $data[$i] = $map->jsonSerialize();
                $replaced = true;
                break;
            }
        }

        if(!$replaced){
            $data[] = $map->jsonSerialize();
        }

        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));

        $old = MapFactory::getMapByName($map->getName());
        if($old !== null){
            MapFactory::removeMap($old->getId());
        }
        MapFactory::addMap($map);

        foreach($plugin->getGameManager()->getGames() as $g){
            if(strtolower($g->getMap()->getName()) !== strtolower($map->getName())){
                continue;
            }

            $g->unloadWorld();
            $plugin->getScheduler()->scheduleRepeatingTask(
                new SafeRemoveGameTask($g->getId(), $g->getMap()->getName() . "-" . $g->getId()),
                20
            );

            // Untrack it immediately instead of waiting for
            // SafeRemoveGameTask's delayed world-folder cleanup to
            // finish (that can take a while - it waits for the world to
            // unload before it even asks to delete the folder). The
            // physical delete still happens on its own schedule above;
            // this just stops the stale pre-edit instance from being
            // counted as "still here" a few lines down, which used to
            // make generateGames() think this map already had enough
            // instances and skip creating fresh ones - leaving the map
            // with zero playable copies until a player's queue attempt
            // lazily triggered generation on demand.
            $plugin->getGameManager()->removeGame($g->getId());
        }

        $plugin->getGameManager()->generateGames($map);

        $worldFolder = $this->worldFolder;
        $worldPath = $this->worldPath;

        $plugin->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function() use ($worldFolder, $worldPath, $plugin): void{
            $wm = Server::getInstance()->getWorldManager();
            $w = $wm->getWorldByName($worldFolder);
            if($w !== null){
                $w->setAutoSave(false);
                $wm->unloadWorld($w, true);
                $plugin->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function() use ($worldFolder, $worldPath): void{
                    if(Server::getInstance()->getWorldManager()->getWorldByName($worldFolder) === null){
                        Server::getInstance()->getAsyncPool()->submitTask(new RemoveWorldFolderTask($worldPath));
                    }
                }), 100);
                return;
            }

            Server::getInstance()->getAsyncPool()->submitTask(new RemoveWorldFolderTask($worldPath));
        }), 100);
    }
}