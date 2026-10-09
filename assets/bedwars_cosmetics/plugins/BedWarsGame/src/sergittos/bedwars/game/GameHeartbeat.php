<?php

declare(strict_types=1);

namespace sergittos\bedwars\game;

use pocketmine\scheduler\Task;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\stage\PlayingStage;

final class GameHeartbeat extends Task{

    private int $tick = 0;

    public function onRun(): void{
        $this->tick++;

        $games = BedWars::getInstance()->getGameManager()->getGames();

        foreach($games as $game){
            $stage = $game->getStage();

            // Offset the once-per-second maintenance work by the game's own id so that
            // many concurrently running games don't all perform it on the exact same
            // tick - previously every game's purge + stage tick landed on tick % 20 === 0
            // simultaneously, which spiked CPU usage for that single tick once a second.
            if((($this->tick + $game->getId()) % 20) === 0){
                $game->purgeInvalidSessions();
            }

            if($stage instanceof PlayingStage){
                $game->tickGenerators();
            }

            if((($this->tick + $game->getId()) % 20) === 0){
                $stage->tick();
            }
        }
    }
}