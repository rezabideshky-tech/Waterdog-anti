<?php

declare(strict_types=1);

namespace sergittos\bedwars\hologram;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use pocketmine\world\particle\FloatingTextParticle;
use sergittos\bedwars\session\Session;

class HologramManager {

    /** @var array<string,Hologram> */
    private array $holograms = [];

    public function __construct(private Plugin $plugin) {}

    public function create(string $id, World $world, Vector3 $pos, array $lines, float $spacing = 0.3): Hologram {
        $this->remove($id);
        $h = new Hologram($id, $world, $pos, $lines, $spacing);
        $this->holograms[$id] = $h;
        return $h;
    }

    public function remove(string $id): void {
        if (isset($this->holograms[$id])) {
            $this->holograms[$id]->despawn();
            unset($this->holograms[$id]);
        }
    }

    public function get(string $id): ?Hologram {
        return $this->holograms[$id] ?? null;
    }

    public function update(string $id, array $lines): void {
        $this->holograms[$id]?->updateLines($lines);
    }

    /**
     * Colorful "KILLS LEADERBOARD" style hologram: bold title line, a
     * subtitle, then one colored rank line per player with the color
     * cycling down the list so the board reads as lively/eye-catching
     * rather than a flat list.
     */
    public function createLeaderboard(string $stat, string $displayName, World $world, Vector3 $pos, array $top, string $idSuffix = ""): void {
        $palette = ["§c", "§a", "§e", "§6", "§d", "§b", "§2", "§e", "§5", "§f"];
        $paletteCount = count($palette);

        $lines = [
            "§l§c" . strtoupper($displayName) . " LEADERBOARD",
            "§7§oLive Rankings",
            "",
        ];

        foreach ($top as $i => $row) {
            $rank = $i + 1;
            $color = $palette[$i % $paletteCount];
            $lines[] = $color . $rank . "- §f" . $row["username"] . " §7- " . $color . number_format((int) $row[$stat]);
        }

        // $idSuffix keeps two boards of the same stat (different positions,
        // or created by different managers) from replacing each other.
        $this->create("leaderboard_" . $stat . $idSuffix, $world, $pos, $lines);
    }

    public function createPlayerStats(Session $session, World $world, Vector3 $pos): void {
        $lines = [
            "       §l§bSTATUS",
            $session->getRankPrefixDisplay() . " §f" . $session->getUsername(),
            "§7Level: " . $session->getLevelColor() . $session->getLevel(),
            "§7Progress: §b" . $session->getXp() . "§7/§a" . $session->getRequiredXPForNextLevel(),
            "§7Coins: §e" . number_format($session->getCoins()),
            "§7Wins: §a" . number_format($session->getWins()),
            "§7Kills: §e" . number_format($session->getKills()),
            "§7Final Kills: §d" . number_format($session->getFinalKills()),
            "§7Deaths: §c" . number_format($session->getDeaths()),
            "§7Beds Broken: §b" . number_format($session->getBedsBroken()),
        ];
        $this->create("player_stats_" . strtolower($session->getUsername()), $world, $pos, $lines, 0.28);
    }

    /**
     * ارسال هولوگرام آمار شخصی به یک بازیکن خاص
     */
    public function sendStatsHologramToPlayer(Player $player, Vector3 $pos, array $lines, float $spacing = 0.3): void {
        $id = "stats_" . $player->getName();
        $this->remove($id);

        $world = $player->getWorld();
        $y = $pos->getY() + (count($lines) - 1) * $spacing;
        $particles = [];
        foreach ($lines as $text) {
            $linePos = new Vector3($pos->getX(), $y, $pos->getZ());
            $particle = new FloatingTextParticle($text);
            $world->addParticle($linePos, $particle, [$player]);
            $particles[] = $particle;
            $y -= $spacing;
        }

        // ذخیره در آرایه برای مدیریت (اختیاری)
        $this->holograms[$id] = new class($id, $world, $pos, $lines, $particles) {
            private string $id;
            private World $world;
            private Vector3 $pos;
            private array $lines;
            private array $particles;

            public function __construct(string $id, World $world, Vector3 $pos, array $lines, array $particles) {
                $this->id = $id;
                $this->world = $world;
                $this->pos = $pos;
                $this->lines = $lines;
                $this->particles = $particles;
            }

            public function getId(): string { return $this->id; }
            public function getWorld(): World { return $this->world; }
            public function getPosition(): Vector3 { return $this->pos; }
            public function getLines(): array { return $this->lines; }

            public function despawn(): void {
                foreach ($this->particles as $p) {
                    $p->setInvisible(true);
                    $this->world->addParticle($this->pos, $p);
                }
            }

            public function updateLines(array $newLines): void {
                // برای سادگی: حذف و دوباره ایجاد
                $this->despawn();
                // نیازی به پیاده‌سازی کامل نیست، چون توسط LobbyManager مدیریت می‌شود
            }

            // این متد گم بود -> showAllTo() روی هر هولوگرام صداش می‌زنه،
            // پس نبودش دقیقاً روی هر Player Join سرور رو کرش می‌کرد.
            public function showTo(Player $player): void {
                if ($player->getWorld() !== $this->world) return;
                $y = $this->pos->getY();
                foreach ($this->particles as $particle) {
                    $linePos = new Vector3($this->pos->getX(), $y, $this->pos->getZ());
                    $this->world->addParticle($linePos, $particle, [$player]);
                    $y -= 0.28;
                }
            }
        };
    }

    /**
     * Re-sends every hologram of the player's world several times shortly
     * after they join / land in the lobby. A join-time particle packet can
     * be dropped because the client has not finished loading the world yet,
     * which is exactly why some (or all) leaderboard holograms were randomly
     * invisible until the next refresh. A few cheap delayed re-sends make
     * them show up reliably every time.
     */
    public function scheduleResync(Player $player): void {
        foreach ([20, 60, 120, 240, 400] as $delay) {
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void {
                if ($player->isConnected()) {
                    $this->showAllTo($player);
                }
            }), $delay);
        }
    }

    public function removeAll(): void {
        foreach ($this->holograms as $h) $h->despawn();
        $this->holograms = [];
    }

    /**
     * Resends every hologram that lives in the player's current world to that player.
     */
    public function showAllTo(Player $player): void {
        foreach ($this->holograms as $h) {
            // هولوگرام‌های آمار شخصی (stats_/player_stats_) مخصوص یک بیننده‌ی
            // خاص‌اند و با تسک جداگانه‌ای (به‌صورت هدفمند فقط برای همون
            // بازیکن) رفرش می‌شن. اگه اینجا هم broadcast بشن، هر بازیکنی که
            // جوین/دوباره‌جوین کنه، آمار شخصیِ بقیه‌ی بازیکن‌ها رو هم می‌بینه.
            $id = $h->getId();
            if (str_starts_with($id, "stats_") || str_starts_with($id, "player_stats_")) {
                continue;
            }
            if ($h->getWorld() === $player->getWorld()) {
                $h->showTo($player);
            }
        }
    }

    /**
     * Hides every hologram belonging to $fromWorld from $player. Meant to be
     * called on an EntityTeleportEvent world change (before the player is considered to
     * be in the new world) so holograms/leaderboards set up in one world
     * never keep showing at the same coordinates once the player has moved
     * to another world.
     */
    public function hideAllFrom(Player $player, World $fromWorld): void {
        foreach ($this->holograms as $h) {
            $id = $h->getId();
            if (str_starts_with($id, "stats_") || str_starts_with($id, "player_stats_")) {
                continue;
            }
            if ($h->getWorld() === $fromWorld) {
                $h->hideFrom($player);
            }
        }
    }
}