<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\misc;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\Position;
use sergittos\bedwars\game\entity\mob\SilverfishEntity;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\team\Team;

/**
 * قبلاً آیتم Silverfish یک snowball بود ولی موقع right-click بلافاصله
 * (PlayerItemUseEvent) دقیقاً کنار پای خودِ پرتاب‌کننده یک سیلورفیش
 * اسپان می‌کرد - یعنی اصلاً پرتاب نمی‌شد. این کلاس یک snowball واقعی است
 * (extends Throwable، دقیقاً هم‌الگو با Fireball/EggBridgeEntity موجود
 * در همین پوشه) که پرتاب می‌شود و سیلورفیش را دقیقاً جایی که به بلاک یا
 * بازیکنی برخورد کرد اسپان می‌کند.
 */
class SilverfishSnowballEntity extends Throwable {

    private ?Team $team;
    private ?Game $game;

    public function __construct(Location $location, ?Player $shootingEntity, ?Team $team, ?Game $game) {
        $this->team = $team;
        $this->game = $game;
        parent::__construct($location, $shootingEntity);
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::SNOWBALL;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.25, 0.25);
    }

    protected function getInitialGravity(): float {
        return 0.03;
    }

    protected function getInitialDragMultiplier(): float {
        return 0.01;
    }

    protected function onHit(ProjectileHitEvent $event): void {
        if ($this->team === null || $this->game === null) {
            return;
        }

        $position = Position::fromObject($event->getRayTraceResult()->getHitVector(), $this->getWorld());
        $fish = new SilverfishEntity(Location::fromObject($position, $this->getWorld()));
        $fish->setup($this->team, $this->game);
        $fish->spawnToAll();
    }
}
