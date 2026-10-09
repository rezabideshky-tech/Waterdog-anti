<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\setup;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\session\Session;

class SaveEditsItem extends BedwarsItem{

    public function __construct(){
        parent::__construct("{GREEN}Save edits");
    }

    public function onInteract(Session $session) : void{
        $setup = $session->getMapSetup();
        if($setup === null || !$setup->isEditing()){
            $session->message("{RED}{BOLD}Save Failed{RESET}{GRAY} - Not in edit mode.");
            return;
        }

        if(!$setup->getMapBuilder()->canBeBuilt()){
            $session->message("{RED}{BOLD}Save Failed{RESET}{GRAY} - Complete all required map settings first.");
            return;
        }

        if(!$setup->updateMap()){
            $session->message("{YELLOW}Already saving this map, please wait...");
            return;
        }

        $session->message("{YELLOW}{BOLD}Saving Changes{RESET}{GRAY} - Please wait...");
        $session->teleportToHub();
    }

    protected function realItem() : Item{
        return VanillaItems::EMERALD();
    }
}