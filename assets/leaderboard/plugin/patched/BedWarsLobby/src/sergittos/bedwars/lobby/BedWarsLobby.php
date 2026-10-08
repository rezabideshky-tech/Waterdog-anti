<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use sergittos\bedwars\lobby\battlepass\command\BattlePassAdminCommand;
use sergittos\bedwars\lobby\battlepass\command\BattlePassCommand;
use sergittos\bedwars\lobby\battlepass\data\PlayerBattlePassDataManager;
use sergittos\bedwars\lobby\battlepass\registry\BattlePassRegistry;
use sergittos\bedwars\lobby\challenges\command\ChallengesAdminCommand;
use sergittos\bedwars\lobby\challenges\command\ChallengesCommand;
use sergittos\bedwars\lobby\challenges\data\PlayerChallengeDataManager;
use sergittos\bedwars\lobby\challenges\registry\ChallengeRegistry;
use sergittos\bedwars\lobby\clan\ClanChatManager;
use sergittos\bedwars\lobby\command\BwHologramCommand;
use sergittos\bedwars\lobby\command\ClanCommand;
use sergittos\bedwars\lobby\command\DeleteJoinEntityCommand;
use sergittos\bedwars\lobby\command\LbSpawnCommand;
use sergittos\bedwars\lobby\command\LeaderboardCommand;
use sergittos\bedwars\lobby\command\LobbyCommand;
use sergittos\bedwars\lobby\command\QuestsCommand;
use sergittos\bedwars\lobby\command\ReportCommand;
use sergittos\bedwars\lobby\command\SetLobbyCommand;
use sergittos\bedwars\lobby\command\SpawnJoinEntityCommand;
use sergittos\bedwars\lobby\command\SpawnStatsHologramCommand;
use sergittos\bedwars\lobby\friends\FriendManager;
use sergittos\bedwars\lobby\halloween\HalloweenLeaderboardCommand;
use sergittos\bedwars\lobby\leaderboard\ClanLeaderboardManager;
use sergittos\bedwars\lobby\leaderboard\LeaderboardManager;
use sergittos\bedwars\lobby\listener\LobbyListener;
use sergittos\bedwars\lobby\manager\LobbyManager;
use sergittos\bedwars\lobby\quests\data\PlayerQuestDataManager;
use sergittos\bedwars\lobby\quests\quest\QuestRegistry;
use sergittos\bedwars\lobby\quests\task\QuestSyncTask;
use sergittos\bedwars\lobby\task\LeaderboardUpdateTask;

class BedWarsLobby extends PluginBase{
    use SingletonTrait;

    private LeaderboardManager $leaderboardManager;
    private LobbyManager $lobbyManager;
    private FriendManager $friendManager;
    private PlayerQuestDataManager $questData;

    private ChallengeRegistry $challengesRegistry;
    private PlayerChallengeDataManager $challengesData;

    private BattlePassRegistry $battlePassRegistry;
    private PlayerBattlePassDataManager $battlePassData;

    private ClanChatManager $clanChatManager;
    private ClanLeaderboardManager $clanLeaderboardManager;

    protected function onLoad() : void{
        self::setInstance($this);
    }

    protected function onEnable() : void{
        $core = $this->getServer()->getPluginManager()->getPlugin("BedWarsCore");
        if($core === null){
            $this->getLogger()->critical("BedWarsCore is required! Disabling...");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->saveDefaultConfig();
        $this->saveResource("config.yml");
        $this->saveResource("challenges.yml");

        $this->leaderboardManager = new LeaderboardManager($this);
        // LeaderboardManager's initial spawnAll() (called from its constructor,
        // above) runs exactly once, at plugin-enable time, and each stat's
        // board comes from its own async DB query. If a query is still slow
        // to resolve, or this world hasn't finished loading yet at that
        // instant, LeaderboardManager::spawn() silently skips that one stat
        // and - since nothing ever called it again - that board simply never
        // appears. Because query/world-load timing varies run to run, this
        // is exactly the "sometimes half the leaderboards are missing on
        // join" symptom: it depends on which queries happened to still be in
        // flight at that single moment. LeaderboardUpdateTask already existed
        // for this (mirroring ClanLeaderboardManager's own repeating refresh
        // a few lines below) but was never scheduled, so it never ran.
        // Scheduling it here makes every board self-heal on the next tick of
        // this task even if it was missed at startup.
        $this->getScheduler()->scheduleRepeatingTask(new LeaderboardUpdateTask($this), 20 * 30);
        $this->lobbyManager = new LobbyManager($this);
        $this->friendManager = new FriendManager($this->getDataFolder());

        $this->questData = new PlayerQuestDataManager($this->getDataFolder());
        QuestRegistry::getInstance()->initDefaults();
        $this->getScheduler()->scheduleRepeatingTask(new QuestSyncTask($this->questData), 20);

        $this->challengesRegistry = new ChallengeRegistry($this->getDataFolder() . "challenges.yml");
        $this->challengesRegistry->reload();
        $this->challengesData = new PlayerChallengeDataManager();

        $this->battlePassRegistry = new BattlePassRegistry($this->getDataFolder() . "battlepass.yml");
        $this->battlePassRegistry->reload();
        $this->battlePassData = new PlayerBattlePassDataManager();

        $this->clanChatManager = new ClanChatManager();
        $this->clanLeaderboardManager = new ClanLeaderboardManager($this);
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(): void{
            $this->clanLeaderboardManager->refresh();
        }), 100);

        \sergittos\bedwars\cosmetics\wearable\HatItemRegistrar::register($this);
        \sergittos\bedwars\lobby\pets\PetItemRegistrar::register($this);
        \sergittos\bedwars\lobby\clan\ClanCrestResourcePackInstaller::install($this);
        \sergittos\bedwars\lobby\gui\LockerIconResourcePackInstaller::install($this);
        \sergittos\bedwars\lobby\profile\ProfileUiResourcePackInstaller::install($this);

        $this->getServer()->getPluginManager()->registerEvents(new LobbyListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new \sergittos\bedwars\lobby\clan\ClanChatListener($this), $this);

        $this->getServer()->getCommandMap()->registerAll("bedwars", [
            new LobbyCommand($this),
            new LeaderboardCommand($this),
            new BwHologramCommand($this),
            new SetLobbyCommand($this),
            new SpawnStatsHologramCommand($this),
            new LbSpawnCommand($this),
            new SpawnJoinEntityCommand(),
            new HalloweenLeaderboardCommand($this),
            new DeleteJoinEntityCommand(),
            new QuestsCommand($this->questData),

            new ChallengesCommand($this->challengesRegistry, $this->challengesData),
            new ChallengesAdminCommand($this->challengesRegistry),

            new BattlePassCommand($this->battlePassRegistry, $this->battlePassData),
            new BattlePassAdminCommand($this->battlePassRegistry, $this->battlePassData),

            // ClanCommand is temporarily disabled (feature kept in code,
            // just not registered) - re-add this line to bring /clan back.
            // new ClanCommand($this),
            new ReportCommand($this),

            new \sergittos\bedwars\lobby\profile\ProfileCommand(),
            new \sergittos\bedwars\lobby\profile\SeasonCommand(),
        ]);

        $this->getLogger()->info("§aBedWarsLobby v2.0 enabled!");
    }

    public function getLeaderboardManager() : LeaderboardManager{ return $this->leaderboardManager; }
    public function getLobbyManager() : LobbyManager{ return $this->lobbyManager; }
    public function getFriendManager() : FriendManager{ return $this->friendManager; }
    public function getQuestData() : PlayerQuestDataManager{ return $this->questData; }

    public function getChallengesRegistry(): ChallengeRegistry{ return $this->challengesRegistry; }
    public function getChallengesData(): PlayerChallengeDataManager{ return $this->challengesData; }

    public function getBattlePassRegistry(): BattlePassRegistry{ return $this->battlePassRegistry; }
    public function getBattlePassData(): PlayerBattlePassDataManager{ return $this->battlePassData; }

    public function getClanChatManager(): ClanChatManager{ return $this->clanChatManager; }
    public function getClanLeaderboardManager(): ClanLeaderboardManager{ return $this->clanLeaderboardManager; }
}