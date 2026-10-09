<?php

declare(strict_types=1);

namespace sergittos\bedwars\item\game;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ProjectileItem;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use sergittos\bedwars\game\entity\misc\SilverfishSnowballEntity;
use sergittos\bedwars\session\SessionFactory;

class Silverfish extends ProjectileItem {

    public function __construct() {
        parent::__construct(new ItemIdentifier(ItemTypeIds::SNOWBALL), "Silverfish");

        $this->setCustomName(TextFormat::GRAY . "Silverfish");
    }

    protected function createEntity(Location $location, Player $thrower): Throwable {
        $session = SessionFactory::hasSession($thrower) ? SessionFactory::getSession($thrower) : null;
        $team = $session?->getTeam();
        $game = $session?->getGame();

        return new SilverfishSnowballEntity($location, $thrower, $team, $game);
    }

    public function getThrowForce(): float {
        return 1.5;
    }

}
