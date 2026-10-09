<?php

declare(strict_types=1);

namespace sergittos\bedwars\gui;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\game\shop\Category;
use sergittos\bedwars\game\shop\Product;
use sergittos\bedwars\game\shop\upgrades\category\TrapsCategory;
use sergittos\bedwars\game\shop\upgrades\category\UpgradesCategory;
use sergittos\bedwars\session\SessionFactory;
use function array_values;

class UpgradesGui {

    private const SLOTS = [11, 13, 15, 20, 22, 24, 29, 31, 33];

    /** @var Category[] */
    private array $categories;

    public function __construct() {
        $this->categories = [new UpgradesCategory(), new TrapsCategory()];
    }

    public function send(Player $player, int $categoryIndex = 0): void {
        if (!SessionFactory::hasSession($player)) return;
        $session = SessionFactory::getSession($player);
        $category = $this->categories[$categoryIndex] ?? $this->categories[0];

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
        $menu->setName(TF::BOLD . TF::GOLD . "Team Upgrades " . TF::GRAY . "» " . TF::YELLOW . $category->getName());
        $inv = $menu->getInventory();

        /** @var array<int, Product> $slotProducts */
        $slotProducts = [];
        foreach (array_values($category->getProducts($session)) as $i => $product) {
            $slot = self::SLOTS[$i] ?? null;
            if ($slot === null) continue;
            $item = $this->getIcon($product->getName());
            $item->setCustomName($product->getDisplayName($session));
            $item->setLore([$product->getDescription($session)]);
            $item->getNamedTag()->setInt("upgrade_slot_ref", $slot);
            $inv->setItem($slot, $item);
            $slotProducts[$slot] = $product;
        }

        foreach ($this->categories as $i => $cat) {
            $icon = $i === 0 ? VanillaItems::DIAMOND() : VanillaBlocks::TRIPWIRE_HOOK()->asItem();
            $icon->setCustomName($i === $categoryIndex ? TF::BOLD . TF::GREEN . "► " . $cat->getName() . " ◄" : TF::YELLOW . $cat->getName());
            $icon->getNamedTag()->setInt("upgrade_category_index", $i);
            $inv->setItem($i === 0 ? 1 : 7, $icon);
        }

        $menu->setListener(function(InvMenuTransaction $tx) use ($player, $session, $slotProducts, $categoryIndex): InvMenuTransactionResult {
            $tag = $tx->getItemClicked()->getNamedTag();

            if ($tag->getTag("upgrade_category_index") !== null) {
                $index = $tag->getInt("upgrade_category_index");
                if ($index !== $categoryIndex) {
                    $player->removeCurrentWindow();
                    $this->send($player, $index);
                }
                return $tx->discard();
            }

            $slotRef = $tag->getTag("upgrade_slot_ref");
            if ($slotRef !== null) {
                $product = $slotProducts[$tag->getInt("upgrade_slot_ref")] ?? null;
                if ($product !== null && ShopPurchaseHelper::attemptPurchase($player, $session, $product)) {
                    $player->removeCurrentWindow();
                    $this->send($player, $categoryIndex);
                }
            }
            return $tx->discard();
        });

        $menu->send($player);
    }

    private function getIcon(string $name): Item {
        return match ($name) {
            "Sharpened Swords" => VanillaItems::IRON_SWORD(),
            "Armor Protection" => VanillaItems::IRON_CHESTPLATE(),
            "Maniac Miner" => VanillaItems::IRON_PICKAXE(),
            "Iron Forge" => VanillaBlocks::FURNACE()->asItem(),
            "Heal Pool" => VanillaItems::GLISTERING_MELON(),
            "It's a trap" => VanillaItems::STRING(),
            "Counter-Offensive Trap" => VanillaItems::FEATHER(),
            "Alarm Trap" => VanillaBlocks::REDSTONE_TORCH()->asItem(),
            "Miner Fatigue Trap" => VanillaItems::INK_SAC(),
            default => VanillaItems::DIAMOND(),
        };
    }

}
