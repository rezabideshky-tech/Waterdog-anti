<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan;

use pocketmine\plugin\Plugin;

/**
 * Reads the `clan:` section of BedWarsCore's config.yml. Every value has a
 * safe built-in default so the feature works even if a server owner never
 * touches config.yml (the section is written there for them regardless, see
 * config.yml itself).
 */
final class ClanConfig{

    private array $data;

    public function __construct(Plugin $plugin){
        $raw = $plugin->getConfig()->get("clan");
        $this->data = is_array($raw) ? $raw : [];
    }

    public function getCreationCost(): int{
        return (int) ($this->data["creation-cost"] ?? 7000);
    }

    public function getKillXpPercent(): float{
        return (float) ($this->data["xp-percent"]["kill"] ?? 10);
    }

    public function getWinXpPercent(): float{
        return (float) ($this->data["xp-percent"]["win"] ?? 15);
    }

    public function getBedXpPercent(): float{
        return (float) ($this->data["xp-percent"]["bed"] ?? 20);
    }

    public function getWeeklyWinWeight(): int{
        return (int) ($this->data["weekly-score"]["win-weight"] ?? 10);
    }

    public function getWeeklyFinalKillWeight(): int{
        return (int) ($this->data["weekly-score"]["final-kill-weight"] ?? 3);
    }

    public function getWeeklyLevelWeight(): int{
        return (int) ($this->data["weekly-score"]["level-weight"] ?? 2);
    }

    /** @return array<int,int> level => capacity, sorted ascending by level */
    public function getCapacityTable(): array{
        $raw = $this->data["capacity"] ?? null;
        if(!is_array($raw) || empty($raw)){
            $raw = [1 => 10, 3 => 15, 5 => 20, 7 => 30, 10 => 50];
        }
        $table = [];
        foreach($raw as $level => $capacity){
            $table[(int) $level] = (int) $capacity;
        }
        ksort($table);
        return $table;
    }

    public function getCapacityForLevel(int $level): int{
        $capacity = 10;
        foreach($this->getCapacityTable() as $lvl => $cap){
            if($level >= $lvl){
                $capacity = $cap;
            }
        }
        return $capacity;
    }

    /** XP required to go from $level to $level + 1. Quadratic growth, same shape as player leveling. */
    public function getRequiredXpForLevel(int $level): int{
        $n = max(0, $level - 1);
        return 4000 + ($n * 1500) + ($n * $n * 60);
    }

    public function getDailyBankDepositLimit(): int{
        return (int) ($this->data["bank"]["daily-deposit-limit"] ?? 5000);
    }

    public function getJoinCooldownSeconds(): int{
        return (int) ($this->data["join-cooldown-hours"] ?? 24) * 3600;
    }

    public function getWeeklyResetDayOfWeek(): int{
        // 0 = Sunday ... 6 = Saturday, matches PHP's `date("w")`.
        return (int) ($this->data["weekly-reset-day"] ?? 0);
    }

    public function getNameMinLength(): int{ return (int) ($this->data["name-min-length"] ?? 3); }
    public function getNameMaxLength(): int{ return (int) ($this->data["name-max-length"] ?? 24); }
    public function getTagMinLength(): int{ return (int) ($this->data["tag-min-length"] ?? 2); }
    public function getTagMaxLength(): int{ return (int) ($this->data["tag-max-length"] ?? 6); }
    public function getDescriptionMaxLength(): int{ return (int) ($this->data["description-max-length"] ?? 100); }
}
