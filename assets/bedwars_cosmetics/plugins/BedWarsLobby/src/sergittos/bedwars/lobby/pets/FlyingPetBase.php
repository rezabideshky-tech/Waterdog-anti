<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\pets;

use pocketmine\math\Vector3;

/**
 * Ported from the Customise plugin's FlyingPetBase - hovers near the owner
 * instead of walking on the ground.
 */
abstract class FlyingPetBase extends PetBase{

    protected float $followSpeed = 0.62;
    protected float $hoverHeight = 0.69;

    protected function followOwner(): void{
        $owner = $this->owner;
        if($owner === null || !$owner->isOnline()){
            return;
        }
        if(!$this->isAlive() || $this->location->getWorld() === null || $owner->getWorld() === null){
            return;
        }
        if($this->location->getWorld()->getId() !== $owner->getWorld()->getId()){
            $this->teleport($owner->getLocation());
            $this->setMotion(new Vector3(0, 0, 0));
            return;
        }

        $ownerLoc = $owner->getLocation();
        $currentLoc = $this->location;
        $distance = $currentLoc->distance($ownerLoc);

        if($distance > 14){
            $this->teleport($ownerLoc);
            return;
        }

        $dirVec = $ownerLoc->asVector3()->subtractVector($currentLoc->asVector3());

        if($distance < 2.1){
            $this->setMotion(new Vector3(0, 0, 0));
            return;
        }

        $speed = min($this->followSpeed, $distance / 10);

        $targetY = $ownerLoc->getY() + $this->hoverHeight;
        $deltaY = $targetY - $currentLoc->getY();
        $motionY = $deltaY * 0.15;

        $dirVec = $dirVec->normalize()->multiply($speed);

        $this->setMotion(new Vector3($dirVec->getX(), $motionY, $dirVec->getZ()));
        $this->location->yaw = rad2deg(atan2(-$dirVec->getX(), $dirVec->getZ()));
    }
}
