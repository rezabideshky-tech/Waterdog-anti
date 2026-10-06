<?php
declare(strict_types=1);

namespace arvan\lobby;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

final class Main extends PluginBase{
	private static self $instance;
	private Config $waiting;

	public function waitingCount(string $key) : int{
		return count((array) $this->waiting->get($key, []));
	}

	public static function get() : self{ return self::$instance; }

	protected function onEnable() : void{
		self::$instance = $this;
		$this->saveDefaultConfig();
		CustomiesEntityFactory::getInstance()->registerEntity(BedwarsDuosNpc::class, BedwarsDuosNpc::NETWORK_ID);
		CustomiesEntityFactory::getInstance()->registerEntity(RoleplayCityNpc::class, RoleplayCityNpc::NETWORK_ID);
		CustomiesEntityFactory::getInstance()->registerEntity(CargoTruckNpc::class, CargoTruckNpc::NETWORK_ID);
		CustomiesEntityFactory::getInstance()->registerEntity(SkyblockSoonNpc::class, SkyblockSoonNpc::NETWORK_ID);
		$this->waiting = new Config($this->getDataFolder() . "waiting.yml", Config::YAML);
	}

	/** @return array<string, mixed> */
	public function npcConfig(string $key) : array{
		return (array) $this->getConfig()->getNested("npcs.$key", []);
	}

	public function use(Player $player, string $key) : void{
		$c = $this->npcConfig($key);
		if(($c["action"] ?? "") === "soon"){
			$list = (array) $this->waiting->get($key, []);
			$name = strtolower($player->getName());
			$new = !in_array($name, $list, true);
			if($new){
				$list[] = $name;
				$this->waiting->set($key, $list);
				$this->waiting->save();
			}
			$player->sendTitle((string) ($c["title"] ?? "§l§eComing Soon!"), (string) ($c["subtitle"] ?? ""), 5, 50, 10);
			$player->sendMessage(str_replace("{count}", (string) count($list), (string) ($new ? ($c["message"] ?? "") : ($c["message_again"] ?? $c["message"] ?? ""))));
			$pos = $player->getPosition();
			$player->getNetworkSession()->sendDataPacket(PlaySoundPacket::create("random.chestclosed", $pos->x, $pos->y, $pos->z, 1.0, 0.8));
			foreach($player->getWorld()->getEntities() as $e){
				if($e instanceof LobbyNpc && $e->getKey() === $key){
					$e->refreshNameTag();
				}
			}
			return;
		}
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
				"truck" => new CargoTruckNpc($sender->getLocation()),
				"skyblock" => new SkyblockSoonNpc($sender->getLocation()),
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
