<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\gui;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\cosmetics\registry\CosmeticsRegistry;
use sergittos\bedwars\cosmetics\wearable\WearableRenderService;
use sergittos\bedwars\lobby\pets\PetRenderService;
use function array_values;
use function number_format;

final class CosmeticsGui{

    /** Hats are visible again. Pets retain their previous visibility setting. */
    private array $categories = [
        CosmeticCategory::FINAL_KILL_EFFECT,
        CosmeticCategory::BED_BREAK_EFFECT,
        CosmeticCategory::DEATH_CRY,
        CosmeticCategory::KILL_SOUND,
        CosmeticCategory::KILL_MESSAGE,
        CosmeticCategory::WIN_EFFECT,
        CosmeticCategory::PROJECTILE_TRAIL,
        CosmeticCategory::VICTORY_DANCE,
        CosmeticCategory::HAT,
        CosmeticCategory::WING,
        CosmeticCategory::CAPE,
    ];

    public function openMain(Player $player): void{
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session === null){
            return;
        }

        $coins = $session->getCoins();
        $mgr = PlayerCosmeticsManager::getInstance();

        $content =
            "§7Coins: §6" . number_format($coins) . "\n\n" .
            "§7Final Kill: §f" . $this->selectedName($player, CosmeticCategory::FINAL_KILL_EFFECT) . "\n" .
            "§7Bed Break: §f" . $this->selectedName($player, CosmeticCategory::BED_BREAK_EFFECT) . "\n" .
            "§7Death Cry: §f" . $this->selectedName($player, CosmeticCategory::DEATH_CRY) . "\n" .
            "§7Kill Sound: §f" . $this->selectedName($player, CosmeticCategory::KILL_SOUND) . "\n" .
            "§7Kill Message: §f" . $this->selectedName($player, CosmeticCategory::KILL_MESSAGE) . "\n" .
            "§7Win Effect: §f" . $this->selectedName($player, CosmeticCategory::WIN_EFFECT) . "\n" .
            "§7Trail: §f" . $this->selectedName($player, CosmeticCategory::PROJECTILE_TRAIL) . "\n" .
            "§7Victory Dance: §f" . $this->selectedName($player, CosmeticCategory::VICTORY_DANCE) . "\n" .
            "§7Pet: §f" . $this->selectedName($player, CosmeticCategory::PET) . "\n" .
            "§7Backbling: §f" . $this->selectedName($player, CosmeticCategory::WING) . "\n" .
            "§7Cape: §f" . $this->selectedName($player, CosmeticCategory::CAPE) . "\n" .
            "§7Hat: §f" . $this->selectedName($player, CosmeticCategory::HAT);

        $form = new SimpleForm(function(Player $p, ?int $data): void{
            if($data === null){
                return;
            }

            $gui = new self();
            $cats = $gui->categories;

            if(!isset($cats[$data])){
                return;
            }

            $gui->openCategory($p, $cats[$data]);
        });

        $form->setTitle("§5§lC§dO§5§lS§dM§5§lE§dT§5§lI§dC§5§lS");
        $form->setContent($content);

        foreach($this->categories as $cat){
            $form->addButton(
                $cat->accent() . $cat->displayName() . "\n§7Selected: §f" . $this->selectedName($player, $cat),
                0,
                $cat->icon()
            );
        }

        $player->sendForm($form);
    }

    public function openCategory(Player $player, CosmeticCategory $category): void{
        $session = BedWarsCore::getInstance()->getSessionManager()->get($player);
        if($session === null){
            return;
        }

        $registry = CosmeticsRegistry::getInstance();
        $mgr = PlayerCosmeticsManager::getInstance();

        $selected = $mgr->getEquipped($player, $category);
        $coins = $session->getCoins();
        $items = array_values($registry->all($category));

        $content =
            $this->categoryBanner($category) .
            $category->accent() . "Selected: §f" . $this->selectedName($player, $category) . "\n" .
            "§7Coins: §6" . number_format($coins);

        $form = new SimpleForm(function(Player $p, ?int $data) use ($category, $items): void{
            if($data === null){
                (new CosmeticsGui())->openMain($p);
                return;
            }

            $session = BedWarsCore::getInstance()->getSessionManager()->get($p);
            if($session === null){
                return;
            }

            $mgr = PlayerCosmeticsManager::getInstance();

            if($data === 0){
                $mgr->equip($p, $category, null);
                self::refreshAppearance($p, $category);
                $p->sendMessage("§a§lCosmetics Updated§r§7 - Selection cleared.");
                (new CosmeticsGui())->openCategory($p, $category);
                return;
            }

            $i = $data - 1;
            if(!isset($items[$i])){
                return;
            }

            if(\sergittos\bedwars\cosmetics\wearable\ResourceCosmeticCatalog::isWearable($category)
                && !\sergittos\bedwars\cosmetics\wearable\ResourceCosmeticService::isReady()){
                $p->sendMessage("§cWearables are temporarily unavailable. No coins were charged.");
                return;
            }
            $def = CosmeticsRegistry::getInstance()->get($category, $items[$i]->getKey());
            if($def === null){ return; }
            $key = $def->getKey();

            if($mgr->getEquipped($p, $category) === $key){
                (new CosmeticsGui())->openCategory($p, $category);
                return;
            }

            if($mgr->hasPurchased($p, $category, $key)){
                $mgr->equip($p, $category, $key);
                self::refreshAppearance($p, $category);
                $p->sendMessage("§a§lCosmetics Equipped§r§7 - Now using §b" . $def->getDisplayName() . "§7.");
                (new CosmeticsGui())->openCategory($p, $category);
                return;
            }

            $price = $def->getPrice();
            if($price <= 0){
                $mgr->purchase($p, $category, $key);
                $mgr->equip($p, $category, $key);
                self::refreshAppearance($p, $category);
                $p->sendMessage("§a§lUnlocked§r§7 - Equipped §b" . $def->getDisplayName() . "§7.");
                (new CosmeticsGui())->openCategory($p, $category);
                return;
            }

            if($session->getCoins() < $price){
                $need = $price - $session->getCoins();
                $p->sendMessage("§c§lNot Enough Coins§r§7 - You need §6" . number_format($need) . " §7more coins to unlock this cosmetic.");
                (new CosmeticsGui())->openCategory($p, $category);
                return;
            }

            $session->setCoins($session->getCoins() - $price);
            $mgr->purchase($p, $category, $key);
            $mgr->equip($p, $category, $key);
            self::refreshAppearance($p, $category);

            $p->sendMessage("§6§lPurchase Successful§r§7 - Unlocked and equipped §b" . $def->getDisplayName() . "§7.");
            (new CosmeticsGui())->openCategory($p, $category);
        });

        $form->setTitle($category->accent() . "§l" . $category->displayName());
        $form->setContent($content);

        $form->addButton("§fNone\n§aFree", 0, "textures/ui/refresh");

        foreach($items as $def){
            $owned = PlayerCosmeticsManager::getInstance()->hasPurchased($player, $category, $def->getKey());
            $isSelected = ($selected !== null && $selected === $def->getKey());

            $status = $isSelected ? "§aSelected" : ($owned ? "§eOwned" : ("§6" . number_format($def->getPrice()) . " Coins"));

            $form->addButton(
                "§f" . $def->getDisplayName() . "\n" .
                $status . "\n" .
                $def->getRarity()->color() . $def->getRarity()->value,
                0,
                $def->getIconPath()
            );
        }

        $player->sendForm($form);
    }

    private static function refreshAppearance(Player $player, CosmeticCategory $category): void{
        if($category === CosmeticCategory::PET){
            PetRenderService::apply($player);
            return;
        }
        if($category === CosmeticCategory::WING || $category === CosmeticCategory::CAPE || $category === CosmeticCategory::HAT){
            WearableRenderService::applyAll($player);
        }
    }

    /**
     * A short decorative title line shown at the top of the Backbling and
     * Cape sub-menus (in addition to the form's own title bar), so those
     * two freshly-curated categories stand out a bit in the menu. Empty
     * for every other category - this is purely cosmetic and never
     * affects the form's actual title, buttons, or callback data indices.
     */
    private function categoryBanner(CosmeticCategory $category): string{
        if($category !== CosmeticCategory::WING && $category !== CosmeticCategory::CAPE){
            return "";
        }
        return $category->accent() . "§l✦ " . $category->displayName() . " ✦§r\n\n";
    }

    private function selectedName(Player $player, CosmeticCategory $category): string{
        $mgr = PlayerCosmeticsManager::getInstance();
        $key = $mgr->getEquipped($player, $category);
        if($key === null){
            return "None";
        }
        $def = CosmeticsRegistry::getInstance()->get($category, $key);
        return $def?->getDisplayName() ?? "None";
    }
}