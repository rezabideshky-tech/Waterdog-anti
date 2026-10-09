<?php

declare(strict_types=1);

namespace sergittos\bedwars\rejoin;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat as TF;
use function strtolower;

/**
 * The physical "Rejoin Ticket" item a player finds sitting in their hotbar
 * when they land on a lobby server while a rejoin record for them is still
 * valid (see RejoinTicketManager / CoreListener).
 *
 * This replaces the old blocking RejoinMatchForm popup: instead of a modal
 * shoved in front of the player the instant they load in, they get a Clock
 * they can act on - or ignore - on their own schedule. Right-click sends
 * them back; ignoring it just lets it expire on its own once the grace
 * window runs out.
 */
final class RejoinTicketItem{

    /** NBT byte flag marking an item as a rejoin ticket. Checked by CoreListener's interact/drop/transaction guards. */
    private const NBT_TICKET = "bw_rejoin_ticket";

    /** NBT string caching the target server this ticket sends the player back to, read back on right-click. */
    private const NBT_TARGET_SERVER = "bw_rejoin_target";

    /**
     * Fixed hotbar slot (0-indexed) the ticket always occupies - slot 9
     * counting the hotbar as 1-9. Deliberately the very last slot and one
     * LobbyItems (BedWarsLobby) never touches, so the ticket always sits in
     * the same place a returning player already expects to check, and never
     * collides with the server selector / quests / cosmetics / party /
     * friends / clan items that live in the other slots.
     */
    public const HOTBAR_SLOT = 8;

    public static function build(string $targetServer, string $mode, string $team, int $secondsLeft): Item{
        $item = VanillaItems::CLOCK();
        $item->setCustomName(TF::YELLOW . TF::BOLD . "Rejoin Match");
        $item->setLore([
            TF::GRAY . "Team: " . self::teamColor($team) . TF::BOLD . self::displayTeam($team),
            TF::GRAY . "Mode: " . TF::AQUA . TF::BOLD . $mode,
            TF::GRAY . "Time Left: " . self::timeColor($secondsLeft) . TF::BOLD . $secondsLeft . "s",
            "",
            TF::GREEN . TF::BOLD . "> Right-click to rejoin your match",
            TF::DARK_GRAY . "Ignoring this will forfeit your spot",
        ]);

        $nbt = $item->getNamedTag();
        $nbt->setByte(self::NBT_TICKET, 1);
        $nbt->setString(self::NBT_TARGET_SERVER, $targetServer);
        $item->setNamedTag($nbt);

        return $item;
    }

    public static function isTicket(Item $item): bool{
        return $item->getNamedTag()->getByte(self::NBT_TICKET, 0) === 1;
    }

    public static function getTargetServer(Item $item): string{
        return $item->getNamedTag()->getString(self::NBT_TARGET_SERVER, "");
    }

    private static function displayTeam(string $team): string{
        return $team !== "" ? $team : "Unknown";
    }

    private static function teamColor(string $team): string{
        return match(strtolower($team)){
            "red" => TF::RED,
            "blue" => TF::BLUE,
            "green" => TF::DARK_GREEN,
            "yellow" => TF::YELLOW,
            "aqua", "cyan" => TF::AQUA,
            "white" => TF::WHITE,
            "pink" => TF::LIGHT_PURPLE,
            "gray", "grey", "silver" => TF::GRAY,
            default => TF::WHITE,
        };
    }

    // Green while there's still plenty of time, gold once it's getting
    // tight, red for the final stretch - gives the player a glance-able
    // sense of urgency without having to read the number.
    private static function timeColor(int $secondsLeft): string{
        return match(true){
            $secondsLeft <= 15 => TF::RED,
            $secondsLeft <= 45 => TF::GOLD,
            default => TF::GREEN,
        };
    }
}
