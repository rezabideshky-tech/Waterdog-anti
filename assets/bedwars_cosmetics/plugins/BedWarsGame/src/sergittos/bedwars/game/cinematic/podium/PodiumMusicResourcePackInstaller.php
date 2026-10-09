<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cinematic\podium;

use pocketmine\plugin\Plugin;
use pocketmine\resourcepacks\ZippedResourcePack;
use ReflectionClass;

/**
 * Force-merges the bundled "PodiumMusic" resource pack (a single custom
 * "podium.theme" sound event, see resources/PodiumMusic.zip and
 * PodiumCeremony::MUSIC_SOUND_ID) into the server's resource pack list and
 * forces the client to accept it - same reflection-based merge approach as
 * HatResourcePackInstaller, so it stacks safely alongside the Hats pack
 * and any other force-applied resource packs instead of replacing them.
 *
 * If your server already ships its own combined resource pack, merge the
 * contents of resources/PodiumMusic.zip into it instead and skip calling
 * this - PodiumCeremony only cares that a sound event named "podium.theme"
 * exists somewhere in the client's active pack stack, not that it came
 * from this specific pack file.
 */
final class PodiumMusicResourcePackInstaller{

    private static bool $installed = false;

    public static function install(Plugin $plugin): void{
        if(self::$installed){
            return;
        }
        self::$installed = true;

        $plugin->saveResource("PodiumMusic.zip", true);

        $manager = $plugin->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($plugin->getDataFolder() . "PodiumMusic.zip");

        $reflection = new ReflectionClass($manager);

        $packs = $reflection->getProperty("resourcePacks");
        $packs->setAccessible(true);
        $current = $packs->getValue($manager);
        $current[] = $pack;
        $packs->setValue($manager, $current);

        $uuids = $reflection->getProperty("uuidList");
        $uuids->setAccessible(true);
        $currentUuids = $uuids->getValue($manager);
        $currentUuids[strtolower($pack->getPackId())] = $pack;
        $uuids->setValue($manager, $currentUuids);

        $forced = $reflection->getProperty("serverForceResources");
        $forced->setAccessible(true);
        $forced->setValue($manager, true);

        // Read the manager back through its own public API (not the
        // reflection we just used to write it) so a silent failure here -
        // e.g. these private property names changing on a future
        // PocketMine-MP update - shows up clearly in the log as "everyone
        // gets no podium music" instead of looking identical to the
        // separate, per-player timing issue PodiumCeremony's own retry
        // logic already covers.
        $installedPack = $manager->getPackById($pack->getPackId());
        if($installedPack === null){
            self::$installed = false;
            $plugin->getLogger()->warning(
                "Podium Ceremony music resource pack did not verify after install - the reflection-based merge likely no longer matches this PocketMine-MP build's ResourcePackManager internals. Ceremony will run without music until this is fixed."
            );
            return;
        }

        $plugin->getLogger()->info("Podium Ceremony music resource pack installed and verified (" . $pack->getPackId() . ").");
    }
}
