<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use sergittos\bedwars\game\BedWarsGame;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\session\Session;

class ViewGamesItem extends BedwarsItem {

    public function __construct() {
        parent::__construct("{AQUA}View Games");
    }

    public function onInteract(Session $session): void {
        $games = BedWarsGame::getInstance()->getGameManager()->getGames();

        $form = new SimpleForm(function(\pocketmine\player\Player $player, ?int $data) use ($games): void {
            if ($data === null) return;
            $game = $games[$data] ?? null;
            if ($game === null || $game->getWorld() === null) {
                $player->sendMessage("§cThat game isn't available anymore.");
                return;
            }

            $modSession = $session;
            $player->teleport($game->getWorld()->getSafeSpawn());
            $player->setGamemode(GameMode::SPECTATOR());
            $modSession->setGame($game);
            $game->addSpectator($modSession);
            $player->sendMessage("§aNow spectating " . $game->getMap()->getName() . "§a. Use your Leave item to return.");
        });
        $form->setTitle("Active Games");

        if (empty($games)) {
            $form->setContent("§7No games are currently running on this server.");
        } else {
            $form->setContent("§7Select a game to spectate:");
            foreach ($games as $game) {
                $stage = (new \ReflectionClass($game->getStage()))->getShortName();
                $form->addButton($game->getMap()->getName() . "\n§7" . $stage . " §8- §7" . $game->getPlayersCount() . " players");
            }
        }

        $session->getPlayer()->sendForm($form);
    }

    protected function realItem(): Item {
        return VanillaItems::ENDER_EYE();
    }

}
