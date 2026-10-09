<?php

declare(strict_types=1);

namespace sergittos\bedwars\game;

use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\session\SessionFactory;

/**
 * matchmaking کاملاً خودکار و رندوم - هیچ‌جای بازی نباید از بازیکن نقشه
 * بخواد. همیشه از این کلاس استفاده کنید (چه موقع Join، چه موقع Play Again).
 */
class Matchmaker {

    private const MAX_RETRIES = 6;   // 6 * 10 ticks = 3 ثانیه تلاش برای پیدا کردن/ساختن بازی
    private const RETRY_DELAY = 10;  // نیم ثانیه بین هر تلاش

    public static function queue(Player $player, int $attempt = 0): void {
        if (!$player->isConnected() || !SessionFactory::hasSession($player)) {
            return;
        }

        $session = SessionFactory::getSession($player);
        $playersPerTeam = self::getThisServerPlayersPerTeam();

        $game = BedWarsGame::getInstance()->getGameManager()->findRandomGame($playersPerTeam);
        if ($game !== null) {
            $game->addPlayer($session);
            return;
        }

        if ($attempt < self::MAX_RETRIES) {
            $player->sendActionBarMessage(TF::YELLOW . "Preparing a match for you, please wait...");
            BedWarsGame::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player, $attempt): void {
                    self::queue($player, $attempt + 1);
                }),
                self::RETRY_DELAY
            );
            return;
        }

        self::overflow($player);
    }

    private static function overflow(Player $player): void {
        $network = BedWarsCore::getInstance()->getNetworkManager();
        $type = $network->getServerType();
        $thisServer = $network->getServerName();

        $other = $network->pickBestServer($type, [$thisServer]);
        if ($other !== null) {
            $player->sendMessage(TF::YELLOW . "This server is full, sending you to " . TF::AQUA . $other->name . TF::YELLOW . "...");
            $network->transferToServer($player, $other->name);
            return;
        }

        $player->sendMessage(TF::RED . "All " . $type . " servers are currently full. Please try again later.");
        $network->transferToLobby($player);
    }

    private static function getThisServerPlayersPerTeam(): int {
        return match (BedWarsCore::getInstance()->getNetworkManager()->getServerType()) {
            "solo" => 1,
            "double" => 2,
            "triple" => 3,
            "squad" => 4,
            default => 1,
        };
    }

}
