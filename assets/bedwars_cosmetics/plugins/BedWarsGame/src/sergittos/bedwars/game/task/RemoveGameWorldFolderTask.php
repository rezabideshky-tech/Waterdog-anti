<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use function is_dir;

final class RemoveGameWorldFolderTask extends AsyncTask{

    private string $path;

    public function __construct(
        private int $gameId,
        private string $worldFolderName
    ){
        // Server::getInstance() can only be called from the main thread.
        // The old code called it from inside onRun(), which runs on an
        // AsyncWorker thread and has no Server instance at all - this
        // threw "Attempt to retrieve Server instance outside server
        // thread", crashed the worker, and took the whole server down
        // (see the EMERGENCY crash log). Resolve the path here, in the
        // constructor, which always runs on the main thread, and only
        // touch the filesystem in onRun().
        $this->path = Server::getInstance()->getDataPath() . "worlds/" . $this->worldFolderName;
    }

    public function onRun(): void{
        if(is_dir($this->path)){
            Filesystem::recursiveUnlink($this->path);
        }
    }

    public function onCompletion(): void{
        $plugin = BedWars::getInstance();
        if($plugin->isEnabled()){
            $plugin->getGameManager()->removeGame($this->gameId);
        }
    }
}