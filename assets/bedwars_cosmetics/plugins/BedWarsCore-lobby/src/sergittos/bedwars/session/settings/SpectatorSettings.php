<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\settings;

use sergittos\bedwars\session\Session;

class SpectatorSettings {

    private Session $session;
    private int $flyingSpeed;
    private bool $autoTeleport;
    private bool $nightVision;

    public function __construct(Session $session, int $flyingSpeed = 1, bool $autoTeleport = false, bool $nightVision = false) {
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
        // در نسخه واقعی توسط BedWarsGame تکمیل می‌شود
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