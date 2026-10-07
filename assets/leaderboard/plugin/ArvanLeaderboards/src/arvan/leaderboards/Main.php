<?php
declare(strict_types=1);

namespace arvan\leaderboards;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

/**
 * Main — پلاگین لیدربوردهای انیمیشنی ArvanGaming
 *
 * دستورات:
 *   /lb crown <kills|wins|beds_broken|final_kills|level|coins>  👑 ساخت تاج غول‌پیکر
 *   /lb crown remove [radius] | list
 *   /lb spawn <top|podium|holo|bed|hw_top|hw_podium|hw_projector|hw_bed|hw_sign>
 *                                          ساخت NPC در محل ایستادن شما
 *   /lb remove [شعاع]                     حذف NPCهای اطراف
 *   /lb set <kills|wins|beds|deaths|points> <بازیکن> <مقدار>
 *   /lb add <track> <بازیکن> [مقدار]      افزودن به آمار (از پلاگین BedWars صدا بزنید)
 *   /lb show <بازیکن>                     نمایش کارنامهٔ یک بازیکن
 *   /lb track <top|podium> <track>        تغییر ستون نمایشی
 *   /lb refresh                           به‌روزرسانی دستی همهٔ تابلوها
 *   /lb reload                            بارگذاری دوبارهٔ config.yml
 */
final class Main extends PluginBase
{
    private static self $instance;
    private StatsStore $stats;
    private ?CrownManager $crowns = null;

    /** @var array<string, class-string<FloatingNpc>> */
    private const TYPES = [
        'top' => TopNpc::class,
        'podium' => PodiumNpc::class,
        'holo' => HoloNpc::class,
        'bed' => BedNpc::class,
        // ست هالووین (نسخهٔ ۱.۱) — همان امضای مشترک، تم کدو/روح/خفاش
        'hw_top' => HwTopNpc::class,
        'hw_podium' => HwPodiumNpc::class,
        'hw_projector' => HwProjectorNpc::class,
        'hw_bed' => HwBedNpc::class,
        'hw_sign' => HwSignNpc::class,
        // 👑 غول‌پیکر تاج‌ها (Giant Crowns) — رنگ جواهر وسط تاج = دستهٔ آمار
        'crown_kills' => CrownKillsNpc::class,
        'crown_wins' => CrownWinsNpc::class,
        'crown_beds_broken' => CrownBedsBrokenNpc::class,
        'crown_final_kills' => CrownFinalKillsNpc::class,
        'crown_level' => CrownLevelNpc::class,
        'crown_coins' => CrownCoinsNpc::class,
    ];

    public static function get(): self
    {
        return self::$instance;
    }

    public function stats(): StatsStore
    {
        return $this->stats;
    }

    protected function onEnable(): void
    {
        self::$instance = $this;
        $this->saveDefaultConfig();
        $this->stats = new StatsStore($this->getDataFolder() . 'stats.yml');
        $this->crowns = new CrownManager($this);

        $factory = CustomiesEntityFactory::getInstance();
        foreach (self::TYPES as $id => $class) {
            $factory->registerEntity($class, $class::NETWORK_ID);
            $this->getLogger()->info('§bثبت شد: §f' . $class::NETWORK_ID);
        }

        // به‌روزرسانی خودکار تابلوها
        $seconds = max(5, (int) $this->getConfig()->get('refresh-seconds', 20));
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->refreshAll();
        }), $seconds * 20);

        $this->getLogger()->info('§aArvanLeaderboards فعال شد — /lb spawn top');
        if (BedWarsSource::hasBedWars()) {
            $this->getLogger()->info('§a✔ اتصال به BedWarsCore برقرار شد — آمار تاج‌ها از bw_players خوانده می‌شود.');
        } else {
            $this->getLogger()->info('§eBedWarsCore پیدا نشد — تاج‌ها از stats.yml محلی تغذیه می‌شوند.');
        }
    }

    /** کلاس تاج مربوط به یک دسته (یا null اگر دسته نامعتبر باشد) */
    public static function crownClass(string $stat): ?string
    {
        $key = 'crown_' . $stat;
        $class = self::TYPES[$key] ?? null;
        return is_string($class) ? $class : null;
    }

    public function crowns(): CrownManager
    {
        return $this->crowns;
    }

    /** @return array<string, mixed> */
    public function npcConfig(string $key): array
    {
        return (array) $this->getConfig()->getNested('npcs.' . $key, []);
    }

    /** به‌روزرسانی نام‌تگ همهٔ NPCهای همهٔ دنیاها */
    public function refreshAll(): void
    {
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof FloatingNpc) {
                    $entity->refreshNameTag();
                }
            }
        }
    }

    /** وقتی بازیکن روی NPC ضربه می‌زند */
    public function onTap(Player $player, FloatingNpc $npc): void
    {
        $limit = 10;
        // 👑 تاج‌ها: جدول کامل همان دسته در چت (با همان اعداد BedWarsCore)
        if ($npc instanceof CrownNpc) {
            $this->crowns()->showTable($player, $npc->stat(), (int) $this->npcConfig($npc->getKey())['limit'] ?? 10);
            return;
        }

        switch ($npc->getKey()) {
            case 'top':
            case 'podium':
                $track = $npc instanceof TopNpc || $npc instanceof PodiumNpc ? $npc->track() : 'wins';
                $label = (string) $this->npcConfig($npc->getKey())['tracks'][$track]['label'] ?? $track;
                $player->sendMessage('§7———————— §l§6برترین‌های ' . $label . ' §r§7————————');
                foreach ($this->stats->top($track, $limit) as $i => $row) {
                    $player->sendMessage('§e' . ($i + 1) . '. §f' . TopNpc::pretty($row['name']) . ' §8» §a' . $row['value']);
                }
                break;
            case 'holo':
                $player->sendMessage('§bARVAN§fGAMING §7| بازیکنان: §a' . $this->stats->playerCount()
                    . ' §7| بردها: §a' . $this->stats->total('wins')
                    . ' §7| تخت‌ها: §a' . $this->stats->total('beds'));
                break;
            case 'bed':
                $player->sendMessage((string) ($this->npcConfig('bed')['tap-message'] ?? '§cBedWars §7روی ArvanGaming!'));
                break;
        }
    }

    /** ساخت NPC در محل بازیکن */
    public function spawn(Player $player, string $type): ?FloatingNpc
    {
        if (!isset(self::TYPES[$type])) {
            return null;
        }
        $class = self::TYPES[$type];
        /** @var FloatingNpc $entity */
        $entity = new $class($player->getLocation());
        $entity->spawnToAll();
        return $entity;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage('§cفقط داخل بازی قابل استفاده است.');
            return true;
        }
        switch ($args[0] ?? '') {
            case 'spawn':
                $type = (string) ($args[1] ?? 'top');
                $e = $this->spawn($sender, $type);
                $sender->sendMessage($e === null
                    ? '§cنوع نامعتبر. یکی از: §f' . implode(', ', array_keys(self::TYPES))
                    : '§aساخته شد: §f' . $type . ' §7(مدلش با ریسورس‌پک ArvanLeaderboard نمایش داده می‌شود)');
                return true;

            case 'crown':
                $sub = strtolower((string) ($args[1] ?? ''));
                if ($sub === 'list') {
                    $sender->sendMessage('§6دسته‌های تاج: §f' . implode('§7, §f', CrownManager::CROWN_STATS));
                    $sender->sendMessage('§7روش: §f/lb crown <دسته> §7— ساخت تاج در محل ایستادنت');
                    return true;
                }
                if ($sub === 'remove') {
                    $n = (int) ($args[2] ?? 10);
                    $sender->sendMessage('§e' . $this->crowns()->remove($sender, $n) . ' تاج حذف شد.');
                    return true;
                }
                if (!in_array($sub, CrownManager::CROWN_STATS, true)) {
                    $sender->sendMessage('§cدسته نامعتبر. یکی از: §f' . implode(', ', CrownManager::CROWN_STATS));
                    $sender->sendMessage('§7مثال: §f/lb crown kills');
                    return true;
                }
                $crown = $this->crowns()->spawn($sender, $sub);
                $sender->sendMessage($crown === null
                    ? '§cساخت تاج ناموفق بود.'
                    : '§a👑 تاج ساخته شد: §f' . $sub
                        . ' §7(رنگ جواهر وسط تاج همین دسته را نشان می‌دهد؛ با ضربه، جدول کامل باز می‌شود)');
                return true;

            case 'remove':
                $radius = (int) ($args[1] ?? 6);
                $n = 0;
                foreach ($sender->getWorld()->getNearbyEntities($sender->getBoundingBox()->expandedCopy($radius, 8, $radius)) as $e) {
                    if ($e instanceof FloatingNpc) {
                        $e->flagForDespawn();
                        $n++;
                    }
                }
                $sender->sendMessage('§e' . $n . ' NPC حذف شد.');
                return true;

            case 'set':
            case 'add':
                $track = (string) ($args[1] ?? '');
                $name = (string) ($args[2] ?? '');
                $value = (int) ($args[3] ?? 1);
                if (!in_array($track, StatsStore::TRACKS, true) || $name === '') {
                    $sender->sendMessage('§cروش: /lb ' . $args[0] . ' <' . implode('|', StatsStore::TRACKS) . '> <بازیکن> <مقدار>');
                    return true;
                }
                if ($args[0] === 'set') {
                    $this->stats->set($name, $track, $value);
                } else {
                    $this->stats->push($name, $track, $value);
                }
                $this->refreshAll();
                $sender->sendMessage('§aثبت شد: §f' . $name . ' §7' . $track . ' = §e' . $this->stats->of($name, $track));
                return true;

            case 'show':
                $name = (string) ($args[1] ?? $sender->getName());
                $sender->sendMessage('§7———— §l§6کارنامه ' . TopNpc::pretty($name) . ' §r§7————');
                foreach (StatsStore::TRACKS as $track) {
                    $sender->sendMessage('§f' . $track . ': §a' . $this->stats->of($name, $track));
                }
                return true;

            case 'track':
                $which = (string) ($args[1] ?? 'top');
                $track = (string) ($args[2] ?? '');
                if (!in_array($track, StatsStore::TRACKS, true)) {
                    $sender->sendMessage('§cستون‌ها: §f' . implode(', ', StatsStore::TRACKS));
                    return true;
                }
                $this->getConfig()->setNested('npcs.' . $which . '.track', $track);
                $this->getConfig()->save();
                $this->refreshAll();
                $sender->sendMessage('§aستون §f' . $which . ' §aبه §e' . $track . ' §aتغییر کرد.');
                return true;

            case 'refresh':
                $this->refreshAll();
                $sender->sendMessage('§aهمهٔ تابلوها به‌روز شدند.');
                return true;

            case 'reload':
                $this->reloadConfig();
                $this->refreshAll();
                $sender->sendMessage('§aconfig.yml دوباره خوانده شد.');
                return true;
        }
        $sender->sendMessage("§6/lb spawn <" . implode('|', array_keys(self::TYPES)) . ">\n§6/lb remove [radius]\n§6/lb set|add <track> <player> <value>\n§6/lb show [player]\n§6/lb track <top|podium> <track>\n§6/lb refresh | reload");
        return true;
    }
}
