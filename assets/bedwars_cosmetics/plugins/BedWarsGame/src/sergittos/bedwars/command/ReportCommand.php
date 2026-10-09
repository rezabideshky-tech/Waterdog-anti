<?php

declare(strict_types=1);

namespace sergittos\bedwars\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\player\Player;
use sergittos\bedwars\form\report\ReportMenuForm;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\session\SessionFactory;

class ReportCommand extends Command implements PluginOwned {

    public function __construct() {
        parent::__construct("report", "Report a player from your current match", "/report", ["reportplayer"]);
        $this->setPermission("bedwars.report.use");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if(!$sender instanceof Player) {
            $sender->sendMessage("You can only use this command in-game!");
            return;
        }

        $session = SessionFactory::getSession($sender);

        if($session->getGame() === null) {
            $session->message("{RED}You need to be in an active match to report a player!");
            return;
        }

        if(!$session->isPlaying() && !$session->isSpectator()) {
            $session->message("{RED}You need to be in an active match to report a player!");
            return;
        }

        $sender->sendForm(new ReportMenuForm($session));
    }

    public function getOwningPlugin(): Plugin {
        return BedWars::getInstance();
    }

}
