<?php
declare(strict_types=1);

namespace arvan\leaderboards;

use pocketmine\player\Player;

/**
 * CrownManager — مدیریت «تاج‌های غول‌پیکر».
 *
 * کارها:
 *   • ساخت تاجِ هر دسته در محل ایستادن ادمین
 *   • حذف تاج‌های اطراف
 *   • نشان دادن جدول کامل یک دسته در چت (وقتی بازیکن تاج را لمس می‌کند)
 *
 * داده از BedWarsSource می‌آید؛ یعنی همان اعداد لیدربوردهای BedWarsCore v2.
 */
final class CrownManager
{
    /** دسته‌های پیشنهادی برای هر تاج (کلید کلاس = crown_<stat>) */
    public const CROWN_STATS = ['kills', 'wins', 'beds_broken', 'final_kills', 'level', 'coins'];

    public function __construct(private Main $plugin) {}

    /** ساخت تاج در محل بازیکن. اگر دسته نامعتبر باشد null برمی‌گرداند. */
    public function spawn(Player $player, string $stat): ?CrownNpc
    {
        $key = 'crown_' . $stat;
        $npc = $this->plugin->spawn($player, $key);
        return $npc instanceof CrownNpc ? $npc : null;
    }

    /**
     * ساخت تاج در یک مکان مشخص (برای صدا زدن از پلاگین‌های دیگر مثل BedWarsLobby).
     * کاملاً محافظت‌شده: اگر چیزی سر جایش نباشد، فقط null برمی‌گرداند.
     */
    public function spawnAt(\pocketmine\world\World $world, \pocketmine\math\Vector3 $pos, string $stat): ?CrownNpc
    {
        try {
            $class = Main::crownClass($stat);
            if ($class === null) {
                return null;
            }
            /** @var CrownNpc $npc */
            $npc = new $class(\pocketmine\entity\Location::fromObject($pos, $world));
            $npc->spawnToAll();
            return $npc;
        } catch (\Throwable $e) {
            $this->plugin->getLogger()->warning('spawnAt(' . $stat . ') failed: ' . $e->getMessage());
            return null;
        }
    }

    /** حذف تاج‌های اطراف بازیکن (و بقیهٔ NPCها اگر all=true) */
    public function remove(Player $player, int $radius = 8, bool $all = false): int
    {
        $n = 0;
        $box = $player->getBoundingBox()->expandedCopy($radius, 12, $radius);
        foreach ($player->getWorld()->getNearbyEntities($box) as $entity) {
            if ($entity instanceof CrownNpc && ($all || $entity->getWorld() === $player->getWorld())) {
                $entity->flagForDespawn();
                $n++;
            }
        }
        return $n;
    }

    /** جدول کامل یک دسته را در چت بازیکن نشان می‌دهد. */
    public function showTable(Player $player, string $stat, int $limit = 10): void
    {
        $icon = BedWarsSource::ICONS[$stat] ?? '👑';
        $label = BedWarsSource::LABELS[$stat] ?? strtoupper($stat);
        $player->sendMessage('§7———————— §l§6👑 ' . $icon . ' ' . $label . ' §r§7————————');
        BedWarsSource::leaderboard($stat, $limit, function (array $rows) use ($player, $stat): void {
            if (!$player->isConnected()) {
                return;
            }
            if ($rows === []) {
                $player->sendMessage('§7هنوز آماری ثبت نشده.');
                return;
            }
            $i = 0;
            foreach ($rows as $row) {
                $i++;
                $color = match (true) {
                    $i === 1 => '§e',
                    $i === 2 => '§7',
                    $i === 3 => '§6',
                    default => '§8',
                };
                $player->sendMessage($color . ($i . '.') . ' §f' . CrownNpc::pretty((string) $row['name'])
                    . ' §8» §a' . number_format((int) $row['value']));
            }
            $player->sendMessage('§8منبع: ' . (BedWarsSource::hasBedWars() ? 'BedWarsCore (bw_players)' : 'stats.yml محلی')
                . ' §7| دسته: §f' . $stat);
        });
    }
}
