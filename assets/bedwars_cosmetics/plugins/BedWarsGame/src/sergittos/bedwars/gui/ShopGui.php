<?php

declare(strict_types=1);

namespace sergittos\bedwars\gui;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use sergittos\bedwars\game\shop\Category;
use sergittos\bedwars\game\shop\item\editor\ShopEditorManager;
use sergittos\bedwars\game\shop\item\editor\ShopEditorSession;
use sergittos\bedwars\game\shop\item\ItemProduct;
use sergittos\bedwars\game\shop\Product;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\session\SessionFactory;
use function array_values;
use function count;
use function strtoupper;

final class ShopGui{

    private const TAG_CATEGORY_INDEX = "bw_shop_cat";
    private const TAG_PRODUCT_SLOT = "bw_shop_slot";
    private const TAG_EDITOR_TOGGLE = "bw_shop_editor_toggle";
    private const TAG_EDITOR_RESET = "bw_shop_editor_reset";

    // These two slots sit in the header rows above every product grid (which
    // only ever starts at slot 18), so they can never collide with a
    // product. They are only actually rendered once renderEditorControls()
    // confirms the current category tab icons don't already occupy them -
    // see the guards there.
    private const EDITOR_TOGGLE_SLOT = 8;
    private const EDITOR_RESET_SLOT = 17;

    private const MAIN_CATEGORY_NAME = "Main";

    // Shop Editor icon (the compass toggle + reset barrier) is now shown in
    // the Item Shop GUI, in its own reserved header slot (see
    // EDITOR_TOGGLE_SLOT/EDITOR_RESET_SLOT) away from every product/category
    // icon. renderEditorControls() only actually draws it while the Main
    // tab is open (see its isMainCategory guard) - the rest of the editor
    // (ShopEditorSession, its overrides, and the TAG_EDITOR_TOGGLE/
    // TAG_EDITOR_RESET click handlers in open()) was already fully wired up
    // and untouched by this flag either way.
    private const EDITOR_ICON_ENABLED = true;

    /** @var Category[] */
    private array $categories;

    /**
     * Index of the "Main" category within $categories, if present. The
     * Shop Editor feature only ever activates for shops built with a Main
     * category (i.e. the real Item Shop) - every other use of ShopGui
     * (should one ever exist) behaves exactly as before this feature was
     * added.
     */
    private ?int $mainCategoryIndex = null;

    public function __construct(Category ...$categories){
        $this->categories = array_values($categories);

        foreach($this->categories as $i => $category){
            if($category->getName() === self::MAIN_CATEGORY_NAME){
                $this->mainCategoryIndex = $i;
                break;
            }
        }
    }

    public function open(Player $player, int $categoryIndex = 0): void{
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        if(!$session->isPlaying() || $session->getGame() === null){
            return;
        }

        $editorSession = ShopEditorManager::get($player);

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName("§l§bITEM SHOP");
        $inv = $menu->getInventory();

        $state = [
            "category" => $categoryIndex,
            "slotProducts" => []
        ];

        $this->render($inv, $session, (int) $state["category"], $state["slotProducts"], $editorSession);

        $menu->setListener(function(InvMenuTransaction $tx) use ($player, $session, $inv, &$state, $editorSession): InvMenuTransactionResult{
            $clicked = $tx->getItemClicked();
            $tag = $clicked->getNamedTag();

            if($tag->getTag(self::TAG_EDITOR_TOGGLE) !== null){
                $editorSession->toggleEditing();
                $this->render($inv, $session, (int) $state["category"], $state["slotProducts"], $editorSession);
                return $tx->discard();
            }

            if($tag->getTag(self::TAG_EDITOR_RESET) !== null){
                if($editorSession->isEditing()){
                    $editorSession->reset();
                    ShopEditorManager::persist($player);
                    $session->message("§d§lShop Editor§r §7- Main tab layout reset to default.");
                    $this->render($inv, $session, (int) $state["category"], $state["slotProducts"], $editorSession);
                }
                return $tx->discard();
            }

            if($tag->getTag(self::TAG_CATEGORY_INDEX) !== null){
                $index = $tag->getInt(self::TAG_CATEGORY_INDEX);
                $state["category"] = $index;
                $this->render($inv, $session, $index, $state["slotProducts"], $editorSession);
                return $tx->discard();
            }

            if($tag->getTag(self::TAG_PRODUCT_SLOT) !== null){
                $slot = $tag->getInt(self::TAG_PRODUCT_SLOT);
                $categoryIndex = (int) $state["category"];
                $isMainCategory = $this->mainCategoryIndex !== null && $categoryIndex === $this->mainCategoryIndex;

                if($editorSession->isEditing() && $isMainCategory){
                    if($editorSession->getPendingSlot() === $slot){
                        // Clicked the "waiting for replacement" placeholder again -
                        // the player confirms they want this slot to stay empty.
                        $editorSession->setEmptyOverride($slot);
                        $editorSession->setPendingSlot(null);
                        ShopEditorManager::persist($player);
                        $session->message("§d§lShop Editor§r §7- Slot left empty.");
                    }else{
                        // Nothing is mutated yet - just mark this slot as the one
                        // waiting for a replacement. If the player never picks
                        // one, the slot silently keeps showing its current item.
                        $editorSession->setPendingSlot($slot);
                        $session->message("§d§lShop Editor§r §7- Switch tab and pick a replacement.");
                    }

                    $this->render($inv, $session, $categoryIndex, $state["slotProducts"], $editorSession);
                    return $tx->discard();
                }

                if($editorSession->isEditing() && !$isMainCategory){
                    $pendingSlot = $editorSession->getPendingSlot();
                    if($pendingSlot === null){
                        $session->message("§d§lShop Editor§r §7- Open Main and pick a slot to edit first.");
                        return $tx->discard();
                    }

                    $product = $state["slotProducts"][$slot] ?? null;
                    $category = $this->categories[$categoryIndex] ?? null;

                    if($product instanceof Product && $category !== null){
                        $editorSession->setOverride($pendingSlot, $category->getName(), $product->getId());
                        $editorSession->setPendingSlot(null);
                        ShopEditorManager::persist($player);
                        $session->message("§d§lShop Editor§r §7- Main slot updated.");

                        $state["category"] = (int) $this->mainCategoryIndex;
                        $this->render($inv, $session, (int) $state["category"], $state["slotProducts"], $editorSession);
                    }

                    return $tx->discard();
                }

                $product = $state["slotProducts"][$slot] ?? null;
                if($product instanceof Product){
                    ShopPurchaseHelper::attemptPurchase($player, $session, $product);
                    $this->render($inv, $session, $categoryIndex, $state["slotProducts"], $editorSession);
                }
                return $tx->discard();
            }

            return $tx->discard();
        });

        // Shop Editor is a per-GUI-session toggle, not a persistent setting -
        // if it's left ON when the player walks away from the item shop (or
        // the menu is otherwise closed without explicitly turning it off),
        // the next time they open the shop it would still show as ON with
        // no way that actually reflects an active edit. Force it back OFF
        // whenever the inventory closes, exactly like clicking the toggle
        // itself would (which also clears any pending slot selection).
        $menu->setInventoryCloseListener(function(Player $player) use ($editorSession): void{
            if($editorSession->isEditing()){
                $editorSession->setEditing(false);
            }
            ShopEditorManager::persist($player);
        });

        $menu->send($player);
    }

    /**
     * @param array<int, Product> $slotProducts
     */
    private function render(\pocketmine\inventory\Inventory $inv, Session $session, int $categoryIndex, array &$slotProducts, ShopEditorSession $editorSession): void{
        $slotProducts = [];

        for($i = 0; $i < 54; $i++){
            $inv->setItem($i, VanillaItems::AIR());
        }

        foreach($this->categories as $i => $cat){
            $icon = $this->getCategoryIcon($cat->getName());
            $name = ($i === $categoryIndex)
                ? ("§a§l" . strtoupper($cat->getName()))
                : ("§7" . strtoupper($cat->getName()));

            $icon->setCustomName($name);
            $icon->setLore($i === $categoryIndex ? [] : ["§fClick to open"]);
            $icon->getNamedTag()->setInt(self::TAG_CATEGORY_INDEX, $i);
            $inv->setItem($i, $icon);
        }

        $category = $this->categories[$categoryIndex] ?? ($this->categories[0] ?? null);
        if($category === null){
            return;
        }

        $isMainCategory = $this->mainCategoryIndex !== null && $categoryIndex === $this->mainCategoryIndex;

        // Shop Editor Activator only ever shows up while the Main tab
        // itself is open - it's a control that belongs to the Main shop,
        // not a global header button that would sit there on every tab.
        if($isMainCategory){
            $this->renderEditorControls($inv, $editorSession);
        }

        $productsBySlot = [];
        foreach($category->getProducts($session) as $product){
            if(!$product instanceof ItemProduct){
                continue;
            }

            $slot = $this->normalizeProductSlot($product->getSlot());
            if($slot < 18 || $slot > 53){
                continue;
            }

            $productsBySlot[$slot] = $product;
        }

        if($isMainCategory){
            $this->applyEditorOverrides($session, $productsBySlot, $editorSession);
        }

        foreach($productsBySlot as $slot => $product){
            $item = $product->getItem();
            if($item->getCount() <= 0){
                $item->setCount(1);
            }

            $item->setCustomName($product->getDisplayName($session));

            $lore = [];
            $lore[] = $product->getDescription($session);

            if(!$product->canBePurchased($session)){
                $lore[] = "§cUnavailable";
            }else{
                $lore[] = "§eClick to purchase";
            }

            if($isMainCategory && $editorSession->isEditing()){
                $lore[] = "§8§m--------------------";
                $lore[] = "§dShop Editor: §fClick to remove";
            }

            $item->setLore($lore);
            $item->getNamedTag()->setInt(self::TAG_PRODUCT_SLOT, $slot);

            $inv->setItem($slot, $item);
            $slotProducts[$slot] = $product;
        }

        if($isMainCategory){
            $pendingSlot = $editorSession->getPendingSlot();
            if($pendingSlot !== null && $pendingSlot >= 18 && $pendingSlot <= 53){
                $placeholder = VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::RED())->asItem();
                $placeholder->setCustomName("§e§lEmpty Slot");
                $placeholder->setLore([
                    "§7Pick a replacement from",
                    "§7another tab to fill this slot.",
                    "",
                    "§eClick again to leave it empty."
                ]);
                $placeholder->getNamedTag()->setInt(self::TAG_PRODUCT_SLOT, $pendingSlot);

                $inv->setItem($pendingSlot, $placeholder);
                unset($slotProducts[$pendingSlot]);
            }

            // Slots the player deliberately left empty stay true AIR (no
            // clutter) once editing is OFF, but while editing is ON they get
            // a faint marker so the player can find and re-fill them.
            if($editorSession->isEditing()){
                foreach($editorSession->getOverrides() as $slot => $override){
                    if($override !== ShopEditorSession::EMPTY_MARKER){
                        continue;
                    }
                    if($slot === $pendingSlot || $slot < 18 || $slot > 53){
                        continue;
                    }

                    $marker = VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::RED())->asItem();
                    $marker->setCustomName("§7§oEmpty Slot (edited)");
                    $marker->setLore(["§7Click to choose a replacement."]);
                    $marker->getNamedTag()->setInt(self::TAG_PRODUCT_SLOT, $slot);

                    $inv->setItem($slot, $marker);
                    unset($slotProducts[$slot]);
                }
            }
        }
    }

    /**
     * @param array<int, ItemProduct> $productsBySlot
     */
    private function applyEditorOverrides(Session $session, array &$productsBySlot, ShopEditorSession $editorSession): void{
        foreach($editorSession->getOverrides() as $slot => $override){
            if($override === ShopEditorSession::EMPTY_MARKER){
                unset($productsBySlot[$slot]);
                continue;
            }

            $resolved = $this->resolveOverrideProduct($session, $override["category"], $override["id"]);
            if($resolved !== null){
                $productsBySlot[$slot] = $resolved;
            }
            // If the saved product can no longer be found this game (e.g. its
            // category temporarily has nothing to offer), the slot quietly
            // keeps its default Main product instead of the layout breaking.
        }
    }

    private function resolveOverrideProduct(Session $session, string $categoryName, string $productId): ?ItemProduct{
        foreach($this->categories as $category){
            if($category->getName() !== $categoryName){
                continue;
            }

            foreach($category->getProducts($session) as $product){
                if($product instanceof ItemProduct && $product->getId() === $productId){
                    return $product;
                }
            }
        }

        return null;
    }

    private function renderEditorControls(\pocketmine\inventory\Inventory $inv, ShopEditorSession $editorSession): void{
        if(!self::EDITOR_ICON_ENABLED){
            return;
        }

        if($this->mainCategoryIndex === null){
            return;
        }

        // Never overwrite a real category tab icon: only draw the toggle if
        // slot 8 isn't already claimed by one.
        if(count($this->categories) > self::EDITOR_TOGGLE_SLOT){
            return;
        }

        $toggle = VanillaItems::COMPASS();

        if($editorSession->isEditing()){
            $toggle->setCustomName("§fShop Editor §7[§aON§7]");
            $toggle->setLore([
                "§a• Editing is ON",
                "§fClick an item to §cremove §fit from Main.",
                "§fThen switch tab and pick a new item.",
                "",
                "§cClick to exit editor."
            ]);
        }else{
            $toggle->setCustomName("§fShop Editor §7[§cOFF§7]");
            $toggle->setLore([
                "§c• Editing is OFF",
                "§fClick to start editing your Main tab.",
                "§fRemove items and replace them with",
                "§fitems from other categories."
            ]);
        }

        $toggle->getNamedTag()->setInt(self::TAG_EDITOR_TOGGLE, 1);
        $inv->setItem(self::EDITOR_TOGGLE_SLOT, $toggle);

        if($editorSession->isEditing() && count($this->categories) <= self::EDITOR_RESET_SLOT){
            $reset = VanillaBlocks::BARRIER()->asItem();
            $reset->setCustomName("§c§lReset Layout");
            $reset->setLore([
                "§7Restore the default Main shop",
                "§7and discard all your changes."
            ]);
            $reset->getNamedTag()->setInt(self::TAG_EDITOR_RESET, 1);
            $inv->setItem(self::EDITOR_RESET_SLOT, $reset);
        }
    }

    private function normalizeProductSlot(int $slot): int{
        while($slot < 18){
            $slot += 9;
        }
        return $slot;
    }

    private function getCategoryIcon(string $name): Item{
        return match($name){
            "Main" => VanillaItems::NETHER_STAR(),
            "Blocks" => VanillaBlocks::BRICKS()->asItem(),
            "Melee" => VanillaItems::GOLDEN_SWORD(),
            "Armor" => VanillaItems::CHAINMAIL_BOOTS(),
            "Tools" => VanillaItems::IRON_PICKAXE(),
            "Ranged" => VanillaItems::BOW(),
            "Potions" => VanillaItems::POTION(),
            "Misc" => VanillaBlocks::TNT()->asItem(),
            default => VanillaItems::EMERALD(),
        };
    }
}
