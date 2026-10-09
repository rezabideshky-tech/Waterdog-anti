<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\map;

use JsonSerializable;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;
use sergittos\bedwars\game\generator\Generator;
use sergittos\bedwars\game\generator\GeneratorType;
use sergittos\bedwars\game\team\Team;
use function array_map;
use function uniqid;

final class Map implements JsonSerializable{
    use MapProperties;

    /** @var Team[] */
    private array $teams;

    /**
     * @param Generator[] $generators
     * @param Team[] $teams
     * @param Vector3[] $shop_locations
     * @param Vector3[] $upgrades_locations
     * @param array<string,float> $shop_yaws
     * @param array<string,float> $upgrades_yaws
     */
    public function __construct(
        string $name,
        Vector3 $spectator_spawn_position,
        Vector3 $waiting_spawn_position,
        int $players_per_team,
        int $max_capacity,
        World $waiting_world,
        array $generators,
        array $teams,
        array $shop_locations,
        array $upgrades_locations,
        array $shop_yaws = [],
        array $upgrades_yaws = [],
        ?string $id = null
    ){
        $this->id = $id ?? uniqid("map-");
        $this->name = $name;

        $this->spectator_spawn_position = $spectator_spawn_position;
        $this->waiting_spawn_position = $waiting_spawn_position;

        $this->players_per_team = $players_per_team;
        $this->max_capacity = $max_capacity;

        $this->waiting_world = $waiting_world;

        $this->generators = $generators;
        $this->teams = $teams;

        $this->shop_positions = $shop_locations;
        $this->upgrades_positions = $upgrades_locations;

        $this->shop_yaws = $shop_yaws;
        $this->upgrades_yaws = $upgrades_yaws;
    }

    /** @return Team[] */
    public function getTeams(): array{
        return $this->teams;
    }

    public function getWaitingWorld(): World{
        $wm = Server::getInstance()->getWorldManager();
        $name = $this->waiting_world->getFolderName();

        $w = $wm->getWorldByName($name);
        if($w === null){
            $wm->loadWorld($name);
            $w = $wm->getWorldByName($name);
        }

        if($w !== null){
            $this->waiting_world = $w;
        }

        return $this->waiting_world;
    }

    public function jsonSerialize(): array{
        return [
            "id" => $this->id,
            "name" => $this->name,
            "waiting_world" => $this->waiting_world->getFolderName(),
            "spectator_spawn_position" => [
                "x" => $this->spectator_spawn_position->getX(),
                "y" => $this->spectator_spawn_position->getY(),
                "z" => $this->spectator_spawn_position->getZ()
            ],
            "waiting_spawn_position" => [
                "x" => $this->waiting_spawn_position->getX(),
                "y" => $this->waiting_spawn_position->getY(),
                "z" => $this->waiting_spawn_position->getZ()
            ],
            "players_per_team" => $this->players_per_team,
            "max_capacity" => $this->max_capacity,
            "generators" => [
                "diamond" => $this->jsonSerializePositions($this->getGeneratorPositions(GeneratorType::DIAMOND)),
                "emerald" => $this->jsonSerializePositions($this->getGeneratorPositions(GeneratorType::EMERALD))
            ],
            "teams" => array_map(fn(Team $team) => $team->jsonSerialize(), $this->teams),
            "shop_positions" => $this->jsonSerializeNpcPositions($this->shop_positions, $this->shop_yaws),
            "upgrades_positions" => $this->jsonSerializeNpcPositions($this->upgrades_positions, $this->upgrades_yaws),
        ];
    }

    private function getGeneratorPositions(GeneratorType $type): array{
        $positions = [];
        foreach($this->generators as $generator){
            if($generator->getType() === $type){
                $positions[] = $generator->getPosition();
            }
        }
        return $positions;
    }

    private function jsonSerializePositions(array $positions): array{
        return array_map(function(Vector3 $position){
            return [
                "x" => $position->getX(),
                "y" => $position->getY(),
                "z" => $position->getZ()
            ];
        }, $positions);
    }

    private function jsonSerializeNpcPositions(array $positions, array $yaws): array{
        return array_map(function(Vector3 $position) use ($yaws){
            $key = $position->getFloorX() . ":" . $position->getFloorY() . ":" . $position->getFloorZ();
            return [
                "x" => $position->getX(),
                "y" => $position->getY(),
                "z" => $position->getZ(),
                "yaw" => (float) ($yaws[$key] ?? 0.0)
            ];
        }, $positions);
    }
}