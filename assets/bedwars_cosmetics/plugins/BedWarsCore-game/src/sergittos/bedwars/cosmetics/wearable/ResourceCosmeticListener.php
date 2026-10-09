<?php
declare(strict_types=1);
namespace sergittos\bedwars\cosmetics\wearable;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;

final class ResourceCosmeticListener implements Listener{
    public function onQuit(PlayerQuitEvent $event): void{ ResourceCosmeticService::forget($event->getPlayer()); }
    public function onDeath(PlayerDeathEvent $event): void{ ResourceCosmeticService::forget($event->getPlayer()); }
}
