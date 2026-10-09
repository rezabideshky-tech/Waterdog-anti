<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\team;

use sergittos\bedwars\game\Game;
use sergittos\bedwars\session\Session;

final class TeamSelectionManager{

    /** @var array<int, array<string, string>> gameId => [username => teamName] */
    private static array $selected = [];

    public static function set(Session $session, ?string $teamName) : void{
        $game = $session->getGame();
        if($game === null){
            return;
        }

        $gid = $game->getId();
        $user = strtolower($session->getUsername());

        if($teamName === null || $teamName === ""){
            unset(self::$selected[$gid][$user]);
            return;
        }

        self::$selected[$gid][$user] = $teamName;
    }

    public static function get(Session $session) : ?string{
        $game = $session->getGame();
        if($game === null){
            return null;
        }

        return self::$selected[$game->getId()][strtolower($session->getUsername())] ?? null;
    }

    public static function clear(Session $session) : void{
        $game = $session->getGame();
        if($game === null){
            return;
        }

        unset(self::$selected[$game->getId()][strtolower($session->getUsername())]);
    }

    public static function clearGame(Game $game) : void{
        unset(self::$selected[$game->getId()]);
    }

    public static function countSelected(Game $game, string $teamName) : int{
        $gid = $game->getId();
        if(!isset(self::$selected[$gid])){
            return 0;
        }

        $count = 0;
        foreach(self::$selected[$gid] as $sel){
            if(strtolower($sel) === strtolower($teamName)){
                $count++;
            }
        }
        return $count;
    }

    /** @return string[] */
    public static function listSelected(Game $game, string $teamName) : array{
        $gid = $game->getId();
        if(!isset(self::$selected[$gid])){
            return [];
        }

        $out = [];
        foreach(self::$selected[$gid] as $user => $sel){
            if(strtolower($sel) === strtolower($teamName)){
                $out[] = $user;
            }
        }
        return $out;
    }
}