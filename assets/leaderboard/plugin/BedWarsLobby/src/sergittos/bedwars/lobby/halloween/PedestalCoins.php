<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

final class PedestalCoins extends HalloweenPedestal {

    public const NETWORK_ID = "arvan:lb_coins";

    public static function getNetworkTypeId() : string{
        return self::NETWORK_ID;
    }

    protected function statKey() : string{
        return "coins";
    }
}
