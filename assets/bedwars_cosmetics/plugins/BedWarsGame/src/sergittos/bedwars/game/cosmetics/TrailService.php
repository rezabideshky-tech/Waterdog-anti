<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\cosmetics;

use pocketmine\entity\projectile\Projectile;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\World;
use SplObjectStorage;
use function mt_rand;

final class TrailService extends Task{

    private static ?TrailService $instance = null;

    /** @var SplObjectStorage<Projectile, array{key:string, world:World}> */
    private SplObjectStorage $tracked;

    private function __construct(){
        $this->tracked = new SplObjectStorage();
    }

    public static function getInstance(): TrailService{
        return self::$instance ??= new TrailService();
    }

    public function track(Projectile $projectile, string $key): void{
        $this->tracked[$projectile] = ["key" => $key, "world" => $projectile->getWorld()];
    }

    public function onRun(): void{
        if($this->tracked->count() === 0){
            return;
        }

        foreach($this->tracked as $projectile){
            $data = $this->tracked[$projectile];

            if(!$projectile->isAlive() || $projectile->isClosed() || $projectile->isFlaggedForDespawn()){
                unset($this->tracked[$projectile]);
                continue;
            }

            $world = $data["world"];
            if($world !== $projectile->getWorld()){
                unset($this->tracked[$projectile]);
                continue;
            }

            $pos = $projectile->getPosition();
            $viewers = $this->nearPlayers($world, $pos, 96.0);
            if($viewers === []){
                continue;
            }

            $particle = match($data["key"]){
                "trail_flame" => new FlameParticle(),
                "trail_hearts" => new HeartParticle(1),
                "trail_portal" => new PortalParticle(),
                "trail_enchant" => new EnchantmentTableParticle(),
                "trail_critical" => new CriticalParticle(),
                default => null
            };

            if($particle === null){
                unset($this->tracked[$projectile]);
                continue;
            }

            $p = new Vector3(
                $pos->x + (mt_rand(-30, 30) / 100),
                $pos->y + 0.1 + (mt_rand(-10, 30) / 100),
                $pos->z + (mt_rand(-30, 30) / 100)
            );

            $world->addParticle($p, $particle, $viewers);
        }
    }

    /** @return Player[] */
    private function nearPlayers(World $world, Vector3 $pos, float $range): array{
        $out = [];
        $r2 = $range * $range;

        foreach($world->getPlayers() as $p){
            if($p->isConnected() && $p->getPosition()->distanceSquared($pos) <= $r2){
                $out[] = $p;
            }
        }

        return $out;
    }
}