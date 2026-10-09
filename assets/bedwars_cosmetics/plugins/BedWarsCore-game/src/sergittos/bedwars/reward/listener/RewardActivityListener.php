<?php

declare(strict_types=1);

namespace sergittos\bedwars\reward\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\player\PlayerToggleSprintEvent;
use sergittos\bedwars\BedWarsCore;

/**
 * Feeds the playtime reward system's AFK detector. Deliberately does
 * nothing but stamp "last activity = now" for the player that triggered
 * the event - no lookups, no branching beyond a null-session guard - so
 * that hooking every one of these high-frequency events (PlayerMoveEvent
 * in particular) adds effectively zero measurable overhead on top of what
 * the rest of the plugin already listens to.
 *
 * Deliberately NOT listening to every possible interaction (e.g. no
 * inventory-transaction hook): movement, block/item interaction, chat and
 * sprint/sneak toggles are already more than enough signal to distinguish
 * a real player from an AFK-machine or a stationary bot, without adding a
 * listener for every single event type in the game.
 */
final class RewardActivityListener implements Listener{

    public function __construct(private BedWarsCore $plugin){}

    public function onMove(PlayerMoveEvent $event): void{
        $this->plugin->getPlaytimeRewardManager()->recordActivity($event->getPlayer());
    }

    public function onInteract(PlayerInteractEvent $event): void{
        $this->plugin->getPlaytimeRewardManager()->recordActivity($event->getPlayer());
    }

    public function onItemUse(PlayerItemUseEvent $event): void{
        $this->plugin->getPlaytimeRewardManager()->recordActivity($event->getPlayer());
    }

    public function onChat(PlayerChatEvent $event): void{
        $this->plugin->getPlaytimeRewardManager()->recordActivity($event->getPlayer());
    }

    public function onSneak(PlayerToggleSneakEvent $event): void{
        $this->plugin->getPlaytimeRewardManager()->recordActivity($event->getPlayer());
    }

    public function onSprint(PlayerToggleSprintEvent $event): void{
        $this->plugin->getPlaytimeRewardManager()->recordActivity($event->getPlayer());
    }
}
