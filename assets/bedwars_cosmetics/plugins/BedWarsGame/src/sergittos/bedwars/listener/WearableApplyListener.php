<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\cosmetics\wearable\WearableRenderService;
use sergittos\bedwars\cosmetics\wearable\WearableSkinService;
use sergittos\bedwars\game\BedWarsGame;

/**
 * Applies the player's equipped Wing/Cape/Hat cosmetics on the game server.
 * Pets are intentionally not handled here - they only exist on the lobby
 * server. This covers the pre-team-assignment window (waiting room,
 * spectating); once a team is assigned, GameSettings::apply() re-applies
 * the equipped Hat itself every time it runs (kit give, respawn, etc.) so
 * it survives the team-colored chestplate/leggings/boots being (re)issued
 * alongside it.
 */
final class WearableApplyListener implements Listener{

    public function onJoin(PlayerJoinEvent $event): void{
        $player = $event->getPlayer();

        WearableSkinService::captureOriginal($player);

        BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void{
            if(!$player->isConnected()){
                return;
            }
            WearableRenderService::applyAll($player);
        }), 30);
    }

    public function onQuit(PlayerQuitEvent $event): void{
        WearableSkinService::forget($event->getPlayer());
    }
}
