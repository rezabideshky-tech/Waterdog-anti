<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use sergittos\bedwars\game\entity\mob\IronGolemEntity;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\team\Team;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class MobSummonItems{

    private const TAG_GOLEM = "bw_iron_golem";

    public static function createIronGolemItem(): Item{
        $item = VanillaItems::VILLAGER_SPAWN_EGG();
        $item->setCustomName("Iron Golem");
        $item->setLore(["Place to summon an Iron Golem"]);
        $item->getNamedTag()->setByte(self::TAG_GOLEM, 1);
        return $item;
    }

    public static function isIronGolemItem(Item $item): bool{
        return $item->getNamedTag()->getByte(self::TAG_GOLEM, 0) === 1;
    }

    public static function summonIronGolem(Player $player, Team $team, Game $game): void{
        $world = $game->getWorld();
        if($world === null){
            return;
        }

        $pos = $player->getPosition();
        $spawn = new Vector3($pos->getX() + 0.5, $pos->getY(), $pos->getZ() + 0.5);

        $loc = Location::fromObject($spawn, $world, $player->getLocation()->getYaw(), 0.0);
        $golem = new IronGolemEntity($loc);
        $golem->setup($team, $game);

        $p = $golem->getPosition()->floor();
        $world->requestChunkPopulation($p->getX() >> Chunk::COORD_BIT_SIZE, $p->getZ() >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
            fn() => $golem->spawnToAll(),
            fn() => null
        );
    }
}