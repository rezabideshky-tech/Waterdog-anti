<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\mob;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class SilverfishEntity extends HostileMob{

    public static function getNetworkTypeId() : string{
        return EntityIds::SILVERFISH;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.3, 0.7);
    }

    protected function getMobName() : string{
        return "§7Silverfish";
    }

    protected function getMobMaxHealth() : float{
        return 8;
    }

    protected function getAttackDamage() : float{
        return 2.0;
    }

    protected function getMoveSpeed() : float{
        return 0.36;
    }

    protected function getAttackRange() : float{
        return 1.6;
    }

    protected function getDetectionRange() : float{
        return 10.0;
    }

    protected function getLifetimeSeconds() : int{
        return 25;
    }

    public function saveNBT() : CompoundTag{
        return parent::saveNBT();
    }
}