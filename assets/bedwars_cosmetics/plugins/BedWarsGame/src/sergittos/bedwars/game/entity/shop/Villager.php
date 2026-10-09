<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\shop;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use sergittos\bedwars\utils\ColorUtils;
use function strtolower;
use function strtoupper;

abstract class Villager extends Entity{

    public static function getNetworkTypeId() : string{
        return EntityIds::VILLAGER;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.8, 0.6);
    }

    protected function getInitialGravity() : float{
        return 0.0;
    }

    protected function getInitialDragMultiplier() : float{
        return 0.0;
    }

    public function canSaveWithChunk() : bool{
        return false;
    }

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);
        $this->setNameTag(ColorUtils::translate("{AQUA}" . strtoupper($this->getName()) . "{RESET}\n{YELLOW}{BOLD}RIGHT CLICK"));
        $this->setNameTagAlwaysVisible();
    }

    public function onUpdate(int $currentTick) : bool{
        $has = parent::onUpdate($currentTick);
        // Shop villagers never move on their own (gravity/drag are both 0), so
        // motion is only ever non-zero for a tick right after something pushes
        // them (e.g. a player colliding with the hitbox). Previously this called
        // setMotion(zero) unconditionally on every single tick for every shop
        // villager in every game, which fires an EntityMotionEvent and forces
        // the entity's movement/collision recalculation to run again even though
        // there was nothing to correct - across hundreds of villagers ticking
        // 20x/sec this was one of the largest Entity Tick costs in the timings.
        // Only re-zero the motion when it actually drifted from zero, which
        // keeps the exact same "villagers can't be pushed around" behaviour
        // while skipping the redundant event/recalculation on every idle tick.
        if(!$this->motion->equals(Vector3::zero())){
            $this->setMotion(Vector3::zero());
        }
        return $has;
    }

    public function attack(EntityDamageEvent $source) : void{
        if($source instanceof EntityDamageByChildEntityEvent || !$source instanceof EntityDamageByEntityEvent){
            return;
        }

        $source->cancel();

        $damager = $source->getDamager();
        if(!$damager instanceof Player || !SessionFactory::hasSession($damager)){
            return;
        }

        $held = $damager->getInventory()->getItemInHand();
        $bw = strtolower($held->getNamedTag()->getString("bedwars_name", ""));
        if($bw === "tracker_shop"){
            BedwarsItems::TRACKER_SHOP()->onInteract(SessionFactory::getSession($damager));
            return;
        }

        $session = SessionFactory::getSession($damager);
        if($session->isPlaying() && $session->hasTeam()){
            $this->getForm($session);
        }
    }

    public function onInteract(Player $player, Vector3 $clickPos) : bool{
        if(!SessionFactory::hasSession($player)){
            return true;
        }

        $held = $player->getInventory()->getItemInHand();
        $bw = strtolower($held->getNamedTag()->getString("bedwars_name", ""));
        if($bw === "tracker_shop"){
            BedwarsItems::TRACKER_SHOP()->onInteract(SessionFactory::getSession($player));
            return true;
        }

        $session = SessionFactory::getSession($player);
        if($session->isPlaying() && $session->hasTeam()){
            $this->getForm($session);
        }

        return true;
    }

    abstract protected function getName() : string;

    abstract protected function getForm(Session $session);
}