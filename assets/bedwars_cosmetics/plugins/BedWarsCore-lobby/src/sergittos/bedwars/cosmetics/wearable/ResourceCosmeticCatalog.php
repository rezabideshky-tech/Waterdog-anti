<?php
declare(strict_types=1);
namespace sergittos\bedwars\cosmetics\wearable;

use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;

/** Server-side identifiers/prices only. No PNG or geometry is loaded from plugin_data. */
final class ResourceCosmeticCatalog{
    private static ?array $data = null;
    private static array $byCategory = [];
    public static function data(): array{
        if(self::$data === null){
            $stream = BedWarsCore::getInstance()->getResource("resource_cosmetics.json");
            if($stream === null){ throw new \RuntimeException("Missing bundled resource_cosmetics.json"); }
            try{ $raw = stream_get_contents($stream); }finally{ fclose($stream); }
            self::$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            foreach(self::$data["items"] as $row){ self::$byCategory[$row["category"]][$row["key"]] = $row; }
        }
        return self::$data;
    }
    public static function all(): array{ return self::data()["items"]; }
    public static function uuid(): string{ return self::data()["pack_uuid"]; }
    public static function isWearable(CosmeticCategory $category): bool{
        return in_array($category, [CosmeticCategory::HAT, CosmeticCategory::WING, CosmeticCategory::CAPE], true);
    }
    public static function canonical(CosmeticCategory $category, ?string $key): ?string{
        if($key === null || $key === "" || strtolower($key) === "none"){ return null; }
        if(!self::isWearable($category)){ return $key; }
        self::data();
        $key = strtolower($key);
        $key = self::$data["legacy_aliases"][$category->value][$key] ?? $key;
        return isset(self::$byCategory[$category->value][$key]) ? $key : null;
    }
    public static function selector(CosmeticCategory $category, ?string $key): int{
        $key = self::canonical($category, $key);
        return $key === null ? 0 : (int) self::$byCategory[$category->value][$key]["selector"];
    }
    public static function ownedKeys(CosmeticCategory $category, string $key): array{
        $canonical = self::canonical($category, $key);
        if($canonical === null){ return [$key]; }
        $keys = [$canonical];
        foreach(self::data()["legacy_aliases"][$category->value] ?? [] as $old => $new){
            if($new === $canonical){ $keys[] = $old; }
        }
        return array_unique($keys);
    }
    public static function encode(int $hat, int $back, int $cape): int{
        if($hat < 0 || $hat > 15 || $back < 0 || $back > 22 || $cape < 0 || $cape > 15){
            throw new \InvalidArgumentException("Wearable selector out of range");
        }
        return $hat + 16 * $back + 368 * $cape;
    }
}
