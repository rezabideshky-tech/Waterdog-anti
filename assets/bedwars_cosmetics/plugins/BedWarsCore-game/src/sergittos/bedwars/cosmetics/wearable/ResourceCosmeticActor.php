<?php
declare(strict_types=1);
namespace sergittos\bedwars\cosmetics\wearable;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

/** One non-persistent, non-colliding visual actor per wearer, not one per item. */
final class ResourceCosmeticActor extends Entity{
    private ?Player $owner = null;
    private bool $visible = false;
    private int $selector = -1;
    private bool $crouched = false;
    private int $teamColor = -1;

    public static function getNetworkTypeId(): string{ return "arvan:cosmetic_overlay_v2"; }
    protected function getInitialSizeInfo(): EntitySizeInfo{ return new EntitySizeInfo(0.001, 0.001); }
    protected function getInitialDragMultiplier(): float{ return 0.0; }
    protected function getInitialGravity(): float{ return 0.0; }
    public function canBeCollidedWith(): bool{ return false; }
    public function canCollideWith(Entity $entity): bool{ return false; }
    public function canBeMovedByCurrents(): bool{ return false; }
    public function attack(EntityDamageEvent $source): void{ $source->cancel(); }

    protected function initEntity(CompoundTag $nbt): void{
        parent::initEntity($nbt);
        $this->setCanSaveWithChunk(false);
        $this->setHasGravity(false);
        $this->setSilent(true);
        $this->setNameTagVisible(false);
        // Keep interpolation enabled; the server synchronizes only changed transforms.
        $this->setNoClientPredictions(false);
    }

    public function bind(Player $owner): void{ $this->owner = $owner; }

    private function mayView(Player $viewer): bool{
        $owner = $this->owner;
        if(!$this->visible || $owner === null || !$owner->isConnected() || !$owner->isAlive()
            || $owner->isInvisible() || $owner->isSpectator() || !$viewer->isConnected()
            || $viewer->getWorld() !== $owner->getWorld() || !$viewer->canSee($owner)){
            return false;
        }
        return $viewer === $owner || isset($owner->getViewers()[spl_object_id($viewer)]);
    }

    public function spawnTo(Player $player): void{
        if($this->mayView($player)){ parent::spawnTo($player); }
    }

    public function synchronize(int $selector, bool $visible, int $teamColor = 0): void{
        $owner = $this->owner;
        if($owner === null || !$owner->isConnected()){ $this->close(); return; }
        $this->visible = $visible;
        $at = $owner->getLocation();
        $old = $this->getLocation();
        $movedWorld = $old->getWorld() !== $at->getWorld();
        $moved = $movedWorld || $old->distanceSquared($at) > 0.000004;
        $turned = abs($old->yaw-$at->yaw) > 0.1 || abs($old->pitch-$at->pitch) > 0.1;
        if($moved){ $this->setPosition($at); }
        if($turned){ $this->setRotation($at->yaw, $at->pitch); }
        if(abs($this->getScale()-$owner->getScale()) > 0.0001){ $this->setScale($owner->getScale()); }
        if($selector !== $this->selector){
            $this->selector = $selector;
            $this->getNetworkProperties()->setInt(EntityMetadataProperties::VARIANT, $selector);
        }
        if($teamColor !== $this->teamColor){
            $this->teamColor = $teamColor;
            $this->getNetworkProperties()->setInt(EntityMetadataProperties::MARK_VARIANT, $teamColor);
        }
        if($owner->isSneaking() !== $this->crouched){
            $this->crouched = $owner->isSneaking();
            $this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SNEAKING, $this->crouched);
        }
        foreach($this->getViewers() as $viewer){
            if(!$this->mayView($viewer)){ $this->despawnFrom($viewer); }
        }
        if($visible){
            foreach($owner->getViewers() as $viewer){ $this->spawnTo($viewer); }
            $this->spawnTo($owner);
        }
        if($moved || $turned){
            $this->updateMovement($movedWorld || $old->distanceSquared($at) > 16);
        }
        $this->scheduleUpdate(); // flush only dirty metadata; no idle movement packets
    }
}
