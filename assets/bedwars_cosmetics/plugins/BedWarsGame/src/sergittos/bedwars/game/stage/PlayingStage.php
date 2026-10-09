<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\stage;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\utils\Limits;
use pocketmine\utils\TextFormat;
use sergittos\bedwars\game\event\Event;
use sergittos\bedwars\game\event\presets\UpgradeGeneratorsTierEvent;
use sergittos\bedwars\game\generator\GeneratorType;
use sergittos\bedwars\game\generator\Tier;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\game\team\TeamSelectionManager;
use sergittos\bedwars\session\scoreboard\GameScoreboard;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\utils\ColorUtils;
use function array_shift;
use function array_slice;
use function count;
use function round;
use function shuffle;
use function strtolower;

class PlayingStage extends Stage{

    private ?Event $next_event = null;

    public function getNextEvent() : ?Event{
        return $this->next_event;
    }

    public function hasStarted() : bool{
        return $this->next_event !== null;
    }

    private function startNextEvent(?Event $event = null) : void{
        $this->next_event = $event ?? $this->next_event?->getNextEvent();
        $this->next_event?->start($this->game);
    }

    protected function onStart() : void{
        $teams = $this->game->getTeams();
        $players = $this->game->getPlayers();

        shuffle($players);

        foreach($players as $session){
            $session->snapshotGameStats();
        }

        $ppt = $this->game->getMap()->getPlayersPerTeam();

        /** @var array<string, Team> $teamByName */
        $teamByName = [];
        foreach($teams as $t){
            $teamByName[strtolower($t->getName())] = $t;
        }

        /** @var Session[] $unassigned */
        $unassigned = [];

        if($ppt <= 1){
            foreach($players as $session){
                if($session->hasTeam()){
                    continue;
                }

                $sel = TeamSelectionManager::get($session);
                $key = $sel !== null ? strtolower($sel) : "";

                if($key !== "" && isset($teamByName[$key]) && !$teamByName[$key]->isFull()){
                    $teamByName[$key]->addMember($session);
                }else{
                    $unassigned[] = $session;
                }
            }

            foreach($unassigned as $session){
                foreach($teams as $team){
                    if(!$team->isFull()){
                        $team->addMember($session);
                        break;
                    }
                }
            }
        }else{
            /** @var array<string, Session[]> $grouped */
            $grouped = [];

            foreach($players as $session){
                if($session->hasTeam()){
                    continue;
                }

                $sel = TeamSelectionManager::get($session);
                $key = $sel !== null ? strtolower($sel) : "";

                if($key === "" || !isset($teamByName[$key])){
                    $unassigned[] = $session;
                    continue;
                }

                $grouped[$key][] = $session;
            }

            foreach($grouped as $key => $list){
                if(count($list) >= $ppt){
                    $team = $teamByName[$key];

                    foreach(array_slice($list, 0, $ppt) as $s){
                        if(!$team->isFull() && !$s->hasTeam()){
                            $team->addMember($s);
                        }else{
                            $unassigned[] = $s;
                        }
                    }

                    foreach(array_slice($list, $ppt) as $s){
                        $unassigned[] = $s;
                    }
                }else{
                    foreach($list as $s){
                        $unassigned[] = $s;
                    }
                }
            }

            foreach($teams as $team){
                $need = $ppt - $team->getMembersCount();
                if($need <= 0){
                    continue;
                }
                if(count($unassigned) < $need){
                    continue;
                }

                for($i = 0; $i < $need; $i++){
                    $s = array_shift($unassigned);
                    if($s instanceof Session){
                        $team->addMember($s);
                    }
                }
            }

            foreach($teams as $team){
                while(!$team->isFull() && $unassigned !== []){
                    $s = array_shift($unassigned);
                    if($s instanceof Session){
                        $team->addMember($s);
                    }
                }
            }
        }

        TeamSelectionManager::clearGame($this->game);

        foreach($teams as $team){
            if(!$team->isAlive()){
                $team->destroyBed($this->game, null, true, true);
            }
        }

        \sergittos\bedwars\profile\ProfileHooks::start($this->game);

        $this->startNextEvent(new UpgradeGeneratorsTierEvent(GeneratorType::DIAMOND, Tier::II));
    }

    public function onJoin(Session $session) : void{
        $session->setScoreboard(new GameScoreboard());
    }

    public function onQuit(Session $session) : void{
        $session->title("{RED}GAME OVER!");
        $session->resetSettings();
        $session->setTrackingSession(null);

        if($session->hasTeam()){
            $team = $session->getTeam();
            $team->removeMember($session);
            if(!$team->isAlive()){
                $this->game->broadcastMessage("{BOLD}{WHITE}TEAM ELIMINATED > {RESET}" . $team->getColoredName() . " Team {RED}has been eliminated!");

                // Covers the one Bounty-cleanup path that doesn't already
                // run through Session::kill()/BountyManager::onDeath(): the
                // last member of a team disconnecting/leaving without ever
                // dying in-match. Harmless no-op otherwise (every other
                // elimination already cleared its own Bounty on death).
                $this->game->getBountyManager()->onTeamEliminated($team);
            }
        }

        $this->game->despawnGeneratorsFrom($session);

        if(count($this->game->getAliveTeams()) === 1){
            $this->game->setStage(new EndingStage());
        }
    }

    public function tick() : void{
        if($this->next_event !== null && $this->next_event->hasEnded()){
            $this->startNextEvent();
        }
        $this->game->updateScoreboards();
        $this->tickPlayersAndSpectators();
    }

    private function tickPlayersAndSpectators() : void{
        foreach($this->game->getPlayers() as $session){
            $this->checkTrackingSession($session);

            // Runs before the isRespawning()/healPool early-continues
            // below on purpose, so the team nametag gets its once-a-
            // second refresh (see Team::refreshNametag()) even while a
            // player is mid-respawn-countdown or their team's heal
            // pool is leveling up - neither of those states should be
            // able to leave a stale/overwritten nametag showing until
            // the next unrelated state change.
            if($session->hasTeam()){
                $session->getTeam()->refreshNametag($session);
            }

            if($session->isRespawning()){
                $session->attemptToRespawn();
                continue;
            }

            if(!$session->hasTeam()){
                continue;
            }

            $team = $session->getTeam();
            if($team->getUpgrades()->getHealPool()->canLevelUp()){
                continue;
            }

            if(!$session->getPlayer()->isConnected()){
                continue;
            }

            if($team->getZone()->isInside($session->getPlayer()->getPosition())){
                $session->addEffect(new EffectInstance(VanillaEffects::REGENERATION(), Limits::INT32_MAX, 0, false));
            }else{
                $session->getPlayer()->getEffects()->remove(VanillaEffects::REGENERATION());
            }
        }

        foreach($this->game->getSpectators() as $session){
            $this->checkTrackingSession($session);
        }
    }

    private function checkTrackingSession(Session $session) : void{
        $tracking_session = $session->getTrackingSession();
        if($tracking_session === null){
            return;
        }

        $player = $session->getPlayer();

        // The viewer disconnected but hasn't been cleaned up from the tick
        // loop yet (e.g. mid-tick quit), or the tracked target disconnected -
        // either way there is nobody left to safely send a popup/teleport to.
        // Player::sendPopup()/getNetworkSession() throw a LogicException
        // ("Player is not connected") instead of failing gracefully, which
        // used to crash the whole server. Bail out and drop the stale
        // tracking link instead.
        if(!$player->isConnected()){
            $session->setTrackingSession(null);
            return;
        }

        $targetPlayer = $tracking_session->getPlayer();
        if(!$targetPlayer->isConnected()){
            $player->sendPopup(TextFormat::RED . "Target lost");
            $session->setTrackingSession(null);
            return;
        }

        if($tracking_session->isRespawning()){
            $player->sendPopup(TextFormat::RED . "Target lost");
            $session->setTrackingSession(null);
            return;
        }

        $session->updateCompassDirection();

        $position = $targetPlayer->getPosition();
        $distance = $player->getPosition()->distance($position);

        $player->sendPopup(ColorUtils::translate(
            "Target: {GREEN}{BOLD}" . $tracking_session->getUsername() . "  {RESET}{WHITE}Distance: {GREEN}{BOLD}" . round($distance, 1) . "m"
        ));

        if($session->isSpectator() && $session->getSpectatorSettings()->getAutoTeleport() && $distance >= 10){
            $player->teleport($position);
        }
    }
}