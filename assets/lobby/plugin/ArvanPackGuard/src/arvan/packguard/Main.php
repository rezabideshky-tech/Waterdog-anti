<?php

declare(strict_types=1);

namespace arvan\packguard;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

final class Main extends PluginBase implements Listener{

	/** @var array<string, array{name:string, xuid:string, ip:string, time:int, bot:bool}> pending logins keyed by lower name */
	private array $pending = [];
	private Config $data;

	protected function onEnable() : void{
		$this->saveDefaultConfig();
		$this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML, ["strikes" => [], "banned" => [], "log" => []]);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->sweep()), 20 * 5);
	}

	protected function onDisable() : void{
		$this->data->save();
	}

	/** @priority LOWEST */
	public function onPreLogin(PlayerPreLoginEvent $ev) : void{
		$info = $ev->getPlayerInfo();
		$xuid = $info instanceof XboxLivePlayerInfo ? $info->getXuid() : "";
		$ip = $ev->getIp();
		$banned = (array) $this->data->get("banned", []);
		if(($xuid !== "" && isset($banned["x:" . $xuid])) || isset($banned["i:" . $ip]) || isset($banned["n:" . strtolower($info->getUsername())])){
			$ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_BANNED, (string) $this->getConfig()->get("kick-message"));
			return;
		}
		$extra = $info->getExtraData();
		$model = (string) ($extra["DeviceModel"] ?? "");
		$os = (int) ($extra["DeviceOS"] ?? -1);
		$bot = $model === "" || $os <= 0 || !isset($extra["SkinData"]) || ($extra["SkinData"] ?? "") === "";
		$this->pending[strtolower($info->getUsername())] = ["name" => $info->getUsername(), "xuid" => $xuid, "ip" => $ip, "time" => time(), "bot" => $bot];
	}

	public function onJoin(PlayerJoinEvent $ev) : void{
		unset($this->pending[strtolower($ev->getPlayer()->getName())]);
	}

	private function sweep() : void{
		$timeout = (int) $this->getConfig()->get("spawn-timeout", 60);
		foreach($this->pending as $k => $p){
			if(time() - $p["time"] < $timeout){
				continue;
			}
			unset($this->pending[$k]);
			if($this->getServer()->getPlayerExact($p["name"]) !== null){
				continue; // still loading but connected -> ignore
			}
			$this->strike($p);
		}
	}

	/** @param array{name:string, xuid:string, ip:string, time:int, bot:bool} $p */
	private function strike(array $p) : void{
		$add = 1 + (($p["bot"] && $this->getConfig()->get("bot-heuristics", true)) ? 1 : 0);
		$strikes = (array) $this->data->get("strikes", []);
		$keys = ["i:" . $p["ip"]];
		if($p["xuid"] !== ""){
			$keys[] = "x:" . $p["xuid"];
		}
		$max = 0;
		foreach($keys as $key){
			$strikes[$key] = (int) ($strikes[$key] ?? 0) + $add;
			$max = max($max, $strikes[$key]);
		}
		$this->data->set("strikes", $strikes);
		$log = (array) $this->data->get("log", []);
		$log[] = date("Y-m-d H:i:s") . " {$p["name"]} xuid={$p["xuid"]} ip={$p["ip"]} bot=" . ($p["bot"] ? "yes" : "no") . " strikes=$max";
		$this->data->set("log", array_slice($log, -500));
		$msg = "§c[PackGuard] §f{$p["name"]} §7downloaded packs without joining §8(xuid {$p["xuid"]}, strikes $max)";
		$this->getLogger()->warning($msg);
		if($max >= (int) $this->getConfig()->get("max-strikes", 3)){
			$this->ban($p);
			$msg .= " §4-> BANNED";
		}
		foreach($this->getServer()->getOnlinePlayers() as $pl){
			if($pl->hasPermission("packguard.notify")){
				$pl->sendMessage($msg);
			}
		}
		$this->data->save();
	}

	/** @param array{name:string, xuid:string, ip:string, time:int, bot:bool} $p */
	private function ban(array $p) : void{
		$banned = (array) $this->data->get("banned", []);
		$entry = "{$p["name"]} " . date("Y-m-d H:i");
		$banned["n:" . strtolower($p["name"])] = $entry;
		if($p["xuid"] !== ""){
			$banned["x:" . $p["xuid"]] = $entry;
		}
		if($this->getConfig()->get("ban-ip", true)){
			$banned["i:" . $p["ip"]] = $entry;
		}
		$this->data->set("banned", $banned);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$sub = strtolower($args[0] ?? "status");
		$banned = (array) $this->data->get("banned", []);
		if($sub === "list"){
			$sender->sendMessage("§6PackGuard bans (" . count($banned) . "):");
			foreach($banned as $k => $v){
				$sender->sendMessage("§7- §f$k §8$v");
			}
		}elseif($sub === "unban" && isset($args[1])){
			$t = strtolower($args[1]);
			$n = 0;
			foreach($banned as $k => $v){
				if(strtolower(substr($k, 2)) === $t || str_starts_with(strtolower($v), $t . " ")){
					$who = explode(" ", $v)[0];
					foreach($banned as $k2 => $v2){
						if(explode(" ", $v2)[0] === $who){
							unset($banned[$k2]); $n++;
						}
					}
				}
			}
			$strikes = (array) $this->data->get("strikes", []);
			unset($strikes["x:" . $args[1]], $strikes["i:" . $args[1]]);
			$this->data->set("strikes", $strikes);
			$this->data->set("banned", $banned);
			$this->data->save();
			$sender->sendMessage("§aRemoved $n ban entries.");
		}else{
			$sender->sendMessage("§6PackGuard §7pending: §f" . count($this->pending) . " §7bans: §f" . count($banned) . " §7strikes: §f" . count((array) $this->data->get("strikes", [])));
		}
		return true;
	}
}
