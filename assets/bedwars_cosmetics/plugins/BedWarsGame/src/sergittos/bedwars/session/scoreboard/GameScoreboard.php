<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\scoreboard;

use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\stage\EndingStage;
use sergittos\bedwars\game\stage\PlayingStage;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\session\Session;
use function floor;

final class GameScoreboard extends Scoreboard{

    protected function getLines(Session $session): array{
        $game = $session->getGame();
        $stage = $game->getStage();

        return match(true){
            $stage instanceof PlayingStage => $this->playing($session, $game, $stage),
            $stage instanceof StartingStage => $this->starting($game, $stage),
            $stage instanceof EndingStage => $this->ending($game, $stage),
            default => $this->waitingLike($game),
        };
    }

    private function playing(Session $session, Game $game, PlayingStage $stage): array{
        $ip = (string) BedWarsCore::getInstance()->getConfig()->get("ip", "§eArvanGaming.IR");

        $lines = [];
        $line = 15;

        $lines[$line--] = " ";

        $event = $stage->getNextEvent();
        if($event !== null){
            $t = $this->mmss($event->getTimeRemaining());
            $lines[$line--] = "{WHITE}" . $event->getName() . " in {GREEN}" . $t;
        }else{
            $lines[$line--] = "{WHITE}Final Battle";
        }

        $lines[$line--] = "  ";

        $my = $session->getTeam();
        foreach($game->getTeams() as $team){
            $isMine = $my !== null && $team === $my;

            if(!$team->isAlive()){
                $status = "{WHITE}\u{F142}";
            }elseif(!$team->isBedDestroyed()){
                $status = "{WHITE}\u{F18A}";
            }else{
                $status = "{GRAY}" . $team->getMembersCount();
            }

            $lines[$line--] = $team->getIcon() . " {WHITE}" . $team->getName() . ": " . $status . ($isMine ? " {GRAY}YOU" : "");
        }

        // Kills / Final Kills / Beds Broken are only shown in Triples and
        // Squads (playersPerTeam >= 3) - hidden from the scoreboard in
        // Solo and Doubles (playersPerTeam 1 or 2).
        $ppt = $game->getMap()->getPlayersPerTeam();
        if($ppt >= 3){
            $lines[$line--] = "   ";
            $lines[$line--] = "{WHITE}Kills: {GREEN}" . $session->getGameKills();
            $lines[$line--] = "{WHITE}Final Kills: {RED}" . $session->getGameFinalKills();
            $lines[$line--] = "{WHITE}Beds Broken: {YELLOW}" . $session->getGameBedsBroken();
        }
        $lines[$line--] = "    ";
        $lines[$line--] = "{GRAY}" . $ip;

        return $lines;
    }

    private function starting(Game $game, StartingStage $stage): array{
        $ip = (string) BedWarsCore::getInstance()->getConfig()->get("ip", "§eArvanGaming.IR");

        return [
            12 => "{GRAY}" . date("m/d/y"),
            11 => " ",
            10 => "{WHITE}Starting in {GREEN}" . $stage->getCountdown() . "s",
            9  => "{WHITE}Map: {GREEN}" . $game->getMap()->getName(),
            8  => "{WHITE}Players: {GREEN}" . $game->getPlayersCount() . "{GRAY}/{GREEN}" . $game->getMap()->getMaxCapacity(),
            7  => "  ",
            6  => "{WHITE}Mode: {GREEN}" . $this->modeName($game->getMap()->getPlayersPerTeam()),
            5  => "   ",
            4  => "{GRAY}" . $ip
        ];
    }

    private function ending(Game $game, EndingStage $stage): array{
        $ip = (string) BedWarsCore::getInstance()->getConfig()->get("ip", "§eArvanGaming.IR");

        $winner = $stage->isTie() || $stage->getWinnerTeam() === null
            ? "{WHITE}Tie"
            : ($stage->getWinnerTeam()->getColor() . $stage->getWinnerTeam()->getName());

        return [
            10 => "{GRAY}" . date("m/d/y"),
            9  => " ",
            8  => "{WHITE}Result: " . $winner,
            7  => "{WHITE}Returning in {GREEN}" . $stage->getTime() . "s",
            6  => "  ",
            5  => "{WHITE}Map: {GREEN}" . $game->getMap()->getName(),
            4  => "{WHITE}Mode: {GREEN}" . $this->modeName($game->getMap()->getPlayersPerTeam()),
            3  => "   ",
            2  => "{GRAY}" . $ip
        ];
    }

    private function waitingLike(Game $game): array{
        $ip = (string) BedWarsCore::getInstance()->getConfig()->get("ip", "§eArvanGaming.IR");

        return [
            10 => "{GRAY}" . date("m/d/y"),
            9  => " ",
            8  => "{WHITE}Waiting for players",
            7  => "{WHITE}Map: {GREEN}" . $game->getMap()->getName(),
            6  => "{WHITE}Players: {GREEN}" . $game->getPlayersCount() . "{GRAY}/{GREEN}" . $game->getMap()->getMaxCapacity(),
            5  => "  ",
            4  => "{WHITE}Mode: {GREEN}" . $this->modeName($game->getMap()->getPlayersPerTeam()),
            3  => "   ",
            2  => "{GRAY}" . $ip
        ];
    }

    private function mmss(int $seconds): string{
        if($seconds < 0) $seconds = 0;
        $m = (int) floor($seconds / 60);
        $s = $seconds % 60;
        return str_pad((string) $m, 2, "0", STR_PAD_LEFT) . ":" . str_pad((string) $s, 2, "0", STR_PAD_LEFT);
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