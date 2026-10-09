<?php
declare(strict_types=1);
namespace sergittos\bedwars\cosmetics\wearable;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;

final class ResourceCosmeticService{
    private static bool $ready = false;
    /** @var array<int, ResourceCosmeticActor> */
    private static array $actors = [];
    /** @var array<int, Player> */
    private static array $owners = [];
    private static array $warned = [];

    public static function isReady(): bool{ return self::$ready; }

    public static function initialize(Plugin $plugin): void{
        if(self::$ready){ return; }
        $customies = $plugin->getServer()->getPluginManager()->getPlugin("Customies");
        if($customies === null || !$customies->isEnabled() || !class_exists(CustomiesEntityFactory::class)){
            $plugin->getLogger()->warning("Cosmetics V2 disabled: enable a compatible Customies plugin. Wearable purchases are blocked until ready.");
            return;
        }
        try{
            $manager = $plugin->getServer()->getResourcePackManager();
            $uuid = ResourceCosmeticCatalog::uuid();
            $loaded = $manager->getPackById($uuid);
            if($loaded !== null && $loaded->getPackVersion() !== "2.0.0"){
                throw new \RuntimeException("Loaded cosmetics pack must be version 2.0.0");
            }
            if($loaded === null){
                $path = rtrim($manager->getPath(), "/\\") . "/ArvanCosmeticsV2.zip";
                if(!is_file($path)){ throw new \RuntimeException("Copy ArvanCosmeticsV2.zip to resource_packs/ (not plugin_data)"); }
                $pack = new ZippedResourcePack($path);
                if(strtolower($pack->getPackId()) !== strtolower($uuid) || $pack->getPackVersion() !== "2.0.0"){
                    throw new \RuntimeException("Resource pack UUID differs from bundled catalog; install matching V2 pack");
                }
                $stack = $manager->getResourceStack();
                $stack[] = $pack;
                $manager->setResourceStack($stack); // preserve all existing UI/maps/sounds packs
            }
            $manager->setResourcePacksRequired(true);
            CustomiesEntityFactory::getInstance()->registerEntity(ResourceCosmeticActor::class, ResourceCosmeticActor::getNetworkTypeId());
            $plugin->getServer()->getPluginManager()->registerEvents(new ResourceCosmeticListener(), $plugin);
            $pulse = 0;
            $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(static function() use ($plugin, &$pulse): void{
                if(!self::$ready){ return; }
                foreach(self::$owners as $owner){ self::apply($owner); }
                // Late async session loading, respawns, and world changes recover automatically.
                if(++$pulse % 10 === 0){
                    foreach($plugin->getServer()->getOnlinePlayers() as $player){
                        if(!isset(self::$owners[$player->getId()])){ self::apply($player); }
                    }
                }
            }), 2);
            self::$ready = true;
            $plugin->getLogger()->info("Cosmetics V2 ready: 15 hats / 22 backblings / 15 capes; skin and real armor are never modified.");
        }catch(\Throwable $error){
            $plugin->getLogger()->error("Cosmetics V2 disabled safely: " . $error->getMessage());
        }
    }

    public static function apply(Player $player): void{
        if(!self::$ready){ return; }
        if(!$player->isConnected()){ self::forget($player); return; }
        $core = BedWarsCore::getInstance();
        $session = $core->getSessionManager()->get($player);
        if($session === null || !$session->isLoaded()){ self::forget($player); return; }
        try{
            $manager = PlayerCosmeticsManager::getInstance();
            $hat = ResourceCosmeticCatalog::selector(CosmeticCategory::HAT, $manager->getEquipped($player, CosmeticCategory::HAT));
            $back = ResourceCosmeticCatalog::selector(CosmeticCategory::WING, $manager->getEquipped($player, CosmeticCategory::WING));
            $cape = ResourceCosmeticCatalog::selector(CosmeticCategory::CAPE, $manager->getEquipped($player, CosmeticCategory::CAPE));
            $selector = ResourceCosmeticCatalog::encode($hat, $back, $cape);
            if($selector === 0){ self::forget($player); return; }
            $id = $player->getId();
            $visible = $player->isAlive() && !$player->isInvisible() && !$player->isSpectator() && !$session->isSpectator();
            // Do not display a standing overlay on horizontal/unsupported player poses.
            if((method_exists($player, "isSwimming") && $player->isSwimming())
                || (method_exists($player, "isGliding") && $player->isGliding())
                || (method_exists($player, "isSleeping") && $player->isSleeping())){
                $visible = false;
            }
            if(!isset(self::$actors[$id]) || self::$actors[$id]->isClosed()){
                if(!$visible){ return; }
                $at = $player->getLocation();
                $actor = new ResourceCosmeticActor(new Location($at->x, $at->y, $at->z, $at->getWorld(), $at->yaw, $at->pitch));
                $actor->bind($player);
                self::$actors[$id] = $actor;
                self::$owners[$id] = $player;
            }
            $color = 0;
            if(($team = $session->getTeam()) !== null && method_exists($team, "getDyeColor")){
                $colors = ["Red","Blue","Green","Yellow","Light Blue","White","Pink","Gray","Purple","Lime","Black","Orange","Brown","Cyan","Light Gray","Magenta"];
                $index = array_search($team->getDyeColor()->getDisplayName(), $colors, true);
                $color = $index === false ? 0 : $index;
            }
            self::$actors[$id]->synchronize($selector, $visible, $color);
        }catch(\Throwable $error){
            $id = $player->getId();
            if(!isset(self::$warned[$id])){
                self::$warned[$id] = true;
                $core->getLogger()->warning("Wearable overlay skipped for " . $player->getName() . ": " . $error->getMessage());
            }
            self::forget($player, false);
        }
    }

    public static function forget(Player $player, bool $clearWarning = true): void{
        $id = $player->getId();
        if(isset(self::$actors[$id]) && !self::$actors[$id]->isClosed()){ self::$actors[$id]->close(); }
        unset(self::$actors[$id], self::$owners[$id]);
        if($clearWarning){ unset(self::$warned[$id]); }
    }
    public static function shutdown(): void{
        self::$ready = false;
        foreach(self::$actors as $actor){ if(!$actor->isClosed()){ $actor->close(); } }
        self::$actors = self::$owners = self::$warned = [];
    }
}
