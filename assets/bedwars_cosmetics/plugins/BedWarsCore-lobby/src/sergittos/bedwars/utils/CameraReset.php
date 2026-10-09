<?php

declare(strict_types=1);

namespace sergittos\bedwars\utils;

use pocketmine\network\mcpe\protocol\CameraInstructionPacket;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Forces a player's client out of any detached/free camera view (as used
 * by the BedWars game server's victory cinematic) and back to normal
 * first-person control.
 *
 * Why this exists on the LOBBY side too, not just the game side: players
 * arrive here via a real cross-server transfer (Player::transfer(), a
 * fresh RakNet/MCPE reconnect) straight out of the victory cinematic. The
 * game server already sends its own clear instruction before that
 * transfer, but relying on that alone left some players landing in the
 * lobby with their camera still locked into the cinematic view - the
 * game server has no way to know whether that packet actually got
 * applied before the disconnect. Clearing again, unconditionally, the
 * moment a player joins the lobby is a cheap, idempotent safety net that
 * fixes it regardless of what happened on the other end.
 */
final class CameraReset{

    public static function clear(Player $player): void{
        if(!$player->isConnected()){
            return;
        }

        try{
            $player->getNetworkSession()->sendDataPacket(
                CameraInstructionPacket::create(...self::buildClearArgs()),
                true
            );
        }catch(\Throwable){
            // Never let a defensive cleanup packet take the join flow down.
        }
    }

    /**
     * Builds CameraInstructionPacket::create()'s full positional argument
     * list from its actual parameter list instead of a hardcoded count -
     * see the equivalent method on the game side (CinematicCamera) for
     * why: different PocketMine-MP forks/versions have shipped this
     * packet with a different number of constructor parameters.
     *
     * @return array<int, mixed>
     */
    private static function buildClearArgs(): array{
        static $parameters = null;
        if($parameters === null){
            $parameters = (new ReflectionMethod(CameraInstructionPacket::class, "create"))->getParameters();
        }

        $args = [];
        foreach($parameters as $index => $parameter){
            if($index === 1){
                // $clear
                $args[] = true;
                continue;
            }

            if($parameter->allowsNull()){
                $args[] = null;
            }elseif($parameter->isDefaultValueAvailable()){
                $args[] = $parameter->getDefaultValue();
            }elseif($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() === "bool"){
                $args[] = false;
            }else{
                continue;
            }
        }

        return $args;
    }

}
