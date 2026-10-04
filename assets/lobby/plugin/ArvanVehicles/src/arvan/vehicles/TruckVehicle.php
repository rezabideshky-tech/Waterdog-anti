<?php
declare(strict_types=1);

namespace arvan\vehicles;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

final class TruckVehicle extends Entity{
	public const NETWORK_ID = "arvan:rp_truck";

	/** @var array<int, TruckVehicle> player id => truck */
	private static array $driving = [];

	private ?Player $driver = null;
	private string $owner = "";
	private float $inputX = 0.0;
	private float $inputZ = 0.0;
	private float $speed = 0.0;

	public static function getNetworkTypeId() : string{ return self::NETWORK_ID; }

	public static function getDriving(Player $player) : ?TruckVehicle{ return self::$driving[$player->getId()] ?? null; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(2.4, 2.2); }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravityMultiplier() : float{ return 0.08; }

	private function cfg(string $k, mixed $def) : mixed{ return Main::get()->getConfig()->get($k, $def); }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->owner = $nbt->getString("Owner", "");
		$this->stepHeight = (float) $this->cfg("step_height", 1.1);
		$this->setNameTag((string) $this->cfg("nametag", "Truck"));
		$this->setNameTagAlwaysVisible();
		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
	}

	public function saveNBT() : CompoundTag{
		return parent::saveNBT()->setString("Owner", $this->owner);
	}

	public function setOwner(string $name) : void{ $this->owner = $name; }

	public function setInput(float $x, float $z) : void{
		$this->inputX = max(-1.0, min(1.0, $x));
		$this->inputZ = max(-1.0, min(1.0, $z));
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$this->tryMount($player);
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->cancel(); // trucks can't be destroyed; tapping on mobile counts as attack, so mount too
		if($source instanceof \pocketmine\event\entity\EntityDamageByEntityEvent && ($p = $source->getDamager()) instanceof Player){
			$this->tryMount($p);
		}
	}

	private function tryMount(Player $player) : void{
		if($this->driver !== null || self::getDriving($player) !== null){
			return;
		}
		if((bool) $this->cfg("owner_only", false) && $this->owner !== "" && strtolower($this->owner) !== strtolower($player->getName())){
			$player->sendTip("§cThis truck belongs to §f" . $this->owner);
			return;
		}
		$this->driver = $player;
		self::$driving[$player->getId()] = $this;
		$seat = (array) $this->cfg("seat", [0.0, 1.9, 1.8]);
		$props = $player->getNetworkProperties();
		$props->setGenericFlag(EntityMetadataFlags::RIDING, true);
		$props->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3((float) $seat[0], (float) $seat[1], (float) $seat[2]));
		$this->broadcastLink(EntityLink::TYPE_RIDER);
		$player->sendTitle("§l§cARVAN §fTRUCK", "§7W/S = drive  A/D = steer  Sneak = exit", 5, 40, 10);
	}

	public function dismount() : void{
		if($this->driver === null){
			return;
		}
		$p = $this->driver;
		$this->broadcastLink(EntityLink::TYPE_REMOVE);
		unset(self::$driving[$p->getId()]);
		$this->driver = null;
		$this->inputX = $this->inputZ = 0.0;
		if(!$p->isClosed()){
			$p->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
			$side = $this->getDirectionVector()->cross(new Vector3(0, 1, 0))->normalize()->multiply(2.0);
			$p->teleport($this->getPosition()->add($side->x, 0.5, $side->z));
		}
	}

	private function broadcastLink(int $type) : void{
		if($this->driver === null){
			return;
		}
		// extra trailing arg (vehicle angular velocity) is ignored on older protocol versions
		$pk = SetActorLinkPacket::create(new EntityLink($this->getId(), $this->driver->getId(), $type, true, true, 0.0));
		$targets = $this->getViewers();
		$targets[] = $this->driver;
		$this->getWorld()->getServer()->broadcastPackets($targets, [$pk]);
	}

	public function spawnTo(Player $player) : void{
		parent::spawnTo($player);
		if($this->driver !== null && $player !== $this->driver){
			$player->getNetworkSession()->sendDataPacket(SetActorLinkPacket::create(
				new EntityLink($this->getId(), $this->driver->getId(), EntityLink::TYPE_RIDER, true, true, 0.0)));
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		$d = $this->driver;
		if($d !== null && (!$d->isOnline() || $d->getWorld() !== $this->getWorld() || $d->getPosition()->distance($this->getPosition()) > 12)){
			$this->dismount();
		}
		$max = (float) $this->cfg("max_speed", 0.55);
		$rev = (float) $this->cfg("reverse_speed", 0.2);
		$acc = (float) $this->cfg("acceleration", 0.03);
		$brake = (float) $this->cfg("brake", 0.06);
		$target = $this->inputZ >= 0 ? $this->inputZ * $max : $this->inputZ * $rev;
		if($this->driver === null){
			$target = 0.0;
		}
		$step = (($target > 0 && $this->speed < 0) || ($target < 0 && $this->speed > 0) || $target == 0.0) ? $brake : $acc;
		$this->speed += max(-$step, min($step, $target - $this->speed)) * $tickDiff;
		if(abs($this->speed) < 0.005 && $target == 0.0){
			$this->speed = 0.0;
		}
		$yaw = $this->location->yaw;
		if($this->speed != 0.0 && $this->inputX != 0.0){
			$dir = (bool) $this->cfg("invert_steering", false) ? 1 : -1;
			$yaw += $dir * $this->inputX * (float) $this->cfg("turn_speed", 4.0) * ($this->speed / max(0.01, $max)) * $tickDiff;
			$this->setRotation(fmod($yaw + 360.0, 360.0), 0.0);
		}
		$rad = deg2rad($this->location->yaw);
		$this->motion = new Vector3(-sin($rad) * $this->speed, $this->motion->y, cos($rad) * $this->speed);
		if($this->driver !== null && abs($this->speed) > 0.05){
			$this->driver->sendTip("§l§c" . (int) round(abs($this->speed) * 20 * 3.6) . " §fkm/h");
		}
		return true || $hasUpdate;
	}
}
