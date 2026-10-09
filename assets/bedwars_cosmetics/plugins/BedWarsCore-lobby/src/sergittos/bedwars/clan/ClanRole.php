<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan;

use pocketmine\utils\TextFormat as TF;

/**
 * PocketMine's PHP baseline for this codebase doesn't use native enums
 * elsewhere (see ClanRole usages mirroring plain string constants like the
 * rest of the plugin), so this stays a simple constant holder instead of an
 * `enum` to match the existing style and avoid a PHP version bump.
 */
final class ClanRole{

    public const OWNER   = "OWNER";
    public const OFFICER = "OFFICER";
    public const MEMBER  = "MEMBER";

    public static function isValid(string $role): bool{
        return in_array($role, [self::OWNER, self::OFFICER, self::MEMBER], true);
    }

    public static function displayName(string $role): string{
        return match($role){
            self::OWNER   => "Owner",
            self::OFFICER => "Officer",
            self::MEMBER  => "Member",
            default       => "Member",
        };
    }

    public static function canManageMembers(string $role): bool{
        return $role === self::OWNER || $role === self::OFFICER;
    }

    public static function canManageBank(string $role): bool{
        return $role === self::OWNER || $role === self::OFFICER;
    }

    public static function canManageSettings(string $role): bool{
        return $role === self::OWNER;
    }

    public static function canDisband(string $role): bool{
        return $role === self::OWNER;
    }

    public static function canReviewApplications(string $role): bool{
        return $role === self::OWNER || $role === self::OFFICER;
    }

    /** Shared rank color, used everywhere a rank is shown next to a name (member list, clan chat). */
    public static function color(string $role): string{
        return match($role){
            self::OWNER   => TF::GOLD,
            self::OFFICER => TF::YELLOW,
            default       => TF::WHITE,
        };
    }
}
