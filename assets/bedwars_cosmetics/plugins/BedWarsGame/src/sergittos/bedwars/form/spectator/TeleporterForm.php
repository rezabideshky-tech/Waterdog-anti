<?php
/*
* Copyright (C) Sergittos - All Rights Reserved
* Unauthorized copying of this file, via any medium is strictly prohibited
* Proprietary and confidential
*/

declare(strict_types=1);


namespace sergittos\bedwars\form\spectator;


use sergittos\bedwars\libs\EasyUI\element\Button;
use sergittos\bedwars\libs\EasyUI\variant\SimpleForm;
use pocketmine\player\Player;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;

class TeleporterForm extends SimpleForm {

    private Session $session;

    public function __construct(Session $session) {
        $this->session = $session;
        parent::__construct("Teleporter");
    }

    protected function onCreation(): void {
        foreach($this->session->getGame()->getPlayers() as $target) {
            if(!$target->isRespawning()) {
                $this->addTeleportButton($target);
            }
        }
    }

    private function addTeleportButton(Session $target): void {
        $button = new Button($target->getUsername());
        $button->setSubmitListener(function(Player $player) use ($target) {
            $session = SessionFactory::getSession($player);
            if(!$session->isSpectator()) {
                $session->message("{RED}You can't do this!");
                return;
            }
            if(!$target->isPlaying() || !$target->getPlayer()->isConnected()) {
                // isConnected() covers the target having disconnected since
                // this form was opened: their rejoin grace period still
                // counts them as isPlaying() (on purpose, so the team/bed
                // stays intact), but they are no longer actually here to
                // teleport to.
                $session->message("{RED}The player you want to teleport is no longer playing!");
                return;
            }
            if($target->isRespawning()) {
                $session->message("{RED}The player you want to teleport is dead!");
                return;
            }

            // Reuse $target directly - it already IS that player's Session.
            // This used to re-look it up via SessionFactory::getSession($target->getPlayer()),
            // which fell through to that method's "create a new Session"
            // fallback whenever the target had disconnected in the
            // meantime (see the isConnected() check above), constructing a
            // brand new Session around an already-closed Player object and
            // crashing on Living::getEffects() (uninitialized
            // $effectManager). Using the Session we already have removes
            // that redundant, unsafe lookup entirely.
            $session->setTrackingSession($target);
            $session->getPlayer()->teleport($target->getPlayer()->getPosition());
            $session->message("{GREEN}You teleported to " . $target->getUsername() . " successfully!");
        });
        $this->addButton($button);
    }

}