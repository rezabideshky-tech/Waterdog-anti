<?php

declare(strict_types=1);

namespace sergittos\bedwars\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;

class BwAdminCommand extends Command {

    public function __construct(private BedWarsCore $plugin) {
        parent::__construct("bwadmin", "BedWars admin commands", "/bwadmin <subcommand>");
        $this->setPermission("bedwars.admin");
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if (!$sender->hasPermission("bedwars.admin")) {
            $sender->sendMessage(TF::RED . "No permission.");
            return;
        }

        $sub = strtolower($args[0] ?? "");

        switch ($sub) {
            case "give-cosmetic":
                // /bwadmin give-cosmetic <player> <cosmetic_id>
                if (!isset($args[2])) {
                    $sender->sendMessage(TF::RED . "Usage: /bwadmin give-cosmetic <player> <cosmetic_id>");
                    break;
                }
                $target = $this->plugin->getServer()->getPlayerByPrefix($args[1]);
                if ($target === null) { $sender->sendMessage(TF::RED . "Player not found."); break; }
                $session = $this->plugin->getSessionManager()->get($target);
                if ($session === null) break;
                $session->unlockCosmetic($args[2]);
                $sender->sendMessage(TF::GREEN . "Unlocked cosmetic " . $args[2] . " for " . $target->getName());
                $target->sendMessage(TF::GOLD . "You unlocked cosmetic: " . TF::WHITE . $args[2]);
                break;

            case "give-coins":
                if (!isset($args[2])) {
                    $sender->sendMessage(TF::RED . "Usage: /bwadmin give-coins <player> <amount>");
                    break;
                }
                $target = $this->plugin->getServer()->getPlayerByPrefix($args[1]);
                if ($target === null) { $sender->sendMessage(TF::RED . "Player not found."); break; }
                $session = $this->plugin->getSessionManager()->get($target);
                if ($session === null) break;
                $amount = (int)$args[2];
                $session->addCoins($amount);
                $sender->sendMessage(TF::GREEN . "Gave $amount coins to " . $target->getName());
                break;

            case "reload":
                $this->plugin->reloadConfig();
                $sender->sendMessage(TF::GREEN . "Config reloaded.");
                break;

            default:
                $sender->sendMessage(
                    TF::GOLD . "BwAdmin Commands:\n" .
                    TF::YELLOW . "/bwadmin give-cosmetic <player> <id>\n" .
                    TF::YELLOW . "/bwadmin give-coins <player> <amount>\n" .
                    TF::YELLOW . "/bwadmin reload"
                );
        }
    }
}
