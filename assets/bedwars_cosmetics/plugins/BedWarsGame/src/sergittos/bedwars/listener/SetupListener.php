<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\entity\Location;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use sergittos\bedwars\game\entity\shop\ItemShopVillager;
use sergittos\bedwars\game\entity\shop\UpgradesShopVillager;
use sergittos\bedwars\game\entity\shop\Villager;
use sergittos\bedwars\item\BedwarsItem;
use sergittos\bedwars\item\BedwarsItems;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use function abs;
use function atan2;
use function rad2deg;
use function strtolower;

final class SetupListener implements Listener{

    /** @var array<string,int> */
    private array $lastEditVillagerTick = [];

    /**
     * Root cause of the "duplicate world copy" bug (see git history /
     * previous fix attempts on this listener): onItemUse() (PlayerItemUseEvent)
     * and onInteract() (PlayerInteractEvent) can BOTH fire for a single right
     * click, since the client's interaction packet can be processed on both
     * paths before either handler runs. Older revisions of this file had both
     * handlers unconditionally call $bwItem->onInteract($session), so one
     * click on "Create map" or "Save edits" could submit two
     * CreateMapTask/UpdateMapTask AsyncTasks, each doing a full
     * Filesystem::recursiveCopy of the world - doubling map creation/edit
     * saves and leaving duplicate/redundant folders behind in the worlds
     * directory. A same-tick guard was added as a band-aid, but that only
     * closes the window when both events land in the same tick; it does
     * nothing if the two packets get processed a tick or more apart, so the
     * duplicate copy could still happen.
     *
     * The actual, structural fix (matching how the legacy plugin - see
     * bedwarsold's SetupListener + its separate global ItemListener::onUse()
     * - avoids this entirely) is architectural, not timing-based: a
     * BedwarsItem's generic onInteract() action (which is what "Create map"
     * and "Save edits" implement) must be triggered from exactly ONE event
     * type. The legacy plugin only ever calls BedwarsItem::onInteract() from
     * PlayerItemUseEvent; PlayerInteractEvent is routed to a *different*
     * method (Step::onBlockInteract(), for block-targeted setup actions like
     * placing a bed or shop position) and never touches the item's generic
     * onInteract(). We mirror that here: onInteract() below now only calls
     * Step::onBlockInteract() and no longer dispatches the item's
     * onInteract() at all, so there is only one code path
     * (onItemUse() -> dispatchItemInteract()) that can ever submit a
     * CreateMapTask/UpdateMapTask, and the two event types can no longer
     * race each other regardless of tick timing.
     *
     * $lastItemInteractTick is kept as a harmless secondary safety net (in
     * case PocketMine itself ever redelivers a PlayerItemUseEvent for the
     * same physical click), and $pendingSave guards against a *different*
     * click - the player pressing the item again while a previous
     * CreateMapTask/UpdateMapTask from an earlier click is still running on
     * the async pool (world copies are not instant), which neither the old
     * nor the tick-based guard protected against.
     * @var array<string,int>
     */
    private array $lastItemInteractTick = [];

    /**
     * Player name (lowercase) => true while a CreateMapTask/UpdateMapTask
     * submitted for that player has not yet reached onCompletion(). Prevents
     * a second "Create map"/"Save edits" click from submitting another full
     * world copy while the first one is still in flight.
     * @var array<string,bool>
     */
    private static array $pendingSave = [];

    public static function beginSave(string $playerName): bool{
        $key = strtolower($playerName);
        if(self::$pendingSave[$key] ?? false){
            return false;
        }
        self::$pendingSave[$key] = true;
        return true;
    }

    public static function endSave(string $playerName): void{
        unset(self::$pendingSave[strtolower($playerName)]);
    }

    public function onBreak(BlockBreakEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $item = $event->getItem();

        if(!$this->isCreatingMap($session, $item)){
            return;
        }

        $event->cancel();

        $bwItem = $this->getBedwarsItem($item);
        if($bwItem === null){
            return;
        }

        $session->getMapSetup()?->getStep()->onBlockBreak($event->getBlock(), $bwItem);
    }

    public function onItemUse(PlayerItemUseEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $item = $event->getItem();

        if(!$this->isCreatingMap($session, $item)){
            return;
        }

        $event->cancel();

        $bwItem = $this->getBedwarsItem($item);
        if($bwItem === null){
            return;
        }

        $session->getMapSetup()?->getStep()->onInteract($bwItem);
        $this->dispatchItemInteract($session, $bwItem);
    }

    public function onInteract(PlayerInteractEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $item = $event->getItem();

        if(!$this->isCreatingMap($session, $item)){
            return;
        }

        $event->cancel();

        $bwItem = $this->getBedwarsItem($item);
        if($bwItem === null){
            return;
        }

        // Block-targeted setup actions (placing a shop/upgrades villager,
        // setting a bed position, claiming an area corner, ...) are routed
        // to the active Step here, and ONLY here.
        //
        // Intentionally does NOT call dispatchItemInteract() here. Routing a
        // block-targeted PlayerInteractEvent to a setup item's generic
        // onInteract() is exactly what caused the duplicate CreateMapTask/
        // UpdateMapTask bug - see the doc comment on $lastItemInteractTick
        // above. Block-targeted setup actions belong in
        // Step::onBlockInteract() only, same as the legacy plugin.
        $session->getMapSetup()?->getStep()->onBlockInteract($event->getBlock()->getPosition(), $event->getAction(), $event, $bwItem);
    }

    /**
     * Single entry point for actually running a setup item's generic action
     * (e.g. "Create map", "Save edits"). Only onItemUse() calls this - see
     * the doc comment on $lastItemInteractTick for why onInteract()
     * deliberately does not.
     */
    private function dispatchItemInteract(Session $session, BedwarsItem $bwItem): void{
        $key = strtolower($session->getPlayer()->getName());
        $tick = Server::getInstance()->getTick();

        if(($this->lastItemInteractTick[$key] ?? -1) === $tick){
            return;
        }
        $this->lastItemInteractTick[$key] = $tick;

        $bwItem->onInteract($session);
    }

    public function onMove(PlayerMoveEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $setup = $session->getMapSetup();
        if($setup === null){
            return;
        }

        if(!$setup->isEditing()){
            return;
        }

        $builder = $setup->getMapBuilder();
        $world = $player->getWorld();
        if($world->getFolderName() !== $builder->getPlayingWorld()){
            return;
        }

        $key = strtolower($player->getName());
        $tick = Server::getInstance()->getTick();
        $last = $this->lastEditVillagerTick[$key] ?? -99999;
        if(($tick - $last) < 10){
            return;
        }
        $this->lastEditVillagerTick[$key] = $tick;

        $center = $player->getPosition()->asVector3();
        $range = 80.0;
        $r2 = $range * $range;

        foreach($builder->getShopPositions() as $pos){
            if($pos->distanceSquared($center) > $r2){
                continue;
            }
            if(!$this->hasVillagerNear($world, $pos, ItemShopVillager::class)){
                $yaw = $builder->getShopYaw($pos);
                $this->spawnEditVillager($world, new ItemShopVillager(new Location($pos->x, $pos->y, $pos->z, $world, $yaw, 0.0)));
            }
        }

        foreach($builder->getUpgradesPositions() as $pos){
            if($pos->distanceSquared($center) > $r2){
                continue;
            }
            if(!$this->hasVillagerNear($world, $pos, UpgradesShopVillager::class)){
                $yaw = $builder->getUpgradesYaw($pos);
                $this->spawnEditVillager($world, new UpgradesShopVillager(new Location($pos->x, $pos->y, $pos->z, $world, $yaw, 0.0)));
            }
        }
    }

    private function spawnEditVillager(World $world, Villager $villager): void{
        $p = $villager->getPosition()->floor();
        $world->requestChunkPopulation($p->getX() >> Chunk::COORD_BIT_SIZE, $p->getZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
            fn() => $villager->spawnToAll(),
            fn() => null
        );
    }

    private function hasVillagerNear(World $world, Vector3 $pos, string $class): bool{
        $bb = new AxisAlignedBB($pos->x - 1.2, $pos->y - 2.0, $pos->z - 1.2, $pos->x + 1.2, $pos->y + 2.0, $pos->z + 1.2);
        foreach($world->getNearbyEntities($bb) as $e){
            if($e instanceof $class && !$e->isClosed() && $e->isAlive() && !$e->isFlaggedForDespawn()){
                return true;
            }
        }
        return false;
    }

    private function getBedwarsItem(Item $item): ?BedwarsItem{
        $key = $item->getNamedTag()->getString("bedwars_name", "");
        if($key === ""){
            return null;
        }

        try{
            return BedwarsItems::get(strtolower($key));
        }catch(\Throwable){
            return null;
        }
    }

    private function isCreatingMap(Session $session, Item $item): bool{
        return $session->isCreatingMap() && $item->getNamedTag()->getTag("setup") !== null;
    }
}