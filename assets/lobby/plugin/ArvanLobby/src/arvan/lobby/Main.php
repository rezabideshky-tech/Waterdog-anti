<?php
declare(strict_types=1);

namespace arvan\lobby;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase{
	private static self $instance;

	public static function get() : self{ return self::$instance; }

	protected function onEnable() : void{
		self::$instance = $this;
		$this->saveDefaultConfig();
		CustomiesEntityFactory::getInstance()->registerEntity(BedwarsDuosNpc::class, BedwarsDuosNpc::NETWORK_ID);
		CustomiesEntityFactory::getInstance()->registerEntity(RoleplayCityNpc::class, RoleplayCityNpc::NETWORK_ID);
	}

	/** @return array<string, mixed> */
	public function npcConfig(string $key) : array{
		return (array) $this->getConfig()->getNested("npcs.$key", []);
	}

	public function use(Player $player, string $key) : void{
		$c = $this->npcConfig($key);
		if(($c["action"] ?? "transfer") === "command"){
			$cmd = str_replace("{player}", '"' . $player->getName() . '"', (string) ($c["command"] ?? ""));
			$this->getServer()->dispatchCommand(new ConsoleCommandSender($this->getServer(), $this->getServer()->getLanguage()), $cmd);
			return;
		}
		$player->sendTitle("§l§bArvan§fGaming", "§7Connecting...", 5, 30, 10);
		$player->transfer((string) ($c["address"] ?? "127.0.0.1"), (int) ($c["port"] ?? 19132));
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage("§cIn-game only.");
			return true;
		}
		if(($args[0] ?? "") === "spawn"){
			$npc = match($args[1] ?? ""){
				"duos" => new BedwarsDuosNpc($sender->getLocation()),
				"roleplay" => new RoleplayCityNpc($sender->getLocation()),
				default => null,
			};
			if($npc === null) return false;
			$npc->spawnToAll();
			$sender->sendMessage("§aNPC spawned.");
			return true;
		}
		if(($args[0] ?? "") === "remove"){
			$n = 0;
			foreach($sender->getWorld()->getNearbyEntities($sender->getBoundingBox()->expandedCopy(6, 6, 6)) as $e){
				if($e instanceof LobbyNpc){ $e->flagForDespawn(); $n++; }
			}
			$sender->sendMessage("§eRemoved $n NPC(s).");
			return true;
		}
		return false;
	}
}
