<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\LobbyItems;

class LobbyCommand extends Command {

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("lobby", "Return to lobby spawn", "/lobby", ["hub", "spawn"]);
        $this->setPermission("bedwars.play");
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "Use in-game only.");
            return;
        }

        $world = $sender->getServer()->getWorldManager()->getDefaultWorld();
        if ($world === null) return;

        $sender->teleport($world->getSpawnLocation());

        $session = BedWarsCore::getInstance()->getSessionManager()->get($sender);
        if ($session !== null) {
            LobbyItems::give($session);
        }

        $sender->sendMessage(TF::GREEN . "Teleported to lobby spawn.");
    }
}
