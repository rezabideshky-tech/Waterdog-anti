<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\profile\ui;

use pocketmine\form\Form;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\lobby\BedWarsLobby;
use sergittos\bedwars\lobby\profile\ProfileMenu;
use sergittos\bedwars\lobby\profile\ProfileView;
use function count;
use function in_array;
use function is_int;
use function str_starts_with;
use function max;

/**
 * A normal Bedrock "long form". The BedWars Profile UI resource pack recognises the hidden marker in the title
 * and draws the full-screen profile; every button carries one value behind a marker (see ProfileLayouts).
 */
final class ProfileForm implements Form{

    public function __construct(private ProfileView $view){}

    /**
     * The client only renders values that start with a colour code (numbers / symbols are dropped), so every
     * value that does not already start with one gets a harmless "reset" code in front of it.
     */
    private static function safe(string $value) : string{
        return str_starts_with($value, "§") ? $value : "§r" . $value;
    }

    public function jsonSerialize() : array{
        $tab = $this->view->tab;
        $values = ProfileValues::build($this->view);
        $data = ProfileLayouts::DATA[$tab];
        $images = ProfileLayouts::IMAGES[$tab];

        $buttons = [];
        foreach($data as $i => $slot){
            $marker = ProfileLayouts::marker($i);
            $value = (string) ($values[$slot] ?? "");
            if(in_array($slot, $images, true)){
                $buttons[] = $value === ""
                    ? ["text" => ""]
                    : ["text" => $marker . "img", "image" => ["type" => "path", "data" => $value]];
            }else{
                $buttons[] = ["text" => $value === "" ? "" : $marker . self::safe($value)];
            }
        }

        $enabled = $this->enabledActions();
        $base = count($data);
        foreach(ProfileLayouts::ACTIONS as $j => $action){
            $buttons[] = ["text" => in_array($action, $enabled, true) ? ProfileLayouts::marker($base + $j) . "btn" : ""];
        }

        return [
            "type" => "form",
            "title" => ProfileLayouts::TITLES[$tab],
            "content" => (string) $this->view->entityId, // the pack uses it to draw the player's 3D model
            "buttons" => $buttons,
        ];
    }

    /** @return list<string> */
    private function enabledActions() : array{
        $on = ["tab_home", "tab_stats", "tab_rank", "tab_medals", "tab_ach", "tab_history", "top", "search"];
        if(in_array($this->view->tab, ["medals", "ach", "history"], true)){
            if($this->view->page > 0){
                $on[] = "prev";
            }
            if($this->view->page < $this->view->pages - 1){
                $on[] = "next";
            }
        }
        return $on;
    }

    public function handleResponse(Player $player, $data) : void{
        if(!is_int($data)){
            return; // closed
        }
        $index = $data - count(ProfileLayouts::DATA[$this->view->tab]);
        $action = ProfileLayouts::ACTIONS[$index] ?? null;
        if($action === null || !in_array($action, $this->enabledActions(), true)){
            return;
        }

        $view = $this->view;
        $target = $view->profile->username;
        $tabs = ["tab_home" => "home", "tab_stats" => "stats", "tab_rank" => "rank", "tab_medals" => "medals", "tab_ach" => "ach", "tab_history" => "history"];

        // one tick later so the closing form and the next one never overlap
        BedWarsLobby::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player, $action, $tabs, $view, $target) : void{
            if(!$player->isConnected()){
                return;
            }
            if(isset($tabs[$action])){
                // on the board the "target" is the viewer, so tabs keep showing the same person
                ProfileMenu::open($player, $target, $tabs[$action], 0);
                return;
            }
            switch($action){
                case "top":
                    ProfileMenu::openBoard($player);
                    break;
                case "search":
                    ProfileMenu::openSearch($player);
                    break;
                case "prev":
                    ProfileMenu::open($player, $target, $view->tab, max(0, $view->page - 1));
                    break;
                case "next":
                    ProfileMenu::open($player, $target, $view->tab, $view->page + 1);
                    break;
            }
        }), 2);
    }
}
