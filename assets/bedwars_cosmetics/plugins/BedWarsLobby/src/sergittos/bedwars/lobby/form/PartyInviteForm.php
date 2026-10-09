<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\form;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\session\Session;

class PartyInviteForm extends CustomForm {

    public function __construct(private Session $inviter) {
        parent::__construct(function(Player $player, ?array $data): void {
            if ($data === null) return;
            $name = trim((string)($data[1] ?? ""));
            if ($name === "") {
                $player->sendMessage(TF::RED . "Please enter a player name.");
                return;
            }
            $target = $player->getServer()->getPlayerByPrefix($name);
            if ($target === null) {
                $player->sendMessage(TF::RED . "Player not found or offline.");
                return;
            }
            $targetSession = BedWarsCore::getInstance()->getSessionManager()->get($target);
            if ($targetSession === null) return;

            BedWarsCore::getInstance()->getPartyManager()->invite($this->inviter, $targetSession);
        });

        $this->setTitle(TF::BOLD . TF::AQUA . "Invite to Party");
        $this->addLabel(TF::GRAY . "Enter the username of the player you want to invite.");
        $this->addInput(TF::WHITE . "Player Name", "Steve", "");
    }
}
