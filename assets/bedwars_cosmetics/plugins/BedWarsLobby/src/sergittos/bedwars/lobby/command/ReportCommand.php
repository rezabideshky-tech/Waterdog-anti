<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\lobby\BedWarsLobby;

/**
 * The Lobby server never hosts real matches (Session::getGame() here is
 * always null - only the Game server actually populates it), and reports
 * are intentionally scoped to "someone from your current match" so staff
 * always have a match to look back on. So `/report` still exists here as
 * requested, it just can't ever show a player list on this server - it
 * points the sender back to a match instead of pretending to offer
 * something it can't deliver.
 */
class ReportCommand extends Command {

    public function __construct(private BedWarsLobby $plugin) {
        parent::__construct("report", "Report a player from your current match", "/report", ["reportplayer"]);
        $this->setPermission("bedwars.report.use");
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if(!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "Use in-game only.");
            return;
        }

        $sender->sendMessage(TF::RED . "You can only report a player while you're in an active match.");
        $sender->sendMessage(TF::GRAY . "Join a game, then use " . TF::YELLOW . "/report" . TF::GRAY . " (or the Report Player item) from there.");
    }

}
