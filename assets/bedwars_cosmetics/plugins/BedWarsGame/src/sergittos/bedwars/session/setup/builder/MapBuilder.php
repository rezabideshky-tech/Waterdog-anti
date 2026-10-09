<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\setup\builder;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use sergittos\bedwars\game\generator\Generator;
use sergittos\bedwars\game\map\Map;
use sergittos\bedwars\game\map\MapProperties;
use sergittos\bedwars\game\team\Team;
use function array_map;
use function array_search;
use function array_values;

final class MapBuilder{
    use MapProperties;

    private string $playing_world;

    /** @var TeamBuilder[] */
    private array $teams = [];

    public function __construct(string $name, World $waiting_world, string $playing_world, int $players_per_team, int $max_capacity){
        $this->name = $name;
        $this->waiting_world = $waiting_world;
        $this->playing_world = $playing_world;
        $this->players_per_team = $players_per_team;
        $this->max_capacity = $max_capacity;

        $sp = $waiting_world->getSpawnLocation();
        $this->waiting_spawn_position = new Vector3($sp->getX(), $sp->getY(), $sp->getZ());

        $this->shop_positions = [];
        $this->upgrades_positions = [];
        $this->shop_yaws = [];
        $this->upgrades_yaws = [];
        $this->generators = [];

        $this->setDefaultTeams();
    }

    public static function fromMap(Map $map, string $playing_world): self{
        $b = new self(
            $map->getName(),
            $map->getWaitingWorld(),
            $playing_world,
            $map->getPlayersPerTeam(),
            $map->getMaxCapacity()
        );

        $b->id = $map->getId();
        $b->setSpectatorSpawnPosition(clone $map->getSpectatorSpawnPosition());
        $b->setWaitingSpawnPosition(clone $map->getWaitingSpawnPosition());

        $b->shop_positions = array_map(fn(Vector3 $v) => clone $v, $map->getShopPositions());
        $b->upgrades_positions = array_map(fn(Vector3 $v) => clone $v, $map->getUpgradesPositions());
        $b->shop_yaws = $map->getShopYaws();
        $b->upgrades_yaws = $map->getUpgradesYaws();

        $b->generators = array_map(fn(Generator $g) => clone $g, $map->getGenerators());

        $b->teams = [];
        foreach($map->getTeams() as $team){
            $tb = new TeamBuilder($team->getName());
            $tb->setSpawnPoint(clone $team->getSpawnPoint());
            $tb->setBedPosition(clone $team->getBedPosition());
            $tb->setZone(clone $team->getZone());
            $tb->setClaim(clone $team->getClaim());

            $genPos = null;
            foreach($team->getGenerators() as $g){
                $t = $g->getType();
                if($t->name === "IRON" || $t->name === "GOLD"){
                    $genPos = $g->getPosition();
                    break;
                }
            }
            $tb->setGeneratorPosition(clone ($genPos ?? $team->getSpawnPoint()));

            $b->teams[] = $tb;
        }

        return $b;
    }

    public function getPlayingWorld() : string{
        return $this->playing_world;
    }

    public function setSpectatorSpawnPosition(Vector3 $spectator_spawn_position) : void{
        $this->spectator_spawn_position = $spectator_spawn_position;
    }

    public function setWaitingSpawnPosition(Vector3 $waiting_spawn_position): void{
        $this->waiting_spawn_position = $waiting_spawn_position;
    }

    private function vecKey(Vector3 $v): string{
        return $v->getFloorX() . ":" . $v->getFloorY() . ":" . $v->getFloorZ();
    }

    public function addShopPosition(Vector3 $position, float $yaw = 0.0) : void{
        foreach($this->shop_positions as $p){
            if($p->getFloorX() === $position->getFloorX() && $p->getFloorY() === $position->getFloorY() && $p->getFloorZ() === $position->getFloorZ()){
                $this->shop_yaws[$this->vecKey($p)] = $yaw;
                return;
            }
        }
        $this->shop_positions[] = $position;
        $this->shop_yaws[$this->vecKey($position)] = $yaw;
    }

    public function addUpgradesPosition(Vector3 $position, float $yaw = 0.0) : void{
        foreach($this->upgrades_positions as $p){
            if($p->getFloorX() === $position->getFloorX() && $p->getFloorY() === $position->getFloorY() && $p->getFloorZ() === $position->getFloorZ()){
                $this->upgrades_yaws[$this->vecKey($p)] = $yaw;
                return;
            }
        }
        $this->upgrades_positions[] = $position;
        $this->upgrades_yaws[$this->vecKey($position)] = $yaw;
    }

    public function clearShopPositions(): void{
        $this->shop_positions = [];
        $this->shop_yaws = [];
    }

    public function clearUpgradesPositions(): void{
        $this->upgrades_positions = [];
        $this->upgrades_yaws = [];
    }

    public function removeNearestShopPosition(Vector3 $near, float $maxDistance = 3.5): bool{
        if($this->shop_positions === []){
            return false;
        }

        $bestIndex = null;
        $bestDist = $maxDistance;

        foreach($this->shop_positions as $i => $pos){
            $d = $pos->distance($near);
            if($d <= $bestDist){
                $bestDist = $d;
                $bestIndex = $i;
            }
        }

        if($bestIndex === null){
            return false;
        }

        $key = $this->vecKey($this->shop_positions[$bestIndex]);
        unset($this->shop_positions[$bestIndex], $this->shop_yaws[$key]);
        $this->shop_positions = array_values($this->shop_positions);
        return true;
    }

    public function removeNearestUpgradesPosition(Vector3 $near, float $maxDistance = 3.5): bool{
        if($this->upgrades_positions === []){
            return false;
        }

        $bestIndex = null;
        $bestDist = $maxDistance;

        foreach($this->upgrades_positions as $i => $pos){
            $d = $pos->distance($near);
            if($d <= $bestDist){
                $bestDist = $d;
                $bestIndex = $i;
            }
        }

        if($bestIndex === null){
            return false;
        }

        $key = $this->vecKey($this->upgrades_positions[$bestIndex]);
        unset($this->upgrades_positions[$bestIndex], $this->upgrades_yaws[$key]);
        $this->upgrades_positions = array_values($this->upgrades_positions);
        return true;
    }

    public function addGenerator(Generator $generator) : void{
        $this->generators[] = $generator;
    }

    public function removeGenerator(Generator $generator) : void{
        $idx = array_search($generator, $this->generators, true);
        if($idx === false){
            return;
        }
        unset($this->generators[$idx]);
    }

    private function addTeam(string $name) : void{
        $this->teams[] = new TeamBuilder($name);
    }

    private function setDefaultTeams() : void{
        $teams = ["Red", "Blue", "Yellow", "Green", "Aqua", "White", "Pink", "Gray"];
        for($i = 0; $i < $this->getTeamsCount(); $i++){
            $this->addTeam($teams[$i]);
        }
    }

    private function getTeamsCount() : int{
        return (int) ($this->max_capacity / $this->players_per_team);
    }

    /** @return TeamBuilder[] */
    public function getTeams() : array{
        return $this->teams;
    }

    public function getShopYaw(Vector3 $pos): float{
        return (float) ($this->shop_yaws[$this->vecKey($pos)] ?? 0.0);
    }

    public function getUpgradesYaw(Vector3 $pos): float{
        return (float) ($this->upgrades_yaws[$this->vecKey($pos)] ?? 0.0);
    }

    public function canBeBuilt() : bool{
        foreach($this->teams as $team){
            if(!$team->canBeBuilt()){
                return false;
            }
        }

        return isset(
            $this->spectator_spawn_position,
            $this->generators,
            $this->teams,
            $this->shop_positions,
            $this->upgrades_positions
        );
    }

    public function build() : Map{
        return new Map(
            $this->name,
            $this->spectator_spawn_position,
            $this->waiting_spawn_position,
            $this->players_per_team,
            $this->max_capacity,
            $this->waiting_world,
            $this->generators,
            array_map(fn(TeamBuilder $team_builder) : Team => $team_builder->build($this), $this->teams),
            $this->shop_positions,
            $this->upgrades_positions,
            $this->shop_yaws,
            $this->upgrades_yaws,
            $this->id ?? null
        );
    }
}