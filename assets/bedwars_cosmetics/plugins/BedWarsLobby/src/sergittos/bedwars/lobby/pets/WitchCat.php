<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\pets;

use pocketmine\entity\EntitySizeInfo;

final class WitchCat extends FlyingPetBase{

    protected function getInitialSizeInfo(): EntitySizeInfo{
        return new EntitySizeInfo(0.8, 0.8);
    }

    protected function getInitialDragMultiplier(): float{
        return 0;
    }

    protected function getInitialGravity(): float{
        return 0;
    }

    public static function getNetworkTypeId(): string{
        return "hivepets:witchcat";
    }
}
