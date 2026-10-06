<?php
declare(strict_types=1);

namespace bedwars\battlepass;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase{
	protected function onEnable() : void{
		CustomiesEntityFactory::getInstance()->registerEntity(BattlePassEntity::class, BattlePassEntity::NETWORK_ID);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage("§cIn-game only.");
			return true;
		}
		switch($args[0] ?? ""){
			case "spawn":
				$e = new BattlePassEntity($sender->getLocation());
				$e->spawnToAll();
				$sender->sendMessage("§aBattle Pass NPC spawned!");
				return true;
			case "remove":
				$n = 0;
				foreach($sender->getWorld()->getNearbyEntities($sender->getBoundingBox()->expandedCopy(5, 5, 5)) as $e){
					if($e instanceof BattlePassEntity){ $e->flagForDespawn(); $n++; }
				}
				$sender->sendMessage("§eRemoved $n Battle Pass NPC(s).");
				return true;
		}
		return false;
	}
}
