<?php
declare(strict_types=1);

namespace arvan\leaderboards;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;

/**
 * FloatingNpc — پایهٔ همهٔNPCهای شناور (لیدربورد، سکو، هولوگرام، تخت).
 *
 * این موجودیت‌ها:
 *   • بدون گرانش و بدون اصطکاک‌اند (سر جای خودشان شناور می‌مانند)
 *   • آسیب نمی‌بینند و با «زدن» (tap) واکنش نشان می‌دهند
 *   • نام‌تگ همیشه‌روشن دارند (تابلوی متنی بالای مدل)
 *
 * مدل هر کدام در ریسورس‌پک ArvanLeaderboard است؛ همین شناسه‌ها آنجا تعریف شده‌اند:
 *   arvan:leaderboard   /  arvan:podium  /  arvan:hologram  /  arvan:bedwars_bed
 */
abstract class FloatingNpc extends Entity
{
    /** کلید تنظیمات این NPC در config.yml */
    abstract public function key(): string;

    /** بازسازی نام‌تگ بر اساس دادهٔ فعلی */
    abstract public function refreshNameTag(): void;

    public function getKey(): string
    {
        return $this->key();
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(7.0, 7.0);
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.0;
    }

    protected function getInitialGravityMultiplier(): float
    {
        return 0.0;
    }

    protected function initEntity(\pocketmine\nbt\tag\CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->setNameTagAlwaysVisible();
        $this->setNoClientPredictions();
        $this->setImmobile(true);
        $this->refreshNameTag();
    }

    public function canSaveWithChunk(): bool
    {
        return true;
    }

    public function attack(EntityDamageEvent $source): void
    {
        $source->cancel();
        if ($source instanceof EntityDamageByEntityEvent && ($p = $source->getDamager()) instanceof Player) {
            Main::get()->onTap($p, $this);
        }
    }

    /** خواندن یک کلید از بخش npcs.<key> در config.yml */
    protected function cfg(string $path, mixed $default = null): mixed
    {
        return Main::get()->npcConfig($this->key())[$path] ?? $default;
    }

    protected function store(): Config
    {
        return Main::get()->stats();
    }
}
