<?php
declare(strict_types=1);

namespace arvan\props;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase{
    protected function onEnable() : void{
        // STARTUP registration also makes saved entities available before world loading.
        $factory = CustomiesEntityFactory::getInstance();
        $factory->registerEntity(PumpProp::class, PumpProp::NETWORK_ID);
        $factory->registerEntity(AmmoCaseProp::class, AmmoCaseProp::NETWORK_ID);
        $this->getLogger()->info("Arvan props registered. Enable ArvanRoleplayProps_RP.zip in resource_packs.yml.");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if(!$sender->hasPermission("arvanprops.admin")){
            $sender->sendMessage("§cYou do not have permission to manage props.");
            return true;
        }
        if(!$sender instanceof Player){
            $sender->sendMessage("Run this command in-game as an operator.");
            return true;
        }
        $action = strtolower($args[0] ?? "");
        if($action === "spawn"){
            $type = strtolower($args[1] ?? "");
            $class = match($type){
                "pump" => PumpProp::class,
                "ammo" => AmmoCaseProp::class,
                default => null
            };
            if($class === null){
                $sender->sendMessage("§eUsage: /arvanprops spawn <pump|ammo>");
                return true;
            }
            $at = $sender->getLocation();
            // Exact feet position makes deliberate placement possible. Step away after spawning.
            $entity = new $class(new Location($at->x, $at->y, $at->z, $at->getWorld(), $at->yaw, 0.0));
            $entity->spawnToAll();
            $sender->sendMessage("§aArvan " . $type . " placed at your feet. Step away to view it.");
            return true;
        }
        if($action === "remove"){
            $nearest = null;
            $distance = 36.0;
            foreach($sender->getWorld()->getNearbyEntities($sender->getBoundingBox()->expandedCopy(6, 6, 6)) as $entity){
                if(!$entity instanceof PropEntity || $entity->isFlaggedForDespawn()){
                    continue;
                }
                $d = $entity->getPosition()->distanceSquared($sender->getPosition());
                if($d <= $distance){
                    $nearest = $entity;
                    $distance = $d;
                }
            }
            if($nearest !== null){
                $nearest->flagForDespawn();
                $sender->sendMessage("§aRemoved the nearest Arvan prop.");
            }else{
                $sender->sendMessage("§eNo Arvan prop found within 6 blocks.");
            }
            return true;
        }
        $sender->sendMessage("§b/arvanprops spawn pump §7| §b/arvanprops spawn ammo §7| §b/arvanprops remove");
        return true;
    }
}
