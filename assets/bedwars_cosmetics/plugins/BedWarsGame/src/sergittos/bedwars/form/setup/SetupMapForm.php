<?php

declare(strict_types=1);

namespace sergittos\bedwars\form\setup;

use pocketmine\player\Player;
use pocketmine\Server;
use sergittos\bedwars\libs\EasyUI\element\Button;
use sergittos\bedwars\form\SimpleForm;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\setup\step\SetShopAndUpgradesStep;

class SetupMapForm extends SimpleForm{

    public function __construct(private Session $session){
        parent::__construct("Setup map", "What would you like to do?");
    }

    protected function onCreation() : void{
        $this->addTeleportToWaitingWorldButton();
        $this->addTeleportToArenaButton();
        $this->addSetWaitingSpawnButton();
        $this->addSetSpectatorSpawnButton();
        $this->addShopAndUpgradesButton();
        $this->addRedirectFormButton("Setup generators", new SetupGeneratorsForm($this->session));
        $this->addRedirectFormButton("Setup teams", new SetupTeamsForm($this->session));
    }

    private function addTeleportToWaitingWorldButton() : void{
        $button = new Button("Teleport to waiting world\n{GRAY}Go set up the waiting area");
        $button->setSubmitListener(function(Player $player) : void{
            if(!$this->session->isCreatingMap()){
                return;
            }

            $mapBuilder = $this->session->getMapSetup()->getMapBuilder();
            $player->teleport($mapBuilder->getWaitingWorld()->getSafeSpawn());
            $this->session->message("{GREEN}{BOLD}Teleported{RESET}{GRAY} - You're now in this map's waiting world.");
        });
        $this->addButton($button);
    }

    private function addTeleportToArenaButton() : void{
        $button = new Button("Teleport to arena\n{GRAY}Go back to the map you're building");
        $button->setSubmitListener(function(Player $player) : void{
            if(!$this->session->isCreatingMap()){
                return;
            }

            $mapBuilder = $this->session->getMapSetup()->getMapBuilder();
            $arenaWorldName = $mapBuilder->getPlayingWorld();

            $wm = Server::getInstance()->getWorldManager();
            if(!$wm->loadWorld($arenaWorldName)){
                $this->session->message("{RED}{BOLD}Error{RESET}{GRAY} - Couldn't load the arena world.");
                return;
            }

            $arenaWorld = $wm->getWorldByName($arenaWorldName);
            if($arenaWorld === null){
                $this->session->message("{RED}{BOLD}Error{RESET}{GRAY} - Arena world is unavailable.");
                return;
            }

            $player->teleport($arenaWorld->getSafeSpawn());
            $this->session->message("{GREEN}{BOLD}Teleported{RESET}{GRAY} - You're back in the arena.");
        });
        $this->addButton($button);
    }

    private function addSetWaitingSpawnButton() : void{
        $button = new Button("Set waiting spawn point\n{GRAY}Sets the lobby spawn inside the waiting world");
        $button->setSubmitListener(function(Player $player) : void{
            if(!$this->session->isCreatingMap()){
                return;
            }

            $mapBuilder = $this->session->getMapSetup()->getMapBuilder();
            $waitingWorld = $mapBuilder->getWaitingWorld();
            $currentWorld = $player->getWorld();

            if($currentWorld->getFolderName() !== $waitingWorld->getFolderName()){
                $this->session->message(
                    "{RED}{BOLD}Wrong World{RESET}{GRAY} - Use §fTeleport to waiting world§7 first, then try again."
                );
                return;
            }

            $pos = $player->getPosition()->asVector3();
            $mapBuilder->setWaitingSpawnPosition($pos);
            $this->session->message("{GREEN}{BOLD}Waiting Spawn Set{RESET}{GRAY} - Players will wait here before the match starts.");
        });
        $this->addButton($button);
    }

    private function addSetSpectatorSpawnButton() : void{
        $button = new Button("Set spectator spawn point");
        $button->setSubmitListener(function(Player $player) : void{
            if($this->session->isCreatingMap()){
                $position = $player->getPosition()->asVector3();
                $this->session->getMapSetup()->getMapBuilder()->setSpectatorSpawnPosition($position);
                $this->session->message("{GREEN}{BOLD}Spectator Spawn Set{RESET}{GRAY} - " . $this->vectorToString($position));
            }
        });
        $this->addButton($button);
    }

    private function addShopAndUpgradesButton() : void{
        $button = new Button("Set Shop & Upgrades");
        $button->setSubmitListener(function(Player $player) : void{
            $this->session->getMapSetup()->setStep(new SetShopAndUpgradesStep());
        });
        $this->addButton($button);
    }
}