<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

final class PedestalFinalKills extends HalloweenPedestal {

    public const NETWORK_ID = "arvan:lb_final_kills";

    public static function getNetworkTypeId() : string{
        return self::NETWORK_ID;
    }

    protected function statKey() : string{
        return "final_kills";
    }
}
