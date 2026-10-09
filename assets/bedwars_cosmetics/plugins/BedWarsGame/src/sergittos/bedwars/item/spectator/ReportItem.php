<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\spectator;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\form\report\ReportMenuForm;
use sergittos\bedwars\session\Session;

class ReportItem extends SpectatorItem {

    public function __construct() {
        parent::__construct("{RED}Report Player");
    }

    protected function onSpectatorInteract(Session $session): void {
        $session->getPlayer()->sendForm(new ReportMenuForm($session));
    }

    protected function realItem(): Item {
        return VanillaItems::BOOK();
    }

}
