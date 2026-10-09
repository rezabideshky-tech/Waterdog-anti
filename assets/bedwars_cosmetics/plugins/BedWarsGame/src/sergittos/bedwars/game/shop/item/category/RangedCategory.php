<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\shop\item\category;


use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\game\shop\Category;
use sergittos\bedwars\game\shop\item\ItemProduct;
use sergittos\bedwars\session\Session;

class RangedCategory extends Category {

    public static function getSelf(): self {
        return new self();
    }

    public function __construct() {
        parent::__construct("Ranged");
    }

    /**
     * @return ItemProduct[]
     */
    public function getProducts(Session $session): array {
        $power = new EnchantmentInstance(VanillaEnchantments::POWER());
        return [
            new ItemProduct("Arrow", 2, 6, VanillaItems::ARROW(), VanillaItems::GOLD_INGOT(),19),
            new ItemProduct("Bow", 12, 1, VanillaItems::BOW(), VanillaItems::GOLD_INGOT(),20),
            new ItemProduct("Bow (Power I)", 20, 1, VanillaItems::BOW()->addEnchantment($power), VanillaItems::GOLD_INGOT(),21),
            new ItemProduct(
                "Bow (Power I, Punch I)",
                6, 1,
                VanillaItems::BOW()
                    ->addEnchantment($power)
                    ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PUNCH())),
                VanillaItems::EMERALD(),
                22
            ),

        ];
    }

}