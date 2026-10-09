<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\player\chat\LegacyRawChatFormatter;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\game\team\TeamSelectionManager;
use sergittos\bedwars\session\SessionFactory;
use function strtolower;
use function strtoupper;

final class GameChatListener implements Listener{

    /**
     * HIGHEST so this always has the final say on chat formatting even
     * if RankSystem is installed with its own chat formatting left
     * enabled (RankSystem's built-in chat handler runs at HIGH). BedWars
     * needs to keep control here for team tags, spectator tags and the
     * level badge, which RankSystem has no knowledge of - RankSystem is
     * only ever used as a data source for the rank prefix/color further
     * down, never as the thing that sets the formatter.
     *
     * @priority HIGHEST
     */
    public function onChat(PlayerChatEvent $event): void{
        $player = $event->getPlayer();

        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $game = $session->getGame();
        if($game === null){
            return;
        }

        if(!$session->isPlaying() && !$session->isSpectator()){
            return;
        }

        $recipients = [];
        foreach($game->getPlayersAndSpectators() as $s){
            $p = $s->getPlayer();
            if($p->isConnected()){
                $recipients[] = $p;
            }
        }
        $event->setRecipients($recipients);

        $level = $session->getFormattedLevel();

        if($session->isSpectator()){
            $event->setFormatter(new LegacyRawChatFormatter(
                $level . " §8[§7SPECTATOR§8] §f{%0} §8» §7{%1}"
            ));
            return;
        }

        $team = $session->getTeam();

        if($team === null){
            $picked = TeamSelectionManager::get($session);
            if($picked !== null && $picked !== ""){
                foreach($game->getTeams() as $t){
                    if(strtolower($t->getName()) === strtolower($picked)){
                        $team = $t;
                        break;
                    }
                }
            }
        }

        if($team !== null){
            $teamTag = $team->getColor() . "[" . strtoupper($team->getName()) . "]";
            $event->setFormatter(new LegacyRawChatFormatter(
                $level . " " . $teamTag . " §f{%0} §8» §7{%1}"
            ));
            return;
        }

        $prefix    = $session->getRankPrefix();
        $nameColor = $session->getRankColor();
        $chatColor = $session->getRankChatColor();

        $event->setFormatter(new LegacyRawChatFormatter(
            $level . " " . $prefix . $nameColor . "{%0}" . TF::DARK_GRAY . " » " . $chatColor . "{%1}"
        ));
    }
}