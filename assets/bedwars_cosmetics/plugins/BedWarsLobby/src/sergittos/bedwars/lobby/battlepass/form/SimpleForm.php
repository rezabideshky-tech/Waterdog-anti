<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\form;

use pocketmine\form\Form;
use pocketmine\player\Player;

final class SimpleForm implements Form{

    private array $buttons = [];

    public function __construct(
        private string $title,
        private string $content,
        private \Closure $onSubmit,
        private ?\Closure $onClose = null
    ){}

    public function addButton(string $text, ?string $iconPath = null): self{
        $btn = ["text" => $text];
        if($iconPath !== null){
            $btn["image"] = ["type" => "path", "data" => $iconPath];
        }
        $this->buttons[] = $btn;
        return $this;
    }

    public function jsonSerialize(): mixed{
        return [
            "type" => "form",
            "title" => $this->title,
            "content" => $this->content,
            "buttons" => $this->buttons
        ];
    }

    public function handleResponse(Player $player, mixed $data): void{
        if($data === null){
            if($this->onClose !== null){
                ($this->onClose)($player);
            }
            return;
        }

        if(!is_int($data) || !isset($this->buttons[$data])){
            return;
        }

        ($this->onSubmit)($player, $data);
    }
}
