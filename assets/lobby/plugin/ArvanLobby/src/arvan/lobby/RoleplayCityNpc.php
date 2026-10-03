<?php
declare(strict_types=1);

namespace arvan\lobby;

final class RoleplayCityNpc extends LobbyNpc{
	public const NETWORK_ID = "arvan:roleplay_city";

	public static function getNetworkTypeId() : string{ return self::NETWORK_ID; }

	protected function key() : string{ return ""; }
}
