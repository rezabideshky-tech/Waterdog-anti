<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan;

/**
 * List of selectable clan crests shown in the crest picker GUI. Every entry
 * pairs a stable id (stored on the clan row) with a client texture path used
 * as the form button icon.
 *
 * These crests come from the bundled "ClanCrests" resource pack (see
 * resources/ClanCrests.zip, installed at runtime by
 * sergittos\bedwars\lobby\clan\ClanCrestResourcePackInstaller). Ids/names
 * are derived from the pack's own texture filenames
 * (textures/ui/icon_r{row}_c{col}_{color}.png -> "{Color} Crest {row}-{col}").
 */
final class ClanCrestRegistry{

    /** @var array<string,array{name:string,texture:string}> */
    private static array $crests = [
        "r1_c10_red" => ["name" => "Red Crest 1-10", "texture" => "textures/ui/icon_r1_c10_red"],
        "r1_c2_red" => ["name" => "Red Crest 1-2", "texture" => "textures/ui/icon_r1_c2_red"],
        "r1_c4_green" => ["name" => "Green Crest 1-4", "texture" => "textures/ui/icon_r1_c4_green"],
        "r1_c6_blue" => ["name" => "Blue Crest 1-6", "texture" => "textures/ui/icon_r1_c6_blue"],
        "r1_c7_pink" => ["name" => "Pink Crest 1-7", "texture" => "textures/ui/icon_r1_c7_pink"],
        "r1_c8_green" => ["name" => "Green Crest 1-8", "texture" => "textures/ui/icon_r1_c8_green"],
        "r1_c9_yellow" => ["name" => "Yellow Crest 1-9", "texture" => "textures/ui/icon_r1_c9_yellow"],
        "r2_c10_blue" => ["name" => "Blue Crest 2-10", "texture" => "textures/ui/icon_r2_c10_blue"],
        "r2_c2_pink" => ["name" => "Pink Crest 2-2", "texture" => "textures/ui/icon_r2_c2_pink"],
        "r2_c3_red" => ["name" => "Red Crest 2-3", "texture" => "textures/ui/icon_r2_c3_red"],
        "r2_c4_pink" => ["name" => "Pink Crest 2-4", "texture" => "textures/ui/icon_r2_c4_pink"],
        "r2_c8_green" => ["name" => "Green Crest 2-8", "texture" => "textures/ui/icon_r2_c8_green"],
        "r3_c10_pink" => ["name" => "Pink Crest 3-10", "texture" => "textures/ui/icon_r3_c10_pink"],
        "r3_c2_yellow" => ["name" => "Yellow Crest 3-2", "texture" => "textures/ui/icon_r3_c2_yellow"],
        "r3_c3_blue" => ["name" => "Blue Crest 3-3", "texture" => "textures/ui/icon_r3_c3_blue"],
        "r3_c3_green" => ["name" => "Green Crest 3-3", "texture" => "textures/ui/icon_r3_c3_green"],
        "r3_c3_pink" => ["name" => "Pink Crest 3-3", "texture" => "textures/ui/icon_r3_c3_pink"],
        "r3_c6_yellow" => ["name" => "Yellow Crest 3-6", "texture" => "textures/ui/icon_r3_c6_yellow"],
        "r3_c7_red" => ["name" => "Red Crest 3-7", "texture" => "textures/ui/icon_r3_c7_red"],
        "r3_c8_green" => ["name" => "Green Crest 3-8", "texture" => "textures/ui/icon_r3_c8_green"],
        "r4_c10_green" => ["name" => "Green Crest 4-10", "texture" => "textures/ui/icon_r4_c10_green"],
        "r4_c1_red" => ["name" => "Red Crest 4-1", "texture" => "textures/ui/icon_r4_c1_red"],
        "r4_c2_pink" => ["name" => "Pink Crest 4-2", "texture" => "textures/ui/icon_r4_c2_pink"],
        "r4_c3_red" => ["name" => "Red Crest 4-3", "texture" => "textures/ui/icon_r4_c3_red"],
        "r4_c4_pink" => ["name" => "Pink Crest 4-4", "texture" => "textures/ui/icon_r4_c4_pink"],
        "r4_c6_red" => ["name" => "Red Crest 4-6", "texture" => "textures/ui/icon_r4_c6_red"],
        "r4_c7_green" => ["name" => "Green Crest 4-7", "texture" => "textures/ui/icon_r4_c7_green"],
        "r4_c8_pink" => ["name" => "Pink Crest 4-8", "texture" => "textures/ui/icon_r4_c8_pink"],
        "r5_c10_green" => ["name" => "Green Crest 5-10", "texture" => "textures/ui/icon_r5_c10_green"],
        "r5_c1_blue" => ["name" => "Blue Crest 5-1", "texture" => "textures/ui/icon_r5_c1_blue"],
        "r5_c2_green" => ["name" => "Green Crest 5-2", "texture" => "textures/ui/icon_r5_c2_green"],
        "r5_c3_green" => ["name" => "Green Crest 5-3", "texture" => "textures/ui/icon_r5_c3_green"],
        "r5_c4_green" => ["name" => "Green Crest 5-4", "texture" => "textures/ui/icon_r5_c4_green"],
        "r5_c6_pink" => ["name" => "Pink Crest 5-6", "texture" => "textures/ui/icon_r5_c6_pink"],
        "r5_c7_pink" => ["name" => "Pink Crest 5-7", "texture" => "textures/ui/icon_r5_c7_pink"],
        "r5_c9_pink" => ["name" => "Pink Crest 5-9", "texture" => "textures/ui/icon_r5_c9_pink"],
        "r6_c2_green" => ["name" => "Green Crest 6-2", "texture" => "textures/ui/icon_r6_c2_green"],
        "r6_c3_red" => ["name" => "Red Crest 6-3", "texture" => "textures/ui/icon_r6_c3_red"],
        "r6_c4_pink" => ["name" => "Pink Crest 6-4", "texture" => "textures/ui/icon_r6_c4_pink"],
        "r6_c5_red" => ["name" => "Red Crest 6-5", "texture" => "textures/ui/icon_r6_c5_red"],
        "r6_c8_green" => ["name" => "Green Crest 6-8", "texture" => "textures/ui/icon_r6_c8_green"],
        "r6_c9_green" => ["name" => "Green Crest 6-9", "texture" => "textures/ui/icon_r6_c9_green"],
        "r7_c10_red" => ["name" => "Red Crest 7-10", "texture" => "textures/ui/icon_r7_c10_red"],
        "r7_c1_green" => ["name" => "Green Crest 7-1", "texture" => "textures/ui/icon_r7_c1_green"],
        "r7_c3_pink" => ["name" => "Pink Crest 7-3", "texture" => "textures/ui/icon_r7_c3_pink"],
        "r7_c4_blue" => ["name" => "Blue Crest 7-4", "texture" => "textures/ui/icon_r7_c4_blue"],
        "r7_c5_green" => ["name" => "Green Crest 7-5", "texture" => "textures/ui/icon_r7_c5_green"],
        "r7_c6_pink" => ["name" => "Pink Crest 7-6", "texture" => "textures/ui/icon_r7_c6_pink"],
        "r7_c8_green" => ["name" => "Green Crest 7-8", "texture" => "textures/ui/icon_r7_c8_green"],
        "r7_c9_red" => ["name" => "Red Crest 7-9", "texture" => "textures/ui/icon_r7_c9_red"],
        "r8_c1_yellow" => ["name" => "Yellow Crest 8-1", "texture" => "textures/ui/icon_r8_c1_yellow"],
        "r8_c3_pink" => ["name" => "Pink Crest 8-3", "texture" => "textures/ui/icon_r8_c3_pink"],
        "r8_c5_green" => ["name" => "Green Crest 8-5", "texture" => "textures/ui/icon_r8_c5_green"],
        "r8_c6_green" => ["name" => "Green Crest 8-6", "texture" => "textures/ui/icon_r8_c6_green"],
        "r8_c7_yellow" => ["name" => "Yellow Crest 8-7", "texture" => "textures/ui/icon_r8_c7_yellow"],
        "r8_c8_green" => ["name" => "Green Crest 8-8", "texture" => "textures/ui/icon_r8_c8_green"],
        "r8_c9_yellow" => ["name" => "Yellow Crest 8-9", "texture" => "textures/ui/icon_r8_c9_yellow"],
    ];

    /** @return array<string,array{name:string,texture:string}> */
    public static function all(): array{
        return self::$crests;
    }

    public static function exists(string $id): bool{
        return isset(self::$crests[$id]);
    }

    public static function getName(string $id): string{
        return self::$crests[$id]["name"] ?? "Unknown Crest";
    }

    public static function getTexture(string $id): string{
        return self::$crests[$id]["texture"] ?? self::getDefaultTexture();
    }

    private static function getDefaultTexture(): string{
        $first = self::$crests[self::getDefault()] ?? null;
        return $first["texture"] ?? "textures/ui/icon_r1_c2_red";
    }

    public static function getDefault(): string{
        $keys = array_keys(self::$crests);
        return $keys[0] ?? "r1_c2_red";
    }

    /**
     * Every crest is unlocked by default. Kept as a hook (rather than
     * hardcoding "true" at every call site) so a future update can gate
     * specific crests behind clan level, an achievement, etc. without
     * touching the GUI code that calls it.
     */
    public static function isUnlockedFor(string $crestId, int $clanLevel): bool{
        return self::exists($crestId);
    }
}
