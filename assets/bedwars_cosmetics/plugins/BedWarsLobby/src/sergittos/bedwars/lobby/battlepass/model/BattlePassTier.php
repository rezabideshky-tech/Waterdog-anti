<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\model;

use sergittos\bedwars\cosmetics\registry\CosmeticsRegistry;
use function is_array;
use function number_format;

final class BattlePassTier{

    public function __construct(
        private int $tier,
        private int $xpRequired,
        private array $freeRewards,
        private array $premiumRewards
    ){}

    public static function fromConfig(int $tier, int $xpRequired, array $data): self{
        return new self(
            $tier,
            $xpRequired,
            is_array($data["free"] ?? null) ? $data["free"] : [],
            is_array($data["premium"] ?? null) ? $data["premium"] : []
        );
    }

    public function toConfig(): array{
        return [
            "free" => $this->freeRewards,
            "premium" => $this->premiumRewards
        ];
    }

    public function getTier(): int{ return $this->tier; }
    public function getXpRequired(): int{ return $this->xpRequired; }

    /** @return array[] */
    public function getFreeRewards(): array{ return $this->freeRewards; }

    /** @return array[] */
    public function getPremiumRewards(): array{ return $this->premiumRewards; }

    public function withFreeRewards(array $rewards): self{
        return new self($this->tier, $this->xpRequired, $rewards, $this->premiumRewards);
    }

    public function withPremiumRewards(array $rewards): self{
        return new self($this->tier, $this->xpRequired, $this->freeRewards, $rewards);
    }

    public function hasPremiumReward(): bool{
        return $this->premiumRewards !== [];
    }

    public function getRewardPreview(array $rewards): string{
        $coins = 0;
        foreach($rewards as $r){
            if(is_array($r) && ($r["type"] ?? "") === RewardType::COINS){
                $coins += (int) ($r["amount"] ?? 0);
            }
        }
        if($coins > 0){
            return "§e" . number_format($coins) . " §6Coins";
        }

        foreach($rewards as $r){
            if(!is_array($r) || ($r["type"] ?? "") !== RewardType::COSMETIC){
                continue;
            }

            $catRaw = (string) ($r["category"] ?? "");
            $key = (string) ($r["key"] ?? "");
            if($catRaw === "" || $key === ""){
                continue;
            }

            $cat = \sergittos\bedwars\cosmetics\registry\CosmeticCategory::tryFrom($catRaw);
            if($cat === null){
                continue;
            }

            $def = CosmeticsRegistry::getInstance()->get($cat, $key);
            if($def !== null){
                return $def->getRarity()->color() . $def->getDisplayName();
            }

            return "§bCosmetic §7(" . $key . ")";
        }

        foreach($rewards as $r){
            if(is_array($r) && ($r["type"] ?? "") === RewardType::COMMAND){
                return "§dSpecial Reward";
            }
        }

        return "§7Empty";
    }

    public function getFreeRewardPreview(): string{ return $this->getRewardPreview($this->freeRewards); }
    public function getPremiumRewardPreview(): string{ return $this->hasPremiumReward() ? $this->getRewardPreview($this->premiumRewards) : "§7—"; }
}
