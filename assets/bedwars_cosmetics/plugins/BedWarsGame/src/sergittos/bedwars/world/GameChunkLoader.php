<?php

declare(strict_types=1);

namespace sergittos\bedwars\world;

use pocketmine\world\ChunkLoader;

final class GameChunkLoader implements ChunkLoader{

    public function __construct(private int $loaderId){}

    public function getLoaderId(): int{
        return $this->loaderId;
    }

    public function isLoaderActive(): bool{
        return true;
    }
}