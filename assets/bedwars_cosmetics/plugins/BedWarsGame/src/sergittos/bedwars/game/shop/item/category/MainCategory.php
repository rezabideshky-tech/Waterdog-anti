<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\shop\item\category;

use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\game\shop\Category;
use sergittos\bedwars\game\shop\item\ItemProduct;
use sergittos\bedwars\item\game\Fireball;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\settings\GameSettings;
use sergittos\bedwars\utils\GameUtils;
use Closure;
use function ucfirst;

class MainCategory extends Category {

    public function __construct() {
        parent::__construct("Main");
    }

    /**
     * @return ItemProduct[]
     */
    public function getProducts(Session $session): array {
        // تغییر اصلی اینجا - بررسی وجود تیم و مقداردهی پیش‌فرض
        $team = $session->getTeam();
        $color = $team !== null ? $team->getDyeColor() : DyeColor::WHITE();
        
        $settings = $session->getGameSettings();
        $power = new EnchantmentInstance(VanillaEnchantments::POWER());
        
        return [
            $this->createMeleeProduct("stone", 10, VanillaItems::IRON_INGOT(), $session, 20),
            $this->createMeleeProduct("iron", 7, VanillaItems::GOLD_INGOT(), $session, 29),
            $this->createMeleeProduct("diamond", 4, VanillaItems::EMERALD(), $session, 38),
            new ItemProduct(
                "Stick (Knockback I)", 5, 1,
                VanillaItems::STICK()->addEnchantment(new EnchantmentInstance(VanillaEnchantments::KNOCKBACK())),
                VanillaItems::GOLD_INGOT(), 22
            ),
            $this->createArmorProduct("chainmail", 30, VanillaItems::IRON_INGOT(), $settings, 21, VanillaItems::CHAINMAIL_BOOTS()->setCustomName("§rPermanent Chainmail Armor")),
            $this->createArmorProduct("iron", 12, VanillaItems::GOLD_INGOT(), $settings, 30, VanillaItems::IRON_BOOTS()->setCustomName("§rPermanent Iron Armor")),
            $this->createArmorProduct("diamond", 6, VanillaItems::EMERALD(), $settings, 39, VanillaItems::DIAMOND_BOOTS()->setCustomName("§rPermanent Diamond Armor")),
            new ItemProduct("Permanent Shears", 20, 1, VanillaItems::SHEARS(), VanillaItems::IRON_INGOT(), 31, function(Session $session) {
                $session->getGameSettings()->setPermanentShears();
                return true;
            }),
            $this->createToolProduct("Pickaxe", $settings->getPickaxeTier(), $settings, 40, $settings->isPickaxeFullUpgraded(), function() use ($settings) {
                $settings->incrasePickaxeTier();
            }),
            $this->createToolProduct("Axe", $settings->getAxeTier(), $settings, 41, $settings->isAxeFullUpgraded(), function() use ($settings) {
                $settings->incraseAxeTier();
            }),
            new ItemProduct("Arrow", 2, 6, VanillaItems::ARROW(), VanillaItems::GOLD_INGOT(), 32),
            new ItemProduct("Bow", 12, 1, VanillaItems::BOW(), VanillaItems::GOLD_INGOT(), 23),
            new ItemProduct("Wool", 4, 16, VanillaBlocks::WOOL()->setColor($color), VanillaItems::IRON_INGOT(), 19),
            new ItemProduct("Ladder", 4, 8, VanillaBlocks::LADDER(), VanillaItems::IRON_INGOT(), 37),
            new ItemProduct("Oak Wood Planks", 4, 16, VanillaBlocks::OAK_PLANKS(), VanillaItems::GOLD_INGOT(), 28),
            new ItemProduct("Golden Apple", 3, 1, VanillaItems::GOLDEN_APPLE(), VanillaItems::GOLD_INGOT(), 43),
            new ItemProduct("Fireball", 40, 1, new Fireball(), VanillaItems::IRON_INGOT(), 22),
            new ItemProduct("TNT", 4, 1, VanillaBlocks::TNT(), VanillaItems::GOLD_INGOT(), 25),
            new ItemProduct("Ender Pearl", 1, 1, VanillaItems::ENDER_PEARL(), VanillaItems::EMERALD(), 42),
            new ItemProduct("Water Bucket", 3, 1, VanillaItems::WATER_BUCKET(), VanillaItems::GOLD_INGOT(), 34),
            new ItemProduct("Jump V Potion (45 seconds)", 1, 1, VanillaItems::POTION()->setType(PotionType::LEAPING())->setCustomName("Jump V Potion (45 seconds)"), VanillaItems::EMERALD(), 33),
            new ItemProduct("Invisibility Potion (30 seconds)", 2, 1, VanillaItems::POTION()->setType(PotionType::INVISIBILITY())->setCustomName("Invisibility Potion (30 seconds)"), VanillaItems::EMERALD(), 24),
        ];
    }

    private function createToolProduct(string $name, int $tier, GameSettings $settings, int $slot,bool $is_full_upgraded, Closure $on_purchase): ItemProduct {
        $tier++;
        return new ItemProduct(
            $settings->getMaterial($tier) . " $name " . ($tier <= 4 ? "Tier " . GameUtils::intToRoman($tier) : "MAX TIER"),
            $this->getPrice($tier), 1, $settings->getTool($name, $tier), $this->getOre($tier), $slot,function(Session $session) use ($name, $tier, $settings, $slot,$is_full_upgraded, $on_purchase) {
            $session->getPlayer()->getInventory()->remove($settings->getTool($name, $tier - 1));
            $on_purchase();
        }, !$is_full_upgraded);
    }

    private function getPrice(int $tier): int {
        return match($tier) {
            1, 2 => 10,
            3 => 3,
            4 => 6,
            5 => 0
        };
    }

    private function getOre(int $tier): Item {
        return match($tier) {
            1, 2 => VanillaItems::IRON_INGOT(),
            3, 4 => VanillaItems::GOLD_INGOT(),
            5 => VanillaItems::AIR()
        };
    }

    private function createMeleeProduct(string $name, int $price, Item $ore, Session $session,int $slot): ItemProduct
    {
        $sword = StringToItemParser::getInstance()->parse($name . "_sword");
        if ($sword instanceof Durable) {
            $sword->setUnbreakable();
        }
        if (!$session->getTeam()->getUpgrades()->getSharpenedSwords()->canLevelUp()) {
              $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS()));
        }

        return new ItemProduct(ucfirst($name) . " Sword", $price, 1, $sword, $ore, $slot,function (Session $session) {
            $session->getPlayer()->getInventory()->remove(VanillaItems::WOODEN_SWORD());
        });

    }

    private function createArmorProduct(string $armor, int $price, Item $ore, GameSettings $settings,int $slot,Item $boots): ItemProduct {
        return new ItemProduct("Permanent " . ucfirst($armor) . " Armor", $price, 0, $boots, $ore,$slot ,function(Session $session) use ($armor) {
            $session->getGameSettings()->setArmor($armor);
        }, $this->getPriority($settings->getArmor()) < $this->getPriority($armor));
    }

    private function getPriority(?string $armor): int {
        return match($armor) {
            "chainmail" => 1,
            "iron" => 2,
            "diamond" => 3,
            default => 0
        };
    }

    public static function getSelf(): MainCategory{
        return new self();
    }
}