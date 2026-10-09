<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\generator\presets;


use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\VanillaItems;
use sergittos\bedwars\game\generator\Generator;
use sergittos\bedwars\game\generator\GeneratorType;

class TeamEmeraldGenerator extends Generator {

    public function getType(): GeneratorType {
        return GeneratorType::TEAM_EMERALD;
    }

    public function getInitialSpeed(): int {
        return 30;
    }

    protected function getItem(): Item {
        return VanillaItems::EMERALD();
    }

    /**
     * نمایش بلوک زمرد به‌جای آیتم زمرد (مثل ژنراتور امرالد اصلی)
     */
    protected function getFloatingItem(): Item {
        return new ItemBlock(VanillaBlocks::EMERALD());
    }

}