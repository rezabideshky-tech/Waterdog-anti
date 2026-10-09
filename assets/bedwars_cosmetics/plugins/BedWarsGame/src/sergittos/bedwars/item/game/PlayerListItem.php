<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use function array_values;

class PlayerListItem extends BedwarsItem {

    public function __construct() {
        parent::__construct("{GOLD}Player List");
    }

    public function onInteract(Session $session): void {
        $players = array_values(array_filter(
            SessionFactory::getSessions(),
            fn(Session $s) => $s->isPlaying() || $s->isRespawning()
        ));

        $form = new SimpleForm(function(Player $player, ?int $data) use ($players, $session): void {
            if ($data === null) return;
            $target = $players[$data] ?? null;
            if ($target === null) {
                $player->sendMessage("§cThat player isn't in a game anymore.");
                return;
            }
            $this->openPlayerActions($session, $target);
        });
        $form->setTitle("Players In-Game");

        if (empty($players)) {
            $form->setContent("§7No one is currently in a match on this server.");
        } else {
            $form->setContent("§7Select a player:");
            foreach ($players as $s) {
                $team = $s->getTeam();
                $teamText = $team !== null ? $team->getColor() . $team->getName() : "§7No team";
                $form->addButton($s->getUsername() . "\n" . $teamText);
            }
        }

        $session->getPlayer()->sendForm($form);
    }

    private function openPlayerActions(Session $moderator, Session $target): void {
        $form = new SimpleForm(function(Player $player, ?int $data) use ($moderator, $target): void {
            if ($data === null) return;
            $targetPlayer = $target->getPlayer();
            if (!$targetPlayer->isConnected()) {
                $player->sendMessage("§cThat player disconnected.");
                return;
            }

            if ($data === 0) {
                // Teleport to
                $player->teleport($targetPlayer->getPosition());
                $player->setGamemode(GameMode::SPECTATOR());
                $player->sendMessage("§aTeleported to " . $target->getUsername() . "§a.");
            } elseif ($data === 1) {
                // Kick
                $targetPlayer->kick("§cYou were kicked by a moderator.");
                $player->sendMessage("§aKicked " . $target->getUsername() . "§a.");
            }
        });
        $form->setTitle($target->getUsername());
        $form->setContent("§7What would you like to do?");
        $form->addButton("§bTeleport to player");
        $form->addButton("§cKick player");
        $moderator->getPlayer()->sendForm($form);
    }

    protected function realItem(): Item {
        return VanillaItems::PAPER();
    }

}
