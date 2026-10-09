<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\lobby\BedWarsLobby;

class SpawnStatsHologramCommand extends Command {

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("spawnstatshologram", "Spawn a stats hologram at your position", "/spawnstatshologram");
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

        $this->plugin->getLobbyManager()->addStatsHologram($sender);
    }
}