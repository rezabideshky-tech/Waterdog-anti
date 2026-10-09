<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\gui\LeaderboardGui;

class LeaderboardCommand extends Command {

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("leaderboard", "Show leaderboards", "/leaderboard [stat]", ["lb", "top"]);
        $this->setPermission("bedwars.play");
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "Use in-game only.");
            return;
        }

        $gui = new LeaderboardGui();
        if (isset($args[0])) {
            $gui->openCategory($sender, strtolower($args[0]));
        } else {
            $gui->openMain($sender);
        }
    }
}
