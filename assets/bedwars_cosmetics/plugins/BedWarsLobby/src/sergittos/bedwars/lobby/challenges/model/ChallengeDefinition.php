<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\challenges\model;

use sergittos\bedwars\cosmetics\registry\CosmeticsRegistry;
use function is_array;
use function number_format;
use function strip_tags;

final class ChallengeDefinition{

    private function __construct(
        private string $id,
        private string $name,
        private string $icon,
        private string $requirement,
        private int $goal,
        private int $start,
        private int $end,
        private int $claimGraceHours,
        private array $rewards
    ){}

    public static function fromConfig(string $id, array $data): self{
        return new self(
            $id,
            (string)($data["name"] ?? $id),
            (string)($data["icon"] ?? "textures/items/book"),
            (string)($data["requirement"] ?? RequirementType::KILLS),
            (int)($data["goal"] ?? 1),
            (int)($data["start"] ?? 0),
            (int)($data["end"] ?? 0),
            (int)($data["claim_grace_hours"] ?? 72),
            is_array($data["rewards"] ?? null) ? $data["rewards"] : []
        );
    }

    public function getId(): string{ return $this->id; }
    public function getName(): string{ return $this->name; }
    public function getNamePlain(): string{ return strip_tags($this->name); }
    public function getColoredName(): string{ return "§l§b" . $this->getNamePlain(); }
    public function getIcon(): string{ return $this->icon; }
    public function getRequirement(): string{ return $this->requirement; }
    public function getGoal(): int{ return $this->goal; }
    public function getStart(): int{ return $this->start; }
    public function getEnd(): int{ return $this->end; }
    public function getRewards(): array{ return $this->rewards; }

    public function getClaimDeadline(): int{
        return $this->end + ($this->claimGraceHours * 3600);
    }

    public function isVisible(): bool{
        return $this->start > 0 && $this->end > 0;
    }

    public function isRunning(int $now): bool{
        return $this->start <= $now && $now <= $this->end;
    }

    public function isExpired(int $now): bool{
        return $now > $this->getClaimDeadline();
    }

    public function getProgressIncrement(array $diff): int{
        return match($this->requirement){
            RequirementType::COINS_EARNED => (int)($diff["coins_earned"] ?? 0),
            RequirementType::KILLS => (int)($diff["kills"] ?? 0),
            RequirementType::FINAL_KILLS => (int)($diff["final_kills"] ?? 0),
            RequirementType::BEDS_BROKEN => (int)($diff["beds_broken"] ?? 0),
            RequirementType::WINS => (int)($diff["wins"] ?? 0),
            default => 0
        };
    }

    public function getRewardPreview(): string{
        $coins = 0;
        foreach($this->rewards as $r){
            if(is_array($r) && ($r["type"] ?? "") === RewardType::COINS){
                $coins += (int)($r["amount"] ?? 0);
            }
        }
        if($coins > 0){
            return "§e" . number_format($coins) . " §6Coins";
        }

        foreach($this->rewards as $r){
            if(!is_array($r) || ($r["type"] ?? "") !== RewardType::COSMETIC){
                continue;
            }

            $catRaw = (string)($r["category"] ?? "");
            $key = (string)($r["key"] ?? "");
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

        foreach($this->rewards as $r){
            if(is_array($r) && ($r["type"] ?? "") === RewardType::COMMAND){
                return "§dSpecial Reward";
            }
        }

        return "§fReward";
    }
}