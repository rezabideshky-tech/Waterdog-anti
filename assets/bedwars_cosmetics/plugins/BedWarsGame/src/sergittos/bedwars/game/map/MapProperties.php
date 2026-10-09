<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\map;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use sergittos\bedwars\game\generator\Generator;

trait MapProperties {

    protected string $id;
    protected string $name;

    protected Vector3 $spectator_spawn_position;
    protected Vector3 $waiting_spawn_position;

    protected int $players_per_team;
    protected int $max_capacity;

    protected World $waiting_world;

    /** @var Vector3[] */
    protected array $shop_positions = [];

    /** @var Vector3[] */
    protected array $upgrades_positions = [];

    /** @var array<string,float> */
    protected array $shop_yaws = [];

    /** @var array<string,float> */
    protected array $upgrades_yaws = [];

    /** @var Generator[] */
    protected array $generators = [];

    public function getId(): string {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getSpectatorSpawnPosition(): Vector3 {
        return $this->spectator_spawn_position;
    }

    public function getWaitingSpawnPosition(): Vector3 {
        return $this->waiting_spawn_position;
    }

    public function getPlayersPerTeam(): int {
        return $this->players_per_team;
    }

    public function getMaxCapacity(): int {
        return $this->max_capacity;
    }

    public function getWaitingWorld(): World {
        return $this->waiting_world;
    }

    /** @return Generator[] */
    public function getGenerators(): array {
        return $this->generators ?? [];
    }

    /** @return Vector3[] */
    public function getShopPositions(): array {
        return $this->shop_positions ?? [];
    }

    /** @return Vector3[] */
    public function getUpgradesPositions(): array {
        return $this->upgrades_positions ?? [];
    }

    public function getShopYaw(Vector3 $pos): float {
        return (float) ($this->shop_yaws[$this->vecKey($pos)] ?? 0.0);
    }

    public function getUpgradesYaw(Vector3 $pos): float {
        return (float) ($this->upgrades_yaws[$this->vecKey($pos)] ?? 0.0);
    }

    /** @return array<string,float> */
    public function getShopYaws(): array {
        return $this->shop_yaws ?? [];
    }

    /** @return array<string,float> */
    public function getUpgradesYaws(): array {
        return $this->upgrades_yaws ?? [];
    }

    protected function vecKey(Vector3 $v): string {
        return $v->getFloorX() . ":" . $v->getFloorY() . ":" . $v->getFloorZ();
    }
}