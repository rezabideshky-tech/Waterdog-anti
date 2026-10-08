<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

final class PedestalBedsBroken extends HalloweenPedestal {

    public const NETWORK_ID = "arvan:lb_beds_broken";

    public static function getNetworkTypeId() : string{
        return self::NETWORK_ID;
    }

    protected function statKey() : string{
        return "beds_broken";
    }
}
