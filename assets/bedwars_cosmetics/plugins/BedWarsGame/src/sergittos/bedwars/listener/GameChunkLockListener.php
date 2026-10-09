<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\world\ChunkUnloadEvent;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\stage\PlayingStage;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\game\stage\WaitingStage;

final class GameChunkLockListener implements Listener{

    public function onChunkUnload(ChunkUnloadEvent $event): void{
        $game = BedWars::getInstance()->getGameManager()->getGameByWorld($event->getWorld());
        if($game === null){
            return;
        }

        if($game->isWorldClosing()){
            return;
        }

        $stage = $game->getStage();
        if(!($stage instanceof WaitingStage || $stage instanceof StartingStage || $stage instanceof PlayingStage)){
            return;
        }

        $game->lockChunk($event->getChunkX(), $event->getChunkZ());
        $event->cancel();
    }
}