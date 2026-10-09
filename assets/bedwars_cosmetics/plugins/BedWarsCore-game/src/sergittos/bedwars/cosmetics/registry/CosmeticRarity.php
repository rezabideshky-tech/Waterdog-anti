<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics\registry;

enum CosmeticRarity: string{
    case COMMON = "Common";
    case RARE = "Rare";
    case EPIC = "Epic";
    case LEGENDARY = "Legendary";

    public function color(): string{
        return match($this){
            self::COMMON => "§7",
            self::RARE => "§b",
            self::EPIC => "§5",
            self::LEGENDARY => "§6",
        };
    }
}