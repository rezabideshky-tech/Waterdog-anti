<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\ui;

use Closure;
use pocketmine\form\Form;
use pocketmine\player\Player;
use function is_int;

/**
 * Plain Bedrock long form. The "arvan ui" resource pack recognises the hidden title flag and draws the
 * full-screen Battle Pass; the buttons are the slots described in BattlePassLayout.
 */
final class BattlePassForm implements Form{

    /**
     * @param array<int, array<string, mixed>> $buttons
     * @param Closure(Player, int): void       $onAction receives the clicked slot index
     */
    public function __construct(private array $buttons, private Closure $onAction){}

    public function jsonSerialize() : array{
        return [
            "type" => "form",
            "title" => BattlePassUi::TITLE,
            "content" => "",
            "buttons" => $this->buttons,
        ];
    }

    public function handleResponse(Player $player, $data) : void{
        if(!is_int($data) || !isset($this->buttons[$data]) || ($this->buttons[$data]["text"] ?? "") === ""){
            return; // closed, or a hidden slot
        }
        ($this->onAction)($player, $data);
    }
}
