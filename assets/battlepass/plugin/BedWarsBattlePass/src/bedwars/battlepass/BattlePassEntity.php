<?php
declare(strict_types=1);

namespace bedwars\battlepass;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class BattlePassEntity extends Entity{
	public const NETWORK_ID = "bedwars:battle_pass";

	public static function getNetworkTypeId() : string{ return self::NETWORK_ID; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(2.6, 1.4); }

	protected function getInitialDragMultiplier() : float{ return 0.0; }

	protected function getInitialGravityMultiplier() : float{ return 0.0; }

	protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setNameTag("§l§bARVAN§fGAMING §6BATTLE PASS\n§r§eTap to open!");
		$this->setNameTagAlwaysVisible();
		$this->setNoClientPredictions();
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->cancel();
		if($source instanceof EntityDamageByEntityEvent && ($p = $source->getDamager()) instanceof Player){
			$p->sendForm(new BattlePassForm());
		}
	}
}
