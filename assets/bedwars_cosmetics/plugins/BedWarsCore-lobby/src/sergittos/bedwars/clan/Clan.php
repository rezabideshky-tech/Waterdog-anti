<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan;

use pocketmine\utils\TextFormat as TF;

/**
 * In-memory representation of a clan, kept in sync with the `bw_clans` /
 * `bw_clan_members` tables by ClanManager. This object is the fast path for
 * everything read-heavy (GUIs, chat prefixes, leaderboard); every mutation
 * that changes persisted state also fires the matching async DB write
 * through ClanManager, never through this class directly, so Clan itself
 * has no database dependency.
 */
class Clan{

    private string $id;
    private string $name;
    private string $tag;
    private string $color;
    private string $description;
    private string $crestId;
    private string $ownerUsername;
    private string $visibility;
    private int    $requiredLevel;
    private int    $xp;
    private int    $weeklyXp;
    private int    $weeklyWins;
    private int    $weeklyFinalKills;
    private int    $level;
    private int    $bankCoins;
    private int    $lastWeekScore;
    private int    $lastWeekRank;
    private int    $bestWeekRank;
    private int    $createdAt;

    /** @var array<string,string> lowercase username => role */
    private array $members = [];

    public function __construct(array $row, array $members = []){
        $this->id               = (string) $row["id"];
        $this->name              = (string) $row["name"];
        $this->tag               = (string) $row["tag"];
        $this->color             = (string) ($row["color"] ?? "WHITE");
        $this->description       = (string) ($row["description"] ?? "");
        $this->crestId           = (string) ($row["crest_id"] ?? ClanCrestRegistry::getDefault());
        $this->ownerUsername     = strtolower((string) $row["owner_username"]);
        $this->visibility        = (string) ($row["visibility"] ?? "PUBLIC");
        $this->requiredLevel     = (int) ($row["required_level"] ?? 1);
        $this->xp                = (int) ($row["xp"] ?? 0);
        $this->weeklyXp          = (int) ($row["weekly_xp"] ?? 0);
        $this->weeklyWins        = (int) ($row["weekly_wins"] ?? 0);
        $this->weeklyFinalKills  = (int) ($row["weekly_final_kills"] ?? 0);
        $this->level             = (int) ($row["level"] ?? 1);
        $this->bankCoins         = (int) ($row["bank_coins"] ?? 0);
        $this->lastWeekScore     = (int) ($row["last_week_score"] ?? 0);
        $this->lastWeekRank      = (int) ($row["last_week_rank"] ?? 0);
        $this->bestWeekRank      = (int) ($row["best_week_rank"] ?? 0);
        $this->createdAt         = (int) ($row["created_at"] ?? time());
        $this->members           = $members;
    }

    public function getId(): string{ return $this->id; }
    public function getName(): string{ return $this->name; }
    public function getTag(): string{ return $this->tag; }
    public function getColor(): string{ return $this->color; }
    public function getDescription(): string{ return $this->description; }
    public function getCrestId(): string{ return $this->crestId; }
    public function getOwnerUsername(): string{ return $this->ownerUsername; }
    public function getVisibility(): string{ return $this->visibility; }
    public function isPublic(): bool{ return $this->visibility === "PUBLIC"; }
    public function getRequiredLevel(): int{ return $this->requiredLevel; }
    public function getXp(): int{ return $this->xp; }
    public function getWeeklyXp(): int{ return $this->weeklyXp; }
    public function getWeeklyWins(): int{ return $this->weeklyWins; }
    public function getWeeklyFinalKills(): int{ return $this->weeklyFinalKills; }
    public function getLevel(): int{ return $this->level; }
    public function getBankCoins(): int{ return $this->bankCoins; }
    public function getLastWeekScore(): int{ return $this->lastWeekScore; }
    public function getLastWeekRank(): int{ return $this->lastWeekRank; }
    public function getBestWeekRank(): int{ return $this->bestWeekRank; }
    public function getCreatedAt(): int{ return $this->createdAt; }

    /** @return array<string,string> lowercase username => role */
    public function getMembers(): array{ return $this->members; }
    public function getMemberCount(): int{ return count($this->members); }

    public function getColorCode(): string{
        return ClanColors::code($this->color);
    }

    public function getColoredTag(): string{
        return $this->getColorCode() . "[" . $this->tag . "]" . TF::RESET;
    }

    public function getColoredName(): string{
        return $this->getColorCode() . $this->name . TF::RESET;
    }

    public function hasMember(string $username): bool{
        return isset($this->members[strtolower($username)]);
    }

    public function getRole(string $username): ?string{
        return $this->members[strtolower($username)] ?? null;
    }

    public function isOwner(string $username): bool{
        return $this->getRole($username) === ClanRole::OWNER;
    }

    // ---- local/optimistic mutators; ClanManager is responsible for
    // persisting the same change and is the only caller that should use
    // these outside of ClanManager's own cache-rebuild path. ----

    public function setMemberLocal(string $username, string $role): void{
        $this->members[strtolower($username)] = $role;
    }

    public function removeMemberLocal(string $username): void{
        unset($this->members[strtolower($username)]);
    }

    public function setMetaLocal(string $name, string $tag, string $color, string $description, string $crestId, string $visibility, int $requiredLevel): void{
        $this->name          = $name;
        $this->tag            = $tag;
        $this->color          = $color;
        $this->description    = $description;
        $this->crestId        = $crestId;
        $this->visibility     = $visibility;
        $this->requiredLevel  = $requiredLevel;
    }

    public function setOwnerLocal(string $username): void{
        $this->ownerUsername = strtolower($username);
    }

    public function addXpLocal(int $amount): void{
        $this->xp       += $amount;
        $this->weeklyXp += $amount;
    }

    public function addWeeklyCombatLocal(int $winsDelta, int $finalKillsDelta): void{
        $this->weeklyWins       = max(0, $this->weeklyWins + $winsDelta);
        $this->weeklyFinalKills = max(0, $this->weeklyFinalKills + $finalKillsDelta);
    }

    public function setLevelLocal(int $level): void{ $this->level = $level; }
    public function addBankLocal(int $amount): void{ $this->bankCoins += $amount; }

    public function setLastWeekLocal(int $score, int $rank, int $bestRank): void{
        $this->lastWeekScore = $score;
        $this->lastWeekRank  = $rank;
        $this->bestWeekRank  = $bestRank;
    }

    public function resetWeeklyLocal(): void{
        $this->weeklyXp         = 0;
        $this->weeklyWins       = 0;
        $this->weeklyFinalKills = 0;
    }

    public function computeWeeklyScore(ClanConfig $config): int{
        return ($this->weeklyWins * $config->getWeeklyWinWeight())
            + ($this->weeklyFinalKills * $config->getWeeklyFinalKillWeight())
            - ($this->level * $config->getWeeklyLevelWeight());
    }

    public function getCapacity(ClanConfig $config): int{
        return $config->getCapacityForLevel($this->level);
    }

    public function isFull(ClanConfig $config): bool{
        return $this->getMemberCount() >= $this->getCapacity($config);
    }
}
