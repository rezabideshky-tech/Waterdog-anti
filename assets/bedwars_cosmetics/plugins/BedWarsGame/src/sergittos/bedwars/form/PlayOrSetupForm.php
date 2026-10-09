<?php

declare(strict_types=1);

namespace sergittos\bedwars\form;

use sergittos\bedwars\form\setup\BedwarsForm;

class PlayOrSetupForm extends SimpleForm {

    public function __construct(private \Closure $onPlay) {
        parent::__construct("BedWars", "You have map-setup permission on this server. What would you like to do?");
    }

    protected function onCreation(): void {
        $playButton = new \sergittos\bedwars\libs\EasyUI\element\Button("Play");
        $playButton->setSubmitListener(function(\pocketmine\player\Player $player): void {
            ($this->onPlay)();
        });
        $this->addButton($playButton);

        $this->addRedirectFormButton("Setup a map", new BedwarsForm());
    }

}
