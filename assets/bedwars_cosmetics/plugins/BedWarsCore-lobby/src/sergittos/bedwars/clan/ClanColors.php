<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan;

use pocketmine\utils\TextFormat as TF;

/**
 * PocketMine's TextFormat class has no getByName() (that was a mistake in
 * an earlier version of this file - it crashed with "Call to undefined
 * method TextFormat::getByName()" the moment any clan menu tried to render
 * a color). Clans only ever store a color as its constant *name* (e.g.
 * "GOLD"), never the raw §-code, so this is the one place that name gets
 * turned into an actual color code - both Clan::getColorCode() and
 * ClanMenu use this instead of the non-existent API.
 */
final class ClanColors{

    /** @var array<string,string> */
    private const MAP = [
        "WHITE"        => TF::WHITE,
        "AQUA"         => TF::AQUA,
        "GREEN"        => TF::GREEN,
        "YELLOW"       => TF::YELLOW,
        "GOLD"         => TF::GOLD,
        "RED"          => TF::RED,
        "LIGHT_PURPLE" => TF::LIGHT_PURPLE,
        "BLUE"         => TF::BLUE,
        "DARK_GREEN"   => TF::DARK_GREEN,
        "DARK_AQUA"    => TF::DARK_AQUA,
        "DARK_BLUE"    => TF::DARK_BLUE,
        "DARK_RED"     => TF::DARK_RED,
        "DARK_PURPLE"  => TF::DARK_PURPLE,
        "DARK_GRAY"    => TF::DARK_GRAY,
        "GRAY"         => TF::GRAY,
        "BLACK"        => TF::BLACK,
    ];

    /** Names selectable in the clan-creation/settings color dropdown, in display order. */
    public const SELECTABLE = ["WHITE", "AQUA", "GREEN", "YELLOW", "GOLD", "RED", "LIGHT_PURPLE", "BLUE", "DARK_GREEN", "DARK_AQUA"];

    public static function code(string $name): string{
        return self::MAP[strtoupper($name)] ?? TF::WHITE;
    }

    public static function isValid(string $name): bool{
        return isset(self::MAP[strtoupper($name)]);
    }
}
