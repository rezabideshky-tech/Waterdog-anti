<?php
declare(strict_types=1);

namespace arvan\islands;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase implements Listener{
    private static self $instance;
    private array $cooldowns = [];
    private const TYPES = ["solo" => SoloIsland::class, "doubles" => DoublesIsland::class,
        "triples" => TriplesIsland::class, "squads" => SquadsIsland::class];

    public static function get() : self{ return self::$instance; }

    protected function onEnable() : void{
        self::$instance = $this;
        $this->saveDefaultConfig();
        foreach(self::TYPES as $class){
            CustomiesEntityFactory::getInstance()->registerEntity($class, $class::getNetworkTypeId());
        }
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("Four Bedwars islands registered. Set each mode's route in config.yml.");
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        unset($this->cooldowns[$event->getPlayer()->getUniqueId()->toString()]);
    }

    public function enter(Player $player, IslandNpc $npc) : void{
        if(!$player->hasPermission("arvanislands.use") || $player->getWorld() !== $npc->getWorld()
            || $player->getPosition()->distanceSquared($npc->getPosition()) > 36){
            return;
        }
        $key = $player->getUniqueId()->toString();
        $now = microtime(true);
        if(($this->cooldowns[$key] ?? 0) > $now){ return; }
        $this->cooldowns[$key] = $now + 1.0;
        $mode = $npc->getMode();
        $route = (array) $this->getConfig()->getNested("modes." . $mode, []);
        $action = (string) ($route["action"] ?? "none");
        if($action === "command"){
            $command = ltrim(trim((string) ($route["command"] ?? "")), "/");
            if($command !== ""){
                // Run as the clicking player, never with elevated console privileges.
                $command = str_replace(["{player}", "{mode}"], [$player->getName(), $mode], $command);
                $this->getServer()->dispatchCommand($player, $command);
                return;
            }
        }elseif($action === "transfer"){
            $address = trim((string) ($route["address"] ?? ""));
            $port = (int) ($route["port"] ?? 19132);
            if($address !== "" && $port > 0 && $port <= 65535){
                $player->transfer($address, $port);
                return;
            }
        }
        $player->sendMessage("§bArvan Gaming §7| §e" . strtoupper($mode) . " §7is not connected yet.");
        if($player->hasPermission("arvanislands.admin")){
            $player->sendMessage("§7Configure modes." . $mode . " in plugin_data/ArvanBedwarsIslands/config.yml, then restart.");
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if(!$sender->hasPermission("arvanislands.admin")){
            $sender->sendMessage("§cYou cannot manage Bedwars islands.");
            return true;
        }
        if(!$sender instanceof Player){
            $sender->sendMessage("Use this command in-game.");
            return true;
        }
        if(($args[0] ?? "") === "spawn"){
            $mode = strtolower($args[1] ?? "");
            if(!isset(self::TYPES[$mode])){
                $sender->sendMessage("§e/bwislands spawn <solo|doubles|triples|squads>");
                return true;
            }
            $at = $sender->getLocation();
            $offset = max(0.0, min(3.0, (float) $this->getConfig()->get("spawn-height-offset", 0.4)));
            $class = self::TYPES[$mode];
            $npc = new $class(new Location($at->x, $at->y+$offset, $at->z, $at->getWorld(), $at->yaw, 0.0));
            $npc->spawnToAll();
            $sender->sendMessage("§a" . strtoupper($mode) . " island placed. Step away to view it.");
            return true;
        }
        if(($args[0] ?? "") === "remove"){
            $best = null;
            $distance = 36.0;
            foreach($sender->getWorld()->getNearbyEntities($sender->getBoundingBox()->expandedCopy(6,6,6)) as $npc){
                if(!$npc instanceof IslandNpc || $npc->isFlaggedForDespawn()){ continue; }
                $d = $npc->getPosition()->distanceSquared($sender->getPosition());
                if($d <= $distance){ $distance = $d; $best = $npc; }
            }
            if($best !== null){
                $best->flagForDespawn();
                $sender->sendMessage("§aNearest Bedwars island removed.");
            }else{
                $sender->sendMessage("§eNo Bedwars island within 6 blocks.");
            }
            return true;
        }
        $sender->sendMessage("§b/bwislands spawn <solo|doubles|triples|squads> §7| §b/bwislands remove");
        return true;
    }
}
