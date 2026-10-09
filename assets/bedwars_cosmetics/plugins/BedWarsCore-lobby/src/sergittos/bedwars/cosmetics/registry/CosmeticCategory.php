<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics\registry;

enum CosmeticCategory: string{
    case FINAL_KILL_EFFECT = "final_kill_effect";
    case BED_BREAK_EFFECT = "bed_break_effect";
    case DEATH_CRY = "death_cry";
    case KILL_SOUND = "kill_sound";
    case KILL_MESSAGE = "kill_message";
    case WIN_EFFECT = "win_effect";
    case PROJECTILE_TRAIL = "projectile_trail";
    case VICTORY_DANCE = "victory_dance";
    case PET = "pet";
    case WING = "wing_wearable";
    case CAPE = "cape_wearable";
    case HAT = "hat_wearable";

    public function displayName(): string{
        return match($this){
            self::FINAL_KILL_EFFECT => "Final Kill Effect",
            self::BED_BREAK_EFFECT => "Bed Break Effect",
            self::DEATH_CRY => "Death Cry",
            self::KILL_SOUND => "Kill Sound",
            self::KILL_MESSAGE => "Kill Message",
            self::WIN_EFFECT => "Win Effect",
            self::PROJECTILE_TRAIL => "Projectile Trail",
            self::VICTORY_DANCE => "Victory Dance",
            self::PET => "Pets",
            self::WING => "Backbling",
            self::CAPE => "Capes",
            self::HAT => "Hats",
        };
    }

    // Category icons: custom PNGs shipped in the "arvan ui" resource pack at
    // textures/ui/cosmeticicon/<name>.png (added from the bedwars_f0
    // resource), one per category, replacing the old vanilla-item icons
    // below (kept in a comment for reference in case of rollback).
    // FINAL_KILL_EFFECT -> textures/items/diamond_sword
    // BED_BREAK_EFFECT  -> textures/items/bed_red
    // DEATH_CRY         -> textures/items/record_11
    // KILL_SOUND        -> textures/blocks/noteblock
    // KILL_MESSAGE      -> textures/items/name_tag
    // WIN_EFFECT        -> textures/items/nether_star
    // PROJECTILE_TRAIL  -> textures/items/arrow
    // VICTORY_DANCE     -> textures/items/record_13
    // PET               -> textures/items/egg
    // WING              -> textures/ui/backbling
    // CAPE              -> textures/blocks/wool_colored_gray
    // HAT               -> textures/items/leather_helmet
    public function icon(): string{
        return match($this){
            self::FINAL_KILL_EFFECT => "textures/ui/cosmeticicon/finalkilleffect",
            self::BED_BREAK_EFFECT => "textures/ui/cosmeticicon/bedbreakeffect",
            self::DEATH_CRY => "textures/ui/cosmeticicon/deathcry",
            self::KILL_SOUND => "textures/ui/cosmeticicon/killsound",
            self::KILL_MESSAGE => "textures/ui/cosmeticicon/killmessage",
            self::WIN_EFFECT => "textures/ui/cosmeticicon/wineffect",
            self::PROJECTILE_TRAIL => "textures/ui/cosmeticicon/projectiletrail",
            self::VICTORY_DANCE => "textures/ui/cosmeticicon/victorydance",
            self::PET => "textures/ui/cosmeticicon/pet",
            self::WING => "textures/ui/arvan_cosmetics/category_backbling",
            self::CAPE => "textures/ui/arvan_cosmetics/category_cape",
            self::HAT => "textures/ui/arvan_cosmetics/category_hat",
        };
    }

    public function accent(): string{
        return match($this){
            self::FINAL_KILL_EFFECT => "§c",
            self::BED_BREAK_EFFECT => "§6",
            self::DEATH_CRY => "§b",
            self::KILL_SOUND => "§d",
            self::KILL_MESSAGE => "§4",
            self::WIN_EFFECT => "§a",
            self::PROJECTILE_TRAIL => "§7",
            self::VICTORY_DANCE => "§e",
            self::PET => "§d",
            self::WING => "§b",
            self::CAPE => "§6",
            self::HAT => "§a",
        };
    }
}