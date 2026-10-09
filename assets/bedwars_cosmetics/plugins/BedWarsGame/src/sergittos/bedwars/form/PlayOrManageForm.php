<?php

declare(strict_types=1);

namespace sergittos\bedwars\form;

use pocketmine\player\Player;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\SessionFactory;

class PlayOrManageForm extends SimpleForm {

    public function __construct(private \Closure $onPlay) {
        parent::__construct("BedWars", "You have moderator permission on this server. What would you like to do?");
    }

    protected function onCreation(): void {
        $playButton = new \sergittos\bedwars\libs\EasyUI\element\Button("Play");
        $playButton->setSubmitListener(function(Player $player): void {
            ($this->onPlay)();
        });
        $this->addButton($playButton);

        $manageButton = new \sergittos\bedwars\libs\EasyUI\element\Button("Manage games");
        $manageButton->setSubmitListener(function(Player $player): void {
            if (!SessionFactory::hasSession($player)) return;
            $session = SessionFactory::getSession($player);
            $session->clearAllInventories();
            $inv = $player->getInventory();
            $inv->setItem(0, BedwarsItems::VIEW_GAMES()->asItem());
            $inv->setItem(1, BedwarsItems::PLAYER_LIST()->asItem());
            $inv->setItem(8, BedwarsItems::MOD_LEAVE()->asItem());
            $player->sendMessage("§aManagement kit given. Use 'View Games' to spectate a match.");
        });
        $this->addButton($manageButton);
    }

}
