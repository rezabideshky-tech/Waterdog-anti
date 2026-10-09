<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\registry;

use pocketmine\utils\Config;
use sergittos\bedwars\lobby\battlepass\model\BattlePassTier;
use function is_array;
use function is_numeric;
use function max;
use function min;
use function time;

final class BattlePassRegistry{

    private const DEFAULT_TIER_COUNT = 50;
    private const DEFAULT_XP_PER_TIER = 1000;
    private const DEFAULT_DURATION_SECONDS = 60 * 24 * 3600;

    private Config $cfg;

    private string $seasonId = "season_1";
    private string $seasonName = "§l§dSeason One §r§8- §f§lGenesis";
    private int $start = 0;
    private int $end = 0;
    private int $xpPerTier = self::DEFAULT_XP_PER_TIER;
    private int $tierCount = self::DEFAULT_TIER_COUNT;

    /** @var array<int, BattlePassTier> */
    private array $tiers = [];

    public function __construct(private string $path){
        $this->cfg = new Config($path, Config::YAML);
    }

    public function reload(): void{
        $season = $this->cfg->get("season", null);

        if(!is_array($season) || ($season["id"] ?? "") === ""){
            $this->seedDefaults();
            return;
        }

        $this->seasonId = (string) ($season["id"] ?? "season_1");
        $this->seasonName = (string) ($season["name"] ?? $this->seasonName);
        $this->start = (int) ($season["start"] ?? 0);
        $this->end = (int) ($season["end"] ?? 0);
        $this->xpPerTier = max(1, (int) ($season["xp_per_tier"] ?? self::DEFAULT_XP_PER_TIER));
        $this->tierCount = max(1, (int) ($season["tier_count"] ?? self::DEFAULT_TIER_COUNT));

        $tiersRaw = $this->cfg->get("tiers", []);
        $tiersRaw = is_array($tiersRaw) ? $tiersRaw : [];

        $this->tiers = [];
        for($t = 1; $t <= $this->tierCount; $t++){
            $data = is_array($tiersRaw[$t] ?? null) ? $tiersRaw[$t] : [];
            $this->tiers[$t] = BattlePassTier::fromConfig($t, $t * $this->xpPerTier, $data);
        }
    }

    private function seedDefaults(): void{
        $now = time();

        $this->seasonId = "season_1";
        $this->seasonName = "§l§dSeason One §r§8- §f§lGenesis";
        $this->start = $now;
        $this->end = $now + self::DEFAULT_DURATION_SECONDS;
        $this->xpPerTier = self::DEFAULT_XP_PER_TIER;
        $this->tierCount = self::DEFAULT_TIER_COUNT;

        $milestoneCosmetics = [
            5  => ["category" => "kill_sound", "key" => "orb"],
            10 => ["category" => "bed_break_effect", "key" => "flame"],
            15 => ["category" => "death_cry", "key" => "skeleton"],
            20 => ["category" => "final_kill_effect", "key" => "flame"],
            25 => ["category" => "projectile_trail", "key" => "trail_flame"],
            30 => ["category" => "win_effect", "key" => "victory_flame"],
            35 => ["category" => "kill_sound", "key" => "thunder"],
            40 => ["category" => "bed_break_effect", "key" => "portal"],
            45 => ["category" => "final_kill_effect", "key" => "portal"],
            50 => ["category" => "win_effect", "key" => "victory_portal"],
        ];

        $this->tiers = [];
        for($t = 1; $t <= $this->tierCount; $t++){
            $freeCoins = 200 + ($t * 40);
            $premiumCoins = 500 + ($t * 120);

            $free = [["type" => "coins", "amount" => $freeCoins]];
            $premium = [["type" => "coins", "amount" => $premiumCoins]];

            if(isset($milestoneCosmetics[$t])){
                $premium[] = [
                    "type" => "cosmetic",
                    "category" => $milestoneCosmetics[$t]["category"],
                    "key" => $milestoneCosmetics[$t]["key"],
                    "equip" => false
                ];
            }

            $this->tiers[$t] = new BattlePassTier($t, $t * $this->xpPerTier, $free, $premium);
        }

        $this->persist();
    }

    public function persist(): void{
        $this->cfg->set("season", [
            "id" => $this->seasonId,
            "name" => $this->seasonName,
            "start" => $this->start,
            "end" => $this->end,
            "xp_per_tier" => $this->xpPerTier,
            "tier_count" => $this->tierCount
        ]);

        $tiersOut = [];
        foreach($this->tiers as $tier => $def){
            $tiersOut[$tier] = $def->toConfig();
        }
        $this->cfg->set("tiers", $tiersOut);
        $this->cfg->save();
    }

    public function getSeasonId(): string{ return $this->seasonId; }
    public function getSeasonName(): string{ return $this->seasonName; }
    public function getStart(): int{ return $this->start; }
    public function getEnd(): int{ return $this->end; }
    public function getXpPerTier(): int{ return $this->xpPerTier; }
    public function getTierCount(): int{ return $this->tierCount; }

    public function isActive(): bool{
        $now = time();
        return $this->start <= $now && $now <= $this->end;
    }

    public function hasEnded(): bool{ return time() > $this->end; }

    /** @return BattlePassTier[] indexed by tier number */
    public function getTiers(): array{ return $this->tiers; }

    public function getTier(int $tier): ?BattlePassTier{ return $this->tiers[$tier] ?? null; }

    public function getMaxXp(): int{ return $this->tierCount * $this->xpPerTier; }

    public function tierForXp(int $xp): int{
        $tier = intdiv(max(0, $xp), $this->xpPerTier);
        return max(0, min($this->tierCount, $tier));
    }

    public function setSeason(string $name, int $durationDays, int $xpPerTier, int $tierCount): void{
        $now = time();

        $this->seasonId = "season_" . $now;
        $this->seasonName = $name;
        $this->start = $now;
        $this->end = $now + max(1, $durationDays) * 86400;
        $this->xpPerTier = max(1, $xpPerTier);
        $this->tierCount = max(1, $tierCount);

        $existing = $this->tiers;
        $this->tiers = [];
        for($t = 1; $t <= $this->tierCount; $t++){
            $prev = $existing[$t] ?? null;
            $this->tiers[$t] = new BattlePassTier(
                $t,
                $t * $this->xpPerTier,
                $prev !== null ? $prev->getFreeRewards() : [["type" => "coins", "amount" => 200 + ($t * 40)]],
                $prev !== null ? $prev->getPremiumRewards() : [["type" => "coins", "amount" => 500 + ($t * 120)]]
            );
        }

        $this->persist();
    }

    public function setTierReward(int $tier, bool $premium, array $reward): bool{
        if(!isset($this->tiers[$tier])){
            return false;
        }

        $def = $this->tiers[$tier];
        $rewards = $premium ? $def->getPremiumRewards() : $def->getFreeRewards();
        $rewards[] = $reward;

        $this->tiers[$tier] = $premium ? $def->withPremiumRewards($rewards) : $def->withFreeRewards($rewards);
        $this->persist();
        return true;
    }

    public function clearTierRewards(int $tier, bool $premium): bool{
        if(!isset($this->tiers[$tier])){
            return false;
        }

        $def = $this->tiers[$tier];
        $this->tiers[$tier] = $premium ? $def->withPremiumRewards([]) : $def->withFreeRewards([]);
        $this->persist();
        return true;
    }
}
