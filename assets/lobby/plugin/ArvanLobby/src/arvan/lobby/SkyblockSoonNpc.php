<?php
declare(strict_types=1);

namespace arvan\lobby;

final class SkyblockSoonNpc extends LobbyNpc{
	public const NETWORK_ID = "arvan:skyblock_soon";

	public static function getNetworkTypeId() : string{ return self::NETWORK_ID; }

	protected function key() : string{ return "skyblock"; }
}
