<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\clan\ClanMenu;

/**
 * The whole clan feature is menu-driven (see the spec's own "no extra
 * commands" note) - this single command just opens the right screen.
 * `/clan` opens the main menu (matches the HUD button), `/clan review` is
 * the staff shortcut to the approval queue, `/clan setboard` /
 * `/clan removeboard` place or remove the weekly leaderboard hologram, and
 * `/clan disband <name>` lets staff force-delete any clan - all still live
 * under this one command rather than adding new ones.
 */
class ClanCommand extends Command{

    public function __construct(private BedWarsLobby $plugin){
        parent::__construct("clan", "Open the clan menu", "/clan [review|setboard|removeboard|disband <name>]");
        $this->setPermission("bedwars.clan.use");
    }

    public function execute(CommandSender $sender, string $label, array $args): void{
        if(!$sender instanceof Player){
            $sender->sendMessage(TF::RED . "Use in-game only.");
            return;
        }

        $sub = strtolower($args[0] ?? "");

        if($sub === "review"){
            ClanMenu::openCreateApproval($sender);
            return;
        }

        if($sub === "disband"){
            if(!$sender->hasPermission("bedwars.admin")){
                $sender->sendMessage(TF::RED . "You don't have permission to do that.");
                return;
            }

            $name = trim(implode(" ", array_slice($args, 1)));
            if($name === ""){
                $sender->sendMessage(TF::RED . "Usage: /clan disband <name>");
                return;
            }

            BedWarsCore::getInstance()->getClanManager()->adminDisbandClan($name, function(bool $ok, string $msg) use ($sender): void{
                $sender->sendMessage(($ok ? TF::GREEN : TF::RED) . "[Clan] " . TF::RESET . $msg);
            });
            return;
        }

        if($sub === "setboard" || $sub === "removeboard"){
            if(!$sender->hasPermission("bedwars.admin")){
                $sender->sendMessage(TF::RED . "You don't have permission to do that.");
                return;
            }

            if($sub === "removeboard"){
                $this->plugin->getClanLeaderboardManager()->removePosition();
                $sender->sendMessage(TF::GREEN . "Clan leaderboard hologram removed.");
                return;
            }

            $this->plugin->getClanLeaderboardManager()->setPosition($sender->getPosition()->asVector3(), $sender->getWorld()->getFolderName());
            $sender->sendMessage(TF::GREEN . "Clan leaderboard hologram placed at your position.");
            return;
        }

        ClanMenu::openMain($sender);
    }
}
