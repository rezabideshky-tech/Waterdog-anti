<?php

declare(strict_types=1);


namespace sergittos\bedwars\form\queue;


use sergittos\bedwars\libs\EasyUI\element\Button;
use sergittos\bedwars\libs\EasyUI\variant\SimpleForm;
use pocketmine\player\Player;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\form\queue\element\PlayGameButton;
use sergittos\bedwars\utils\GameUtils;

class PlayBedwarsForm extends SimpleForm {

    private int $players_per_team;

    public function __construct(int $players_per_team) {
        $this->players_per_team = $players_per_team;
        parent::__construct("Play Again | Mode > " . GameUtils::getMode($players_per_team));
    }

    protected function onCreation(): void {
        $this->addButton(new PlayGameButton("[ Play Again ]", BedWars::getInstance()->getGameManager()->findRandomGame($this->players_per_team)));
    }
}