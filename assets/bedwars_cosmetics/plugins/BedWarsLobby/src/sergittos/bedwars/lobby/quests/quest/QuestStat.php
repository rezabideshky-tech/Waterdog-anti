<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\quests\quest;

enum QuestStat: string{
    case KILLS = "kills";
    case FINAL_KILLS = "final_kills";
    case BEDS_BROKEN = "beds_broken";
    case WINS = "wins";

    public function label(): string{
        return match($this){
            self::KILLS => "Kills",
            self::FINAL_KILLS => "Final Kills",
            self::BEDS_BROKEN => "Beds Broken",
            self::WINS => "Wins",
        };
    }
}