<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\pets;

use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\lobby\pets\BabyDragon;
use sergittos\bedwars\lobby\pets\Cubee;
use sergittos\bedwars\lobby\pets\Endolotl;
use sergittos\bedwars\lobby\pets\PirateParrot;
use sergittos\bedwars\lobby\pets\Pirateship;
use sergittos\bedwars\lobby\pets\WitchCat;
use sergittos\bedwars\lobby\pets\Rock;

/**
 * Spawns/despawns the player's equipped cosmetic pet. Lobby-server only by
 * design - never call this from BedWarsGame. Each player can have at most
 * one active pet entity at a time.
 */
final class PetRenderService{

    /** @var array<string, string> pet key (lowercase) => entity class */
    private const CLASS_MAP = [
        "babydragon" => BabyDragon::class,
        "cubee" => Cubee::class,
        "endolotl" => Endolotl::class,
        "pirateparrot" => PirateParrot::class,
        "pirateship" => Pirateship::class,
        "witchcat" => WitchCat::class,
        "rock" => Rock::class,
    ];

    /** @var array<int, PetBase> playerId => pet entity */
    private static array $active = [];

    public static function apply(Player $player): void{
        if(!PetItemRegistrar::isRegistered()){
            return;
        }
        if(!$player->isConnected()){
            return;
        }

        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session === null){
            return;
        }

        $key = PlayerCosmeticsManager::getInstance()->getEquipped($player, CosmeticCategory::PET);

        self::despawn($player);

        if($key === null){
            return;
        }

        $class = self::CLASS_MAP[strtolower($key)] ?? null;
        if($class === null){
            return;
        }

        $world = $player->getWorld();
        $location = Location::fromObject($player->getPosition(), $world, $player->getLocation()->yaw, $player->getLocation()->pitch);

        try{
            /** @var PetBase $pet */
            $pet = new $class($location, null);
        }catch(\Throwable $e){
            BedWarsCore::getInstance()->getLogger()->debug("Failed to spawn pet '$key' for " . $player->getName() . ": " . $e->getMessage());
            return;
        }

        $pet->setOwner($player);
        $pet->setNameTag($player->getName() . "'s Pet");
        $pet->setNameTagAlwaysVisible(false);
        $pet->spawnToAll();

        self::$active[$player->getId()] = $pet;
    }

    public static function despawn(Player $player): void{
        $existing = self::$active[$player->getId()] ?? null;
        if($existing !== null){
            if(!$existing->isClosed()){
                $existing->flagForDespawn();
            }
            unset(self::$active[$player->getId()]);
        }
    }
}
