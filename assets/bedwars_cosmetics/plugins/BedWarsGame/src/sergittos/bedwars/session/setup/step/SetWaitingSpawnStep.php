<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\setup\step;

use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\item\setup\SetWaitingSpawnItem;

final class SetWaitingSpawnStep extends Step{

    protected function onStart(): void{
        $this->session->clearAllInventories();
        $inv = $this->session->getPlayer()->getInventory();
        $inv->setItem(0, BedwarsItems::WAITING_SPAWN()->asItem());
        $inv->setItem(8, BedwarsItems::CANCEL()->asItem());
    }

    public function onInteract(BedwarsItem $item): void{
        if(!$item instanceof SetWaitingSpawnItem){
            return;
        }

        $setup = $this->session->getMapSetup();
        if($setup === null){
            return;
        }

        $pos = $this->session->getPlayer()->getPosition();
        $setup->getMapBuilder()->setWaitingSpawnPosition($pos->asVector3());

        $setup->setStep(new PreparingMapStep());
    }
}