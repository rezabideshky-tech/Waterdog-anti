<?php

declare(strict_types=1);

namespace sergittos\bedwars;

use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use sergittos\bedwars\clan\ClanManager;
use sergittos\bedwars\command\BwAdminCommand;
use sergittos\bedwars\command\PartyCommand;
use sergittos\bedwars\cosmetics\CosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticsRegistry;
use sergittos\bedwars\entity\PlayBedwarsEntity;
use sergittos\bedwars\hologram\HologramManager;
use sergittos\bedwars\kill\KillEffectManager;
use sergittos\bedwars\listener\CoreListener;
use sergittos\bedwars\network\NetworkManager;
use sergittos\bedwars\party\PartyManager;
use sergittos\bedwars\provider\mysql\AsyncMysqlProvider;
use sergittos\bedwars\rank\RankSystemAvailabilityWatcher;
use sergittos\bedwars\report\ReportService;
use sergittos\bedwars\reward\NotificationService;
use sergittos\bedwars\reward\listener\RewardActivityListener;
use sergittos\bedwars\reward\playtime\PlaytimeRewardManager;
use sergittos\bedwars\reward\streak\DailyLoginStreakManager;
use sergittos\bedwars\reward\task\RewardHeartbeatTask;
use sergittos\bedwars\session\SessionManager;
use sergittos\bedwars\task\PartyCleanupTask;

class BedWarsCore extends PluginBase{
    use SingletonTrait;

    private AsyncMysqlProvider $provider;
    private SessionManager $sessionManager;
    private PartyManager $partyManager;
    private CosmeticsManager $cosmeticsManager;
    private KillEffectManager $killEffectManager;
    private HologramManager $hologramManager;
    private NetworkManager $networkManager;
    private ReportService $reportService;
    private NotificationService $notificationService;
    private PlaytimeRewardManager $playtimeRewardManager;
    private DailyLoginStreakManager $dailyLoginStreakManager;
    private ClanManager $clanManager;
    private \sergittos\bedwars\profile\ProfileService $profileService;

    protected function onLoad(): void{
        self::setInstance($this);
    }

    protected function onEnable(): void{
        $this->saveDefaultConfig();
        $this->saveResource("config.yml");
        $this->saveResource("cosmetics.yml");
        $this->saveResource("mysql.sql", true);
        $this->saveResource("profile.yml");

        $this->provider = new AsyncMysqlProvider($this);
        $this->sessionManager = new SessionManager();
        $this->partyManager = new PartyManager();
        $this->cosmeticsManager = new CosmeticsManager($this);
        $this->killEffectManager = new KillEffectManager($this);
        $this->hologramManager = new HologramManager($this);
        $this->networkManager = new NetworkManager($this);
        $this->clanManager = new ClanManager($this);
        $this->clanManager->refreshAll();
        $this->reportService = new ReportService($this, $this->provider, $this->networkManager);
        $this->profileService = new \sergittos\bedwars\profile\ProfileService($this, $this->provider);

        $this->notificationService = new NotificationService();
        $this->dailyLoginStreakManager = new DailyLoginStreakManager($this, $this->notificationService);
        $this->playtimeRewardManager = new PlaytimeRewardManager($this, $this->notificationService, $this->dailyLoginStreakManager);

        CosmeticsRegistry::getInstance()->initDefaults();

        EntityFactory::getInstance()->register(PlayBedwarsEntity::class, function(World $world, CompoundTag $nbt): PlayBedwarsEntity{
            return new PlayBedwarsEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ["bedwars:play_entity"]);

        $coreListener = new CoreListener($this);
        // onQuitMonitor is registered automatically at MONITOR priority by the
        // registerEvents() call above (see its @priority MONITOR docblock) - it
        // used to ALSO be registered here manually, which meant it ran TWICE per
        // quit: once via this manual call (correctly, at MONITOR), but ALSO a
        // second time auto-detected by registerEvents() above at the default
        // NORMAL priority, since it's a public method with a single Event-typed
        // parameter like any other handler. That NORMAL-priority copy removed
        // the session from SessionManager immediately after CoreListener::onQuit
        // ran - i.e. before BedWarsGame's own PlayerQuitEvent handlers
        // (RejoinDisconnectListener, GameListener, JoinListener's session
        // checks, ...) ever got a chance to run, since BedWarsGame enables
        // after BedWarsCore and so registers its NORMAL-priority listeners
        // later. Every single mid-match disconnect saw an already-missing
        // session and silently skipped writing the rejoin record - this is
        // the actual root cause of "rejoin does nothing at all". The
        // duplicate manual registration below is removed; the @priority
        // MONITOR annotation is now the only thing controlling when this runs.
        $this->getServer()->getPluginManager()->registerEvents($coreListener, $this);
        $this->getServer()->getPluginManager()->registerEvents(new RewardActivityListener($this), $this);

        // See RankSystemAvailabilityWatcher's docblock: BedWarsCore loads at
        // STARTUP, RankSystem loads at the default POSTWORLD phase, so it is
        // never enabled yet at this exact point even when installed. This
        // watcher registers the actual rank-change listener once RankSystem
        // finishes enabling (or immediately, if it somehow already has).
        $rankSystemWatcher = new RankSystemAvailabilityWatcher($this);
        $this->getServer()->getPluginManager()->registerEvents($rankSystemWatcher, $this);
        $rankSystemWatcher->tryRegisterNow();

        $this->getServer()->getCommandMap()->register("bedwars", new PartyCommand($this));
        $this->getServer()->getCommandMap()->register("bedwars", new BwAdminCommand($this));

        $this->getScheduler()->scheduleRepeatingTask(new PartyCleanupTask($this), 600);
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(): void{
            foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
                foreach($world->getEntities() as $entity){
                    if($entity instanceof PlayBedwarsEntity){
                        $entity->updateNameTag();
                    }
                }
            }
        }), 100);

        $this->getScheduler()->scheduleRepeatingTask(
            new RewardHeartbeatTask($this, $this->notificationService, $this->playtimeRewardManager),
            1
        );

        // Keep the clan cache warm - this is what lets XP that Game servers
        // credit directly to MySQL show up in the Lobby's GUIs/hologram
        // without either side needing to talk to the other directly.
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(): void{
            $this->clanManager->refreshAll();
        }), 100);

        // Once-a-day check for the configured weekly reset day; a marker
        // file (not the DB) tracks the last reset so a server restart or a
        // check running twice in the same day can never double-reset.
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(): void{
            $this->checkClanWeeklyReset();
        }), 20 * 60 * 15);

        $this->getLogger()->info("§aBedWarsCore v2.0 enabled!");
    }

    protected function onDisable(): void{
        \sergittos\bedwars\cosmetics\wearable\ResourceCosmeticService::shutdown();
        foreach($this->sessionManager->getAll() as $session){
            $this->playtimeRewardManager->unloadPlayer($session->getPlayer());
        }
        if(isset($this->profileService)){
            $this->profileService->shutdown();
        }
        $this->sessionManager->saveAll();
        $this->networkManager->close();
        $this->getLogger()->info("§cBedWarsCore disabled.");
    }

    private function checkClanWeeklyReset(): void{
        $markerFile = $this->getDataFolder() . "clan_last_reset.txt";
        $today = date("Y-m-d");
        $lastReset = is_file($markerFile) ? trim((string) file_get_contents($markerFile)) : "";

        if($lastReset === $today){
            return;
        }
        if(((int) date("w")) !== $this->clanManager->getConfig()->getWeeklyResetDayOfWeek()){
            return;
        }

        $this->clanManager->performWeeklyReset();
        file_put_contents($markerFile, $today);
        $this->getLogger()->info("§aClan weekly leaderboard reset performed.");
    }

    public function getProvider(): AsyncMysqlProvider{ return $this->provider; }
    public function getSessionManager(): SessionManager{ return $this->sessionManager; }
    public function getPartyManager(): PartyManager{ return $this->partyManager; }
    public function getCosmeticsManager(): CosmeticsManager{ return $this->cosmeticsManager; }
    public function getKillEffectManager(): KillEffectManager{ return $this->killEffectManager; }
    public function getHologramManager(): HologramManager{ return $this->hologramManager; }
    public function getNetworkManager(): NetworkManager{ return $this->networkManager; }
    public function getReportService(): ReportService{ return $this->reportService; }
    public function getNotificationService(): NotificationService{ return $this->notificationService; }
    public function getPlaytimeRewardManager(): PlaytimeRewardManager{ return $this->playtimeRewardManager; }
    public function getDailyLoginStreakManager(): DailyLoginStreakManager{ return $this->dailyLoginStreakManager; }
    public function getClanManager(): ClanManager{ return $this->clanManager; }
    public function getProfileService(): \sergittos\bedwars\profile\ProfileService{ return $this->profileService; }
}