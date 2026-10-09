<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\shop;

use sergittos\bedwars\gui\UpgradesGui;
use sergittos\bedwars\session\Session;

class UpgradesShopVillager extends Villager {

    protected function getName(): string {
        return "TEAM\n{AQUA}UPGRADES";
    }

    protected function getForm(Session $session) {
        $gui = new UpgradesGui();
        $gui->send($session->getPlayer());
    }

}