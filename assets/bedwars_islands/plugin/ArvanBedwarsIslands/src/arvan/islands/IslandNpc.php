<?php
declare(strict_types=1);

namespace arvan\islands;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

abstract class IslandNpc extends Entity{
    public const MODE = "";

    public static function getNetworkTypeId() : string{
        return "arvan:bw_" . static::MODE . "_island";
    }

    public function getMode() : string{ return static::MODE; }
    protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(2.0, 1.55); }
    protected function getInitialDragMultiplier() : float{ return 0.0; }
    protected function getInitialGravity() : float{ return 0.0; }
    public function canBeMovedByCurrents() : bool{ return false; }

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);
        $this->setNameTag("");
        $this->setNameTagVisible(false);
        $this->setHasGravity(false);
        $this->setNoClientPredictions(true);
        $this->setCanSaveWithChunk(true);
    }

    public function onInteract(Player $player, Vector3 $clickPos) : bool{
        Main::get()->enter($player, $this);
        return true;
    }

    public function attack(EntityDamageEvent $source) : void{
        $source->cancel();
        if($source instanceof EntityDamageByEntityEvent && ($player = $source->getDamager()) instanceof Player){
            Main::get()->enter($player, $this);
        }
    }
}
