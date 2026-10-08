<?php
declare(strict_types=1);

namespace arvan\props;

use pocketmine\entity\EntitySizeInfo;

final class AmmoCaseProp extends PropEntity{
    public const NETWORK_ID = "arvan:rp_ammo_case";

    public static function getNetworkTypeId() : string{
        return self::NETWORK_ID;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.5, 1.9);
    }
}
