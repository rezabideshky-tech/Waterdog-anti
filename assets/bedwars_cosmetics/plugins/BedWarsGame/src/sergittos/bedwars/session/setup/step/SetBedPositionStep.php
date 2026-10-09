<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\setup\step;

use pocketmine\event\Cancellable;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\item\setup\SetBedPositionItem;
use sergittos\bedwars\session\setup\builder\TeamBuilder;

final class SetBedPositionStep extends Step{

    private TeamBuilder $team;

    public function __construct(TeamBuilder $team){
        $this->team = $team;
    }

    protected function onStart(): void{
        $this->session->clearAllInventories();
        $this->session->message("{YELLOW}Place the bed to set the bed position.");

        $inv = $this->session->getPlayer()->getInventory();
        $inv->setItem(0, BedwarsItems::BED_POSITION()->setColor($this->team->getDyeColor())->asItem());
        $inv->setItem(8, BedwarsItems::CANCEL()->asItem());
    }

    public function onBlockInteract(Vector3 $touch_vector, int $action, Cancellable $event, BedwarsItem $item): void{
        if(!$item instanceof SetBedPositionItem || $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            return;
        }

        $bedPos = $touch_vector->add(0, 1, 0);
        $this->team->setBedPosition($bedPos);

        $this->session->getMapSetup()->setStep(new PreparingMapStep());
        $this->session->message("{GREEN}Bed position set.");

        $event->uncancel();
    }
}