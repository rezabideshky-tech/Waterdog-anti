<?php
declare(strict_types=1);

namespace arvan\props;

use pocketmine\entity\EntitySizeInfo;

final class PumpProp extends PropEntity{
    public const NETWORK_ID = "arvan:rp_digital_pump";

    public static function getNetworkTypeId() : string{
        return self::NETWORK_ID;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(2.3, 2.2);
    }
}
