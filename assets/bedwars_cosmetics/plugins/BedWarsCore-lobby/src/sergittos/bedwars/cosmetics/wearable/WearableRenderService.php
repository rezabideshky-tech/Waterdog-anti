<?php
declare(strict_types=1);
namespace sergittos\bedwars\cosmetics\wearable;
use pocketmine\player\Player;
/** Resource-only visuals. Keep the established API for lobby/game/session callers. */
final class WearableRenderService{
    public static function applyAll(Player $player): void{ ResourceCosmeticService::apply($player); }
}
