<?php

declare(strict_types=1);

namespace sergittos\bedwars\hologram;

use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\world\particle\FloatingTextParticle;

class Hologram {

    /** @var FloatingTextParticle[] */
    private array $lines = [];

    /** @var Vector3[] */
    private array $linePositions = [];

    public function __construct(
        private string  $id,
        private World   $world,
        private Vector3 $position,
        private array   $textLines,
        private float   $lineSpacing = 0.3
    ) {
        $this->spawn();
    }

    private function spawn(): void {
        // Broadcasting with a null $players list makes World::addParticle()
        // fall back to its internal per-chunk viewer cache. In a multi-world
        // setup (several concurrent game worlds re-using the same map
        // coordinates, plus the lobby world) that cache can still contain a
        // player right after they change world/dimension, so the particle
        // leaks into whatever world happens to share the same x/y/z. Passing
        // the world's own, always-accurate player list removes that
        // ambiguity: only players PocketMine currently considers to be in
        // *this* World instance ever receive the packet.
        $y = $this->position->y + (count($this->textLines) - 1) * $this->lineSpacing;
        $viewers = $this->world->getPlayers();
        foreach ($this->textLines as $text) {
            $pos = new Vector3($this->position->x, $y, $this->position->z);
            $particle = new FloatingTextParticle($text);
            $this->world->addParticle($pos, $particle, $viewers);
            $this->lines[] = $particle;
            $this->linePositions[] = $pos;
            $y -= $this->lineSpacing;
        }
    }

    public function showTo(Player $player): void {
        if ($player->getWorld() !== $this->world) return;
        foreach ($this->lines as $i => $particle) {
            $this->world->addParticle($this->linePositions[$i], $particle, [$player]);
        }
    }

    /**
     * Hides this hologram from a single player (e.g. because they just left
     * this hologram's world) without touching it for anyone else, so it
     * never keeps rendering client-side after they've moved to a different
     * world/coordinate space.
     */
    public function hideFrom(Player $player): void {
        foreach ($this->lines as $i => $particle) {
            $invisible = clone $particle;
            $invisible->setInvisible(true);
            $this->world->addParticle($this->linePositions[$i], $invisible, [$player]);
        }
    }

    public function updateLines(array $newLines): void {
        $this->despawn();
        $this->textLines = $newLines;
        $this->lines = [];
        $this->linePositions = [];
        $this->spawn();
    }

    public function despawn(): void {
        // Send the removal explicitly to everyone currently in this world
        // (and at each line's own position) instead of relying on the
        // chunk-viewer cache, which can miss players who just
        // joined/teleported - that left stale lines behind and made the
        // refreshed board look missing/duplicated.
        $viewers = $this->world->getPlayers();
        foreach ($this->lines as $i => $particle) {
            $particle->setInvisible(true);
            $this->world->addParticle($this->linePositions[$i] ?? $this->position, $particle, $viewers);
        }
        $this->lines = [];
        $this->linePositions = [];
    }

    public function getId(): string     { return $this->id; }
    public function getLines(): array   { return $this->textLines; }
    public function getWorld(): World   { return $this->world; }
    public function getPosition(): Vector3 { return $this->position; }
}