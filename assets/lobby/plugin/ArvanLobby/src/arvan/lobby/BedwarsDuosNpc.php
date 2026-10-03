<?php
declare(strict_types=1);

namespace arvan\lobby;

final class BedwarsDuosNpc extends LobbyNpc{
	public const NETWORK_ID = "arvan:bedwars_duos:duos";

	public static function getNetworkTypeId() : string{ return self::NETWORK_ID; }

	protected function key() : string{ return ""; }
}
