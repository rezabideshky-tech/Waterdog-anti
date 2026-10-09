<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\challenges\model;

final class RequirementType{
    public const COINS_EARNED = "coins_earned";
    public const KILLS = "kills";
    public const FINAL_KILLS = "final_kills";
    public const BEDS_BROKEN = "beds_broken";
    public const WINS = "wins";

    public static function isValid(string $value): bool{
        return in_array($value, [
            self::COINS_EARNED,
            self::KILLS,
            self::FINAL_KILLS,
            self::BEDS_BROKEN,
            self::WINS
        ], true);
    }
}