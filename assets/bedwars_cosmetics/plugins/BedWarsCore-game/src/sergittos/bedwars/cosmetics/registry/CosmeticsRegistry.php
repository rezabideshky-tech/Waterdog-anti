<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics\registry;

use function array_values;

final class CosmeticsRegistry{

    private static ?CosmeticsRegistry $instance = null;

    /** @var array<string, array<string, CosmeticDefinition>> */
    private array $items = [];

    public static function getInstance(): CosmeticsRegistry{
        return self::$instance ??= new CosmeticsRegistry();
    }

    public function initDefaults(): void{
        $this->items = [];

        $this->add(new CosmeticDefinition("snowflake", CosmeticCategory::FINAL_KILL_EFFECT, "Snowflake", 8600, CosmeticRarity::RARE, "textures/items/snowball"));
        $this->add(new CosmeticDefinition("critical_hit", CosmeticCategory::FINAL_KILL_EFFECT, "Critical Hit", 9900, CosmeticRarity::RARE, "textures/items/diamond_sword"));
        $this->add(new CosmeticDefinition("water_splash", CosmeticCategory::FINAL_KILL_EFFECT, "Water Splash", 11100, CosmeticRarity::RARE, "textures/items/potion_bottle_splash"));
        $this->add(new CosmeticDefinition("happy_villager", CosmeticCategory::FINAL_KILL_EFFECT, "Happy Villager", 12400, CosmeticRarity::RARE, "textures/items/emerald"));
        $this->add(new CosmeticDefinition("enchant", CosmeticCategory::FINAL_KILL_EFFECT, "Enchant", 13700, CosmeticRarity::RARE, "textures/items/book_enchanted"));
        $this->add(new CosmeticDefinition("portal", CosmeticCategory::FINAL_KILL_EFFECT, "Portal", 15000, CosmeticRarity::LEGENDARY, "textures/blocks/portal"));
        $this->add(new CosmeticDefinition("flame", CosmeticCategory::FINAL_KILL_EFFECT, "Flame", 6000, CosmeticRarity::COMMON, "textures/items/fire_charge"));
        $this->add(new CosmeticDefinition("hearts", CosmeticCategory::FINAL_KILL_EFFECT, "Hearts", 7300, CosmeticRarity::COMMON, "textures/items/apple_golden"));

        $this->add(new CosmeticDefinition("flame", CosmeticCategory::BED_BREAK_EFFECT, "Flame", 11000, CosmeticRarity::COMMON, "textures/items/fire_charge"));
        $this->add(new CosmeticDefinition("hearts", CosmeticCategory::BED_BREAK_EFFECT, "Hearts", 11700, CosmeticRarity::COMMON, "textures/items/apple_golden"));
        $this->add(new CosmeticDefinition("snowflake", CosmeticCategory::BED_BREAK_EFFECT, "Snowflake", 12400, CosmeticRarity::RARE, "textures/items/snowball"));
        $this->add(new CosmeticDefinition("shatter", CosmeticCategory::BED_BREAK_EFFECT, "Shatter", 13100, CosmeticRarity::RARE, "textures/blocks/glass"));
        $this->add(new CosmeticDefinition("water_splash", CosmeticCategory::BED_BREAK_EFFECT, "Water Splash", 13900, CosmeticRarity::RARE, "textures/items/potion_bottle_splash"));
        $this->add(new CosmeticDefinition("happy_villager", CosmeticCategory::BED_BREAK_EFFECT, "Happy Villager", 14600, CosmeticRarity::RARE, "textures/items/emerald"));
        $this->add(new CosmeticDefinition("enchant", CosmeticCategory::BED_BREAK_EFFECT, "Enchant", 15300, CosmeticRarity::RARE, "textures/items/book_enchanted"));
        $this->add(new CosmeticDefinition("portal", CosmeticCategory::BED_BREAK_EFFECT, "Portal", 16000, CosmeticRarity::LEGENDARY, "textures/blocks/portal"));

        $this->add(new CosmeticDefinition("skeleton", CosmeticCategory::DEATH_CRY, "Skeleton", 6000, CosmeticRarity::COMMON, "textures/items/bone"));
        $this->add(new CosmeticDefinition("blaze", CosmeticCategory::DEATH_CRY, "Blaze", 6900, CosmeticRarity::COMMON, "textures/items/blaze_rod"));
        $this->add(new CosmeticDefinition("cat_meow", CosmeticCategory::DEATH_CRY, "Cat Meow", 7700, CosmeticRarity::COMMON, "textures/items/fish_salmon_raw"));
        $this->add(new CosmeticDefinition("pling", CosmeticCategory::DEATH_CRY, "Pling", 8600, CosmeticRarity::COMMON, "textures/items/record_11"));
        $this->add(new CosmeticDefinition("ghast", CosmeticCategory::DEATH_CRY, "Ghast", 10300, CosmeticRarity::RARE, "textures/items/ghast_tear"));
        $this->add(new CosmeticDefinition("explosion", CosmeticCategory::DEATH_CRY, "Explosion", 11100, CosmeticRarity::RARE, "textures/items/gunpowder"));
        $this->add(new CosmeticDefinition("villager_no", CosmeticCategory::DEATH_CRY, "Villager", 9400, CosmeticRarity::COMMON, "textures/items/emerald"));
        $this->add(new CosmeticDefinition("dragon", CosmeticCategory::DEATH_CRY, "Dragon", 12000, CosmeticRarity::LEGENDARY, "textures/items/dragon_breath"));

        $this->add(new CosmeticDefinition("orb", CosmeticCategory::KILL_SOUND, "Orb", 6000, CosmeticRarity::COMMON, "textures/items/experience_bottle"));
        $this->add(new CosmeticDefinition("levelup", CosmeticCategory::KILL_SOUND, "Level Up", 7500, CosmeticRarity::COMMON, "textures/items/experience_bottle"));
        $this->add(new CosmeticDefinition("anvil", CosmeticCategory::KILL_SOUND, "Anvil", 10500, CosmeticRarity::RARE, "textures/blocks/anvil"));
        $this->add(new CosmeticDefinition("thunder", CosmeticCategory::KILL_SOUND, "Thunder", 12000, CosmeticRarity::RARE, "textures/items/nether_star"));
        $this->add(new CosmeticDefinition("pling", CosmeticCategory::KILL_SOUND, "Pling", 9000, CosmeticRarity::COMMON, "textures/blocks/noteblock"));

        $this->add(new CosmeticDefinition("savage", CosmeticCategory::KILL_MESSAGE, "Savage", 7000, CosmeticRarity::COMMON, "textures/items/name_tag"));
        $this->add(new CosmeticDefinition("royal", CosmeticCategory::KILL_MESSAGE, "Royal Decree", 8600, CosmeticRarity::RARE, "textures/items/name_tag"));
        $this->add(new CosmeticDefinition("assassin", CosmeticCategory::KILL_MESSAGE, "Silent Blade", 10200, CosmeticRarity::RARE, "textures/items/name_tag"));
        $this->add(new CosmeticDefinition("warlord", CosmeticCategory::KILL_MESSAGE, "Warlord", 11800, CosmeticRarity::RARE, "textures/items/name_tag"));
        $this->add(new CosmeticDefinition("champion", CosmeticCategory::KILL_MESSAGE, "Champion", 13400, CosmeticRarity::LEGENDARY, "textures/items/name_tag"));
        $this->add(new CosmeticDefinition("phantom", CosmeticCategory::KILL_MESSAGE, "Phantom", 15000, CosmeticRarity::LEGENDARY, "textures/items/name_tag"));

        $this->add(new CosmeticDefinition("victory_flame", CosmeticCategory::WIN_EFFECT, "Victory Flame", 6000, CosmeticRarity::COMMON, "textures/items/fire_charge"));
        $this->add(new CosmeticDefinition("victory_hearts", CosmeticCategory::WIN_EFFECT, "Victory Hearts", 7000, CosmeticRarity::COMMON, "textures/items/apple_golden"));
        $this->add(new CosmeticDefinition("victory_enchant", CosmeticCategory::WIN_EFFECT, "Arcane Triumph", 9000, CosmeticRarity::RARE, "textures/items/book_enchanted"));
        $this->add(new CosmeticDefinition("victory_portal", CosmeticCategory::WIN_EFFECT, "Dimensional Crown", 12000, CosmeticRarity::LEGENDARY, "textures/blocks/portal"));
        $this->add(new CosmeticDefinition("victory_critical", CosmeticCategory::WIN_EFFECT, "Champion Burst", 10000, CosmeticRarity::RARE, "textures/items/nether_star"));
        $this->add(new CosmeticDefinition("victory_snowflake", CosmeticCategory::WIN_EFFECT, "Victory Snowflake", 8000, CosmeticRarity::COMMON, "textures/items/snowball"));
        $this->add(new CosmeticDefinition("victory_happy", CosmeticCategory::WIN_EFFECT, "Victory Villager", 11000, CosmeticRarity::RARE, "textures/items/emerald"));

        $this->add(new CosmeticDefinition("trail_flame", CosmeticCategory::PROJECTILE_TRAIL, "Flame Trail", 9000, CosmeticRarity::COMMON, "textures/items/fire_charge"));
        $this->add(new CosmeticDefinition("trail_hearts", CosmeticCategory::PROJECTILE_TRAIL, "Hearts Trail", 11500, CosmeticRarity::COMMON, "textures/items/apple_golden"));
        $this->add(new CosmeticDefinition("trail_portal", CosmeticCategory::PROJECTILE_TRAIL, "Portal Trail", 14000, CosmeticRarity::RARE, "textures/blocks/portal"));
        $this->add(new CosmeticDefinition("trail_enchant", CosmeticCategory::PROJECTILE_TRAIL, "Enchant Trail", 16500, CosmeticRarity::RARE, "textures/items/book_enchanted"));
        $this->add(new CosmeticDefinition("trail_critical", CosmeticCategory::PROJECTILE_TRAIL, "Critical Trail", 19000, CosmeticRarity::RARE, "textures/items/diamond_sword"));

        $this->add(new CosmeticDefinition("hooray", CosmeticCategory::VICTORY_DANCE, "Hooray!", 15000, CosmeticRarity::COMMON, "textures/items/nether_star"));
        $this->add(new CosmeticDefinition("groovin", CosmeticCategory::VICTORY_DANCE, "Groovin'", 15800, CosmeticRarity::COMMON, "textures/items/record_13"));
        $this->add(new CosmeticDefinition("cheer_routine", CosmeticCategory::VICTORY_DANCE, "Cheer Routine", 16700, CosmeticRarity::RARE, "textures/items/record_cat"));
        $this->add(new CosmeticDefinition("salsa", CosmeticCategory::VICTORY_DANCE, "Salsa Dancing", 17500, CosmeticRarity::RARE, "textures/items/record_mall"));
        $this->add(new CosmeticDefinition("breakdance", CosmeticCategory::VICTORY_DANCE, "Breakdance", 18300, CosmeticRarity::RARE, "textures/items/record_blocks"));
        $this->add(new CosmeticDefinition("victory_cheer", CosmeticCategory::VICTORY_DANCE, "Victory Cheer", 19200, CosmeticRarity::LEGENDARY, "textures/items/nether_star"));
        $this->add(new CosmeticDefinition("sonic_spin", CosmeticCategory::VICTORY_DANCE, "Champion Spin", 20000, CosmeticRarity::LEGENDARY, "textures/items/record_far"));

        // --- Pets (lobby-only wearable cosmetics) ---
        $this->add(new CosmeticDefinition("babydragon", CosmeticCategory::PET, "Baby Dragon", 500000, CosmeticRarity::LEGENDARY, "textures/items/egg"));
        $this->add(new CosmeticDefinition("cubee", CosmeticCategory::PET, "Cubee", 100000, CosmeticRarity::COMMON, "textures/items/egg"));
        $this->add(new CosmeticDefinition("endolotl", CosmeticCategory::PET, "Endolotl", 220000, CosmeticRarity::RARE, "textures/items/egg"));
        $this->add(new CosmeticDefinition("pirateparrot", CosmeticCategory::PET, "Pirate Parrot", 230000, CosmeticRarity::RARE, "textures/items/egg"));
        $this->add(new CosmeticDefinition("pirateship", CosmeticCategory::PET, "Pirate Ship", 480000, CosmeticRarity::LEGENDARY, "textures/items/egg"));
        $this->add(new CosmeticDefinition("witchcat", CosmeticCategory::PET, "Witch Cat", 240000, CosmeticRarity::RARE, "textures/items/egg"));
        $this->add(new CosmeticDefinition("rock", CosmeticCategory::PET, "Rock", 90000, CosmeticRarity::COMMON, "textures/items/egg"));

        // One shared, versioned catalog supplies both lobby and game servers.
        foreach(\sergittos\bedwars\cosmetics\wearable\ResourceCosmeticCatalog::all() as $row){
            $this->add(new CosmeticDefinition($row["key"], CosmeticCategory::from($row["category"]),
                $row["name"], (int) $row["price"], CosmeticRarity::from($row["rarity"]), $row["icon"]));
        }
    }

    private function add(CosmeticDefinition $def): void{
        $this->items[$def->getCategory()->value][$def->getKey()] = $def;
    }

    /** @return CosmeticDefinition[] */
    public function all(CosmeticCategory $category): array{
        return array_values($this->items[$category->value] ?? []);
    }

    public function get(CosmeticCategory $category, string $key): ?CosmeticDefinition{
        $key = \sergittos\bedwars\cosmetics\wearable\ResourceCosmeticCatalog::canonical($category, $key);
        return $key === null ? null : ($this->items[$category->value][$key] ?? null);
    }
}