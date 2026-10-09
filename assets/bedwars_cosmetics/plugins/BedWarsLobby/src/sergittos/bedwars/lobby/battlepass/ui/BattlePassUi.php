<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\ui;

use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use sergittos\bedwars\cosmetics\registry\CosmeticsRegistry;
use sergittos\bedwars\lobby\battlepass\model\RewardType;
use function is_array;
use function max;
use function mb_strlen;
use function mb_substr;
use function min;
use function number_format;
use function sprintf;
use function str_replace;

/**
 * Builds the button list of the redesigned Battle Pass screen (resource pack: ui/bwpass/battlepass.json).
 * The form title carries the hidden flag that routes the client to that screen, every button is one slot
 * (see BattlePassLayout).
 */
final class BattlePassUi{

    /** hidden title flag - same value as "$battlepass" in the resource pack's _global_variables.json */
    public const TITLE = "\u{00a7}b\u{00a7}w\u{00a7}t\u{00a7}q";

    private const ICON_DIR = "textures/bwpass/icons/";
    private const COSMETIC_ICON_DIR = "textures/ui/cosmeticicon/";

    private const COSMETIC_ICONS = [
        "final_kill_effect" => "finalkilleffect",
        "bed_break_effect" => "bedbreakeffect",
        "death_cry" => "deathcry",
        "kill_sound" => "killsound",
        "kill_message" => "killmessage",
        "win_effect" => "wineffect",
        "projectile_trail" => "projectiletrail",
        "victory_dance" => "victorydance",
        "pet" => "pet",
        "wing_wearable" => "backbling",
        "cape_wearable" => "cape",
        "hat_wearable" => "hat",
    ];

    /** @return array<int, array<string, mixed>> one empty (hidden) button per slot */
    public static function blankButtons() : array{
        $buttons = [];
        for($i = 0; $i < BattlePassLayout::COUNT; ++$i){
            $buttons[$i] = ["text" => ""];
        }
        return $buttons;
    }

    public static function text(int $slot, string $value) : array{
        return ["text" => BattlePassLayout::marker($slot) . $value];
    }

    public static function image(int $slot, string $path) : array{
        return [
            "text" => BattlePassLayout::marker($slot) . "img",
            "image" => ["type" => "path", "data" => $path],
        ];
    }

    /** a reward card: state tokens + short label, plus the reward icon (unless the card is empty) */
    public static function card(int $slot, string $tokens, string $label, ?string $icon) : array{
        $button = ["text" => BattlePassLayout::marker($slot) . $tokens . $label];
        if($icon !== null){
            $button["image"] = ["type" => "path", "data" => $icon];
        }
        return $button;
    }

    /** 0..100 -> 5% step texture of the xp bar */
    public static function xpBar(int $percent) : string{
        return sprintf("textures/bwpass/bars/xp_%02d", self::step($percent));
    }

    /** 0..100 -> 5% step texture of the gold tier line */
    public static function track(int $percent) : string{
        return sprintf("textures/bwpass/bars/tr_%02d", self::step($percent));
    }

    private static function step(int $percent) : int{
        return max(0, min(20, (int) round(max(0, min(100, $percent)) / 5)));
    }

    /** the season name comes with dark-gray parts that vanish on the purple screen */
    public static function seasonName(string $name) : string{
        $name = str_replace("\u{00a7}8", "\u{00a7}7", $name);
        return str_starts_with($name, "\u{00a7}") ? $name : "\u{00a7}f" . $name;
    }

    /** @param array<int, array<string, mixed>> $rewards */
    private static function coins(array $rewards) : int{
        $coins = 0;
        foreach($rewards as $r){
            if(is_array($r) && ($r["type"] ?? "") === RewardType::COINS){
                $coins += (int) ($r["amount"] ?? 0);
            }
        }
        return $coins;
    }

    /** @return array{0: string, 1: string}|null [category, key] of the first cosmetic reward */
    private static function cosmetic(array $rewards) : ?array{
        foreach($rewards as $r){
            if(is_array($r) && ($r["type"] ?? "") === RewardType::COSMETIC){
                $cat = (string) ($r["category"] ?? "");
                $key = (string) ($r["key"] ?? "");
                if($cat !== "" && $key !== ""){
                    return [$cat, $key];
                }
            }
        }
        return null;
    }

    private static function hasCommand(array $rewards) : bool{
        foreach($rewards as $r){
            if(is_array($r) && ($r["type"] ?? "") === RewardType::COMMAND){
                return true;
            }
        }
        return false;
    }

    /** icon shown on a reward card */
    public static function rewardIcon(array $rewards) : string{
        $cosmetic = self::cosmetic($rewards);
        if($cosmetic !== null){
            $file = self::COSMETIC_ICONS[$cosmetic[0]] ?? null;
            return $file !== null ? self::COSMETIC_ICON_DIR . $file : self::ICON_DIR . "special";
        }
        $coins = self::coins($rewards);
        if($coins > 0){
            return self::ICON_DIR . ($coins < 800 ? "coin1" : ($coins < 2500 ? "coin2" : "coin3"));
        }
        if(self::hasCommand($rewards)){
            return self::ICON_DIR . "special";
        }
        return self::ICON_DIR . "unknown";
    }

    /** short (max two lines) caption of a reward card - always starts with a colour code */
    public static function rewardLabel(array $rewards) : string{
        $coins = self::coins($rewards);
        $cosmetic = self::cosmetic($rewards);

        if($cosmetic !== null){
            $name = "\u{00a7}bCosmetic";
            $cat = CosmeticCategory::tryFrom($cosmetic[0]);
            if($cat !== null){
                $def = CosmeticsRegistry::getInstance()->get($cat, $cosmetic[1]);
                if($def !== null){
                    $shown = $def->getDisplayName();
                    if(mb_strlen($shown) > 11){
                        $shown = mb_substr($shown, 0, 10) . ".";
                    }
                    $name = $def->getRarity()->color() . "\u{00a7}l" . $shown;
                }
            }
            return $coins > 0 ? $name . "\n\u{00a7}e+" . number_format($coins) : $name;
        }
        if($coins > 0){
            return "\u{00a7}f\u{00a7}l" . number_format($coins);
        }
        if(self::hasCommand($rewards)){
            return "\u{00a7}d\u{00a7}lSpecial";
        }
        return "\u{00a7}7-";
    }

    private function __construct(){}
}
