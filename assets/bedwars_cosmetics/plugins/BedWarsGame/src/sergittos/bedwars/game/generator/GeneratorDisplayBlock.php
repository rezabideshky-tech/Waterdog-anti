<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\generator;

use pocketmine\entity\Entity;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageEvent;

/**
 * بلوکِ نمایشیِ شناورِ زیر هولوگرام ژنراتورهای دایمند/امرالد/امرالدِ تیمی
 * (سرگیتوس - ژنراتور).
 *
 * این جایگاه یه بار به یک FallingBlock کاملاً بی‌حرکت تبدیل شده بود تا
 * انیمیشن bob (شناوریِ بالا-پایین) و چرخشِ کلاینت‌ساید که آیتم‌ها همیشه
 * دارن حذف بشه - اما همون bob + چرخش دقیقاً همون افکتیه که این‌جا
 * خواسته شده (شناور بودن + چرخیدن)، فقط با جایگاهِ درست: کمی زیرِ خطِ
 * هولوگرام، نه پایین روی زمینِ ژنراتور. برای همین دوباره از ItemEntity
 * استفاده می‌شه؛ اما این‌بار با setScale(2.0) (تو Generator::tickFloatingDisplay)
 * اندازه‌ش به یه بلوکِ کامل نزدیک می‌شه (مدلِ آیتمِ بلوک‌ها پیش‌فرض تقریباً
 * نصفِ یک بلوکِ کامله) و جایگاهش درست زیرِ آخرین خطِ هولوگرام تنظیم شده،
 * نه پایینِ ژنراتور.
 *
 * owner روی "generator_display" ست می‌شه (نه "generator") تا از منطقِ
 * جمع‌کردنِ آیتم توسط GameListener::onPickup() جدا بمونه: اون‌جا هر
 * آیتمی با owner==="generator_display" همیشه از EntityItemPickupEvent
 * لغو می‌شه، پس بازیکن‌ها هیچ‌وقت نمی‌تونن این نمایشگر رو "بردارن" - دقیقاً
 * مثل رفتار قبلیِ ItemEntity.
 */
final class GeneratorDisplayBlock extends ItemEntity{

    protected function getInitialGravity(): float{
        return 0.0;
    }

    protected function getInitialDragMultiplier(): float{
        return 0.0;
    }

    public function canBeMovedByCurrents(): bool{
        return false;
    }

    public function canCollideWith(Entity $entity): bool{
        return false;
    }

    /**
     * فقط یه نمایشگره - نه یه آیتمِ واقعیِ قابل‌برداشت یا قابل‌آسیب با ضربه.
     */
    public function attack(EntityDamageEvent $source): void{
        // بی‌آسیب و بدون despawn روی برخورد/انفجار اطراف.
    }

}
