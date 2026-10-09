<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\quests\quest;

final class QuestDefinition{

    public function __construct(
        private string $id,
        private QuestType $type,
        private QuestStat $stat,
        private string $name,
        private int $goal,
        private int $rewardCoins,
        private string $iconPath,
        private int $rewardExp = 0,
        private string $modeLabel = "In Any Mode"
    ){}

    public function getId(): string{ return $this->id; }
    public function getType(): QuestType{ return $this->type; }
    public function getStat(): QuestStat{ return $this->stat; }
    public function getName(): string{ return $this->name; }
    public function getGoal(): int{ return $this->goal; }
    public function getRewardCoins(): int{ return $this->rewardCoins; }
    public function getIconPath(): string{ return $this->iconPath; }
    public function getRewardExp(): int{ return $this->rewardExp; }
    public function getModeLabel(): string{ return $this->modeLabel; }
}
