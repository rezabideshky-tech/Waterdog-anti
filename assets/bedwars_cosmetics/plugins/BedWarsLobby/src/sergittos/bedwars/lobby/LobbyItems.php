<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat as TF;
use sergittos\bedwars\BedWarsCore;
use sergittos\bedwars\rejoin\RejoinTicketManager;
use sergittos\bedwars\session\Session;
use function count;
use function number_format;

class LobbyItems{

    public const ITEM_SERVER_SELECTOR = "server_selector";
    public const ITEM_QUESTS          = "quests";
    public const ITEM_COSMETICS       = "cosmetics";
    public const ITEM_PARTY           = "party";
    public const ITEM_FRIENDS         = "friends";
    public const ITEM_CLAN            = "clan";
    public const ITEM_LEADERBOARD     = "leaderboard";
    public const ITEM_PROFILE         = "profile";

    public static function give(Session $session): void{
        $player = $session->getPlayer();
        if(!$player->isOnline()){
            return;
        }

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->setGamemode(GameMode::ADVENTURE());
        $player->getHungerManager()->setFood(20);
        $player->setHealth($player->getMaxHealth());
        $session->syncXpBar();

        // Hotbar layout, grouped by purpose with a one-slot gap between each
        // group so every item sits clearly on its own instead of the whole
        // bar reading as one solid row:
        //   slot 0       -> entry point (play)
        //   slots 2 - 4  -> personal progression (quests, cosmetics, profile & ranked)
        //   slots 6 - 7  -> social (party, friends)
        // Profile & Ranked sits at slot 4, the middle slot of the 0-8 hotbar.
        // The Clan item is temporarily removed from this hotbar (its
        // command is disabled too, see BedWarsLobby::onEnable) - the whole
        // clan feature (ClanMenu, makeClan(), etc.) is left untouched below
        // so it can be turned back on later just by re-adding the setItem()
        // call and the command registration.
        // Slot 8 is intentionally never used here and must stay that way -
        // it's reserved for the Rejoin Ticket (BedWarsCore's
        // RejoinTicketItem::HOTBAR_SLOT / RejoinTicketManager), which is
        // re-applied below. Placing any lobby item there would silently
        // overwrite an active ticket the next time give() runs.
        $inv = $player->getInventory();
        $inv->setItem(0, self::makeServerSelector());
        $inv->setItem(1, self::makeQuests());
        $inv->setItem(2, self::makeCosmetics());
        $inv->setItem(4, self::makeProfile());
        $inv->setItem(6, self::makeParty($session));
        $inv->setItem(7, self::makeFriends($session));

        // clearAll() above would silently wipe a Rejoin Ticket (BedWarsCore's
        // RejoinTicketItem/RejoinTicketManager) that was sitting in slot 8 if
        // this player has one actively counting down - e.g. dying and
        // respawning in the lobby while it's showing. Re-placing it here
        // (a no-op if there's no active ticket) is what keeps the two
        // features fully in sync instead of racing each other.
        RejoinTicketManager::reapplyIfActive($player);
    }

    public static function refreshClanItem(Session $session): void{
        $player = $session->getPlayer();
        if(!$player->isOnline()){
            return;
        }
        $player->getInventory()->setItem(7, self::makeClan($session));
    }

    private static function makeServerSelector(): Item{
        $item = VanillaItems::COMPASS();
        $item->setCustomName(TF::GREEN . TF::BOLD . "§bGameSelector");
        $item->setLore([
            TF::GRAY . "Pick a mode and jump into a match.",
            "",
            TF::GREEN . "Solo §8| " . TF::AQUA . "Doubles §8| " . TF::GOLD . "Squads",
            "",
            TF::YELLOW . "Right-click to open",
        ]);
        self::tag($item, self::ITEM_SERVER_SELECTOR);
        return $item;
    }

    private static function makeQuests(): Item{
        $item = VanillaItems::BOOK();
        $item->setCustomName(TF::YELLOW . TF::BOLD . "§eQuests");
        $item->setLore([
            TF::GRAY . "Complete daily & weekly quests",
            TF::GRAY . "for coins and rewards.",
            "",
            TF::YELLOW . "Right-click to open",
        ]);
        self::tag($item, self::ITEM_QUESTS);
        return $item;
    }

    private static function makeProfile(): Item{
        $item = VanillaItems::NETHER_STAR();
        $item->setCustomName(TF::LIGHT_PURPLE . TF::BOLD . "§dProfile §7& §dRanked");
        $item->setLore([
            TF::GRAY . "Your stats, ranked season, medals",
            TF::GRAY . "and achievements in one place.",
            "",
            TF::LIGHT_PURPLE . "Climb from Bronze to Grandmaster!",
            "",
            TF::YELLOW . "Right-click to open",
        ]);
        self::tag($item, self::ITEM_PROFILE);
        return $item;
    }

    private static function makeCosmetics(): Item{
        $item = VanillaItems::EMERALD();
        $item->setCustomName(TF::LIGHT_PURPLE . TF::BOLD . "§aCosmetics");
        $item->setLore([
            TF::GRAY . "Customize your style and effects.",
            "",
            TF::YELLOW . "Right-click to open",
        ]);
        self::tag($item, self::ITEM_COSMETICS);
        return $item;
    }

    private static function makeParty(Session $session): Item{
        $item = VanillaItems::HEART_OF_THE_SEA();
        $pm = BedWarsCore::getInstance()->getPartyManager();
        $party = $pm->getPartyOf($session);

        if($party !== null){
            $item->setCustomName(TF::AQUA . TF::BOLD . "§bParty " . TF::GRAY . "(" . TF::WHITE . $party->getSize() . TF::GRAY . "/" . TF::WHITE . $party->getMaxSize() . TF::GRAY . ")");
            $item->setLore([
                TF::GRAY . "Leader: " . TF::YELLOW . $party->getLeader()->getUsername(),
                TF::GRAY . "Members: " . TF::WHITE . count($party->getMembers()),
                "",
                TF::YELLOW . "Right-click to manage",
            ]);
        }else{
            $item->setCustomName(TF::AQUA . TF::BOLD . "§bParty");
            $item->setLore([
                TF::GRAY . "Create a party and play with friends.",
                "",
                TF::YELLOW . "Right-click to open",
            ]);
        }

        self::tag($item, self::ITEM_PARTY);
        return $item;
    }

    private static function makeFriends(Session $session): Item{
        // PLAYER_HEAD is a block-type item in Bedrock (skinned player skulls
        // aren't exposed as a plain VanillaItems factory in this build) -
        // using it here would have reproduced the exact "undefined method"
        // crash already documented on makeClan() below (the old paper()
        // bug), just with a different item. NAME_TAG reads more clearly
        // as "people/identity" than the previous WRITABLE_BOOK did (which
        // looked too similar to Quests' plain BOOK() at a glance on the
        // hotbar) and, like every other item in this class, is a plain
        // VanillaItems registry call with no extra NBT required to render
        // correctly.
        $item = VanillaItems::NAME_TAG();
        $item->setCustomName(TF::GREEN . TF::BOLD . "§aFriends");
        $item->setLore([
            TF::GRAY . "Add friends and manage requests.",
            TF::GRAY . "Stay connected across the network.",
            "",
            TF::YELLOW . "Right-click to open",
        ]);
        self::tag($item, self::ITEM_FRIENDS);
        return $item;
    }

    private static function makeClan(Session $session): Item{
        // Was VanillaItems::paper() (lowercase) - VanillaItems' registry is
        // case-sensitive and only exposes the uppercase PAPER() method, so
        // this call threw "Call to undefined method VanillaItems::paper()"
        // every time a session's inventory/clan item was built, crashing
        // for Pocket Edition players. Later swapped to a shield, but that
        // now collides visually with the combat shield players carry into
        // matches, so this uses a banner instead - a much more natural
        // "clan crest/heraldry" icon (it directly matches the banner-based
        // crests in resources/ClanCrests.zip) and, like every other item
        // built in this class, a plain VanillaItems registry call with no
        // extra NBT required to render correctly.
        $item = VanillaItems::BANNER();
        $clan = BedWarsCore::getInstance()->getClanManager()->getClanOf($session->getPlayer()->getName());

        if($clan !== null){
            $item->setCustomName(TF::GOLD . TF::BOLD . "§6Clan " . TF::GRAY . "(" . $clan->getColoredTag() . TF::GRAY . ")");
            $item->setLore([
                TF::GRAY . "Clan: " . $clan->getColoredName(),
                TF::GRAY . "Level: " . TF::WHITE . $clan->getLevel() . TF::GRAY . "  Members: " . TF::WHITE . $clan->getMemberCount() . "/" . $clan->getCapacity(BedWarsCore::getInstance()->getClanManager()->getConfig()),
                "",
                TF::YELLOW . "Right-click to open",
            ]);
        }else{
            $item->setCustomName(TF::GOLD . TF::BOLD . "§6Clan");
            $item->setLore([
                TF::GRAY . "Create or join a clan and team up",
                TF::GRAY . "with other players across the network.",
                "",
                TF::YELLOW . "Right-click to open",
            ]);
        }

        self::tag($item, self::ITEM_CLAN);
        return $item;
    }

    private static function tag(Item $item, string $id): void{
        $nbt = $item->getNamedTag();
        $nbt->setString("lobby_item", $id);
        $item->setNamedTag($nbt);
    }

    public static function getLobbyItemId(Item $item): ?string{
        $id = $item->getNamedTag()->getString("lobby_item", "");
        return $id !== "" ? $id : null;
    }
}