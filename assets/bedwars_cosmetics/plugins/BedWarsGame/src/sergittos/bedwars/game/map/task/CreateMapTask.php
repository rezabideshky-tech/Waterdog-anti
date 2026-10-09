<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\map\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\listener\SetupListener;
use sergittos\bedwars\session\setup\builder\MapBuilder;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;

class CreateMapTask extends AsyncTask{

    private string $world_path;
    private string $destination_path;
    private string $worldName;
    private string $playerName;

    public function __construct(MapBuilder $map, string $playerName){
        $this->storeLocal("map", $map);
        $this->playerName = $playerName;

        $this->worldName = $map->getPlayingWorld();
        $this->world_path = Server::getInstance()->getDataPath() . "worlds/" . $this->worldName;
        $this->destination_path = BedWars::getInstance()->getDataFolder() . "worlds/" . $map->getName();

        $wm = Server::getInstance()->getWorldManager();
        $world = $wm->getWorldByName($this->worldName);

        if($world !== null){
            $default = $wm->getDefaultWorld();
            foreach($world->getPlayers() as $player){
                if($default !== null && $default !== $world){
                    $player->teleport($default->getSafeSpawn());
                }
            }
            $wm->unloadWorld($world);
        }
    }

    public function onRun() : void{
        Filesystem::recursiveCopy($this->world_path, $this->destination_path);
    }

    public function onCompletion() : void{
        SetupListener::endSave($this->playerName);

        $plugin = BedWars::getInstance();

        $wm = Server::getInstance()->getWorldManager();
        $wm->loadWorld($this->worldName);

        $map = $this->fetchLocal("map")->build();

        $path = $plugin->getDataFolder() . "maps.json";
        $data = json_decode(file_get_contents($path), true);
        if(!is_array($data)){
            $data = [];
        }

        $data[] = $map->jsonSerialize();
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));

        MapFactory::addMap($map);
        $plugin->getGameManager()->generateGames($map);
    }
}