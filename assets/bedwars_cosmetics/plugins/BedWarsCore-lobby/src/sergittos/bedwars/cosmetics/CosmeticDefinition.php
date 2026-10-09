<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics;

class CosmeticDefinition {

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $description,
        public readonly string $rarity,
        public readonly string $permission,   // "" = همه می‌تونن
        public readonly int    $price,
        public readonly string $texture,       // cape/wing/hat
        public readonly string $particleId,    // particle
        public readonly string $soundId,       // kill_sound
    ) {}

    public function getRarityColor(): string {
        return match (strtolower($this->rarity)) {
            "common"    => "§7",
            "uncommon"  => "§a",
            "rare"      => "§9",
            "epic"      => "§5",
            "legendary" => "§6",
            "mythic"    => "§d",
            default     => "§f",
        };
    }

    public function getRarityDisplay(): string {
        return $this->getRarityColor() . ucfirst($this->rarity);
    }
}
