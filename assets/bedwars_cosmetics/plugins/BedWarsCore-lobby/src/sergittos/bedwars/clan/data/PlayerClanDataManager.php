<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan\data;

use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\session\Session;

/**
 * Thin wrapper around AsyncMysqlProvider::getClanPlayerState()/mutateClanPlayerState()
 * for the per-player clan data that isn't shared clan state: notification
 * toggles, join/leave history, and the anti-abuse join cooldown.
 */
final class PlayerClanDataManager{

    public const NOTIFY_MEMBER_JOIN     = "member_join";
    public const NOTIFY_MEMBER_LEAVE    = "member_leave";
    public const NOTIFY_LEVEL_UP        = "level_up";
    public const NOTIFY_CLAN_LEVEL_UP   = "clan_level_up";
    public const NOTIFY_QUEST_COMPLETE  = "quest_complete";
    public const NOTIFY_WEEKLY_RESULT   = "weekly_result";

    public const ALL_NOTIFICATIONS = [
        self::NOTIFY_MEMBER_JOIN,
        self::NOTIFY_MEMBER_LEAVE,
        self::NOTIFY_LEVEL_UP,
        self::NOTIFY_CLAN_LEVEL_UP,
        self::NOTIFY_QUEST_COMPLETE,
        self::NOTIFY_WEEKLY_RESULT,
    ];

    public static function notificationLabel(string $key): string{
        return match($key){
            self::NOTIFY_MEMBER_JOIN    => "Member Join",
            self::NOTIFY_MEMBER_LEAVE   => "Member Leave / Kick",
            self::NOTIFY_LEVEL_UP       => "Personal Level Up",
            self::NOTIFY_CLAN_LEVEL_UP  => "Clan Level Up",
            self::NOTIFY_QUEST_COMPLETE => "Quest Completed",
            self::NOTIFY_WEEKLY_RESULT  => "Weekly Leaderboard",
            default                     => $key,
        };
    }

    public function __construct(private BedWarsCore $plugin){}

    public function getState(string $username, callable $callback): void{
        $this->plugin->getProvider()->getClanPlayerState($username, $callback);
    }

    public function isNotificationEnabled(string $username, string $key, callable $callback): void{
        $this->getState($username, function(array $state) use ($key, $callback): void{
            $callback((bool) ($state["notifications"][$key] ?? true));
        });
    }

    public function setNotificationEnabled(string $username, string $key, bool $enabled): void{
        $this->plugin->getProvider()->mutateClanPlayerState($username, function(array $row) use ($key, $enabled): array{
            $row["notifications"][$key] = $enabled;
            return $row;
        });
    }

    public function getCooldownUntil(string $username, callable $callback): void{
        $this->getState($username, function(array $state) use ($callback): void{
            $callback((int) ($state["cooldown_until"] ?? 0));
        });
    }

    public function setCooldownUntil(string $username, int $timestamp): void{
        $this->plugin->getProvider()->mutateClanPlayerState($username, function(array $row) use ($timestamp): array{
            $row["cooldown_until"] = $timestamp;
            return $row;
        });
    }

    /** @param array{name:string,tag:string,joined_at:int,left_at:int,method:string} $entry */
    public function pushHistory(string $username, array $entry): void{
        $this->plugin->getProvider()->mutateClanPlayerState($username, function(array $row) use ($entry): array{
            $history = is_array($row["history"] ?? null) ? $row["history"] : [];
            $history[] = $entry;
            // Keep the last 25 entries only, this is a display list, not an audit log.
            if(count($history) > 25){
                $history = array_slice($history, -25);
            }
            $row["history"] = $history;
            return $row;
        });
    }

    public function getHistory(string $username, callable $callback): void{
        $this->getState($username, function(array $state) use ($callback): void{
            $history = is_array($state["history"] ?? null) ? $state["history"] : [];
            $callback(array_reverse($history));
        });
    }
}
