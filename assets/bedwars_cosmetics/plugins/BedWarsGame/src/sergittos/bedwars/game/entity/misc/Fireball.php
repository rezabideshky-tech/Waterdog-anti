<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\entity\misc;


use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\projectile\Throwable;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

class Fireball extends Throwable {

    public static function getNetworkTypeId(): string {
        return EntityIds::FIREBALL;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.31, 0.31);
    }

    /**
     * قبلاً 0.01 بود که همچنان یه گرانش واقعی (هرچند کم) اعمال می‌کرد و
     * روی مسافت پرتاب طبیعی فایربال (که باید مثل جاوا تقریباً مستقیم به
     * جلو بره، نه مثل تخم‌مرغ/گلوله‌برفی به شکل کمانی سقوط کنه) کاملاً
     * محسوس بود. فایربال واقعی ماینکرفت (Ghast fireball) اصلاً گرانش
     * نداره - صفر می‌کنیم تا مسیرش مستقیم بمونه.
     */
    protected function getInitialGravity(): float {
        return 0.0;
    }

    /** بدون درگ محسوس، سرعتش تا برخورد تقریباً ثابت می‌مونه - دقیقاً رفتار جاوا. */
    protected function getInitialDragMultiplier(): float {
        return 0.001;
    }

    protected function onHit(ProjectileHitEvent $event): void {
        $explosion = new Explosion(Position::fromObject($event->getRayTraceResult()->getHitVector(), $this->getWorld()), 4, $this);
        $explosion->explodeA();
        $explosion->explodeB();
    }

}