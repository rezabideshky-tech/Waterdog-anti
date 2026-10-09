<?php
declare(strict_types=1);
namespace sergittos\bedwars\cosmetics\wearable;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
/** Compatibility hooks: V2 never reads, composites, stores, or replaces player skins. */
final class WearableSkinService{
    public static function captureOriginal(Player $player): void{}
    public static function forget(Player $player): void{ ResourceCosmeticService::forget($player); }
    public static function restore(Player $player): void{ ResourceCosmeticService::apply($player); }
    public static function hasGd(): bool{ return true; } // V2 has no GD dependency
    public static function apply(Plugin $plugin, Player $player, ?string $wingKey, ?string $capeKey): void{
        ResourceCosmeticService::apply($player);
    }
}
