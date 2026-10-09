<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\pets;

use pocketmine\block\Block;
use pocketmine\block\Carpet;
use pocketmine\block\Flowable;
use pocketmine\block\Liquid;
use pocketmine\block\TallGrass;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

/**
 * Ported from the Customise plugin's PetBase - a walking pet that follows
 * its owner around. Lobby-only: never registered or spawned on the game
 * server.
 */
abstract class PetBase extends Entity{

    protected ?Player $owner = null;
    protected bool $systemSpawned = false;

    public function setSystemSpawned(bool $value): void{
        $this->systemSpawned = $value;
    }

    public function isSystemSpawned(): bool{
        return $this->systemSpawned;
    }

    protected function initEntity(CompoundTag $nbt): void{
        $this->setGravity(0.1);
        parent::initEntity($nbt);
    }

    public function setOwner(Player $player): void{
        $this->owner = $player;
    }

    public function getOwner(): ?Player{
        return $this->owner;
    }

    public function attack(EntityDamageEvent $source): void{
        $source->cancel();
    }

    protected function entityBaseTick(int $tickDiff = 1): bool{
        if($this->owner === null && !$this->isSystemSpawned()){
            $this->flagForDespawn();
            return false;
        }
        if($this->owner !== null && !$this->owner->isConnected()){
            $this->flagForDespawn();
            return false;
        }

        $hasUpdate = parent::entityBaseTick($tickDiff);
        $this->followOwner();

        return $hasUpdate;
    }

    protected function followOwner(): void{
        $owner = $this->getOwner();
        if($owner === null){
            return;
        }

        if($this->getPosition()->distance($owner->getPosition()) <= 2){
            return;
        }
        if($this->getPosition()->distance($owner->getPosition()) > 10){
            $this->teleport($owner->getLocation());
            return;
        }

        if($this->shouldJump()){
            $this->jump();
        }

        $x = $owner->getLocation()->x - $this->getLocation()->x;
        $y = $owner->getLocation()->y - $this->getLocation()->y;
        $z = $owner->getLocation()->z - $this->getLocation()->z;

        if($x * $x + $z * $z < mt_rand(3, 8)){
            $this->motion->x = 0;
            $this->motion->z = 0;
        }else{
            $len = abs($x) + abs($z);
            if($len > 0){
                $this->motion->x = 0.17 * ($x / $len);
                $this->motion->z = 0.17 * ($z / $len);
            }
        }

        $this->getLocation()->yaw = rad2deg(atan2(-$x, $z));
        $this->getLocation()->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));

        $this->move($this->motion->x, $this->motion->y, $this->motion->z);
        $this->updateMovement();
    }

    protected function jump(): void{
        $this->setMotion(new Vector3($this->motion->x, 0.5, $this->motion->z));
    }

    public function shouldJump(): bool{
        if($this->getBlockInFront()->getName() !== VanillaBlocks::AIR()->getName()){
            return $this->getBlockInFront(1)->getName() === VanillaBlocks::AIR()->getName();
        }
        if($this->getBlockInFront(-0.9) instanceof Carpet){
            return !($this->getWorld()->getBlock($this->getPosition()->add(0, -0.9, 0)) instanceof Carpet);
        }
        if($this->getBlockInFront() instanceof Flowable){
            return false;
        }
        if($this->getBlockInFront() instanceof TallGrass){
            return false;
        }
        if($this->getBlockInFront() instanceof Liquid){
            return true;
        }
        return false;
    }

    public function getBlockInFront(float $y = 0): Block{
        $pos = $this->getPosition()->add($this->getDirectionVector()->x * $this->getScale(), $y, $this->getDirectionVector()->z * $this->getScale())->round();
        return $this->getWorld()->getBlock($pos);
    }
}
