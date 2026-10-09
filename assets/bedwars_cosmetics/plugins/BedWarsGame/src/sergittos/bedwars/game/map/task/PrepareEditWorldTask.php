<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\map\task;

use pocketmine\entity\Location;
use pocketmine\player\GameMode;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\Chunk;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\entity\shop\ItemShopVillager;
use sergittos\bedwars\game\entity\shop\UpgradesShopVillager;
use sergittos\bedwars\game\entity\shop\Villager;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\session\SessionFactory;
use sergittos\bedwars\session\setup\MapSetup;
use sergittos\bedwars\session\setup\builder\MapBuilder;
use function is_dir;
use function preg_replace;
use function str_replace;
use function strtolower;

final class PrepareEditWorldTask extends AsyncTask{

    private string $playerName;
    private string $mapName;
    private string $sourcePath;
    private string $destPath;
    private string $editWorld;

    public function __construct(string $playerName, string $mapName){
        $this->playerName = $playerName;
        $this->mapName = $mapName;

        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', "_", str_replace(" ", "_", $mapName));
        $this->editWorld = "edit_" . $safe;

        $data = Server::getInstance()->getDataPath() . "worlds/";

        $this->sourcePath = BedWars::getInstance()->getDataFolder() . "worlds/" . $mapName;
        $this->destPath = $data . $this->editWorld;

        $wm = Server::getInstance()->getWorldManager();
        $world = $wm->getWorldByName($this->editWorld);
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
        if(is_dir($this->destPath)){
            Filesystem::recursiveUnlink($this->destPath);
        }
        Filesystem::recursiveCopy($this->sourcePath, $this->destPath);
    }

    public function onCompletion() : void{
        $server = Server::getInstance();
        $player = $server->getPlayerExact($this->playerName);
        if($player === null || !$player->isConnected()){
            return;
        }
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $map = MapFactory::getMapByName($this->mapName);
        if($map === null){
            $session->message("§cEdit failed.");
            return;
        }

        $wm = $server->getWorldManager();
        if(!$wm->loadWorld($this->editWorld)){
            $session->message("§cEdit failed.");
            return;
        }

        $world = $wm->getWorldByName($this->editWorld);
        if($world === null){
            $session->message("§cEdit failed.");
            return;
        }

        foreach($world->getEntities() as $e){
            if($e instanceof Villager){
                $e->close();
            }
        }

        foreach($map->getShopPositions() as $pos){
            $yaw = method_exists($map, "getShopYaw") ? $map->getShopYaw($pos) : 0.0;
            $villager = new ItemShopVillager(new Location($pos->x, $pos->y, $pos->z, $world, $yaw, 0.0));

            $p = $villager->getPosition()->floor();
            $world->requestChunkPopulation($p->getX() >> Chunk::COORD_BIT_SIZE, $p->getZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
                fn() => $villager->spawnToAll(),
                fn() => null
            );
        }

        foreach($map->getUpgradesPositions() as $pos){
            $yaw = method_exists($map, "getUpgradesYaw") ? $map->getUpgradesYaw($pos) : 0.0;
            $villager = new UpgradesShopVillager(new Location($pos->x, $pos->y, $pos->z, $world, $yaw, 0.0));

            $p = $villager->getPosition()->floor();
            $world->requestChunkPopulation($p->getX() >> Chunk::COORD_BIT_SIZE, $p->getZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
                fn() => $villager->spawnToAll(),
                fn() => null
            );
        }

        $builder = MapBuilder::fromMap($map, $this->editWorld);
        $session->setMapSetup(new MapSetup($session, $builder, true));

        $player->setGamemode(GameMode::CREATIVE());
        $player->teleport($world->getSafeSpawn());

        $session->message("§aEdit mode enabled.");
    }
}