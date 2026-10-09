<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\setup;

use pocketmine\Server;
use sergittos\bedwars\game\map\task\CreateMapTask;
use sergittos\bedwars\game\map\task\UpdateMapTask;
use sergittos\bedwars\listener\SetupListener;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\setup\builder\MapBuilder;
use sergittos\bedwars\session\setup\step\PreparingMapStep;
use sergittos\bedwars\session\setup\step\Step;

class MapSetup{

    private Session $session;
    private MapBuilder $map_builder;
    private Step $step;
    private bool $editing;

    public function __construct(Session $session, MapBuilder $map_builder, bool $editing = false){
        $this->session = $session;
        $this->map_builder = $map_builder;
        $this->editing = $editing;
        $this->setStep(new PreparingMapStep());
    }

    public function isEditing() : bool{
        return $this->editing;
    }

    public function getMapBuilder() : MapBuilder{
        return $this->map_builder;
    }

    public function getStep() : Step{
        return $this->step;
    }

    public function setStep(Step $step) : void{
        $this->step = $step;
        $this->step->start($this->session);
    }

    /**
     * @return bool false if a save/create for this player is already in
     * flight (a previous click's world copy hasn't finished yet), in which
     * case no task is submitted.
     */
    public function createMap() : bool{
        $playerName = $this->session->getPlayer()->getName();
        if(!SetupListener::beginSave($playerName)){
            return false;
        }
        Server::getInstance()->getAsyncPool()->submitTask(new CreateMapTask($this->map_builder, $playerName));
        return true;
    }

    public function updateMap() : bool{
        $playerName = $this->session->getPlayer()->getName();
        if(!SetupListener::beginSave($playerName)){
            return false;
        }
        Server::getInstance()->getAsyncPool()->submitTask(new UpdateMapTask($this->map_builder, $playerName));
        return true;
    }
}