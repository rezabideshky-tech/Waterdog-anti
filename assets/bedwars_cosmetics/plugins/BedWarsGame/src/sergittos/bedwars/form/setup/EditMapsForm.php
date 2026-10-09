<?php

declare(strict_types=1);

namespace sergittos\bedwars\form\setup;

use sergittos\bedwars\form\SimpleForm;
use sergittos\bedwars\game\map\MapFactory;

class EditMapsForm extends SimpleForm{

    public function __construct(){
        parent::__construct("Edit maps", "Select a map to edit:");
    }

    protected function onCreation() : void{
        foreach(MapFactory::getMaps() as $map){
            $this->addRedirectFormButton($map->getName(), new EditMapConfirmForm($map->getName()));
        }
    }
}