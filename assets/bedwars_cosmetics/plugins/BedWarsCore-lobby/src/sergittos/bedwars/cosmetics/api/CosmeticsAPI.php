<?php

declare(strict_types=1);

namespace sergittos\bedwars\cosmetics\api;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\EmotePacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\SnowballPoofParticle;
use pocketmine\world\particle\SplashParticle;
use pocketmine\world\World;
use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
use sergittos\bedwars\cosmetics\registry\CosmeticCategory;
use function count;
use function mt_rand;

final class CosmeticsAPI{

    private const VIEW_RANGE = 160.0;

    /**
     * Played on a winner when neither they nor anyone else on their team
     * owns a Victory Dance cosmetic, so the winning team always dances on
     * victory instead of nothing happening.
     */
    public const DEFAULT_VICTORY_DANCE_KEY = "hooray";

    public static function triggerBedBreakEffect(Player $owner, Vector3 $pos, array $viewers): void{
        $key = PlayerCosmeticsManager::getInstance()->getEquipped($owner, CosmeticCategory::BED_BREAK_EFFECT);
        if($key === null){
            return;
        }

        $world = $owner->getWorld();
        $targets = self::filterViewers($world, $pos, $viewers, self::VIEW_RANGE);
        if($targets === []){
            return;
        }

        match($key){
            "flame" => self::burst($pos, new FlameParticle(), 28, 1, $world, $targets),
            "hearts" => self::burst($pos, new HeartParticle(1), 20, 1, $world, $targets),
            "snowflake" => self::burst($pos, new SnowballPoofParticle(), 26, 1, $world, $targets),
            "shatter" => self::burst($pos, new BlockBreakParticle(VanillaBlocks::GLASS()), 24, 1, $world, $targets),
            "water_splash" => self::burst($pos, new SplashParticle(), 24, 1, $world, $targets),
            "happy_villager" => self::burst($pos, new HappyVillagerParticle(), 24, 1, $world, $targets),
            "enchant" => self::burst($pos, new EnchantmentTableParticle(), 22, 1, $world, $targets),
            "portal" => self::burst($pos, new PortalParticle(), 26, 1, $world, $targets),
            default => null
        };
    }

    public static function triggerFinalKillEffect(Player $owner, Vector3 $pos, array $viewers): void{
        $key = PlayerCosmeticsManager::getInstance()->getEquipped($owner, CosmeticCategory::FINAL_KILL_EFFECT);
        if($key === null){
            return;
        }

        $world = $owner->getWorld();
        $targets = self::filterViewers($world, $pos, $viewers, self::VIEW_RANGE);
        if($targets === []){
            return;
        }

        match($key){
            "snowflake" => self::burst($pos, new SnowballPoofParticle(), 34, 2, $world, $targets),
            "critical_hit" => self::burst($pos, new CriticalParticle(), 34, 2, $world, $targets),
            "water_splash" => self::burst($pos, new SplashParticle(), 34, 2, $world, $targets),
            "happy_villager" => self::burst($pos, new HappyVillagerParticle(), 34, 2, $world, $targets),
            "enchant" => self::burst($pos, new EnchantmentTableParticle(), 34, 2, $world, $targets),
            "portal" => self::burst($pos, new PortalParticle(), 42, 2, $world, $targets),
            "flame" => self::burst($pos, new FlameParticle(), 36, 2, $world, $targets),
            "hearts" => self::burst($pos, new HeartParticle(1), 28, 2, $world, $targets),
            default => null
        };
    }

    public static function triggerDeathCry(Player $owner, Vector3 $pos, array $viewers): void{
        $key = PlayerCosmeticsManager::getInstance()->getEquipped($owner, CosmeticCategory::DEATH_CRY);
        if($key === null){
            return;
        }

        $sound = match($key){
            "skeleton" => "mob.skeleton.death",
            "blaze" => "mob.blaze.death",
            "cat_meow" => "mob.cat.meow",
            "pling" => "note.pling",
            "ghast" => "mob.ghast.scream",
            "explosion" => "random.explode",
            "villager_no" => "mob.villager.no",
            "dragon" => "mob.enderdragon.growl",
            default => null
        };

        if($sound === null){
            return;
        }

        $world = $owner->getWorld();
        $targets = self::filterViewers($world, $pos, $viewers, self::VIEW_RANGE);
        if($targets === []){
            return;
        }

        foreach($targets as $p){
            $p->getNetworkSession()->sendDataPacket(PlaySoundPacket::create($sound, $pos->x, $pos->y, $pos->z, 1.0, 1.0));
        }
    }

    public static function triggerKillSound(Player $owner): void{
        $key = PlayerCosmeticsManager::getInstance()->getEquipped($owner, CosmeticCategory::KILL_SOUND);
        if($key === null){
            return;
        }

        $sound = match($key){
            "orb" => "random.orb",
            "levelup" => "random.levelup",
            "anvil" => "random.anvil_use",
            "thunder" => "ambient.weather.thunder",
            "pling" => "note.pling",
            default => null
        };

        if($sound === null){
            return;
        }

        $pos = $owner->getPosition();
        $owner->getNetworkSession()->sendDataPacket(PlaySoundPacket::create($sound, $pos->x, $pos->y, $pos->z, 1.0, 1.0));
    }

    /**
     * Builds a fully custom broadcast line for a kill from the killer's
     * equipped Kill Message cosmetic. $victimUsername and $killerUsername
     * are expected to already carry their own rank/team colour codes (as
     * produced by Session::getColoredUsername()) - only the connecting
     * flavour text is coloured here, so player name colours are preserved.
     *
     * Returns null whenever the killer has no Kill Message equipped, so
     * the caller can fall back to the plugin's default wording unchanged.
     */
    public static function buildKillMessage(Player $killer, string $victimUsername, string $killerUsername, bool $isVoidKill, bool $isFinalKill): ?string{
        $key = PlayerCosmeticsManager::getInstance()->getEquipped($killer, CosmeticCategory::KILL_MESSAGE);
        if($key === null){
            return null;
        }

        $verb = $isFinalKill
            ? match($key){
                "savage" => "was annihilated by",
                "royal" => "was struck down by",
                "assassin" => "was silently ended by",
                "warlord" => "was crushed by",
                "champion" => "was defeated by",
                "phantom" => "vanished at the hands of",
                default => null
            }
            : ($isVoidKill
                ? match($key){
                    "savage" => "was hurled into the void by",
                    "royal" => "was cast into the void by",
                    "assassin" => "was dropped into the void by",
                    "warlord" => "was thrown into the void by",
                    "champion" => "was sent into the void by",
                    "phantom" => "faded into the void, courtesy of",
                    default => null
                }
                : match($key){
                    "savage" => "was destroyed by",
                    "royal" => "was bested by",
                    "assassin" => "was eliminated by",
                    "warlord" => "was overpowered by",
                    "champion" => "was outplayed by",
                    "phantom" => "was taken down by",
                    default => null
                });

        if($verb === null){
            return null;
        }

        $accent = match($key){
            "savage" => "§4",
            "royal" => "§6",
            "assassin" => "§5",
            "warlord" => "§c",
            "champion" => "§e",
            "phantom" => "§b",
            default => "§7"
        };

        $line = $victimUsername . " " . $accent . $verb . " " . $killerUsername . "§7.";

        if($isFinalKill){
            $line .= " §b§lFINAL KILL!";
        }

        return $line;
    }

    public static function triggerWinEffect(Player $owner, Vector3 $pos, array $viewers): void{
        $key = PlayerCosmeticsManager::getInstance()->getEquipped($owner, CosmeticCategory::WIN_EFFECT);
        if($key === null){
            return;
        }

        $world = $owner->getWorld();
        $targets = self::filterViewers($world, $pos, $viewers, self::VIEW_RANGE);
        if($targets === []){
            return;
        }

        foreach($targets as $p){
            $p->getNetworkSession()->sendDataPacket(PlaySoundPacket::create("random.levelup", $pos->x, $pos->y, $pos->z, 1.0, 1.0));
        }

        match($key){
            "victory_flame" => self::burst($pos, new FlameParticle(), 45, 2, $world, $targets),
            "victory_hearts" => self::burst($pos, new HeartParticle(1), 36, 2, $world, $targets),
            "victory_enchant" => self::burst($pos, new EnchantmentTableParticle(), 46, 2, $world, $targets),
            "victory_portal" => self::burst($pos, new PortalParticle(), 62, 3, $world, $targets),
            "victory_critical" => self::burst($pos, new CriticalParticle(), 52, 2, $world, $targets),
            "victory_snowflake" => self::burst($pos, new SnowballPoofParticle(), 44, 2, $world, $targets),
            "victory_happy" => self::burst($pos, new HappyVillagerParticle(), 46, 2, $world, $targets),
            default => null
        };
    }

    /**
     * Plays a Victory Dance emote on the owner's player model for everyone
     * in the game to see, including the owner themselves - matching how
     * the other victory cosmetics behave.
     *
     * By default this plays whatever dance the owner has equipped. Pass
     * $forcedKey to make them perform a specific dance instead - used by
     * EndingStage so an entire winning team performs the same shared
     * dance (either the one dance someone on the team owns, a random pick
     * among several owned dances, or the default fallback dance) rather
     * than each member's own individually equipped one.
     *
     * @param Player[] $viewers
     */
    public static function triggerVictoryDance(Player $owner, array $viewers, ?string $forcedKey = null): void{
        if(!$owner->isConnected()){
            self::logDanceSkip($owner, $forcedKey, "owner not connected");
            return;
        }

        $key = $forcedKey ?? PlayerCosmeticsManager::getInstance()->getEquipped($owner, CosmeticCategory::VICTORY_DANCE);
        if($key === null){
            $key = self::DEFAULT_VICTORY_DANCE_KEY;
        }

        $emoteId = self::danceEmoteId($key);
        if($emoteId === null){
            // An unrecognized/stale key (e.g. a cosmetic key added to one
            // server's registry but not the other - BedWarsCore-lobby and
            // BedWarsCore-game each keep their own copy of
            // CosmeticsRegistry/this class, and they have to be kept in
            // sync by hand) used to mean the dance silently never played
            // at all, for anyone, with no trace of why - which matches a
            // report of "even the default dance doesn't play". Falling
            // back to the guaranteed-known default dance here means a
            // mismatched key degrades to "wrong dance plays" instead of
            // "no dance plays", and the debug log below still names the
            // key that failed to resolve so the actual mismatch can be
            // fixed at its source.
            self::logDanceSkip($owner, $key, "key did not resolve to a known emote - falling back to default");
            $key = self::DEFAULT_VICTORY_DANCE_KEY;
            $emoteId = self::danceEmoteId($key);
            if($emoteId === null){
                // Unreachable in practice (DEFAULT_VICTORY_DANCE_KEY is
                // always one of danceEmoteId()'s match arms), but never
                // send a packet with a null id.
                return;
            }
        }

        $pos = $owner->getPosition();
        $world = $owner->getWorld();
        $targets = self::filterViewers($world, $pos, $viewers, self::VIEW_RANGE);
        if($targets === []){
            self::logDanceSkip($owner, $key, "no connected viewers within range/world after filterViewers()");
            return;
        }

        // FLAG_SERVER is required so the Bedrock client treats this as a
        // server-authoritative emote and actually renders it - without it,
        // clients silently ignore EmotePackets that weren't triggered by
        // that player's own emote wheel, so the purchased dance never
        // visibly plays for anyone (including the winner) on victory.
        $pk = EmotePacket::create($owner->getId(), $emoteId, 0, "", "", EmotePacket::FLAG_SERVER | EmotePacket::FLAG_MUTE_ANNOUNCEMENT);
        $sent = 0;
        foreach($targets as $p){
            if($p->isConnected()){
                try{
                    $p->getNetworkSession()->sendDataPacket($pk);
                    $sent++;
                }catch(\Throwable $e){
                    \pocketmine\Server::getInstance()->getLogger()->debug(
                        "CosmeticsAPI: failed to send victory dance EmotePacket to " . $p->getName() . ": " . $e->getMessage()
                    );
                }
            }
        }

        if($sent === 0){
            self::logDanceSkip($owner, $key, "sendDataPacket failed for every target (see debug log above)");
        }
    }

    /**
     * One-line, debug-level trace of exactly why a Victory Dance did not
     * play (or fell back to the default), so a report like "the dance
     * doesn't play for anyone" can be matched against a specific branch
     * in triggerVictoryDance() from the server console instead of
     * re-reading this whole method every time it happens. Debug-level
     * only - invisible at normal log verbosity, so this is safe to leave
     * in permanently rather than needing to be added back in later.
     */
    private static function logDanceSkip(Player $owner, ?string $key, string $reason): void{
        \pocketmine\Server::getInstance()->getLogger()->debug(
            "CosmeticsAPI: victory dance for " . $owner->getName() . " (key=" . ($key ?? "null") . ") skipped - " . $reason
        );
    }

    private static function danceEmoteId(string $key): ?string{
        return match($key){
            "hooray" => "c4b5b251-24d3-43eb-9c05-46be246aeefb",
            "groovin" => "d863b9cc-9f8c-498b-a8a3-7ebd542cb08e",
            "cheer_routine" => "3d10a8c7-213c-4fbe-a208-a0f7990d5bbb",
            "salsa" => "6bcf44bd-ff8a-48a5-9254-3983a0b0f702",
            "breakdance" => "1dbaa006-0ec6-42c3-9440-a3bfa0c6fdbe",
            "victory_cheer" => "d0c60245-538e-4ea2-bdd4-33477db5aa89",
            "sonic_spin" => "cd8c3bc6-f455-43d2-836e-62c1a19474c7",
            default => null
        };
    }

    /**
     * How long (in seconds) the given Victory Dance key's emote animation
     * actually takes to play out. Used by EndingStage to re-broadcast the
     * looping Victory Dance only once each cycle has genuinely finished,
     * instead of on a fixed timer that could restart (and visibly snap)
     * a longer dance before it ever completes.
     *
     * A small buffer is intentionally baked into each value so a full
     * play-through never gets cut off even slightly - looping a beat late
     * reads as a natural pause, looping early reads as broken.
     */
    public static function danceDurationSeconds(?string $key): float{
        return match($key){
            "hooray" => 4.5,
            "groovin" => 6.0,
            "cheer_routine" => 7.0,
            "salsa" => 6.5,
            "breakdance" => 5.5,
            "victory_cheer" => 5.0,
            "sonic_spin" => 3.5,
            default => 5.0
        };
    }

    private static function filterViewers(World $world, Vector3 $pos, array $viewers, float $range): array{
        $out = [];
        $r2 = $range * $range;

        foreach($viewers as $p){
            if(!$p instanceof Player || !$p->isConnected() || $p->getWorld() !== $world){
                continue;
            }
            if($p->getPosition()->distanceSquared($pos) <= $r2){
                $out[] = $p;
            }
        }

        if($out !== []){
            return $out;
        }

        foreach($world->getPlayers() as $p){
            if($p->isConnected() && $p->getPosition()->distanceSquared($pos) <= $r2){
                $out[] = $p;
            }
        }

        return $out;
    }

    private static function burst(Vector3 $center, object $particle, int $count, int $radius, World $world, array $viewers): void{
        $vcount = count($viewers);
        if($vcount <= 0){
            return;
        }

        $mult = 1.0;
        if($vcount > 8){
            $mult = 8.0 / $vcount;
            if($mult < 0.35){
                $mult = 0.35;
            }
        }

        $count = (int) max(1, (int) round($count * $mult));

        for($i = 0; $i < $count; $i++){
            $x = $center->x + (mt_rand(-100, 100) / 100) * $radius;
            $y = $center->y + (mt_rand(0, 100) / 100) * 1.6;
            $z = $center->z + (mt_rand(-100, 100) / 100) * $radius;
            $world->addParticle(new Vector3($x, $y, $z), $particle, $viewers);
        }
    }
}