<?php

declare(strict_types=1);

namespace sergittos\bedwars\session;

use pocketmine\player\Player;
use sergittos\bedwars\BedWarsCore;

/**
 * Thin static-call compatibility shim over BedWarsCore's SessionManager, so
 * that all the game logic ported from the original single-server BedWars
 * plugin (which called SessionFactory::getSession($player) everywhere) keeps
 * working completely unchanged against the shared, multi-server Session.
 */
class SessionFactory {

    public static function getSession(Player $player): Session {
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if ($session === null) {
            // should never happen after CoreListener::onJoin has run, but
            // fall back to creating one rather than crashing
            $session = BedWarsCore::getInstance()->getSessionManager()->create($player);
        }
        return $session;
    }

    public static function hasSession(Player $player): bool {
        return BedWarsCore::getInstance()->getSessionManager()->get($player) !== null;
    }

    /**
     * @return Session[]
     */
    public static function getSessions(): array {
        return BedWarsCore::getInstance()->getSessionManager()->getAll();
    }

}
