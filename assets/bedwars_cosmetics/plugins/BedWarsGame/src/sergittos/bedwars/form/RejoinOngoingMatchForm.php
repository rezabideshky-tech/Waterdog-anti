<?php

declare(strict_types=1);

namespace sergittos\bedwars\form;

use pocketmine\player\Player;
use sergittos\bedwars\libs\EasyUI\element\ModalOption;
use sergittos\bedwars\libs\EasyUI\variant\ModalForm;

final class RejoinOngoingMatchForm extends ModalForm{

    public function __construct(
        private int $secondsLeft,
        private \Closure $onYes,
        private \Closure $onNo
    ){
        parent::__construct(
            "§b§lRejoin Match",
            "§fWe saved your spot in an ongoing match!\n\n" .
            "§7Time remaining: §e§l{$secondsLeft}s\n\n" .
            "§fWould you like to rejoin your previous match and pick up right where you left off?",
            new ModalOption("§a§lYes, Rejoin"),
            new ModalOption("§c§lNo, Start Fresh")
        );
    }

    protected function onAccept(Player $player): void{
        ($this->onYes)($player);
    }

    protected function onDeny(Player $player): void{
        ($this->onNo)($player);
    }
}
