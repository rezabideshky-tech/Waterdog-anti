<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\pets;

use pocketmine\entity\EntitySizeInfo;

final class Rock extends PetBase{

    protected function getInitialSizeInfo(): EntitySizeInfo{
        return new EntitySizeInfo(0.8, 0.8);
    }

    protected function getInitialDragMultiplier(): float{
        return 0.1;
    }

    protected function getInitialGravity(): float{
        return 0.1;
    }

    public static function getNetworkTypeId(): string{
        return "hivepets:rock";
    }
}
