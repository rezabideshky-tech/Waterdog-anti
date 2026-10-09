<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

final class ProfileListener implements Listener{

    public function __construct(private ProfileService $service){}

    public function onJoin(PlayerJoinEvent $event) : void{
        $this->service->onJoin($event->getPlayer());
    }

    /** @priority MONITOR */
    public function onQuit(PlayerQuitEvent $event) : void{
        $this->service->onQuit($event->getPlayer());
    }
}
