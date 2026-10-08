<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

final class PedestalLevel extends HalloweenPedestal {

    public const NETWORK_ID = "arvan:lb_level";

    public static function getNetworkTypeId() : string{
        return self::NETWORK_ID;
    }

    protected function statKey() : string{
        return "level";
    }
}
