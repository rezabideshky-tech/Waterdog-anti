<?php
declare(strict_types=1);

namespace arvan\vehicles;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase implements Listener{
	private static self $instance;

	public static function get() : self{ return self::$instance; }

	protected function onEnable() : void{
		self::$instance = $this;
		$this->saveDefaultConfig();
		CustomiesEntityFactory::getInstance()->registerEntity(TruckVehicle::class, TruckVehicle::NETWORK_ID);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPacket(DataPacketReceiveEvent $event) : void{
		$player = $event->getOrigin()->getPlayer();
		if($player === null || ($truck = TruckVehicle::getDriving($player)) === null){
			return;
		}
		$pk = $event->getPacket();
		if($pk instanceof PlayerAuthInputPacket){
			$truck->setInput($pk->getMoveVecX(), $pk->getMoveVecZ());
		}elseif($pk instanceof InteractPacket && $pk->action === InteractPacket::ACTION_LEAVE_VEHICLE){
			$truck->dismount();
		}
	}

	public function onSneak(PlayerToggleSneakEvent $event) : void{
		if($event->isSneaking() && ($truck = TruckVehicle::getDriving($event->getPlayer())) !== null){
			$truck->dismount();
		}
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		TruckVehicle::getDriving($event->getPlayer())?->dismount();
	}

	public function onDeath(PlayerDeathEvent $event) : void{
		TruckVehicle::getDriving($event->getPlayer())?->dismount();
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage("§cIn-game only.");
			return true;
		}
		switch($args[0] ?? ""){
			case "spawn":
				$truck = new TruckVehicle($sender->getLocation());
				$truck->setOwner($sender->getName());
				$truck->spawnToAll();
				$sender->sendMessage("§aTruck spawned! Tap it to drive, sneak to get out.");
				return true;
			case "remove":
				$n = 0;
				foreach($sender->getWorld()->getNearbyEntities($sender->getBoundingBox()->expandedCopy(6, 6, 6)) as $e){
					if($e instanceof TruckVehicle){ $e->dismount(); $e->flagForDespawn(); $n++; }
				}
				$sender->sendMessage("§eRemoved $n truck(s).");
				return true;
		}
		return false;
	}
}
