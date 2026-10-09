<?php

declare(strict_types=1);

namespace sergittos\bedwars\rank;

use IvanCraft623\RankSystem\RankSystem;
use pocketmine\player\Player;
use pocketmine\Server;
use function class_exists;
use function is_string;

/**
 * Read-only bridge from BedWars into RankSystem.
 *
 * This is intentionally the ONLY place in BedWarsCore that ever touches
 * RankSystem classes directly. Everything here is defensive on purpose:
 *  - RankSystem is a softdepend, not a hard dependency. If it's missing,
 *    disabled, or ever throws, every method below just returns null and
 *    the caller (Session::getRank()/getRankPrefixDisplay()/etc.) falls
 *    back to BedWars' original PurePerms/permission-based logic. BedWars
 *    must never go down, or show a broken nametag/chat line, because of
 *    something happening on the RankSystem side.
 *  - This class never WRITES anything to RankSystem (no rank changes,
 *    no permission changes) - it only reads whatever RankSystem already
 *    has for the player, so RankSystem's own database/commands/forms
 *    remain the single source of truth for ranks.
 *  - This class never touches the player's nametag, chat formatter, or
 *    tab list by itself. BedWars' Session/GameChatListener/CoreListener
 *    stay fully in control of *how* things are displayed (team tags,
 *    spectator tags, level badges, message layout); RankSystem only
 *    supplies the raw prefix/color *values* for the rank portion.
 *  - These methods run on EVERY server, whether or not RankSystem is
 *    installed at all (BedWars' Session calls them unconditionally).
 *    To make sure that's safe even when RankSystem's classes don't
 *    exist, none of the internal helpers below declare a RankSystem
 *    class as a parameter/return type - PHP would try to resolve that
 *    type the moment the method runs, regardless of what value it
 *    actually returns. `RankSystem::class` as a bare string literal is
 *    fine everywhere (it never triggers autoloading), and the guarded
 *    RankSystemRefreshListener - not this class - is the only place
 *    that ever declares a real RankSystem type, because that listener
 *    is only ever instantiated after isAvailable() is confirmed true.
 */
final class RankSystemBridge {

    private static ?bool $classesPresent = null;

    private function __construct() {
        // static-only utility class
    }

    /**
     * Whether the RankSystem plugin is installed, loaded and enabled on
     * this server. Cheap to call often - the class_exists() check is
     * cached, only the live plugin-enabled check runs every time (and
     * that's just an array lookup on the PluginManager).
     */
    public static function isAvailable(): bool {
        if (self::$classesPresent === null) {
            self::$classesPresent = class_exists(RankSystem::class, false);
        }
        if (!self::$classesPresent) {
            return false;
        }

        $plugin = Server::getInstance()->getPluginManager()->getPlugin("RankSystem");
        return $plugin !== null && $plugin->isEnabled();
    }

    /**
     * Fetches the player's RankSystem session, but only if it has
     * already finished loading from the database. RankSystem sessions
     * load asynchronously, so right at PlayerJoinEvent time this can
     * briefly return null - callers must be fine with that (they all
     * fall back to BedWars' own logic when this returns null).
     *
     * @return object|null a IvanCraft623\RankSystem\session\Session,
     *         kept untyped here on purpose - see the class docblock.
     */
    private static function getSession(string $username): ?object {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $session = RankSystem::getInstance()->getSessionManager()->get($username);
            if (!$session->isInitialized()) {
                return null;
            }
            return $session;
        } catch (\Throwable $e) {
            // Never let a RankSystem-side error break BedWars.
            return null;
        }
    }

    /**
     * @return object|null a IvanCraft623\RankSystem\rank\Rank, kept
     *         untyped here on purpose - see the class docblock.
     */
    private static function getHighestRank(string $username): ?object {
        $session = self::getSession($username);
        if ($session === null) {
            return null;
        }

        try {
            return $session->getHighestRank();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The name of the player's highest-priority RankSystem rank
     * (e.g. "Owner", "VIP+", "Player"), or null if unavailable.
     */
    public static function getRankName(string $username): ?string {
        return self::getHighestRank($username)?->getName();
    }

    /**
     * The nametag prefix configured on the player's highest rank in
     * RankSystem (e.g. "§6[VIP] "), or null if unavailable.
     */
    public static function getNametagPrefix(string $username): ?string {
        $rank = self::getHighestRank($username);
        if ($rank === null) {
            return null;
        }
        $value = $rank->getNameTagFormat()['prefix'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * The name color configured on the player's highest rank's nametag
     * format in RankSystem (e.g. "§6"), or null if unavailable.
     */
    public static function getNametagColor(string $username): ?string {
        $rank = self::getHighestRank($username);
        if ($rank === null) {
            return null;
        }
        $value = $rank->getNameTagFormat()['nameColor'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * The prefix configured on the player's highest rank's chat format
     * in RankSystem, or null if unavailable.
     */
    public static function getChatPrefix(string $username): ?string {
        $rank = self::getHighestRank($username);
        if ($rank === null) {
            return null;
        }
        $value = $rank->getChatFormat()['prefix'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * The name color configured on the player's highest rank's chat
     * format in RankSystem, or null if unavailable.
     */
    public static function getChatNameColor(string $username): ?string {
        $rank = self::getHighestRank($username);
        if ($rank === null) {
            return null;
        }
        $value = $rank->getChatFormat()['nameColor'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Convenience: same lookup, but takes a Player instead of a name.
     */
    public static function getRankNameFor(Player $player): ?string {
        return self::getRankName($player->getName());
    }
}
