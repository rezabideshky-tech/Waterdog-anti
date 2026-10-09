<?php

declare(strict_types=1);

namespace sergittos\bedwars\form\setup;

use pocketmine\player\Player;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\game\map\task\PrepareEditWorldTask;
use sergittos\bedwars\libs\EasyUI\element\ModalOption;
use sergittos\bedwars\libs\EasyUI\variant\ModalForm;
use sergittos\bedwars\session\SessionFactory;

class EditMapConfirmForm extends ModalForm{

    public function __construct(private string $mapName){
        parent::__construct(
            "Edit Map",
            "Edit §e" . $mapName . "§f?\n\n§7This will open an edit world and enable setup tools.",
            new ModalOption("§aStart Editing"),
            new ModalOption("§cCancel")
        );
    }

    protected function onAccept(Player $player) : void{
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $map = MapFactory::getMapByName($this->mapName);
        if($map === null){
            SessionFactory::getSession($player)->message("{RED}{BOLD}Edit Failed{RESET}{GRAY} - Map not found.");
            return;
        }

        $session = SessionFactory::getSession($player);
        if($session->isPlaying() || $session->isSpectator()){
            $session->message("{RED}{BOLD}Edit Locked{RESET}{GRAY} - Leave the match first.");
            return;
        }

        $session->message("{YELLOW}{BOLD}Preparing Edit World{RESET}{GRAY} - Please wait...");
        BedWars::getInstance()->getServer()->getAsyncPool()->submitTask(
            new PrepareEditWorldTask($player->getName(), $this->mapName)
        );
    }

    protected function onDeny(Player $player) : void{
    }
}