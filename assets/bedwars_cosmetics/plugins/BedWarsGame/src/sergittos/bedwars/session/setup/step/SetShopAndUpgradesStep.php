<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\setup\step;

use pocketmine\entity\Location;
use pocketmine\event\Cancellable;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use sergittos\bedwars\game\entity\shop\ItemShopVillager;
use sergittos\bedwars\game\entity\shop\UpgradesShopVillager;
use sergittos\bedwars\game\entity\shop\Villager;
use sergittos\bedwars\game\shop\Shop;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\item\setup\AddVillagerItem;
use sergittos\bedwars\item\setup\ClearVillagersItem;
use sergittos\bedwars\item\setup\RemoveVillagerItem;
use function atan2;
use function rad2deg;

final class SetShopAndUpgradesStep extends Step{

    protected function onStart() : void{
        $this->session->clearAllInventories();
        $this->session->message("Villager editor enabled.");

        $inv = $this->session->getPlayer()->getInventory();
        $inv->setItem(0, BedwarsItems::ITEM_VILLAGER()->asItem());
        $inv->setItem(1, BedwarsItems::UPGRADES_VILLAGER()->asItem());
        $inv->setItem(2, BedwarsItems::REMOVE_VILLAGER()->asItem());
        $inv->setItem(3, BedwarsItems::REMOVE_ALL_VILLAGERS()->asItem());
        $inv->setItem(8, BedwarsItems::CANCEL()->asItem());
    }

    public function onInteract(BedwarsItem $item): void{
        if(!$item instanceof ClearVillagersItem){
            return;
        }

        $setup = $this->session->getMapSetup();
        if($setup === null){
            return;
        }

        $builder = $setup->getMapBuilder();
        $builder->clearShopPositions();
        $builder->clearUpgradesPositions();

        $world = $this->session->getPlayer()->getWorld();
        foreach($world->getEntities() as $e){
            if($e instanceof Villager){
                $e->close();
            }
        }

        $this->session->message("All villagers removed.");
    }

    public function onBlockInteract(Vector3 $touch_vector, int $action, Cancellable $event, BedwarsItem $item) : void{
        if($action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
            return;
        }

        $setup = $this->session->getMapSetup();
        if($setup === null){
            return;
        }

        $event->cancel();

        $world = $this->session->getPlayer()->getWorld();
        $spawn = new Vector3($touch_vector->getFloorX() + 0.5, $touch_vector->getFloorY() + 1.0, $touch_vector->getFloorZ() + 0.5);

        if($item instanceof RemoveVillagerItem){
            $removed = $this->removeNearestAnyVillager($setup->getMapBuilder(), $world, $spawn);
            $this->session->message($removed ? "Villager removed." : "No villager found.");
            return;
        }

        if(!$item instanceof AddVillagerItem){
            return;
        }

        if($this->session->getPlayer()->isSneaking()){
            $removed = false;

            if($item->getName() === Shop::ITEM){
                $removed = $setup->getMapBuilder()->removeNearestShopPosition($spawn);
                $this->despawnNearestVillager($world, ItemShopVillager::class, $spawn);
            }elseif($item->getName() === Shop::UPGRADES){
                $removed = $setup->getMapBuilder()->removeNearestUpgradesPosition($spawn);
                $this->despawnNearestVillager($world, UpgradesShopVillager::class, $spawn);
            }

            $this->session->message($removed ? "Villager removed." : "No villager found.");
            return;
        }

        $yaw = $this->yawToPlayer($spawn);

        if($item->getName() === Shop::ITEM){
            $setup->getMapBuilder()->addShopPosition($spawn, $yaw);
            $villager = new ItemShopVillager(new Location($spawn->x, $spawn->y, $spawn->z, $world, $yaw, 0.0));
            $this->spawnVillagerNow($world, $villager);
            $this->session->message("Item shop villager placed.");
            return;
        }

        if($item->getName() === Shop::UPGRADES){
            $setup->getMapBuilder()->addUpgradesPosition($spawn, $yaw);
            $villager = new UpgradesShopVillager(new Location($spawn->x, $spawn->y, $spawn->z, $world, $yaw, 0.0));
            $this->spawnVillagerNow($world, $villager);
            $this->session->message("Upgrades villager placed.");
            return;
        }
    }

    private function yawToPlayer(Vector3 $villagerPos): float{
        $p = $this->session->getPlayer()->getPosition();
        $dx = $p->x - $villagerPos->x;
        $dz = $p->z - $villagerPos->z;
        return rad2deg(atan2(-$dx, $dz));
    }

    private function removeNearestAnyVillager(\sergittos\bedwars\session\setup\builder\MapBuilder $builder, \pocketmine\world\World $world, Vector3 $near): bool{
        $best = null;
        $bestD = 3.5;

        foreach($world->getEntities() as $e){
            if(!$e instanceof Villager){
                continue;
            }
            $d = $e->getPosition()->distance($near);
            if($d <= $bestD){
                $bestD = $d;
                $best = $e;
            }
        }

        if($best === null){
            return false;
        }

        if($best instanceof ItemShopVillager){
            $builder->removeNearestShopPosition($near);
        }elseif($best instanceof UpgradesShopVillager){
            $builder->removeNearestUpgradesPosition($near);
        }

        $best->close();
        return true;
    }

    private function spawnVillagerNow($world, Villager $villager) : void{
        $p = $villager->getPosition()->floor();
        $world->requestChunkPopulation($p->getX() >> Chunk::COORD_BIT_SIZE, $p->getZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
            fn() => $villager->spawnToAll(),
            fn() => null
        );
    }

    private function despawnNearestVillager($world, string $class, Vector3 $near) : void{
        $best = null;
        $bestD = 3.5;

        foreach($world->getEntities() as $e){
            if(!$e instanceof $class){
                continue;
            }
            $d = $e->getPosition()->distance($near);
            if($d <= $bestD){
                $bestD = $d;
                $best = $e;
            }
        }

        if($best !== null){
            $best->close();
        }
    }
}