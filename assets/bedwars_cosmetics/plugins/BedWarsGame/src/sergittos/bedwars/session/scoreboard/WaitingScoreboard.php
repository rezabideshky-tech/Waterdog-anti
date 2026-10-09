<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\scoreboard;

use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\session\Session;

final class WaitingScoreboard extends Scoreboard{

    protected function getLines(Session $session): array{
        $game = $session->getGame();
        $map = $game->getMap();
        $stage = $game->getStage();

        $ip = (string) BedWarsCore::getInstance()->getConfig()->get("ip", "§eArvanGaming.IR");

        $state = ($stage instanceof StartingStage)
            ? ("Starting in " . $stage->getCountdown() . "s")
            : "Waiting for players";

        return [
            12 => "{GRAY}" . date("m/d/y"),
            11 => " ",
            10 => "{WHITE}Map: {GREEN}" . $map->getName(),
            9  => "{WHITE}Players: {GREEN}" . $game->getPlayersCount() . "{GRAY}/{GREEN}" . $map->getMaxCapacity(),
            8  => "{WHITE}" . $state,
            7  => "  ",
            6  => "{WHITE}Mode: {GREEN}" . $this->modeName($map->getPlayersPerTeam()),
            5  => "   ",
            4  => "{GRAY}" . $ip
        ];
    }

    private function modeName(int $ppt): string{
        return match($ppt){
            1 => "SOLO",
            2 => "DOUBLES",
            3 => "TRIPLES",
            4 => "SQUADS",
            default => "UNKNOWN",
        };
    }
}