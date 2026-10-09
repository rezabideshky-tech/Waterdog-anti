<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics\registry;

final class CosmeticDefinition{

    public function __construct(
        private string $key,
        private CosmeticCategory $category,
        private string $displayName,
        private int $price,
        private CosmeticRarity $rarity,
        private string $iconPath
    ){}

    public function getKey(): string{ return $this->key; }
    public function getCategory(): CosmeticCategory{ return $this->category; }
    public function getDisplayName(): string{ return $this->displayName; }
    public function getPrice(): int{ return $this->price; }
    public function getRarity(): CosmeticRarity{ return $this->rarity; }
    public function getIconPath(): string{ return $this->iconPath; }
}