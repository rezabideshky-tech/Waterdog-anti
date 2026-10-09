<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\map;

use pocketmine\block\Bed;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\utils\ServerException;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\generator\GeneratorType;
use sergittos\bedwars\game\generator\presets\GoldGenerator;
use sergittos\bedwars\game\generator\presets\IronGenerator;
use sergittos\bedwars\game\team\Area;
use sergittos\bedwars\game\team\Team;
use function array_filter;
use function array_map;
use function array_search;
use function file_get_contents;
use function is_array;
use function json_decode;
use function strtolower;
use function ucfirst;
use function usort;

final class MapFactory{

    /** @var Map[] */
    private static array $maps = [];

    public static function init() : void{
        $raw = json_decode(file_get_contents(BedWars::getInstance()->getDataFolder() . "maps.json"), true);
        if(!is_array($raw)){
            $raw = [];
        }

        foreach($raw as $map_data){
            $id = isset($map_data["id"]) ? (string) $map_data["id"] : null;
            $name = (string) $map_data["name"];
            $world_name = (string) $map_data["waiting_world"];

            $players_per_team = (int) $map_data["players_per_team"];
            $capacity = (int) $map_data["max_capacity"];

            $wm = Server::getInstance()->getWorldManager();
            if(!$wm->loadWorld($world_name)){
                throw new ServerException("Couldn't load map world: " . $world_name);
            }

            $waiting_world = $wm->getWorldByName($world_name);
            if($waiting_world === null){
                throw new ServerException("World unavailable: " . $world_name);
            }

            $spec = (array) ($map_data["spectator_spawn_position"] ?? []);
            $spectator_spawn_position = new Vector3((float) $spec["x"], (float) $spec["y"], (float) $spec["z"]);

            $wait = $map_data["waiting_spawn_position"] ?? null;
            if(is_array($wait)){
                $waiting_spawn_position = new Vector3((float) $wait["x"], (float) $wait["y"], (float) $wait["z"]);
            }else{
                $sp = $waiting_world->getSpawnLocation();
                $waiting_spawn_position = new Vector3($sp->getX(), $sp->getY(), $sp->getZ());
            }

            $generators = [];
            foreach(($map_data["generators"] ?? []) as $type => $generator_data){
                foreach(($generator_data ?? []) as $position){
                    $generators[] = GeneratorType::toGenerator(self::createVector((array) $position), GeneratorType::fromString((string) $type));
                }
            }

            [$shop_locations, $shop_yaws] = self::createNpcPositions((array) ($map_data["shop_positions"] ?? []));
            [$upgrades_locations, $upgrades_yaws] = self::createNpcPositions((array) ($map_data["upgrades_positions"] ?? []));

            $teams = [];
            foreach((array) ($map_data["teams"] ?? []) as $data){
                $rawName = (string) ($data["name"] ?? "");
                $teamName = match(strtolower($rawName)){
                    "cyan" => "Aqua",
                    "orange" => "White",
                    "magenta" => "Pink",
                    default => ucfirst($rawName)
                };

                $generator_data = (array) ($data["generator"] ?? []);
                $areas_data = (array) ($data["areas"] ?? []);
                $bed_data = (array) ($data["bed"] ?? []);

                $bedPos = new Vector3((float) $bed_data["x"], (float) $bed_data["y"], (float) $bed_data["z"]);
                $b0 = $waiting_world->getBlockAt($bedPos->getFloorX(), $bedPos->getFloorY(), $bedPos->getFloorZ());
                if(!$b0 instanceof Bed){
                    $b1 = $waiting_world->getBlockAt($bedPos->getFloorX(), $bedPos->getFloorY() + 1, $bedPos->getFloorZ());
                    if($b1 instanceof Bed){
                        $bedPos = $bedPos->add(0, 1, 0);
                    }
                }

                $team_generators = [
                    new IronGenerator(self::createVector($generator_data)),
                    new GoldGenerator(self::createVector($generator_data))
                ];

                $teams[] = new Team(
                    $teamName,
                    $players_per_team,
                    self::createVector((array) $data["spawn_point"]),
                    $bedPos,
                    Area::fromData((array) ($areas_data["zone"] ?? [])),
                    Area::fromData((array) ($areas_data["claim"] ?? [])),
                    $team_generators
                );
            }

            $order8 = ["Red","Blue","Yellow","Green","Aqua","White","Pink","Gray"];
            $order4 = ["Red","Blue","Yellow","Green"];
            $order = ($players_per_team === 1 || $players_per_team === 2) ? $order8 : $order4;

            usort($teams, function(Team $a, Team $b) use ($order) : int{
                $ai = array_search($a->getName(), $order, true);
                $bi = array_search($b->getName(), $order, true);
                $ai = $ai === false ? 999 : $ai;
                $bi = $bi === false ? 999 : $bi;
                return $ai <=> $bi;
            });

            self::addMap(new Map(
                $name,
                $spectator_spawn_position,
                $waiting_spawn_position,
                $players_per_team,
                $capacity,
                $waiting_world,
                $generators,
                $teams,
                $shop_locations,
                $upgrades_locations,
                $shop_yaws,
                $upgrades_yaws,
                $id
            ));
        }
    }

    /** @return Map[] */
    public static function getMaps() : array{
        return self::$maps;
    }

    /** @return Map[] */
    public static function getMapsByPlayers(int $players_per_team) : array{
        return array_filter(self::$maps, fn(Map $map) => $map->getPlayersPerTeam() === $players_per_team);
    }

    public static function getMapByName(string $name) : ?Map{
        foreach(self::$maps as $map){
            if(strtolower($map->getName()) === strtolower($name)){
                return $map;
            }
        }
        return null;
    }

    public static function getMapById(string $id) : ?Map{
        return self::$maps[$id] ?? null;
    }

    public static function addMap(Map $map) : void{
        self::$maps[$map->getId()] = $map;
    }

    public static function removeMap(string $id) : void{
        unset(self::$maps[$id]);
    }

    private static function createVector(array $data) : Vector3{
        return new Vector3((float) $data["x"] + 0.5, (float) $data["y"] + 1.5, (float) $data["z"] + 0.5);
    }

    /**
     * @return array{0: Vector3[], 1: array<string,float>}
     */
    private static function createNpcPositions(array $positions): array{
        $out = [];
        $yaws = [];

        foreach($positions as $row){
            if(!is_array($row)){
                continue;
            }

            $pos = self::normalizeNpcVector($row);
            $key = $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();

            $out[$key] = $pos;

            $yaw = (float) ($row["yaw"] ?? 0.0);
            $yaws[$key] = $yaw;
        }

        return [array_values($out), $yaws];
    }

    private static function normalizeNpcVector(array $data): Vector3{
        $x = (float) ($data["x"] ?? 0.0);
        $y = (float) ($data["y"] ?? 0.0);
        $z = (float) ($data["z"] ?? 0.0);

        $fx = (float) floor($x);
        $fz = (float) floor($z);

        $nx = (abs(($x - $fx) - 0.5) < 0.000001) ? $x : ($fx + 0.5);
        $nz = (abs(($z - $fz) - 0.5) < 0.000001) ? $z : ($fz + 0.5);

        $fy = (float) floor($y);
        $fracY = $y - $fy;
        $ny = (abs($fracY - 0.5) < 0.000001) ? ($y - 0.5) : $y;

        return new Vector3($nx, $ny, $nz);
    }
}