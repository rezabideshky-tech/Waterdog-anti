<?php
declare(strict_types=1);

namespace arvan\props;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;

abstract class PropEntity extends Entity{
    protected function getInitialDragMultiplier() : float{
        return 0.0;
    }

    protected function getInitialGravity() : float{
        return 0.0;
    }

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);
        $this->setNameTag("");
        $this->setNameTagVisible(false);
        $this->setHasGravity(false);
        $this->setNoClientPredictions(true);
        $this->setCanSaveWithChunk(true);
    }

    public function canBeMovedByCurrents() : bool{
        return false;
    }

    public function attack(EntityDamageEvent $source) : void{
        $source->cancel();
    }
}
