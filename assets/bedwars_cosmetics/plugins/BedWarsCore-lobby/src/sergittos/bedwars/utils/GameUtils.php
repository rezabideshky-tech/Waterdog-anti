<?php

declare(strict_types=1);

namespace sergittos\bedwars\utils;

class GameUtils {

    /**
     * دریافت نام حالت بازی بر اساس تعداد بازیکن در هر تیم
     */
    public static function getMode(int $players_per_team): string {
        return match($players_per_team) {
            1 => "SOLO\n{GRAY}1vs1vs1vs1...",
            2 => "DOUBLE\n{GRAY}2vs2vs2vs2...",
            3 => "TRIPLE\n{GRAY}3vs3vs3vs3...",
            4 => "SQUAD\n{GRAY}4vs4vs4vs4",
            default => "Unknown"
        };
    }

    /**
     * تبدیل عدد به اعداد رومی (برای نمایش تایر ابزارها و ...)
     */
    public static function intToRoman(int $number): string {
        return match($number) {
            1 => "I",
            2 => "II",
            3 => "III",
            4 => "IV",
            5 => "MAX",
            default => (string)$number
        };
    }

    /**
     * دریافت رنگ متناسب با نام ژنراتور
     */
    public static function getGeneratorColor(string $name): string {
        return match(strtolower($name)) {
            "emerald" => "{DARK_GREEN}",
            "diamond" => "{AQUA}",
            "gold" => "{GOLD}",
            "iron" => "{WHITE}",
            default => ""
        };
    }

    /**
     * دریافت عدد با رنگ مناسب برای نمایش در پیام‌ها
     */
    public static function getColoredMessageNumber(int $number): string {
        return match(true) {
            $number <= 5 => "{RED}",
            $number <= 10 => "{GOLD}",
            $number <= 20 => "{AQUA}",
            default => "{GREEN}"
        } . $number;
    }

    /**
     * دریافت عدد با رنگ مناسب برای نمایش در تایتل
     */
    public static function getColoredTitleNumber(int $number): string {
        return match(true) {
            $number <= 3 => "{RED}",
            $number <= 5 => "{YELLOW}",
            default => "{GREEN}"
        } . $number;
    }

    /**
     * ترپ متناظر با اسم آپگرید (استفاده در TrapProduct هنگام خرید تله).
     * توجه: این متد فقط از پلاگین BedWarsGame صدا زده می‌شه (هیچ سرور
     * دیگه‌ای تله نمی‌خره)، پس وابستگیش به کلاس‌های Game مشکلی ایجاد نمی‌کنه.
     */
    public static function getTrapByName(string $name): \sergittos\bedwars\game\team\upgrade\trap\Trap {
        return match($name) {
            "It's a trap" => new \sergittos\bedwars\game\team\upgrade\trap\DefaultTrap(),
            "Counter-Offensive Trap" => new \sergittos\bedwars\game\team\upgrade\trap\CounterOffensiveTrap(),
            "Alarm Trap" => new \sergittos\bedwars\game\team\upgrade\trap\AlarmTrap(),
            "Miner Fatigue Trap" => new \sergittos\bedwars\game\team\upgrade\trap\MinerFatigueTrap(),
            default => new \sergittos\bedwars\game\team\upgrade\trap\DefaultTrap(),
        };
    }

    public static function getEffectDuration(\pocketmine\entity\effect\EffectInstance $effect): int {
        return match($effect->getType()) {
            \pocketmine\entity\effect\VanillaEffects::SPEED(), \pocketmine\entity\effect\VanillaEffects::JUMP_BOOST() => 45,
            \pocketmine\entity\effect\VanillaEffects::INVISIBILITY() => 30,
            default => 0
        } * 20;
    }

    public static function getEffectAmplifier(\pocketmine\entity\effect\EffectInstance $effect): int {
        return match($effect->getType()) {
            \pocketmine\entity\effect\VanillaEffects::SPEED() => 1,
            \pocketmine\entity\effect\VanillaEffects::JUMP_BOOST() => 4,
            default => 0
        };
    }

    /**
     * حداکثر تعداد قبل از merge شدن دو تا انتیتی آیتم روی زمین (کمتر از
     * حداکثر استک واقعی آیتم - عمداً، تا انبوه منابع روی زمین زشت نشه).
     */
    public static function getCountById(int $id): int {
        return match($id) {
            \pocketmine\item\ItemTypeIds::IRON_INGOT => 48,
            \pocketmine\item\ItemTypeIds::GOLD_INGOT => 16,
            \pocketmine\item\ItemTypeIds::DIAMOND => 4,
            \pocketmine\item\ItemTypeIds::EMERALD => 2,
            default => 64
        };
    }
}