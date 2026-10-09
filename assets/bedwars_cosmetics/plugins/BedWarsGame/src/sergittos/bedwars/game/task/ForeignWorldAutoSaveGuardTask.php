<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\task;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\World;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use function count;
use function str_starts_with;

/**
 * Defensive, non-invasive mitigation for worlds this plugin never loaded
 * itself but that end up loaded on the same server anyway (e.g. the
 * WaitingLobby-* world folders found alongside this plugin's own maps in
 * the timings analysis - 16+ of them, loaded simultaneously, none of them
 * referenced anywhere in BedWarsGame/BedWarsCore, so they're coming from
 * something else: a worlds.yml auto-load entry, another plugin, or a
 * leftover from a previous setup).
 *
 * This plugin has no way to know *why* those worlds are loaded or whether
 * they're still needed, so it deliberately does NOT unload or delete them -
 * that's a server-config decision for whoever manages worlds.yml /
 * pocketmine.yml. What it DOES do, safely, is the same thing already done
 * for every world this plugin genuinely owns (see Game::unloadWorld() and
 * the map-editing tasks): turn autosave off, so PocketMine's scheduled
 * autosave sweep can no longer walk into one of these and trigger a
 * multi-hundred-millisecond synchronous chunk save on the main thread
 * mid-tick (the WaitingLobby-12 spike that lined up with the worst frame
 * in the whole timings capture). Turning autosave off changes nothing
 * about how the world behaves to players in it; it only stops PocketMine
 * from periodically force-saving it in the background.
 *
 * Matching is prefix-based and configurable (see
 * "foreign-world-autosave-guard" in config.yml) specifically so it only
 * ever touches worlds that look like the reported leftovers, never this
 * plugin's own arena/map worlds (which already manage their own autosave
 * state) and never an operator's main lobby/hub world.
 */
final class ForeignWorldAutoSaveGuardTask extends Task{

    /** @var array<string,true> world names already handled, so this doesn't re-log every run */
    private array $handled = [];

    /**
     * @param string[] $prefixes world-name prefixes to treat as foreign/orphaned (e.g. "WaitingLobby-")
     */
    public function __construct(
        private array $prefixes
    ){}

    public function onRun(): void{
        if($this->prefixes === []){
            return;
        }

        $wm = Server::getInstance()->getWorldManager();
        $gameManager = BedWars::getInstance()->getGameManager();

        foreach($wm->getWorlds() as $world){
            $name = $world->getFolderName();

            if(isset($this->handled[$name])){
                continue;
            }

            if(!$this->matchesPrefix($name)){
                continue;
            }

            // Never touch a world this plugin is actively using as a match
            // arena - those already control their own autosave lifecycle
            // in Game::unloadWorld()/setupWorld(), and forcing it off here
            // too early (or logging noise about it) would just be
            // confusing. In practice a genuine "WaitingLobby-*" prefix
            // should never collide with a map's own world name, but this
            // check makes that guarantee explicit instead of assumed.
            if($gameManager->getGameByWorld($world) !== null){
                continue;
            }

            // Not every PocketMine-MP build exposes a getter for the
            // current autosave state (isAutoSaveEnabled() doesn't exist on
            // this server's API version - confirmed from the crash log).
            // setAutoSave(false) is idempotent and cheap either way, so
            // just call it unconditionally; $handled below still makes
            // sure this only happens (and only logs) once per world.
            $world->setAutoSave(false);
            BedWars::getInstance()->getLogger()->notice(
                "[ForeignWorldAutoSaveGuard] Disabled autosave on foreign/unmanaged world \"$name\" " .
                "(matched prefix, not owned by any active game) to stop it from causing main-thread " .
                "chunk-save stalls. This does not unload or delete the world - if it's not actually " .
                "needed, remove it from worlds.yml/auto-load so it stops being loaded at all."
            );

            $this->handled[$name] = true;
        }
    }

    private function matchesPrefix(string $name): bool{
        foreach($this->prefixes as $prefix){
            if($prefix !== "" && str_starts_with($name, $prefix)){
                return true;
            }
        }
        return false;
    }
}
