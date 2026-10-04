<?php
declare(strict_types=1);

namespace arvan\lobby;

final class CargoTruckNpc extends LobbyNpc{
	public const NETWORK_ID = "arvan:cargo_truck";

	public static function getNetworkTypeId() : string{ return self::NETWORK_ID; }

	protected function key() : string{ return "truck"; }
}
