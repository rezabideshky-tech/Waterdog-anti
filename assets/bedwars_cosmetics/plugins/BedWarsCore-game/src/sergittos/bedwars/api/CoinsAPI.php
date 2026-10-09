<?php

declare(strict_types=1);

namespace sergittos\bedwars\api;

use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\session\Session;

final class CoinsAPI{

    private const RETRY_DELAY_TICKS = 10;
    private const MAX_RETRIES = 30;

    private static function toInt(float|int $amount): int{
        $v = (int) round((float) $amount);
        return $v < 0 ? 0 : $v;
    }

    private static function withLoadedSession(Player $player, \Closure $fn, int $attempt = 0): bool{
        $core = BedWarsCore::getInstance();
        $session = $core->getSessionManager()->get($player);

        if($session === null){
            return false;
        }

        if($session->isLoaded()){
            $fn($session);
            return true;
        }

        if($attempt >= self::MAX_RETRIES){
            return false;
        }

        $core->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $fn, $attempt): void{
            if(!$player->isConnected()){
                return;
            }
            self::withLoadedSession($player, $fn, $attempt + 1);
        }), self::RETRY_DELAY_TICKS);

        return true;
    }

    public static function getCoins(Player $player): float{
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        return $session !== null ? (float) $session->getCoins() : 0.0;
    }

    public static function hasCoins(Player $player, float $amount): bool{
        return self::getCoins($player) >= (float) self::toInt($amount);
    }

    public static function setCoins(Player $player, float $amount): bool{
        $value = self::toInt($amount);

        return self::withLoadedSession($player, function(Session $session) use ($value): void{
            $session->setCoins($value);
        });
    }

    public static function addCoins(Player $player, float $amount): bool{
        $add = self::toInt($amount);
        if($add <= 0){
            return true;
        }

        return self::withLoadedSession($player, function(Session $session) use ($add): void{
            $session->addCoins($add);
        });
    }

    public static function removeCoins(Player $player, float $amount): bool{
        $cost = self::toInt($amount);
        if($cost <= 0){
            return true;
        }

        $core = BedWarsCore::getInstance();
        $session = $core->getSessionManager()->get($player);
        if($session === null){
            return false;
        }

        if($session->isLoaded()){
            if($session->getCoins() < $cost){
                return false;
            }
            $session->setCoins($session->getCoins() - $cost);
            return true;
        }

        return self::withLoadedSession($player, function(Session $session) use ($cost): void{
            if($session->getCoins() < $cost){
                return;
            }
            $session->setCoins($session->getCoins() - $cost);
        });
    }
}