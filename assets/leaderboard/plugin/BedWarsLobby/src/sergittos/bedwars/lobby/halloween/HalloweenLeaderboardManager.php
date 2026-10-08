<?php
declare(strict_types=1);

namespace sergittos\bedwars\lobby\halloween;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use sergittos\bedwars\lobby\BedWarsLobby;

/**
 * HalloweenLeaderboardManager — ساخت/حذف پایه‌های هالووینی لیدربوردها.
 *
 * محل مصرف: بعد از این‌که LobbyManager (یا LeaderboardManager) هولوگرام متن لیدربورد رو
 * روی $pos ساختن، همون $pos و $world به summon() داده می‌شه و انتیتی روی
 * $pos.y - HalloweenPedestal::Y_OFFSET اسپاون می‌شه (پایین پای متن، تاج بالای متن).
 *
 * اگر Customies نصب نباشه، پایه‌ها بی‌صدا غیرفعال می‌شن و لیدربورد متنی سر جاش می‌مونه.
 */
final class HalloweenLeaderboardManager {

    /** stat (همون ستون دیتابیس پلاگین) => کلاس انتیتی */
    public const VARIANTS = [
        "kills" => PedestalKills::class,
        "wins" => PedestalWins::class,
        "final_kills" => PedestalFinalKills::class,
        "beds_broken" => PedestalBedsBroken::class,
        "deaths" => PedestalDeaths::class,
        "level" => PedestalLevel::class,
        "coins" => PedestalCoins::class,
        "win_streak" => PedestalWinStreak::class,
    ];

    private BedWarsLobby $plugin;
    private bool $enabled = false;
    private bool $active = true;

    /** @var array<string, int> key => entity id */
    private array $spawned = [];
    /** @var array<string, array{world: string, x: float, y: float, z: float, stat: string}> */
    private array $positions = [];

    public function __construct(BedWarsLobby $plugin) {
        $this->plugin = $plugin;
        $this->active = (bool) $plugin->getConfig()->getNested("halloween_pedestals", true);
    }

    /** در onEnable صدا زده می‌شه: ثبت ۸ واریانت در Customies. */
    public function registerVariants() : void{
        if(!class_exists(CustomiesEntityFactory::class)) {
            $this->plugin->getLogger()->notice("Customies پیدا نشد — دکور هالووینی لیدربوردها غیرفعال موند (خود متن لیدربوردها مشکلی نداره).");
            return;
        }
        $factory = CustomiesEntityFactory::getInstance();
        foreach(self::VARIANTS as $class) {
            $factory->registerEntity($class, $class::NETWORK_ID);
        }
        $this->enabled = true;
    }

    public function isEnabled() : bool{
        return $this->enabled && $this->active;
    }

    public function setActive(bool $active) : void{
        $this->active = $active;
        $this->plugin->getConfig()->setNested("halloween_pedestals", $active);
        $this->plugin->getConfig()->save();
        if(!$active) {
            foreach($this->spawned as $key => $id) {
                $this->despawnByKey($key);
            }
        }
    }

    /**
     * پایه‌ی مخصوص یک استت را روی نقطه‌ی هولوگرام می‌سازه (یا اگه از قبل هست، هم‌جا می‌کنه).
     */
    public function summon(string $stat, World $world, Vector3 $pos) : void{
        $class = self::VARIANTS[$stat] ?? null;
        if($class === null || !$this->isEnabled()) {
            return;
        }

        $key = $this->key($stat, $world, $pos);
        $this->positions[$key] = ["world" => $world->getFolderName(), "x" => $pos->x, "y" => $pos->y, "z" => $pos->z, "stat" => $stat];

        $existing = $this->resolve($key);
        if($existing !== null) {
            $existing->teleport(self::baseLocation($pos, $world));
            return;
        }

        /** @var HalloweenPedestal $entity */
        $entity = new $class(self::baseLocation($pos, $world));
        $entity->spawnToAll();
        $this->spawned[$key] = $entity->getId();
    }

    public function remove(string $stat, World $world, Vector3 $pos) : void{
        $key = $this->key($stat, $world, $pos);
        unset($this->positions[$key]);
        $this->despawnByKey($key);
    }

    /** همه‌ی پایه‌های ثبت‌شده را از نو می‌سازه (بعد از ری‌لود چانک/ورلد یا /bwhalloween respawn). */
    public function respawnAll() : int{
        $count = 0;
        foreach($this->positions as $data) {
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($data["world"]);
            if($world === null) {
                continue;
            }
            $this->summon($data["stat"], $world, new Vector3((float) $data["x"], (float) $data["y"], (float) $data["z"]));
            $count++;
        }
        return $count;
    }

    /** @return array<string, int> */
    public function getSpawned() : array{
        return $this->spawned;
    }

    private function despawnByKey(string $key) : void{
        $entity = $this->resolve($key);
        if($entity !== null) {
            $entity->flagForDespawn();
        }
        unset($this->spawned[$key]);
    }

    private function resolve(string $key) : ?HalloweenPedestal{
        if(!isset($this->spawned[$key])) {
            return null;
        }
        [$folder] = explode("|", $key, 2);
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($folder);
        if($world === null) {
            unset($this->spawned[$key]);
            return null;
        }
        $entity = $world->getEntity($this->spawned[$key]);
        if(!$entity instanceof HalloweenPedestal) {
            unset($this->spawned[$key]);
            return null;
        }
        return $entity;
    }

    private static function baseLocation(Vector3 $pos, World $world) : Location{
        // پای مدل روی زمین پایه می‌شینه؛ متن هم دقیقاً وسط قاب قرار می‌گیره.
        return Location::fromObject(
            new Vector3($pos->x, $pos->y - HalloweenPedestal::Y_OFFSET, $pos->z),
            $world
        );
    }

    private function key(string $stat, World $world, Vector3 $pos) : string{
        return $world->getFolderName() . "|" . $stat . "|" . round($pos->x, 2) . "|" . round($pos->y, 2) . "|" . round($pos->z, 2);
    }
}
