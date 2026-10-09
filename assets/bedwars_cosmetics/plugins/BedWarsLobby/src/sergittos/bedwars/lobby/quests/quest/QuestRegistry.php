<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\quests\quest;

use DateTimeImmutable;
use DateTimeZone;
use function array_slice;
use function crc32;
use function mt_srand;
use function shuffle;

final class QuestRegistry{

    private const SLOTS = 4;

    private static ?QuestRegistry $instance = null;

    /** @var QuestDefinition[] Pool every daily quest can be picked from. */
    private array $pool = [];

    /** @var array<string, QuestDefinition> Today's rotated slot_1..slot_N quests. */
    private array $quests = [];

    private string $rotatedStamp = "";

    public static function getInstance(): QuestRegistry{
        return self::$instance ??= new QuestRegistry();
    }

    /**
     * Builds the pool of possible daily quests. Every real day (Asia/Tehran,
     * matching PlayerQuestDataManager::applyResets()) a fixed number of
     * slots (SLOTS) is deterministically re-rolled from this pool via
     * rotateForToday() / ensureRotated(), so players actually see different
     * missions from one day to the next instead of the same 4 forever -
     * only the goal/reward text changes, the slot ids ("daily.slot_1", ...)
     * stay stable so progress tracking and resets keep working unchanged.
     */
    public function initDefaults(): void{
        // توجه: پاداش کوینِ هر ماموریت (پارامتر ششم) دست‌نخورده مونده -
        // فقط پاداش XP (پارامتر هشتم) طبق درخواست به زیر ۱۰۰ کاهش پیدا
        // کرده، با حفظ ترتیب نسبیِ سختی/ارزش ماموریت‌ها نسبت به هم.
        $this->pool = [
            new QuestDefinition("pool.win_squad", QuestType::DAILY, QuestStat::WINS, "Win 1 Game in Squad", 1, 65, "textures/items/golden_sword", 84, "In Squad"),
            new QuestDefinition("pool.win_any", QuestType::DAILY, QuestStat::WINS, "Win 1 Game", 1, 55, "textures/items/nether_star", 76, "In Any Mode"),
            new QuestDefinition("pool.win_duos", QuestType::DAILY, QuestStat::WINS, "Win 1 Game", 1, 60, "textures/items/emerald", 80, "In Any Mode"),
            new QuestDefinition("pool.final_kills_duos", QuestType::DAILY, QuestStat::FINAL_KILLS, "Get 4 Final Kills", 4, 50, "textures/items/diamond_sword", 64, "In Any Mode"),
            new QuestDefinition("pool.final_kills_squad", QuestType::DAILY, QuestStat::FINAL_KILLS, "Get 6 Final Kills in Squad", 6, 60, "textures/items/diamond_sword", 72, "In Squad"),
            new QuestDefinition("pool.kills_solo", QuestType::DAILY, QuestStat::KILLS, "Get 5 Kills", 5, 30, "textures/items/iron_sword", 36, "In Any Mode"),
            new QuestDefinition("pool.kills_any", QuestType::DAILY, QuestStat::KILLS, "Get 10 Kills", 10, 45, "textures/items/stone_sword", 52, "In Any Mode"),
            new QuestDefinition("pool.beds_any", QuestType::DAILY, QuestStat::BEDS_BROKEN, "Break 2 Beds", 2, 50, "textures/blocks/bed_red", 60, "In Any Mode"),
            new QuestDefinition("pool.beds_squad", QuestType::DAILY, QuestStat::BEDS_BROKEN, "Break 3 Beds in Squad", 3, 65, "textures/blocks/bed_red", 70, "In Squad"),
            new QuestDefinition("pool.kills_duos", QuestType::DAILY, QuestStat::KILLS, "Get 8 Kills", 8, 40, "textures/items/iron_sword", 48, "In Any Mode"),
        ];

        $this->rotatedStamp = "";
        $this->ensureRotated();
    }

    /** Re-rolls today's slots if the (Asia/Tehran) day has changed since the last rotation. */
    private function ensureRotated(): void{
        $stamp = (new DateTimeImmutable("now", new DateTimeZone("Asia/Tehran")))->format("Y-m-d");
        if($stamp === $this->rotatedStamp && !empty($this->quests)){
            return;
        }

        $this->rotatedStamp = $stamp;
        $this->quests = [];

        if(empty($this->pool)){
            return;
        }

        $picks = $this->pool;
        // Seed deterministically from the date so every player (and every
        // server process) picks the same daily set, but the set itself
        // still changes every day.
        mt_srand((int) crc32($stamp));
        shuffle($picks);
        mt_srand();

        // Without the "in Solo"/"in Duos" suffixes two different pool entries
        // can share the exact same name (e.g. "Win 1 Game"), so keep only
        // the first of each name - players never see two identical missions.
        $unique = [];
        $seenNames = [];
        foreach($picks as $template){
            $name = $template->getName();
            if(isset($seenNames[$name])){
                continue;
            }
            $seenNames[$name] = true;
            $unique[] = $template;
        }

        $picks = array_slice($unique, 0, self::SLOTS);

        foreach($picks as $i => $template){
            $slotId = "daily.slot_" . ($i + 1);
            $this->quests[$slotId] = new QuestDefinition(
                $slotId,
                $template->getType(),
                $template->getStat(),
                $template->getName(),
                $template->getGoal(),
                $template->getRewardCoins(),
                $template->getIconPath(),
                $template->getRewardExp(),
                $template->getModeLabel()
            );
        }
    }

    /** @return QuestDefinition[] */
    public function allByType(QuestType $type): array{
        $this->ensureRotated();

        $out = [];
        foreach($this->quests as $q){
            if($q->getType() === $type){
                $out[] = $q;
            }
        }
        return $out;
    }

    public function get(string $id): ?QuestDefinition{
        $this->ensureRotated();
        return $this->quests[$id] ?? null;
    }
}
