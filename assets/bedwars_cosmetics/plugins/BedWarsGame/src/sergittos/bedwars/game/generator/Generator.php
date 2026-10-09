<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\generator;

use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\utils\ColorUtils;
use sergittos\bedwars\utils\GameUtils;

abstract class Generator {

    protected Vector3 $position;
    protected Tier $tier;

    protected int $speed;
    protected int $countdown = 0;
    protected int $time = 0;

    /**
     * Height, in blocks, of the floating display block above the
     * generator's base position. Must stay just BELOW the hologram's
     * bottom line (GeneratorText::BASE_Y_OFFSET, currently 3.25 -> bottom
     * line at +3.75) so the block visibly floats right under the text
     * without ever overlapping it, instead of sitting near the spawn pad -
     * tweak this single value (together with GeneratorText::BASE_Y_OFFSET)
     * if it still needs nudging up/down.
     */
    private const DISPLAY_BLOCK_Y_OFFSET = 3.4;

    /**
     * Visual scale of the floating display entity. The default item-model
     * render for a block item is roughly half the size of a placed block,
     * so it's doubled here to read as a full block - matching the
     * reference screenshots - instead of a small floating icon.
     */
    private const DISPLAY_BLOCK_SCALE = 2.0;

    private ?GeneratorDisplayBlock $displayEntity = null;
    private int $displayRefreshTicks = 0;
    private int $displayMoveCooldown = 0;

    public function __construct(Vector3 $position) {
        $this->position = $position;
        $this->tier = Tier::I;
        $this->setSpeed($this->getInitialSpeed());
    }

    public function getName(): string {
        $name = $this->getType()->toString();
        return ColorUtils::translate(GameUtils::getGeneratorColor($name) . $name);
    }

    public function getPosition(): Vector3 {
        return $this->position;
    }

    public function getTier(): Tier {
        return $this->tier;
    }

    public function getSpeed(): int {
        return $this->speed;
    }

    public function getTime(): int {
        return $this->time;
    }

    public function setTier(Tier $tier): void {
        $this->tier = $tier;
    }

    public function setSpeed(int $speed): void {
        $this->speed = $speed;
        $this->setCountdown(1 / $speed);
    }

    public function setCountdown(float $countdown): void {
        $this->countdown = (int) (20 / $countdown);
        $this->resetTime();
    }

    private function resetTime(): void {
        $this->time = $this->countdown;
    }

    public function tick(Game $game): void {
        $this->time--;
        if ($this->time <= 0) {
            $this->resetTime();
            $this->dropItem($game->getWorld());
        }

        if ($this->hasFloatingDisplay()) {
            $this->tickFloatingDisplay($game->getWorld());
        }
    }

    private function hasFloatingDisplay(): bool {
        return match($this->getType()) {
            GeneratorType::DIAMOND, GeneratorType::EMERALD, GeneratorType::TEAM_EMERALD => true,
            default => false,
        };
    }

    protected function getFloatingItem(): Item {
        return $this->getItem();
    }

    private function tickFloatingDisplay(?World $world): void {
        if ($world === null) return;

        $this->displayRefreshTicks--;
        $this->displayMoveCooldown--;

        if ($this->displayEntity !== null && ($this->displayEntity->isClosed() || !$this->displayEntity->isAlive() || $this->displayEntity->isFlaggedForDespawn())) {
            $this->displayEntity = null;
        }

        if ($this->displayEntity === null || $this->displayRefreshTicks <= 0) {
            if ($this->displayEntity !== null && !$this->displayEntity->isClosed()) {
                $this->displayEntity->flagForDespawn();
            }

            // بلوکِ نمایشیِ شناور و در حالِ چرخش (bob + spin کلاینت‌ساید
            // خودِ آیتم‌هاست، دقیقاً همون افکتِ خواسته‌شده) - نگاه کن به
            // توضیحات کلاس GeneratorDisplayBlock برای جزئیات. getFloatingItem()
            // برای دایمند/امرالد/امرالدِ تیمی یک ItemBlock (شکلِ آیتمیِ
            // diamond_block / emerald_block) برمی‌گردونه که مستقیماً به
            // ItemEntity داده می‌شه.
            $location = Location::fromObject($this->position->add(0, self::DISPLAY_BLOCK_Y_OFFSET, 0), $world);
            $entity = new GeneratorDisplayBlock($location, $this->getFloatingItem());
            $entity->setOwner("generator_display");
            $entity->setPickupDelay(PHP_INT_MAX);
            $entity->setScale(self::DISPLAY_BLOCK_SCALE);
            $entity->setMotion(Vector3::zero());
            $entity->spawnToAll();

            $this->displayEntity = $entity;
            // Jitter the refresh interval (+/- ~5s) so generators spawned together
            // (every generator in a game is created during the same world setup)
            // don't all refresh their display entity on the exact same tick every
            // 3 minutes - that synchronized burst across every concurrent game was
            // the source of the periodic multi-second-equivalent lag spikes.
            $this->displayRefreshTicks = 3600 + mt_rand(-100, 100);
            $this->displayMoveCooldown = 10;
            return;
        }

        if ($this->displayEntity !== null && !$this->displayEntity->isClosed() && $this->displayMoveCooldown <= 0) {
            $this->displayEntity->teleport(Location::fromObject($this->position->add(0, self::DISPLAY_BLOCK_Y_OFFSET, 0), $world));
            $this->displayEntity->setMotion(Vector3::zero());
            $this->displayMoveCooldown = 10;
        }
    }

    public function despawnDisplay(): void {
        if ($this->displayEntity !== null) {
            if (!$this->displayEntity->isClosed() && $this->displayEntity->isAlive() && !$this->displayEntity->isFlaggedForDespawn()) {
                $this->displayEntity->flagForDespawn();
            }
        }
        $this->displayEntity = null;
        $this->displayRefreshTicks = 0;
        $this->displayMoveCooldown = 0;
    }

    private function dropItem(?World $world): void {
        if ($world === null) return;

        $entity = $world->dropItem($this->position->add(0, 0.1, 0), clone $this->getItem(), Vector3::zero());
        if ($entity !== null) {
            $entity->setOwner("generator");
            $this->onDropItem($world);
        }
    }

    public function reset(): void {
        $this->tier = Tier::I;
        $this->setSpeed($this->getInitialSpeed());
        $this->despawnDisplay();
    }

    public function onDropItem(World $world): void {}

    abstract public function getType(): GeneratorType;

    abstract public function getInitialSpeed(): int;

    abstract protected function getItem(): Item;
}