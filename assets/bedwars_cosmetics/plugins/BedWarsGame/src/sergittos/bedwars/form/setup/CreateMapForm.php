<?php

declare(strict_types=1);

namespace sergittos\bedwars\form\setup;

use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use sergittos\bedwars\form\CustomForm;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\libs\EasyUI\element\Dropdown;
use sergittos\bedwars\libs\EasyUI\element\Input;
use sergittos\bedwars\libs\EasyUI\element\Option;
use sergittos\bedwars\libs\EasyUI\element\Slider;
use sergittos\bedwars\libs\EasyUI\utils\FormResponse;
use sergittos\bedwars\session\SessionFactory;
use sergittos\bedwars\session\setup\builder\MapBuilder;
use sergittos\bedwars\session\setup\MapSetup;
use Symfony\Component\Filesystem\Path;

class CreateMapForm extends CustomForm{

    public function __construct(){
        parent::__construct("Create a map");
    }

    protected function onCreation() : void{
        $this->addElement("name", new Input("Set a name:"));
        $this->addElement("teams", new Slider("Set the teams", 2, 8, 8, 1));
        $this->addWorldsDropdown("playing_world", "Select the map world (Playing):");
        $this->addWorldsDropdown("waiting_world", "Select the waiting world:");
        $this->addSelectModeDropdown();
        $this->addElement("waiting_x", new Input("Waiting spawn X (optional):", null, "leave blank to use the waiting world's own spawn"));
        $this->addElement("waiting_y", new Input("Waiting spawn Y (optional):", null, "leave blank to use the waiting world's own spawn"));
        $this->addElement("waiting_z", new Input("Waiting spawn Z (optional):", null, "leave blank to use the waiting world's own spawn"));
    }

    protected function onSubmit(Player $player, FormResponse $response) : void{
        $name = trim($response->getInputSubmittedText("name"));
        $playing_world = $response->getDropdownSubmittedOptionId("playing_world");
        $waiting_world_name = $response->getDropdownSubmittedOptionId("waiting_world");
        $teams = (int) $response->getSliderSubmittedStep("teams");
        $players_per_team = (int) $response->getDropdownSubmittedOptionId("players_per_team");

        $waiting_x = trim($response->getInputSubmittedText("waiting_x"));
        $waiting_y = trim($response->getInputSubmittedText("waiting_y"));
        $waiting_z = trim($response->getInputSubmittedText("waiting_z"));

        if($name === ""){
            $player->sendMessage(TextFormat::RED . "You must set a map name.");
            return;
        }
        if(MapFactory::getMapByName($name) !== null){
            $player->sendMessage(TextFormat::RED . "A map with that name already exists.");
            return;
        }
        if($this->checkIfDefaultWorld($playing_world)){
            $player->sendMessage(TextFormat::RED . "You cannot use the default world as a BedWars map.");
            return;
        }
        if($this->checkIfDefaultWorld($waiting_world_name)){
            $player->sendMessage(TextFormat::RED . "You cannot use the default world as the waiting world.");
            return;
        }
        if(strtolower($waiting_world_name) === strtolower($playing_world)){
            $player->sendMessage(TextFormat::RED . "The waiting world must be different from the map world - pick a separate world for players to wait in.");
            return;
        }

        if(($players_per_team === 1 || $players_per_team === 2) && $teams !== 8){
            $player->sendMessage("§c§lInvalid Setup§r§7 - Solo/Doubles must use §e8 §7teams.");
            return;
        }
        if(($players_per_team === 3 || $players_per_team === 4) && $teams !== 4){
            $player->sendMessage("§c§lInvalid Setup§r§7 - Triples/Squads must use §e4 §7teams.");
            return;
        }

        // Either all three waiting-spawn coordinates are given, or none -
        // a partial set (e.g. only X filled in) would silently fall back
        // to 0 for the missing axes and very likely place players inside
        // a wall or the void, so that's rejected instead of guessed at.
        $waitingCoords = [$waiting_x, $waiting_y, $waiting_z];
        $filledCount = count(array_filter($waitingCoords, fn(string $v) : bool => $v !== ""));
        if($filledCount > 0 && $filledCount < 3){
            $player->sendMessage(TextFormat::RED . "Fill in all of Waiting spawn X/Y/Z, or leave all three blank to use the waiting world's own spawn.");
            return;
        }
        $customWaitingSpawn = null;
        if($filledCount === 3){
            if(!is_numeric($waiting_x) || !is_numeric($waiting_y) || !is_numeric($waiting_z)){
                $player->sendMessage(TextFormat::RED . "Waiting spawn X/Y/Z must be numbers.");
                return;
            }
            $customWaitingSpawn = new Vector3((float) $waiting_x, (float) $waiting_y, (float) $waiting_z);
        }

        $wm = Server::getInstance()->getWorldManager();
        if(!$wm->loadWorld($playing_world)){
            $player->sendMessage(TextFormat::RED . "Failed to load world: $playing_world");
            return;
        }

        $world = $wm->getWorldByName($playing_world);
        if($world === null){
            $player->sendMessage(TextFormat::RED . "World is unavailable: $playing_world");
            return;
        }

        if(!$wm->loadWorld($waiting_world_name)){
            $player->sendMessage(TextFormat::RED . "Failed to load waiting world: $waiting_world_name");
            return;
        }

        $waiting_world = $wm->getWorldByName($waiting_world_name);
        if($waiting_world === null){
            $player->sendMessage(TextFormat::RED . "Waiting world is unavailable: $waiting_world_name");
            return;
        }

        $player->setGamemode(GameMode::CREATIVE());
        $player->teleport($world->getSafeSpawn());

        $mapBuilder = new MapBuilder(
            $name,
            $waiting_world,
            $playing_world,
            $players_per_team,
            (int) ($teams * $players_per_team)
        );
        if($customWaitingSpawn !== null){
            $mapBuilder->setWaitingSpawnPosition($customWaitingSpawn);
        }

        $session = SessionFactory::getSession($player);
        $session->setMapSetup(new MapSetup($session, $mapBuilder));

        $session->message("{GREEN}{BOLD}Setup Started{RESET}{GRAY} - Use the setup menu to configure the arena.");
        $session->message("{YELLOW}{BOLD}Waiting World{RESET}{GRAY} - Players will wait in §f" . $waiting_world_name . "§7 before the match starts, separate from the arena.");
        if($customWaitingSpawn !== null){
            $session->message("{YELLOW}{BOLD}Waiting Spawn{RESET}{GRAY} - Set to " . $this->vectorToString($customWaitingSpawn) . " inside that world.");
        }else{
            $session->message("{YELLOW}{BOLD}Waiting Spawn{RESET}{GRAY} - Defaulted to that world's own spawn point.");
        }
    }

    private function vectorToString(Vector3 $v) : string{
        return "(" . round($v->getX(), 1) . ", " . round($v->getY(), 1) . ", " . round($v->getZ(), 1) . ")";
    }

    private function addWorldsDropdown(string $id, string $name) : void{
        $dropdown = new Dropdown($name);
        foreach(glob($this->getWorldsPath(), GLOB_ONLYDIR) as $world){
            $dropdown->addOption(new Option($n = basename($world), $n));
        }
        $this->addElement($id, $dropdown);
    }

    private function checkIfDefaultWorld(string $world) : bool{
        $default = Server::getInstance()->getWorldManager()->getDefaultWorld();
        return $default !== null && $default->getFolderName() === $world;
    }

    private function getWorldsPath() : string{
        return Path::join(Server::getInstance()->getDataPath(), "worlds", "*");
    }
}