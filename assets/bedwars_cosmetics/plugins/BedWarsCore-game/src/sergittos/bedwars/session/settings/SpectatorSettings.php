<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\settings;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\utils\Limits;
use sergittos\bedwars\session\Session;

class SpectatorSettings {

    private Session $session;
    private int $flyingSpeed;
    private bool $autoTeleport;
    private bool $nightVision;

    public function __construct(Session $session, int $flyingSpeed = 1, bool $autoTeleport = false, bool $nightVision = true) {
        $this->session = $session;
        $this->flyingSpeed = $flyingSpeed;
        $this->autoTeleport = $autoTeleport;
        $this->nightVision = $nightVision;
    }

    public function getFlyingSpeed(): int { return $this->flyingSpeed; }
    public function getAutoTeleport(): bool { return $this->autoTeleport; }
    public function getNightVision(): bool { return $this->nightVision; }

    public function setFlyingSpeed(int $speed): void { $this->flyingSpeed = $speed; }
    public function setAutoTeleport(bool $value): void { $this->autoTeleport = $value; }
    public function setNightVision(bool $value): void { $this->nightVision = $value; }

    public function apply(): void {
        $player = $this->session->getPlayer();
        if(!$player->isConnected()){
            return;
        }

        if(!$this->session->isSpectator()){
            return;
        }

        $this->applyFlight($player);
        $this->applyNightVision($player);
        $this->applySpeed($player);
    }

    // Always on for spectators (not user-toggleable, unlike night vision) -
    // a plain speed boost on top of the flight speed above, so spectators
    // that drop out of flight for a moment (e.g. right after promotion,
    // before setAllowFlight/setFlying above lands client-side) still move
    // around briskly instead of at normal walk speed.
    private function applySpeed(Player $player): void{
        $player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), Limits::INT32_MAX, 1, false));
    }

    private function applyFlight(Player $player): void{
        $player->setAllowFlight(true);
        $player->setFlying(true);

        $s = $this->flyingSpeed;
        if($s < 0) $s = 0;
        if($s > 4) $s = 4;

        $mapped = match($s){
            0 => 0.05,
            1 => 0.10,
            2 => 0.20,
            3 => 0.30,
            4 => 0.40,
            default => 0.10
        };

        if(method_exists($player, "setFlyingSpeed")){
            $player->setFlyingSpeed($mapped);
        }elseif(method_exists($player, "setFlySpeed")){
            $player->setFlySpeed($mapped);
        }
    }

    private function applyNightVision(Player $player): void{
        if($this->nightVision){
            $player->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(), Limits::INT32_MAX, 0, false));
        }else{
            $player->getEffects()->remove(VanillaEffects::NIGHT_VISION());
        }
    }

    public static function fromData(Session $session, array $data): self {
        return new self(
            $session,
            (int)($data["flying_speed"] ?? 1),
            (bool)($data["auto_teleport"] ?? false),
            (bool)($data["night_vision"] ?? false)
        );
    }
}