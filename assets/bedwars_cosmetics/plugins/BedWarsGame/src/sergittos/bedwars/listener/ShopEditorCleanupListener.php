<?php

declare(strict_types=1);


namespace sergittos\bedwars\listener;


use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use sergittos\bedwars\game\shop\item\editor\ShopEditorManager;

/**
 * Frees the in-memory Shop Editor layout for a player as soon as they
 * disconnect, so ShopEditorManager's static registry never accumulates
 * stale entries across a long-running server uptime.
 */
final class ShopEditorCleanupListener implements Listener {

    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        ShopEditorManager::persist($player);
        ShopEditorManager::remove($player);
    }

}
