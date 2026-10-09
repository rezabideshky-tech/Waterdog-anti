<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\shop\item\editor;


use pocketmine\player\Player;
use sergittos\bedwars\BedWarsCore;

/**
 * Static per-UUID registry of ShopEditorSession instances, one per
 * connected player. The registry itself is kept isolated from
 * SessionFactory/Session on purpose (it's keyed by connection, not by the
 * persistent player record) - but the layout each ShopEditorSession holds
 * IS backed by the database (bw_shop_layout, via
 * BedWarsCore::getProvider()->getShopLayout()/setShopLayout()), kept
 * entirely separate from stats/cosmetics so a bug here can never corrupt
 * those. load() fetches it once on join; persist() is called after every
 * edit (see ShopGui) and once more on quit as a safety net. Cleared from
 * memory on quit by ShopEditorCleanupListener so the array cannot grow
 * unbounded across a long server uptime - by then the layout has already
 * been flushed to the database and will be reloaded on their next join,
 * on this server or any other game server on the network.
 */
final class ShopEditorManager {

    /** @var array<string, ShopEditorSession> */
    private static array $sessions = [];

    public static function get(Player $player): ShopEditorSession {
        $uuid = $player->getUniqueId()->toString();
        return self::$sessions[$uuid] ??= new ShopEditorSession();
    }

    public static function remove(Player $player): void {
        unset(self::$sessions[$player->getUniqueId()->toString()]);
    }

    /**
     * Kicks off the async fetch of this player's saved Main-tab layout and
     * applies it once it lands. Safe to call as soon as the player joins -
     * get() above already hands back a usable (empty-until-loaded)
     * ShopEditorSession in the meantime, so opening the shop before this
     * completes just shows the default layout rather than blocking or
     * erroring.
     */
    public static function load(Player $player): void {
        $username = $player->getName();

        BedWarsCore::getInstance()->getProvider()->getShopLayout($username, function(array $overrides) use ($player): void {
            // Player may have disconnected before this async lookup landed -
            // re-fetch the session by current player object rather than
            // trusting a captured reference, so a stale result can never
            // clobber a session created after this call was fired off.
            if(!$player->isConnected()){
                return;
            }

            self::get($player)->loadOverrides($overrides);
        });
    }

    /**
     * Flushes this player's current in-memory layout to the database.
     * Called after every edit (add/remove/reset) so changes are never lost
     * to an unclean disconnect, and once more on quit as a safety net.
     * A no-op until the initial load() above has actually landed, so a save
     * triggered in that narrow window can never overwrite a player's real
     * saved layout with an empty one.
     */
    public static function persist(Player $player): void {
        $editorSession = self::get($player);
        if(!$editorSession->hasLoaded()){
            return;
        }

        BedWarsCore::getInstance()->getProvider()->setShopLayout($player->getName(), $editorSession->getOverrides());
    }

}
