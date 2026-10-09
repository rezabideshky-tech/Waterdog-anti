<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\battlepass\ui;

use function sprintf;

/**
 * Slot map shared with the "arvan ui" resource pack (ui/bwpass/battlepass.json - GENERATED together with it).
 *
 * The Battle Pass is one full-screen purple/green form. Every button of the form is one slot of the screen,
 * in the exact order below; its text is marker(index) + [state tokens] + value, and the resource pack finds
 * the slot by that marker. Empty slots are sent as "" (the pack hides them).
 */
final class BattlePassLayout{

    public const PER_PAGE = 5;

    public const TIME = 0;      // season countdown
    public const SEASON = 1;    // season name
    public const PAGE = 2;      // page x/y
    public const LVL = 3;       // current tier number (circle at the end of the xp bar)
    public const XPBAR = 4;     // image  textures/bwpass/bars/xp_NN
    public const XPTEXT = 5;    // xp text drawn on the bar
    public const TRACK = 6;     // image  textures/bwpass/bars/tr_NN (gold line between the tier nodes)
    public const NODE = 7;      // 7..11 tier nodes
    public const FREE = 12;     // 12..16 free reward cards   (clickable)
    public const PREM = 17;     // 17..21 premium reward cards (clickable)
    public const CLAIM = 22;    // "Claim All" (clickable)
    public const ACTIVATE = 23; // "Activate" / "Active" (clickable)
    public const PREV = 24;     // previous page arrow (clickable)
    public const NEXT = 25;     // next page arrow (clickable)
    public const PSTATUS = 26;  // status text under the Golden Pass banner

    public const COUNT = 27;

    /** state tokens (removed by the pack before the text is drawn) */
    public const T_NORMAL = "\u{2039}N\u{203A}";
    public const T_READY = "\u{2039}G\u{203A}";   // gold card, claimable
    public const T_DONE = "\u{2039}D\u{203A}";    // claimed (dimmed + check mark)
    public const T_EMPTY = "\u{2039}E\u{203A}";   // no reward on this track
    public const T_LOCK = "\u{2039}K\u{203A}";    // padlock (premium not owned)
    public const N_OFF = "\u{2039}N\u{203A}";
    public const N_REACHED = "\u{2039}R\u{203A}";
    public const N_CURRENT = "\u{2039}C\u{203A}";
    public const B_ACTIVE = "\u{2039}A\u{203A}";  // green button
    public const B_OFF = "\u{2039}O\u{203A}";     // greyed button
    public const B_PURPLE = "\u{2039}P\u{203A}";  // purple button

    public static function marker(int $index) : string{
        return sprintf("\u{00AB}%02d\u{00BB}", $index);
    }

    private function __construct(){}
}
