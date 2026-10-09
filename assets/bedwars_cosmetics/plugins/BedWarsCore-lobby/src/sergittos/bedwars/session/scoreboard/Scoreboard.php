<?php

declare(strict_types=1);

namespace sergittos\bedwars\session\scoreboard;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use sergittos\bedwars\session\Session;
use sergittos\bedwars\utils\ColorUtils;

abstract class Scoreboard {

    private const TITLE = "BedWarsScore";

    /**
     * Objective name recognized by the bundled "TitleAndScore" resource
     * pack's scoreboard UI (ui/scoreboards.json). That pack binds the
     * sidebar's title label's visibility to
     * "not(#objective_sidebar_name = 'BedWarsScore' or ... )" and shows a
     * matching PNG logo control instead whenever the objective name equals
     * one of a fixed set of names (LobbyScore, BedWarsScore, RpScore,
     * AmongUsScore, FootballScore) - exactly the same mechanism ScoreHud
     * relies on for its own titles. Sending this name instead of a plain
     * display string is what "pulls the title from the resource pack": on
     * a client with the pack installed, the plain-text title is hidden and
     * the BedWars logo image is drawn in its place; on a client without
     * the pack, the text in self::TITLE below still renders normally as a
     * fallback since the pack's visibility binding never applies.
     *
     * Previously this was $session->getUsername() - that only needed to
     * be *some* per-session-unique value so show()/hide() paired up
     * correctly (the packet is sent directly to that one connection, never
     * broadcast, so nothing about it needs to be globally unique across
     * players). Swapping it for a fixed, resource-pack-recognized name
     * keeps that same show()/hide() pairing (every subclass still hides
     * and re-shows under the same name) while additionally triggering the
     * pack's logo swap. getObjectiveName() lets an individual scoreboard
     * (see LobbyScoreboard) pull a different logo than the BedWars one.
     */
    private const RESOURCE_OBJECTIVE_NAME = "BedWarsScore";

    public function show(Session $session): void {
        if (!$session->isOnline()) return;

        $this->hide($session);

        $objectiveName = $this->getObjectiveName();

        $packet = new SetDisplayObjectivePacket();
        $packet->displaySlot = SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR;
        $packet->objectiveName = $objectiveName;
        $packet->displayName = ColorUtils::translate($this->getTitle());
        $packet->criteriaName = "dummy";
        $packet->sortOrder = SetDisplayObjectivePacket::SORT_ORDER_DESCENDING;
        $session->sendDataPacket($packet);

        // getLines() is the single source of truth for every row on the
        // board, including its own final "server IP" line at whichever
        // score index that particular scoreboard uses (see
        // LobbyScoreboard/WaitingScoreboard/GameScoreboard). This method
        // used to *also* unconditionally tack on a second, hardcoded IP
        // line ("ArvanGaming.IR", ignoring the configured "ip" value
        // entirely) plus a blank spacer, at fixed scores 1 and 2 - which
        // is a duplicate "IP method" any time a subclass's own getLines()
        // already used score 1 and/or 2 for something real (its own ip
        // line, a stat line, etc.): both the real line and this hardcoded
        // one would render, either overlapping at the same row or padding
        // an extra "ArvanGaming.IR" underneath the subclass's own,
        // correctly-configured IP line. Removed - subclasses already own
        // their entire board, IP line included.
        //
        // All lines are batched into a single SetScorePacket (SetScorePacket
        // already supports multiple entries per packet) instead of sending
        // one packet per line. This method runs every tick, per player,
        // while PlayingStage/StartingStage are active, so cutting a 9-16
        // line board from 9-16 packets down to 1 meaningfully reduces
        // packet/network overhead without changing anything the player
        // sees.
        $entries = [];
        foreach ($this->getLines($session) as $score => $line) {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = $objectiveName;
            $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
            $entry->customName = ColorUtils::translate(" " . $line);
            $entry->score = $score;
            $entry->scoreboardId = $score;
            $entries[] = $entry;
        }

        if ($entries !== []) {
            $packet = new SetScorePacket();
            $packet->type = SetScorePacket::TYPE_CHANGE;
            $packet->entries = $entries;
            $session->sendDataPacket($packet);
        }
    }

    public function hide(Session $session): void {
        if (!$session->isOnline()) return;
        $packet = new RemoveObjectivePacket();
        $packet->objectiveName = $this->getObjectiveName();
        $session->sendDataPacket($packet);
    }

    /**
     * See RESOURCE_OBJECTIVE_NAME's doc comment above. Override this in a
     * specific scoreboard to pull a different resource-pack logo (e.g.
     * LobbyScoreboard overrides it to "LobbyScore").
     */
    protected function getObjectiveName(): string {
        return self::RESOURCE_OBJECTIVE_NAME;
    }

    /**
     * Plain-text fallback title, only ever actually seen by a client
     * without the resource pack installed (see RESOURCE_OBJECTIVE_NAME).
     */
    protected function getTitle(): string {
        return self::TITLE;
    }

    /**
     * @return array<int, string>  key = score (higher = higher on board)
     */
    abstract protected function getLines(Session $session): array;
}