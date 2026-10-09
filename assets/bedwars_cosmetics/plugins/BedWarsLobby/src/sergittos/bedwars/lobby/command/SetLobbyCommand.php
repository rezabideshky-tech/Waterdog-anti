<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\lobby\BedWarsLobby;

class SetLobbyCommand extends Command {

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("setlobby", "Set the lobby spawn location", "/setlobby");
        $this->setPermission("bedwars.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "Use in-game only.");
            return;
        }
        if (!$sender->hasPermission("bedwars.admin")) {
            $sender->sendMessage(TF::RED . "No permission.");
            return;
        }

        $this->plugin->getLobbyManager()->setLobby($sender->getWorld(), $sender->getPosition());
        $sender->sendMessage(TF::GREEN . "Lobby spawn set to your current position.");
    }
}