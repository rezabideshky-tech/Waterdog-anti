<?php

namespace sergittos\bedwars\game\stage\trait;

use pocketmine\block\utils\DyeColor;
use pocketmine\player\GameMode;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\game\team\TeamSelectionManager;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\scoreboard\WaitingScoreboard;
use sergittos\bedwars\session\Session;
use function strtolower;
use function strtoupper;

trait JoinableTrait{

    public function start(Game $game) : void{
        $this->game = $game;
    }

    public function onJoin(Session $session) : void{
        $player = $session->getPlayer();

        $player->getEffects()->clear();
        $player->setGamemode(GameMode::ADVENTURE());
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood(20);

        $session->setGame($this->game);
        $session->setRespawnTime(null);

        $session->clearAllInventories();

        $selected = TeamSelectionManager::get($session);
        $color = DyeColor::WHITE();
        $selectedTeam = null;

        if($selected !== null){
            foreach($this->game->getTeams() as $t){
                if(strtolower($t->getName()) === strtolower($selected)){
                    $color = $t->getDyeColor();
                    $selectedTeam = $t;
                    break;
                }
            }
        }

        $inv = $player->getInventory();
        $inv->setItem(0, BedwarsItems::TEAM_SELECTOR()->setColor($color)->asItem());
        $inv->setItem(8, BedwarsItems::LEAVE_GAME()->asItem());

        if($selectedTeam instanceof Team){
            $player->setNameTag($selectedTeam->getFormattedNametag($session));

            // Explicitly re-enable both nametag flags here instead of assuming
            // they're already on. A player who placed top-3 in their previous
            // match has their nametag forced invisible for the duration of the
            // Podium Ceremony (see PodiumCeremony::hideOccupantNametags()) and,
            // on a shared connection like Play Again's instant requeue, never
            // goes through a fresh join that would reset it. Without this line,
            // that player's nametag stayed invisible for their entire next
            // match purely because they had a team preselected - visibility
            // was never touched, only the label text was.
            $player->setNameTagVisible(true);
            $player->setNameTagAlwaysVisible(true);
        }else{
            $session->updateNametag();
        }

        $session->setScoreboard(new WaitingScoreboard());
        $session->teleportToWaitingWorld();

        $this->game->broadcastMessage(
            "{GRAY}" . $session->getUsername() . " {YELLOW}joined {GRAY}(" .
            "{AQUA}" . $this->game->getPlayersCount() . "{YELLOW}/{AQUA}" . $this->game->getMap()->getMaxCapacity() . "{GRAY})"
        );
    }

    public function onQuit(Session $session) : void{
        TeamSelectionManager::clear($session);
        $session->updateNametag();
        $this->game->broadcastMessage("{GRAY}" . $session->getUsername() . " {YELLOW}left the lobby.");
    }
}