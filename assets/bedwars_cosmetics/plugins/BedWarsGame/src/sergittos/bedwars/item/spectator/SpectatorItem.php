<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\spectator;

use pocketmine\player\GameMode;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\session\Session;

abstract class SpectatorItem extends BedwarsItem{

    public function onInteract(Session $session): void{
        $p = $session->getPlayer();
        if($session->isSpectator() || $p->getGamemode() === GameMode::SPECTATOR()){
            $this->onSpectatorInteract($session);
        }
    }

    abstract protected function onSpectatorInteract(Session $session): void;
}