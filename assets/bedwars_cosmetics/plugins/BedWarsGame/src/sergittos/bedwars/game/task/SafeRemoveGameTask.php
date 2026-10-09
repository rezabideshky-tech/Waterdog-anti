<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\task;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use sergittos\bedwars\game\BedWarsGame as BedWars;

final class SafeRemoveGameTask extends Task{

    private bool $unloadRequested = false;
    private int $deleteAtTick = 0;
    private int $attempts = 0;

    public function __construct(
        private int $gameId,
        private string $worldFolderName,
        private int $delayTicks = 100,
        private int $maxAttempts = 12
    ){}

    public function onRun(): void{
        $this->attempts++;

        $server = Server::getInstance();
        $tick = $server->getTick();
        $wm = $server->getWorldManager();

        if(!$this->unloadRequested){
            $this->unloadRequested = true;

            $game = BedWars::getInstance()->getGameManager()->getGameById($this->gameId);
            if($game !== null){
                $game->unloadWorld();
            }else{
                $world = $wm->getWorldByName($this->worldFolderName);
                if($world !== null){
                    $world->setAutoSave(false);
                    $wm->unloadWorld($world, true);
                }
            }

            $this->deleteAtTick = $tick + $this->delayTicks;
            return;
        }

        if($tick < $this->deleteAtTick){
            return;
        }

        $world = $wm->getWorldByName($this->worldFolderName);
        if($world !== null){
            $world->setAutoSave(false);
            $wm->unloadWorld($world, true);
            $this->deleteAtTick = $tick + $this->delayTicks;

            if($this->attempts >= $this->maxAttempts){
                $this->getHandler()?->cancel();
            }
            return;
        }

        $server->getAsyncPool()->submitTask(new RemoveGameWorldFolderTask($this->gameId, $this->worldFolderName));
        $this->getHandler()?->cancel();
    }
}