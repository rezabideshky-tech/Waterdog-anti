<?php
declare(strict_types=1);

namespace arvan\lobby;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

abstract class LobbyNpc extends Entity{
	abstract protected function key() : string;

	public function getKey() : string{ return $this->key(); }

	public function refreshNameTag() : void{
		$tag = (string) (Main::get()->npcConfig($this->key())["nametag"] ?? "");
		$this->setNameTag(str_replace("{count}", (string) Main::get()->waitingCount($this->key()), $tag));
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(7.0, 7.0); }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravityMultiplier() : float{ return 0.0; }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->refreshNameTag();
		$this->setNameTagAlwaysVisible();
		$this->setNoClientPredictions();
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->cancel();
		if($source instanceof EntityDamageByEntityEvent && ($p = $source->getDamager()) instanceof Player){
			Main::get()->use($p, $this->key());
		}
	}
}
