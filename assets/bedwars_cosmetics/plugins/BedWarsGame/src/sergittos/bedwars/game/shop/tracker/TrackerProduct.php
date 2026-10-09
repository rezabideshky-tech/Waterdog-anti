<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\shop\tracker;

use pocketmine\item\VanillaItems;
use sergittos\bedwars\game\shop\Product;
use sergittos\bedwars\session\Session;
use function array_rand;

class TrackerProduct extends Product{

    public function __construct(string $id, string $name){
        parent::__construct($id, $name, 2, VanillaItems::EMERALD());
    }

    public function onPurchase(Session $session) : bool{
        $game = $session->getGame();
        $myTeam = $session->getTeam();

        if($game === null || $myTeam === null){
            $session->message("Tracker is not available right now.");
            return false;
        }

        $trackingTeam = null;

        foreach($game->getTeams() as $team){
            if($team->getName() === $this->id){
                $trackingTeam = $team;
            }

            if($team->getName() === $myTeam->getName()){
                continue;
            }

            if($team->isAlive() && !$team->isBedDestroyed()){
                $session->message("You must destroy every enemy bed first.");
                return false;
            }
        }

        if($trackingTeam === null){
            $session->message("Target team not found.");
            return false;
        }

        if(!$trackingTeam->isAlive()){
            $session->message("That team has been eliminated.");
            return false;
        }

        $candidates = [];
        foreach($trackingTeam->getMembers() as $m){
            $p = $m->getPlayer();
            if($p->isConnected() && $p->isAlive()){
                $candidates[] = $m;
            }
        }

        if($candidates === []){
            $session->message("No valid target found.");
            return false;
        }

        $session->setTrackingSession($candidates[array_rand($candidates)]);
        $session->message("Tracking enabled.");
        return true;
    }

    public function canBePurchased(Session $session) : bool{
        return true;
    }
}