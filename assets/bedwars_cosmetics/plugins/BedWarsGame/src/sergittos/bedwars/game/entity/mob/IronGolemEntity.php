<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\mob;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class IronGolemEntity extends HostileMob{

    public static function getNetworkTypeId() : string{
        return EntityIds::IRON_GOLEM;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(2.9, 1.4);
    }

    protected function getMobName() : string{
        return "§fIron Golem";
    }

    protected function getMobMaxHealth() : float{
        return 100;
    }

    protected function getAttackDamage() : float{
        return 9.0;
    }

    protected function getMoveSpeed() : float{
        return 0.28;
    }

    protected function getAttackRange() : float{
        return 2.8;
    }

    protected function getDetectionRange() : float{
        return 14.0;
    }

    protected function getLifetimeSeconds() : int{
        return 150;
    }

    public function saveNBT() : CompoundTag{
        return parent::saveNBT();
    }
}