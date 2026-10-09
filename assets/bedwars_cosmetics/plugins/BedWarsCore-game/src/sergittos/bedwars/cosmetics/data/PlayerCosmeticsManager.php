<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics\data;

use pocketmine\player\Player;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\session\Session;

final class PlayerCosmeticsManager{

    private static ?PlayerCosmeticsManager $instance = null;

    public static function getInstance(): PlayerCosmeticsManager{
        return self::$instance ??= new PlayerCosmeticsManager();
    }

    private function session(Player $player): ?Session{
        return BedWarsCore::getInstance()->getSessionManager()->get($player);
    }

    private function purchaseId(CosmeticCategory $category, string $key): string{
        return $category->value . ":" . $key;
    }

    public function getEquipped(Player $player, CosmeticCategory $category): ?string{
        $s = $this->session($player);
        if($s === null){
            return null;
        }

        $val = match($category){
            CosmeticCategory::FINAL_KILL_EFFECT => $s->getSelectedKillEffect(),
            CosmeticCategory::BED_BREAK_EFFECT => $s->getSelectedCape(),
            CosmeticCategory::DEATH_CRY => $s->getSelectedWing(),
            CosmeticCategory::KILL_SOUND => $s->getSelectedKillSound(),
            CosmeticCategory::KILL_MESSAGE => $s->getSelectedKillMessage(),
            CosmeticCategory::WIN_EFFECT => $s->getSelectedHat(),
            CosmeticCategory::PROJECTILE_TRAIL => $s->getSelectedParticle(),
            CosmeticCategory::VICTORY_DANCE => $s->getSelectedDance(),
            CosmeticCategory::PET => $s->getWearablePet(),
            CosmeticCategory::WING => $s->getWearableWing(),
            CosmeticCategory::CAPE => $s->getWearableCape(),
            CosmeticCategory::HAT => $s->getWearableHat(),
        };

        if($val === "" || strtolower($val) === "none"){
            return null;
        }

        return \sergittos\bedwars\cosmetics\wearable\ResourceCosmeticCatalog::canonical($category, $val);
    }

    public function equip(Player $player, CosmeticCategory $category, ?string $key): void{
        $s = $this->session($player);
        if($s === null){
            return;
        }

        $resolved = \sergittos\bedwars\cosmetics\wearable\ResourceCosmeticCatalog::canonical($category, $key);
        if($key !== null && $resolved === null){ return; }
        $val = $resolved ?? "none";

        match($category){
            CosmeticCategory::FINAL_KILL_EFFECT => $s->setSelectedKillEffect($val),
            CosmeticCategory::BED_BREAK_EFFECT => $s->setSelectedCape($val),
            CosmeticCategory::DEATH_CRY => $s->setSelectedWing($val),
            CosmeticCategory::KILL_SOUND => $s->setSelectedKillSound($val),
            CosmeticCategory::KILL_MESSAGE => $s->setSelectedKillMessage($val),
            CosmeticCategory::WIN_EFFECT => $s->setSelectedHat($val),
            CosmeticCategory::PROJECTILE_TRAIL => $s->setSelectedParticle($val),
            CosmeticCategory::VICTORY_DANCE => $s->setSelectedDance($val),
            CosmeticCategory::PET => $s->setWearablePet($val),
            CosmeticCategory::WING => $s->setWearableWing($val),
            CosmeticCategory::CAPE => $s->setWearableCape($val),
            CosmeticCategory::HAT => $s->setWearableHat($val),
        };
    }

    public function hasPurchased(Player $player, CosmeticCategory $category, string $key): bool{
        $s = $this->session($player);
        if($s === null){
            return false;
        }
        foreach(\sergittos\bedwars\cosmetics\wearable\ResourceCosmeticCatalog::ownedKeys($category, $key) as $candidate){
            if($s->hasCosmetic($this->purchaseId($category, $candidate))){ return true; }
        }
        return false;
    }

    public function purchase(Player $player, CosmeticCategory $category, string $key): void{
        $s = $this->session($player);
        if($s === null){
            return;
        }
        $key = \sergittos\bedwars\cosmetics\wearable\ResourceCosmeticCatalog::canonical($category, $key);
        if($key !== null){ $s->unlockCosmetic($this->purchaseId($category, $key)); }
    }
}