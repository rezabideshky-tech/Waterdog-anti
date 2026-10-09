<?php

declare(strict_types=1);

namespace sergittos\bedwars\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;

class PartyCommand extends Command {

    public function __construct(private BedWarsCore $plugin) {
        parent::__construct("party", "Party management", "/party <subcommand>", ["p"]);
        $this->setPermission("bedwars.party");
    }

    public function execute(CommandSender $sender, string $label, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TF::RED . "Use in-game only.");
            return;
        }

        $sm      = $this->plugin->getSessionManager();
        $pm      = $this->plugin->getPartyManager();
        $session = $sm->get($sender);

        if ($session === null) {
            $sender->sendMessage(TF::RED . "Session not loaded yet. Please wait.");
            return;
        }

        $sub = strtolower($args[0] ?? "");

        switch ($sub) {
            case "create":
                $pm->create($session);
                break;

            case "invite":
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED . "Usage: /party invite <player>");
                    break;
                }
                $target = $this->plugin->getServer()->getPlayerByPrefix($args[1]);
                if ($target === null) {
                    $sender->sendMessage(TF::RED . "Player not found.");
                    break;
                }
                $targetSession = $sm->get($target);
                if ($targetSession === null) break;
                $pm->invite($session, $targetSession);
                break;

            case "accept":
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED . "Usage: /party accept <leader>");
                    break;
                }
                $pm->accept($session, $args[1]);
                break;

            case "decline":
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED . "Usage: /party decline <leader>");
                    break;
                }
                $pm->decline($session, $args[1]);
                break;

            case "leave":
                $pm->leave($session);
                break;

            case "disband":
                $pm->disband($session);
                break;

            case "kick":
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED . "Usage: /party kick <player>");
                    break;
                }
                $target = $this->plugin->getServer()->getPlayerByPrefix($args[1]);
                if ($target === null) { $sender->sendMessage(TF::RED . "Player not found."); break; }
                $targetSession = $sm->get($target);
                if ($targetSession === null) break;
                $pm->kick($session, $targetSession);
                break;

            case "transfer":
                if (!isset($args[1])) {
                    $sender->sendMessage(TF::RED . "Usage: /party transfer <player>");
                    break;
                }
                $target = $this->plugin->getServer()->getPlayerByPrefix($args[1]);
                if ($target === null) { $sender->sendMessage(TF::RED . "Player not found."); break; }
                $targetSession = $sm->get($target);
                if ($targetSession === null) break;
                $pm->transfer($session, $targetSession);
                break;

            case "list":
                $party = $pm->getPartyOf($session);
                if ($party === null) {
                    $sender->sendMessage(TF::RED . "You are not in a party.");
                    break;
                }
                $sender->sendMessage(TF::GOLD . "═══ Party (" . $party->getSize() . "/" . $party->getMaxSize() . ") ═══");
                foreach ($party->getMembers() as $m) {
                    $leader = $party->isLeader($m) ? TF::GOLD . " ★ Leader" : "";
                    $sender->sendMessage(TF::WHITE . "  • " . $m->getFormattedLevel() . " " . $m->getUsername() . $leader);
                }
                break;

            default:
                $sender->sendMessage(
                    TF::GOLD . "Party Commands:\n" .
                    TF::YELLOW . "/party create\n" .
                    TF::YELLOW . "/party invite <player>\n" .
                    TF::YELLOW . "/party accept <leader>\n" .
                    TF::YELLOW . "/party decline <leader>\n" .
                    TF::YELLOW . "/party leave\n" .
                    TF::YELLOW . "/party kick <player>\n" .
                    TF::YELLOW . "/party transfer <player>\n" .
                    TF::YELLOW . "/party disband\n" .
                    TF::YELLOW . "/party list"
                );
        }
    }
}
