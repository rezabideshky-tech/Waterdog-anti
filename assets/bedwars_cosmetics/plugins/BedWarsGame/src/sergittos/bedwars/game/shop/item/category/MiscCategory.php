<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\shop\item\category;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\game\shop\Category;
use sergittos\bedwars\game\shop\item\ItemProduct;
use sergittos\bedwars\item\game\CastleBlock;
use sergittos\bedwars\item\game\EggBridge;
use sergittos\bedwars\item\game\Fireball;
use sergittos\bedwars\item\game\Silverfish;
use sergittos\bedwars\session\Session;

final class MiscCategory extends Category{

    public function __construct(){
        parent::__construct("Misc");
    }

    public static function getSelf(): self{
        return new self();
    }

    public function getProducts(Session $session): array{
        return [
            new ItemProduct("Golden Apple", 3, 1, VanillaItems::GOLDEN_APPLE(), VanillaItems::GOLD_INGOT(), 19),
            new ItemProduct("Fireball", 40, 1, new Fireball(), VanillaItems::IRON_INGOT(), 20),
            new ItemProduct("TNT", 3, 1, VanillaBlocks::TNT(), VanillaItems::GOLD_INGOT(), 21),
            new ItemProduct("Ender Pearl", 1, 1, VanillaItems::ENDER_PEARL(), VanillaItems::EMERALD(), 22),
            new ItemProduct("Water Bucket", 3, 1, VanillaItems::WATER_BUCKET(), VanillaItems::GOLD_INGOT(), 23),
            new ItemProduct("Magic Milk", 4, 1, VanillaItems::MILK_BUCKET(), VanillaItems::GOLD_INGOT(), 24),

            new ItemProduct("Sponge", 3, 4, VanillaBlocks::SPONGE(), VanillaItems::GOLD_INGOT(), 28),
            new ItemProduct("Egg Bridge", 1, 1, new EggBridge(), VanillaItems::EMERALD(), 29),
            new ItemProduct("PopupTower", 24, 1, CastleBlock::create(), VanillaItems::IRON_INGOT(), 30),
            new ItemProduct("DreamDefender", 120, 1, \sergittos\bedwars\item\game\MobSummonItems::createIronGolemItem(), VanillaItems::IRON_INGOT(), 31),
            new ItemProduct("Bedbug", 28, 1, new Silverfish(), VanillaItems::IRON_INGOT(), 32),
        ];
    }
}