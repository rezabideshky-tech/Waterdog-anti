<?php

declare(strict_types=1);

namespace sergittos\bedwars\game;

use muqsit\invmenu\InvMenuHandler;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\event\Listener;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use sergittos\bedwars\command\BedWarsCommand;
use sergittos\bedwars\command\ReportCommand;
use sergittos\bedwars\game\cinematic\podium\PodiumMusicResourcePackInstaller;
use sergittos\bedwars\game\cosmetics\TrailListener;
use sergittos\bedwars\game\cosmetics\TrailService;
use sergittos\bedwars\game\entity\misc\EggBridgeEntity;
use sergittos\bedwars\game\entity\misc\Fireball;
use sergittos\bedwars\game\entity\shop\ItemShopVillager;
use sergittos\bedwars\game\entity\shop\UpgradesShopVillager;
use sergittos\bedwars\game\generator\GeneratorDisplayBlock;
use sergittos\bedwars\game\map\MapFactory;
use sergittos\bedwars\game\task\ForeignWorldAutoSaveGuardTask;
use sergittos\bedwars\listener\ExplosionParticleReducerListener;
use sergittos\bedwars\listener\GameChatListener;
use sergittos\bedwars\listener\GameChunkLockListener;
use sergittos\bedwars\listener\GameListener;
use sergittos\bedwars\listener\ItemListener;
use sergittos\bedwars\listener\JoinListener;
use sergittos\bedwars\listener\RejoinDisconnectListener;
use sergittos\bedwars\listener\SetupListener;
use sergittos\bedwars\listener\ShopEditorCleanupListener;
use sergittos\bedwars\listener\SpawnProtectionListener;
use sergittos\bedwars\listener\SpectatorProtectionListener;
use sergittos\bedwars\listener\SpectatorRawItemUseListener;
use sergittos\bedwars\listener\WaitingListener;
use sergittos\bedwars\listener\WearableApplyListener;
use sergittos\bedwars\session\SessionFactory;
use sergittos\bedwars\utils\ConfigGetter;
use pocketmine\utils\Filesystem;
use function basename;
use function ctype_digit;
use function is_dir;
use function is_string;
use function scandir;
use function strrpos;
use function substr;

class BedWarsGame extends PluginBase{
    use SingletonTrait;

    private GameManager $game_manager;

    protected function onLoad(): void{
        self::setInstance($this);

        $worlds_dir = $this->getDataFolder() . "worlds/";
        if(!is_dir($worlds_dir)){
            mkdir($worlds_dir, 0777, true);
        }

        $this->saveResource("maps.json");
    }

    protected function onEnable(): void{
        MapFactory::init();

        // Deletes any leftover per-game world copies ("<mapName>-<id>"
        // folders) sitting in the server's real worlds/ folder from a
        // previous run before anything else touches that folder. These
        // are only ever left behind by an unclean shutdown/crash now
        // (see onDisable(), which cleans up properly on a normal
        // stop) but this guarantees they can never survive a restart
        // and quietly build up over time either way.
        $this->cleanupOrphanedGameWorlds();

        if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }

        $this->game_manager = new GameManager();

        \sergittos\bedwars\cosmetics\wearable\ResourceCosmeticService::initialize($this);

        try{
            PodiumMusicResourcePackInstaller::install($this);
        }catch(\Throwable $e){
            $this->getLogger()->warning("Podium Ceremony music resource pack failed to install (ceremony will continue silently): " . $e->getMessage());
        }

        $this->registerVillager(ItemShopVillager::class);
        $this->registerVillager(UpgradesShopVillager::class);

        $this->registerFireball();
        $this->registerEggBridge();
        $this->registerSilverfishSnowball();
        $this->registerMobs();
        $this->registerGeneratorDisplayBlock();

        $listeners = [
            new GameListener($this->getServer()),
            new GameChunkLockListener(),
            new RejoinDisconnectListener(),
            new SpectatorProtectionListener(),
            new SpectatorRawItemUseListener(),
            new ExplosionParticleReducerListener(),
            new GameChatListener(),
            new ItemListener(),
            new JoinListener(),
            new SetupListener(),
            new WaitingListener(),
            new WearableApplyListener(),
            new TrailListener(),
            new ShopEditorCleanupListener(),
        ];

        if(ConfigGetter::isSpawnProtectionEnabled()){
            $listeners[] = new SpawnProtectionListener();
        }

        foreach($listeners as $listener){
            $this->registerListener($listener);
        }

        $this->getServer()->getCommandMap()->register("bedwars", new BedWarsCommand());
        $this->getServer()->getCommandMap()->register("bedwars", new ReportCommand());
        $this->getScheduler()->scheduleRepeatingTask(new GameHeartbeat(), 1);
        $this->getScheduler()->scheduleRepeatingTask(TrailService::getInstance(), 2);

        $guardConfig = (array) $this->getConfig()->get("foreign-world-autosave-guard", []);
        $guardEnabled = (bool) ($guardConfig["enabled"] ?? true);
        if($guardEnabled){
            $prefixes = [];
            foreach((array) ($guardConfig["prefixes"] ?? []) as $prefix){
                if(is_string($prefix) && $prefix !== ""){
                    $prefixes[] = $prefix;
                }
            }
            if($prefixes === []){
                $prefixes = ["WaitingLobby-"];
            }
            // Runs every 30s - cheap (just a foreach over already-loaded
            // worlds, no I/O of its own) and only ever needs to act once
            // per foreign world's lifetime (see $handled in the task).
            $this->getScheduler()->scheduleRepeatingTask(new ForeignWorldAutoSaveGuardTask($prefixes), 20 * 30);
        }
    }

    protected function onDisable(): void{
        \sergittos\bedwars\cosmetics\wearable\ResourceCosmeticService::shutdown();
        foreach(SessionFactory::getSessions() as $session){
            $session->save();
        }

        if(isset($this->game_manager)){
            foreach($this->game_manager->getGames() as $game){
                $world_folder_name = $game->getMap()->getName() . "-" . $game->getId();
                $game->unloadWorld();

                // Matches never resume across a restart, so a game's
                // world copy has no reason to survive shutdown. This
                // used to only unload the world and leave its folder
                // on disk, relying on an async task (SafeRemoveGameTask
                // / RemoveGameWorldFolderTask) to delete it later - but
                // the async pool can be mid-shutdown too, so that task
                // was never guaranteed to actually run before the
                // process exited, leaving the folder as a permanent
                // orphan. Deleting it here, synchronously, on the main
                // thread, is what actually guarantees it's gone.
                $path = $this->getServer()->getDataPath() . "worlds/" . $world_folder_name;
                if(is_dir($path)){
                    try{
                        Filesystem::recursiveUnlink($path);
                    }catch(\Throwable $e){
                        $this->getLogger()->warning("Failed to remove leftover game world folder '" . $world_folder_name . "': " . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Removes any "<mapName>-<id>" folder in the server's worlds/
     * directory that belongs to a known map but isn't the map's own
     * setup/master copy (that one has no numeric "-<id>" suffix and
     * lives under this plugin's data folder, not here) - i.e. every
     * folder this plugin could ever have generated as a per-game
     * instance copy.
     */
    private function cleanupOrphanedGameWorlds(): void{
        $worlds_dir = $this->getServer()->getDataPath() . "worlds/";
        if(!is_dir($worlds_dir)){
            return;
        }

        $map_names = [];
        foreach(MapFactory::getMaps() as $map){
            $map_names[$map->getName()] = true;
        }
        if($map_names === []){
            return;
        }

        $entries = @scandir($worlds_dir);
        if($entries === false){
            return;
        }

        foreach($entries as $entry){
            if($entry === "." || $entry === ".."){
                continue;
            }

            $dash = strrpos($entry, "-");
            if($dash === false){
                continue;
            }

            $map_name = substr($entry, 0, $dash);
            $instance_id = substr($entry, $dash + 1);

            if($instance_id === "" || !ctype_digit($instance_id)){
                continue;
            }

            if(!isset($map_names[$map_name])){
                continue;
            }

            $path = $worlds_dir . $entry;
            if(is_dir($path)){
                try{
                    Filesystem::recursiveUnlink($path);
                }catch(\Throwable $e){
                    $this->getLogger()->warning("Failed to remove orphaned game world folder '" . $entry . "': " . $e->getMessage());
                }
            }
        }
    }

    private function registerListener(Listener $listener): void{
        $this->getServer()->getPluginManager()->registerEvents($listener, $this);
    }

    private function registerVillager(string $class): void{
        EntityFactory::getInstance()->register(
            $class,
            function(World $world, CompoundTag $nbt) use ($class): Entity{
                return new $class(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            },
            ["bedwars:" . basename(str_replace('\\', '/', $class))]
        );
    }

    private function registerFireball(): void{
        EntityFactory::getInstance()->register(Fireball::class, function(World $world, CompoundTag $nbt): Fireball{
            return new Fireball(EntityDataHelper::parseLocation($nbt, $world), null);
        }, ["bedwars:fireball"]);
    }

    /**
     * بدون این ثبت، World::addEntity() هر بار که یه GeneratorDisplayBlock
     * (بلوکِ نمایشیِ ثابتِ زیر هولوگرام ژنراتور دایمند/امرالد) اسپاون
     * می‌شه با LogicException کرش می‌کنه: "Entity ... is not registered
     * for a save ID in EntityFactory" - این چک مستقل از اینه که اون
     * انتیتی واقعاً قراره روی دیسک سیو بشه یا نه، برای هر entity ای که
     * وارد دنیا می‌شه اجباریه. بقیه‌ی انتیتی‌های سفارشی این پلاگین
     * (Fireball، EggBridgeEntity، مموب‌ها و ...) هم دقیقاً به همین دلیل
     * اینجا ثبت شدن.
     *
     * از اون‌جایی که GeneratorDisplayBlock الان یه ItemEntity هست (نه
     * FallingBlock - نگاه کن به توضیحات کلاس خودش)، بازسازیِ NBT هم از
     * همون الگوی خودِ pocketmine برای ItemEntity استفاده می‌کنه: آیتمِ
     * ذخیره‌شده از تگِ کامپاوندِ "Item" با Item::nbtDeserialize() خونده
     * می‌شه. در عمل این انتیتی‌ها همراه با خودِ دنیای موقتِ مسابقه از بین
     * می‌رن و اصلاً به این مسیر نیاز پیدا نمی‌کنن؛ فقط برای این‌که
     * World::addEntity() کرش نکنه ثبت شده (نگاه کن به توضیحات بالا).
     */
    private function registerGeneratorDisplayBlock(): void{
        EntityFactory::getInstance()->register(GeneratorDisplayBlock::class, function(World $world, CompoundTag $nbt): GeneratorDisplayBlock{
            $itemTag = $nbt->getCompoundTag(GeneratorDisplayBlock::TAG_ITEM);
            $item = $itemTag !== null ? \pocketmine\item\Item::nbtDeserialize($itemTag) : \pocketmine\item\VanillaItems::AIR();
            return new GeneratorDisplayBlock(EntityDataHelper::parseLocation($nbt, $world), $item, $nbt);
        }, ["bedwars:generator_display_block"]);
    }

    private function registerEggBridge(): void{
        EntityFactory::getInstance()->register(EggBridgeEntity::class, function(World $world, CompoundTag $nbt): EggBridgeEntity{
            return new EggBridgeEntity(EntityDataHelper::parseLocation($nbt, $world), null, \pocketmine\block\utils\DyeColor::WHITE(), null, 0);
        }, ["bedwars:egg_bridge"]);

        // The walker the egg spawns once it lands (see EggBridgeEntity::spawnBridgeBuilder()).
        // Reloaded orphans (e.g. after a server restart) get no owner/game and
        // despawn on their very first tick - see BridgeBuilderEntity::onUpdate().
        EntityFactory::getInstance()->register(
            \sergittos\bedwars\game\entity\misc\BridgeBuilderEntity::class,
            function(World $world, CompoundTag $nbt): \sergittos\bedwars\game\entity\misc\BridgeBuilderEntity{
                return new \sergittos\bedwars\game\entity\misc\BridgeBuilderEntity(EntityDataHelper::parseLocation($nbt, $world));
            },
            ["bedwars:bridge_builder"]
        );
    }

    private function registerSilverfishSnowball(): void{
        EntityFactory::getInstance()->register(
            \sergittos\bedwars\game\entity\misc\SilverfishSnowballEntity::class,
            function(World $world, CompoundTag $nbt): \sergittos\bedwars\game\entity\misc\SilverfishSnowballEntity{
                return new \sergittos\bedwars\game\entity\misc\SilverfishSnowballEntity(EntityDataHelper::parseLocation($nbt, $world), null, null, null);
            },
            ["bedwars:silverfish_snowball"]
        );
    }

    private function registerMobs(): void{
        EntityFactory::getInstance()->register(
            \sergittos\bedwars\game\entity\mob\IronGolemEntity::class,
            function(World $world, CompoundTag $nbt): \sergittos\bedwars\game\entity\mob\IronGolemEntity{
                return new \sergittos\bedwars\game\entity\mob\IronGolemEntity(EntityDataHelper::parseLocation($nbt, $world));
            },
            ["bedwars:iron_golem"]
        );

        EntityFactory::getInstance()->register(
            \sergittos\bedwars\game\entity\mob\SilverfishEntity::class,
            function(World $world, CompoundTag $nbt): \sergittos\bedwars\game\entity\mob\SilverfishEntity{
                return new \sergittos\bedwars\game\entity\mob\SilverfishEntity(EntityDataHelper::parseLocation($nbt, $world));
            },
            ["bedwars:silverfish"]
        );
    }

    public function getGameManager(): GameManager{
        return $this->game_manager;
    }
}