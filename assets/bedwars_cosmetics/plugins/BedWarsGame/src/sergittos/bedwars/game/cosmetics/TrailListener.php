<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cosmetics;

use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\session\SessionFactory;

final class TrailListener implements Listener{

    public function onLaunch(ProjectileLaunchEvent $event): void{
        $entity = $event->getEntity();
        if(!$entity instanceof Projectile){
            return;
        }

        $owner = $entity->getOwningEntity();
        if(!$owner instanceof Player){
            return;
        }

        if(!SessionFactory::hasSession($owner)){
            return;
        }

        $session = SessionFactory::getSession($owner);
        if(!$session->isPlaying()){
            return;
        }

        $key = PlayerCosmeticsManager::getInstance()->getEquipped($owner, CosmeticCategory::PROJECTILE_TRAIL);
        if($key === null){
            return;
        }

        TrailService::getInstance()->track($entity, $key);
    }
}