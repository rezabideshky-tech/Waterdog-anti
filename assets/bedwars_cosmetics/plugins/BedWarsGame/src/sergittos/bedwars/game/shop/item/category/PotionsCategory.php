<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\shop\item\category;


use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\game\shop\Category;
use sergittos\bedwars\game\shop\item\ItemProduct;
use sergittos\bedwars\session\Session;

class PotionsCategory extends Category {

    public static function getSelf(): self {
        return new self();
    }

    public function __construct() {
        parent::__construct("Potions");
    }

    /**
     * @return ItemProduct[]
     */
    public function getProducts(Session $session): array {
        return [
            new ItemProduct("Speed II Potion (45 seconds)", 1, 1, VanillaItems::POTION()->setType(PotionType::SWIFTNESS())->setCustomName("Speed II Potion (45 seconds)"), VanillaItems::EMERALD(),19),
            new ItemProduct("Jump V Potion (45 seconds)", 1, 1, VanillaItems::POTION()->setType(PotionType::LEAPING())->setCustomName("Jump V Potion (45 seconds)"), VanillaItems::EMERALD(),20),
            new ItemProduct("Invisibility Potion (30 seconds)", 2, 1, VanillaItems::POTION()->setType(PotionType::INVISIBILITY())->setCustomName("Invisibility Potion (30 seconds)"), VanillaItems::EMERALD(),21)
        ];
    }

}