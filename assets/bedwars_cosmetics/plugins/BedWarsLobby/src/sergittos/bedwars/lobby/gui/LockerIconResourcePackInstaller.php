<?php
declare(strict_types=1);
namespace sergittos\bedwars\lobby\gui;
use pocketmine\plugin\Plugin;
use pocketmine\resourcepacks\ZippedResourcePack;
/** Optional legacy UI pack: retain existing art; missing archive must not stop BedWars. */
final class LockerIconResourcePackInstaller{
    private static bool $installed = false;
    public static function install(Plugin $plugin): void{
        if(self::$installed){ return; }
        $stream = $plugin->getResource("LockerIcons.zip");
        if($stream !== null){ fclose($stream); $plugin->saveResource("LockerIcons.zip", false); }
        $path = $plugin->getDataFolder() . "LockerIcons.zip";
        if(!is_file($path)){
            $plugin->getLogger()->warning("Optional LockerIcons.zip was not supplied. Existing resource stack is unchanged; retain your original UI pack.");
            return;
        }
        try{
            $pack = new ZippedResourcePack($path);
            $manager = $plugin->getServer()->getResourcePackManager();
            if($manager->getPackById($pack->getPackId()) === null){
                $stack = $manager->getResourceStack(); $stack[] = $pack;
                $manager->setResourceStack($stack);
            }
            $manager->setResourcePacksRequired(true);
            self::$installed = true;
        }catch(\Throwable $e){ $plugin->getLogger()->warning("Optional UI pack could not be loaded: " . $e->getMessage()); }
    }
}
