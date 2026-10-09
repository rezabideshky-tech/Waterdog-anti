<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\quests\quest;

enum QuestType: string{
    case DAILY = "daily";
    case WEEKLY = "weekly";

    public function displayName(): string{
        return match($this){
            self::DAILY => "Daily Quests",
            self::WEEKLY => "Weekly Quests",
        };
    }

    public function color(): string{
        return match($this){
            self::DAILY => "§e",
            self::WEEKLY => "§6",
        };
    }

    public function icon(): string{
        return match($this){
            self::DAILY => "textures/items/clock_item",
            self::WEEKLY => "textures/items/book",
        };
    }
}