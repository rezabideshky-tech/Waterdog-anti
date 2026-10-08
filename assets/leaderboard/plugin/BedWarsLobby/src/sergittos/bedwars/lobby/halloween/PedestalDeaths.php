<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

final class PedestalDeaths extends HalloweenPedestal {

    public const NETWORK_ID = "arvan:lb_deaths";

    public static function getNetworkTypeId() : string{
        return self::NETWORK_ID;
    }

    protected function statKey() : string{
        return "deaths";
    }
}
