<?php

declare(strict_types=1);


namespace sergittos\bedwars\form\queue;


use sergittos\bedwars\libs\EasyUI\variant\SimpleForm;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\form\queue\element\PlayGameButton;
use sergittos\bedwars\game\map\MapFactory;

class SelectMapForm extends SimpleForm {

    private int $players_per_team;

    public function __construct(int $players_per_team) {
        $this->players_per_team = $players_per_team;
        parent::__construct("Select a map!");
    }

    protected function onCreation(): void {
        foreach(MapFactory::getMapsByPlayers($this->players_per_team) as $map) {
            try {

                $world = $map->getWaitingWorld();
                if ($world !== null) {
                    $playersCount = count($world->getPlayers());
                } else {
                    $playersCount = 0;
                }
            } catch (\Exception $e) {
                $playersCount = 0;
            }
            $this->addButton(new PlayGameButton("§l§6>> §e".$map->getName()." §6<<\n§a".$playersCount, BedWars::getInstance()->getGameManager()->findGame($map)));
        }
    }

}