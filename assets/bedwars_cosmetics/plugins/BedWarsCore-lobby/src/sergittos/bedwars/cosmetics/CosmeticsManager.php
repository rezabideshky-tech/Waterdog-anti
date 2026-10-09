<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics;

use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;

class CosmeticsManager {

    /** @var array<string, array<string, CosmeticDefinition>> */
    private array $cosmetics = [
        "particle"    => [],
        "cape"        => [],
        "wing"        => [],
        "hat"         => [],
        "kill_effect" => [],
        "kill_sound"  => [],
    ];

    public function __construct(Plugin $plugin) {
        $cfg = new Config($plugin->getDataFolder() . "cosmetics.yml", Config::YAML);
        foreach ($cfg->getAll() as $type => $items) {
            if (!array_key_exists($type, $this->cosmetics)) continue;
            foreach ((array)$items as $id => $data) {
                $this->cosmetics[$type][$id] = new CosmeticDefinition(
                    id:          (string)$id,
                    type:        $type,
                    name:        $data["name"]        ?? $id,
                    description: $data["description"] ?? "",
                    rarity:      $data["rarity"]      ?? "common",
                    permission:  $data["permission"]  ?? "",
                    price:       (int)($data["price"] ?? 0),
                    texture:     $data["texture"]     ?? "",
                    particleId:  $data["particle_id"] ?? "",
                    soundId:     $data["sound_id"]    ?? "",
                );
            }
        }
        $plugin->getLogger()->info("§aLoaded " . array_sum(array_map("count", $this->cosmetics)) . " cosmetics.");
    }

    /** @return CosmeticDefinition[] */
    public function getByType(string $type): array {
        return $this->cosmetics[$type] ?? [];
    }

    public function get(string $type, string $id): ?CosmeticDefinition {
        return $this->cosmetics[$type][$id] ?? null;
    }

    /**
     * بررسی اینکه بازیکن می‌تونه این کازمتیک رو استفاده کنه
     * اگه permission خالی باشه = همه می‌تونن (unlock شده یا free)
     */
    public function canUse(\pocketmine\player\Player $player, CosmeticDefinition $def): bool {
        if ($def->permission === "") return true;
        return $player->hasPermission($def->permission);
    }
}
