<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\world\ChunkUnloadEvent;
use sergittos\bedwars\game\BedWarsGame as BedWars;

final class GameWorldChunkLockListener implements Listener{

    public function onChunkUnload(ChunkUnloadEvent $event): void{
        $game = BedWars::getInstance()->getGameManager()->getGameByWorld($event->getWorld());
        if($game !== null && !$game->isWorldClosing()){
            $event->cancel();
        }
    }
}