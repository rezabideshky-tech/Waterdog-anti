<?php

declare(strict_types=1);


namespace sergittos\bedwars\game\generator;


use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\World;
use sergittos\bedwars\session\Session;

class GeneratorText {

    /**
     * Height, in blocks, of the bottom hologram line above the generator's
     * base position. Raised again from 2.75 so the whole hologram stack
     * sits higher, with the floating display block (Generator::
     * DISPLAY_BLOCK_Y_OFFSET, currently 3.4) tucked just underneath the
     * bottom line (+3.75) instead of overlapping it - tweak this single
     * value (together with Generator::DISPLAY_BLOCK_Y_OFFSET) if it still
     * needs nudging up/down.
     */
    private const BASE_Y_OFFSET = 3.25;

    /** @var FloatingTextParticle[] */
    private array $particles;

    public function __construct() {
        $this->particles[0] = new FloatingTextParticle("");
        $this->particles[1] = new FloatingTextParticle("");
        $this->particles[2] = new FloatingTextParticle("");
    }

    public function update(Generator $generator, World $world): void {
        $this->particles[0]->setText(TextFormat::YELLOW . "Tier " . TextFormat::RED . $generator->getTier()->name);
        $this->particles[1]->setText($generator->getName());
        $this->particles[2]->setText(TextFormat::YELLOW . "Spawns in " . TextFormat::RED . ($time = ((int) ($generator->getTime() / 20))) . TextFormat::YELLOW . ($time === 1 ? " second" : " seconds"));

        // Explicitly restrict the broadcast to players PocketMine currently
        // considers part of THIS world. A null viewers list falls back to
        // the internal per-chunk viewer cache, which can still list a
        // player for a brief moment after they've already changed world -
        // with several concurrent game worlds reusing the same map
        // coordinates (and the lobby world doing the same thing for its own
        // holograms), that was enough for the generator text to leak into
        // whatever other world happened to share that x/y/z.
        $viewers = $world->getPlayers();
        foreach($this->particles as $index => $line) {
            $world->addParticle($generator->getPosition()->add(0, (3 - $index) / 2 + self::BASE_Y_OFFSET, 0), $line, $viewers);
        }
    }

    public function despawnFrom(Session $session): void {
        foreach($this->particles as $line) {
            $line->setInvisible();
            foreach($line->encode(Vector3::zero()) as $packet) {
                $session->sendDataPacket($packet);
            }
            $line->setInvisible(false);
        }
    }

}