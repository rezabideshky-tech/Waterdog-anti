<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\clan\ui;

use pocketmine\form\Form;
use pocketmine\player\Player;
use function count;
use function in_array;
use function is_int;
use function str_starts_with;

/**
 * Generic renderer for every red/gold BedWars Clan UI screen (ui/bwp/clan.json, namespace "bwc").
 * Same trick as ProfileForm: a normal Bedrock long form whose title carries a hidden marker
 * (ClanLayouts::TITLES) and whose buttons each carry marker(i) . value - the resource pack
 * recognises the marker and draws the full custom screen instead of vanilla buttons.
 *
 * All 6 dashboard screens (home/members/war/bank/apps/info) share the same fixed 40-slot
 * DASH_DATA/DASH_ACTIONS layout; form_dialog uses the smaller 10-slot DIALOG_DATA/DIALOG_ACTIONS
 * layout instead. ClanMenu builds the $values map (slot => text or texture path) and passes an
 * $onAction closure that receives the clicked action's name - it never has to touch marker/index
 * bookkeeping itself.
 */
final class ClanForm implements Form{

    /** @var list<string> action names whose visible label comes from the value itself (not a fixed JSON label) */
    private const DYNAMIC_LABEL_ACTIONS = ["primary", "secondary", "tertiary", "opt0", "opt1", "opt2", "opt3", "opt4", "opt5"];

    /**
     * @param array<string, string> $values slot name (DATA or ACTIONS key) => text / texture path / button label.
     *                                       An action key is only enabled when present with a non-empty value.
     * @param callable(Player, string): void $onAction called with the clicked action's name.
     */
    public function __construct(
        private string $screen,
        private array $values,
        private $onAction,
    ){}

    private function data(): array{
        return $this->screen === "dialog" ? ClanLayouts::DIALOG_DATA : ClanLayouts::DASH_DATA;
    }

    private function actions(): array{
        return $this->screen === "dialog" ? ClanLayouts::DIALOG_ACTIONS : ClanLayouts::DASH_ACTIONS;
    }

    private function images(): array{
        return $this->screen === "dialog" ? ClanLayouts::DIALOG_IMAGES : ClanLayouts::DASH_IMAGES;
    }

    /** The client only renders values that start with a colour code - numbers/symbols get dropped otherwise. */
    private static function safe(string $value): string{
        return str_starts_with($value, "\u{00A7}") ? $value : "\u{00A7}r" . $value;
    }

    public function jsonSerialize(): array{
        $data = $this->data();
        $images = $this->images();

        $buttons = [];
        foreach($data as $i => $slot){
            $marker = ClanLayouts::marker($i);
            $value = (string) ($this->values[$slot] ?? "");
            if(in_array($slot, $images, true)){
                $buttons[] = $value === ""
                    ? ["text" => ""]
                    : ["text" => $marker . "img", "image" => ["type" => "path", "data" => $value]];
            }else{
                $buttons[] = ["text" => $value === "" ? "" : $marker . self::safe($value)];
            }
        }

        $base = count($data);
        foreach($this->actions() as $j => $action){
            $value = (string) ($this->values[$action] ?? "");
            $marker = ClanLayouts::marker($base + $j);
            if($value === ""){
                $buttons[] = ["text" => ""];
            }elseif(in_array($action, self::DYNAMIC_LABEL_ACTIONS, true)){
                $buttons[] = ["text" => $marker . self::safe($value)];
            }else{
                $buttons[] = ["text" => $marker . "btn"];
            }
        }

        return [
            "type" => "form",
            "title" => ClanLayouts::TITLES[$this->screen],
            "content" => "",
            "buttons" => $buttons,
        ];
    }

    public function handleResponse(Player $player, $data): void{
        if(!is_int($data)){
            return; // closed without choosing anything
        }
        $dataCount = count($this->data());
        if($data < $dataCount){
            return; // clicked a non-interactive info slot, nothing to do
        }
        $action = $this->actions()[$data - $dataCount] ?? null;
        if($action === null || (string) ($this->values[$action] ?? "") === ""){
            return; // disabled/hidden action, ignore
        }
        ($this->onAction)($player, $action);
    }
}
