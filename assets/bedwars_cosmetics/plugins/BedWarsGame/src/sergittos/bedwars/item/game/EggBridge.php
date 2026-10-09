<?php

declare(strict_types=1);


namespace sergittos\bedwars\item\game;


use pocketmine\block\utils\DyeColor;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\ProjectileItem;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use sergittos\bedwars\game\entity\misc\EggBridgeEntity;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use function max;

class EggBridge extends ProjectileItem {

    /** How many single-file bridge blocks a fresh Egg Bridge lays before running out. */
    public const DEFAULT_BLOCKS = 40;

    /** NBT tag used to carry a partially-used bridge's remaining block count when it's handed back to a player (see BridgeBuilderEntity::terminate()). */
    private const BLOCKS_TAG = "bedwars_bridge_blocks";

    public function __construct() {
        parent::__construct(new ItemIdentifier(ItemTypeIds::EGG), "Egg Bridge");

        $this->setCustomName(TextFormat::YELLOW . "Egg Bridge");
    }

    protected function createEntity(Location $location, Player $thrower): Throwable {
        $session = SessionFactory::hasSession($thrower) ? SessionFactory::getSession($thrower) : null;
        $color = $session !== null && $session->getTeam() !== null ? $session->getTeam()->getDyeColor() : DyeColor::WHITE();
        $game = $session?->getGame();
        $blocks = $this->getNamedTag()->getInt(self::BLOCKS_TAG, self::DEFAULT_BLOCKS);
        return new EggBridgeEntity($location, $thrower, $color, $game, $blocks);
    }

    /** قبلاً 1.3 بود - ضعیف‌تر از پرتاب واقعی تخم‌مرغ در جاوا (1.5)، برای همین زودتر از حد سقوط می‌کرد. */
    public function getThrowForce(): float {
        return 1.5;
    }

    /**
     * A fresh Egg Bridge item pre-loaded with a specific number of
     * remaining blocks - used to give an unfinished bridge back to a
     * player when their bridge builder is picked up instead of destroyed
     * (see BridgeBuilderEntity::terminate()).
     */
    public static function withBlocks(int $blocks): self{
        $item = new self();
        $item->getNamedTag()->setInt(self::BLOCKS_TAG, max(0, $blocks));
        return $item;
    }

}
