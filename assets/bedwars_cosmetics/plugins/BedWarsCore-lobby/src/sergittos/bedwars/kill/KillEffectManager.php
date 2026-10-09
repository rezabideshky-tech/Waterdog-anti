<?php

declare(strict_types=1);

namespace sergittos\bedwars\kill;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\world\particle\ExplodeParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\LavaDripParticle;
use pocketmine\world\particle\SmokeParticle;
use pocketmine\world\particle\TotemParticle;
use pocketmine\world\sound\AnvilBreakSound;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\sound\PopSound;
use pocketmine\world\sound\XpCollectSound;
use pocketmine\world\World;
use sergittos\bedwars\session\Session;

class KillEffectManager {

    /** @var array<string, callable(Vector3, World): void> */
    private array $effects = [];

    /** @var array<string, callable(Player): void> */
    private array $sounds  = [];

    public function __construct(Plugin $plugin) {
        $this->registerDefaults();
    }

    private function registerDefaults(): void {
        // Effects
        $this->effects["none"] = function(Vector3 $pos, World $w): void {};

        $this->effects["flames"] = function(Vector3 $pos, World $w): void {
            for ($i = 0; $i < 20; $i++) {
                $w->addParticle($pos->add(
                    (lcg_value() - 0.5) * 1.5,
                    lcg_value() * 2.0,
                    (lcg_value() - 0.5) * 1.5
                ), new FlameParticle());
            }
        };

        $this->effects["explosion"] = function(Vector3 $pos, World $w): void {
            $w->addParticle($pos->add(0, 1, 0), new ExplodeParticle());
            for ($i = 0; $i < 15; $i++) {
                $w->addParticle($pos->add(
                    (lcg_value() - 0.5) * 2,
                    lcg_value() * 2,
                    (lcg_value() - 0.5) * 2
                ), new SmokeParticle(5));
            }
        };

        $this->effects["hearts"] = function(Vector3 $pos, World $w): void {
            for ($i = 0; $i < 12; $i++) {
                $w->addParticle($pos->add(
                    (lcg_value() - 0.5) * 1.5,
                    lcg_value() * 2.0,
                    (lcg_value() - 0.5) * 1.5
                ), new HeartParticle());
            }
        };

        $this->effects["totem"] = function(Vector3 $pos, World $w): void {
            for ($i = 0; $i < 30; $i++) {
                $w->addParticle($pos->add(
                    (lcg_value() - 0.5) * 2,
                    lcg_value() * 2.5,
                    (lcg_value() - 0.5) * 2
                ), new TotemParticle());
            }
        };

        $this->effects["lava"] = function(Vector3 $pos, World $w): void {
            for ($i = 0; $i < 20; $i++) {
                $w->addParticle($pos->add(
                    (lcg_value() - 0.5) * 2,
                    lcg_value() * 2,
                    (lcg_value() - 0.5) * 2
                ), new LavaDripParticle());
            }
        };

        // Sounds
        $this->sounds["none"]    = function(Player $p): void {};
        $this->sounds["classic"] = function(Player $p): void { $this->sendSound($p, "random.orb"); };
        $this->sounds["anvil"]   = function(Player $p): void { $p->getWorld()->addSound($p->getPosition(), new AnvilBreakSound(), [$p]); };
        $this->sounds["pop"]     = function(Player $p): void { $p->getWorld()->addSound($p->getPosition(), new PopSound(), [$p]); };
        $this->sounds["xp"]      = function(Player $p): void { $p->getWorld()->addSound($p->getPosition(), new XpCollectSound(), [$p]); };
        $this->sounds["thunder"] = function(Player $p): void { $this->sendSound($p, "ambient.weather.thunder"); };
        $this->sounds["goat"]    = function(Player $p): void { $this->sendSound($p, "mob.goat.screaming.ambient"); };
    }

    public function playKillEffect(Session $killer, Vector3 $deathPos, World $world): void {
        $fn = $this->effects[$killer->getSelectedKillEffect()] ?? $this->effects["none"];
        ($fn)($deathPos, $world);
    }

    public function playKillSound(Session $killer): void {
        $fn = $this->sounds[$killer->getSelectedKillSound()] ?? $this->sounds["none"];
        ($fn)($killer->getPlayer());
    }

    public function playBedBreakEffect(Vector3 $pos, World $world): void {
        $world->addParticle($pos->add(0.5, 0.5, 0.5), new ExplodeParticle());
        for ($i = 0; $i < 25; $i++) {
            $world->addParticle($pos->add(
                (lcg_value() - 0.5) * 3,
                lcg_value() * 3,
                (lcg_value() - 0.5) * 3
            ), new SmokeParticle(7));
        }
        $world->addSound($pos->add(0.5, 0.5, 0.5), new ExplodeSound());
    }

    private function sendSound(Player $player, string $name, float $vol = 1.0, float $pitch = 1.0): void {
        $pk = new PlaySoundPacket();
        $pk->soundName = $name;
        $pk->x = $player->getPosition()->getX();
        $pk->y = $player->getPosition()->getY();
        $pk->z = $player->getPosition()->getZ();
        $pk->volume = $vol;
        $pk->pitch  = $pitch;
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    public function getEffectIds(): array { return array_keys($this->effects); }
    public function getSoundIds(): array  { return array_keys($this->sounds); }
}
