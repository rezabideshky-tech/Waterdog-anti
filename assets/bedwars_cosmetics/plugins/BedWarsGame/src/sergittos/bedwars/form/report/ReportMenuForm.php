<?php
/*
* Copyright (C) Sergittos - All Rights Reserved
* Unauthorized copying of this file, via any medium is strictly prohibited
* Proprietary and confidential
*/

declare(strict_types=1);

namespace sergittos\bedwars\form\report;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\libs\EasyUI\element\Button;
use sergittos\bedwars\libs\EasyUI\variant\SimpleForm;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;

/**
 * First screen of the report flow. Only ever lists players from the
 * reporter's OWN current match - by design, reports can't be filed against
 * someone the reporter never actually shared a game with, which is both a
 * light anti-abuse measure and gives staff a guaranteed match to look back
 * on when reviewing a report.
 */
class ReportMenuForm extends SimpleForm {

    private Session $session;

    public function __construct(Session $session) {
        $this->session = $session;
        parent::__construct(
            TF::RED . TF::BOLD . "Report a Player",
            TF::GRAY . "Select the player you want to report below." . "\n" .
            TF::DARK_GRAY . "Only players from your current match are shown." . "\n" . " "
        );
    }

    protected function onCreation(): void {
        $game = $this->session->getGame();
        if($game === null) {
            return;
        }

        foreach($game->getPlayersAndSpectators() as $target) {
            if($target->getUsername() === $this->session->getUsername()) {
                continue;
            }
            $this->addReportButton($target);
        }
    }

    private function addReportButton(Session $target): void {
        $button = new Button(TF::WHITE . $target->getUsername());
        $button->setSubmitListener(function(Player $player) use ($target): void {
            $session = SessionFactory::getSession($player);
            if(!$session->isPlaying() && !$session->isSpectator()) {
                $session->message("{RED}You can only report players while inside a match!");
                return;
            }

            $player->sendForm(new ReportReasonForm($session, $target->getUsername()));
        });
        $this->addButton($button);
    }

}
