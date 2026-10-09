<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\stage;

use sergittos\bedwars\game\stage\trait\JoinableTrait;
use sergittos\bedwars\session\Session;

final class WaitingStage extends Stage{
    use JoinableTrait{
        onJoin as onSessionJoin;
    }

    protected function onStart(): void{
        if($this->game->getWorld() === null){
            try{
                $this->game->setupWorld();
            }catch(\Throwable){
            }
        }
    }

    public function onJoin(Session $session): void{
        $this->onSessionJoin($session);
        $this->startIfReady();
    }

    private function startIfReady(): void{
        if($this->isReadyToStart()){
            $this->game->setStage(new StartingStage());
        }
    }

    private function isReadyToStart(): bool{
        // Only the minimum player count matters for deciding whether to
        // START the countdown. Whether the final player count divides
        // evenly between teams is irrelevant here: PlayingStage::onStart()
        // already distributes leftover players across teams (allowing
        // uneven team sizes), so we must not block/cancel the countdown
        // over team-size divisibility.
        return $this->game->getPlayersCount() >= $this->getMinPlayers();
    }

    private function getMinPlayers(): int{
        $map = $this->game->getMap();
        $ppt = $map->getPlayersPerTeam();

        return match($ppt){
            1 => 2,
            2 => 4,
            3 => 6,
            4 => 8,
            default => (int) ($map->getMaxCapacity() / 2)
        };
    }

    public function tick(): void{}
}