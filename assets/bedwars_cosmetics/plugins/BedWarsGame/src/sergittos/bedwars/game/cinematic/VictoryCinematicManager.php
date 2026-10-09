<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic;

use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use function array_filter;
use function array_values;

/**
 * Starts/stops the per-spectator VictoryCinematicController and keeps
 * track of which players currently have one active.
 *
 * One instance of this belongs to a single EndingStage, so its state
 * (and every controller/task it owns) naturally goes away with that
 * stage once the arena resets for the next match.
 */
final class VictoryCinematicManager{

    /** @var \WeakMap<Player, VictoryCinematicController> */
    private \WeakMap $controllers;

    public function __construct(
        private readonly Plugin $plugin
    ){
        $this->controllers = new \WeakMap();
    }

    /**
     * Starts the victory orbit camera for every given spectator, centered
     * on the given winner(s). Players who are themselves in $winners are
     * skipped from the *spectator* loop below (even if accidentally
     * included in $spectators) but are not left out entirely - each
     * connected winner gets their own self-view camera afterward, so they
     * can actually watch their own Victory Dance instead of being stuck
     * in a normal first-person view that never renders their own dance
     * animation.
     *
     * Each player's setup runs in its own try/catch so a failure for one
     * spectator (or winner) can't abort the loop and leave everyone after
     * them without a camera.
     *
     * @param Player[] $spectators
     * @param Player[] $winners
     */
    public function startFor(array $spectators, array $winners, string $label) : void{
        $connectedWinners = array_values(array_filter(
            $winners,
            static fn(Player $p) : bool => $p->isConnected()
        ));
        if($connectedWinners === []){
            return;
        }

        foreach($spectators as $spectator){
            if(!$spectator->isConnected() || $this->isWinner($spectator, $connectedWinners)){
                continue;
            }

            try{
                // Replace any stale controller first so we never end up
                // with two competing tasks sending camera packets to one
                // player.
                $this->stop($spectator);

                $controller = new VictoryCinematicController($this->plugin, $spectator, $connectedWinners, $label);
                $this->controllers[$spectator] = $controller;
                $controller->start();
            }catch(\Throwable $e){
                $this->plugin->getLogger()->debug(
                    "Victory cinematic camera failed to start for spectator " . $spectator->getName() . ": " . $e->getMessage()
                );
            }
        }

        foreach($connectedWinners as $winner){
            try{
                $this->stop($winner);

                $controller = new VictoryCinematicController($this->plugin, $winner, $connectedWinners, $label, true);
                $this->controllers[$winner] = $controller;
                $controller->start();
            }catch(\Throwable $e){
                $this->plugin->getLogger()->debug(
                    "Victory cinematic self-view camera failed to start for winner " . $winner->getName() . ": " . $e->getMessage()
                );
            }
        }
    }

    /** @param Player[] $winners */
    private function isWinner(Player $player, array $winners) : bool{
        foreach($winners as $winner){
            if($winner === $player){
                return true;
            }
        }
        return false;
    }

    public function stop(Player $player) : void{
        if(!isset($this->controllers[$player])){
            return;
        }

        $controller = $this->controllers[$player];
        unset($this->controllers[$player]);
        $controller->stop();
    }

    public function isActive(Player $player) : bool{
        return isset($this->controllers[$player]);
    }

}
