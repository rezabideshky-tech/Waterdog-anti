<?php

declare(strict_types=1);

namespace sergittos\bedwars\report;

/**
 * Static registry of every reason a player can pick when filing a report.
 * Ordered the way they should appear in the in-game dropdown - most
 * commonly-needed / most severe categories first.
 */
final class ReportReasons{

    /** @var ReportReason[]|null */
    private static ?array $all = null;

    /** @return ReportReason[] */
    public static function getAll(): array{
        if(self::$all === null){
            self::$all = [
                new ReportReason("cheating", "{RED}Hacking / Cheating"),
                new ReportReason("teaming", "{GOLD}Illegal Teaming"),
                new ReportReason("griefing", "{YELLOW}Griefing / Bed Sabotage"),
                new ReportReason("abuse", "{LIGHT_PURPLE}Chat Abuse / Harassment"),
                new ReportReason("spamming", "{AQUA}Spamming"),
                new ReportReason("advertising", "{BLUE}Advertising"),
                new ReportReason("inappropriate_name", "{DARK_PURPLE}Inappropriate Name / Skin"),
                new ReportReason("bug_abuse", "{DARK_AQUA}Bug Abuse / Exploiting"),
                new ReportReason("other", "{GRAY}Other"),
            ];
        }

        return self::$all;
    }

    public static function get(string $id): ?ReportReason{
        foreach(self::getAll() as $reason){
            if($reason->getId() === $id){
                return $reason;
            }
        }

        return null;
    }

    /** @return string[] indexed the same order as getAll(), for building a Dropdown */
    public static function getDisplayLabels(): array{
        return array_map(fn(ReportReason $reason) => $reason->getDisplayLabel(), self::getAll());
    }

}
