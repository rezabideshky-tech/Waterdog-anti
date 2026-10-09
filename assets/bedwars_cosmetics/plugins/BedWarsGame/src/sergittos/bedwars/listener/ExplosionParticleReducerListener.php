<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\ParticleIds;

/**
 * Why this listener exists:
 *
 * Both TNT and the Fireball item (sergittos\bedwars\game\entity\misc\Fireball)
 * go through the exact same pocketmine\world\Explosion::explodeB() core
 * routine to actually destroy blocks, damage/knock back entities and play
 * the effects. Block destruction, damage and the explosion sound are all
 * unaffected by this listener - only the visual "debris cloud" particle is
 * touched, and only after the engine has already finished computing/
 * applying every gameplay effect of the explosion.
 *
 * That visual is a single HugeExplodeSeedParticle per explosion (client-side
 * ParticleIds::HUGE_EXPLODE_SEED), which Bedrock renders as a large, heavy
 * cloud of rock/dust debris. During normal Bedwars play - cannoning,
 * TNT bridges, fireball spam in team fights - several of these can
 * overlap within the same second or two, turning into a dense, disorienting
 * cluster of particles that makes it hard to see what's actually happening
 * and adds unnecessary client-side rendering load, without changing
 * anything about the outcome of the explosion.
 *
 * This listener intercepts the packet at DataPacketSendEvent (after the
 * explosion has already fully happened server-side) and swaps the heaviest
 * HUGE_EXPLODE_SEED particle id for the medium ParticleIds::HUGE_EXPLODE id
 * before it reaches the client. The packet is mutated in place (its
 * properties are public) rather than cancelled, so the explosion sound
 * (a separate packet) and every other effect keep working exactly as
 * before - only the particle intensity changes.
 *
 * Tuning note: this used to downgrade all the way to the small
 * ParticleIds::EXPLODE puff, which made TNT/fireball explosions look a
 * little too thin. It now stops one step short of the original problem
 * particle instead: ParticleIds::HUGE_EXPLODE is a single, self-contained
 * "big poof" (no debris swarm), so overlapping explosions still don't
 * cluster into the disorienting mess HUGE_EXPLODE_SEED caused - the
 * visual is just noticeably bigger/more satisfying than the thin EXPLODE
 * puff, with no change to block destruction, damage, knockback, or sound.
 */
final class ExplosionParticleReducerListener implements Listener{

    private const HUGE_EXPLODE_SEED_EVENT_ID = LevelEvent::ADD_PARTICLE_MASK | ParticleIds::HUGE_EXPLODE_SEED;
    private const HUGE_EXPLODE_EVENT_ID = LevelEvent::ADD_PARTICLE_MASK | ParticleIds::HUGE_EXPLODE;
    private const LIGHT_EXPLODE_EVENT_ID = LevelEvent::ADD_PARTICLE_MASK | ParticleIds::HUGE_EXPLODE;

    public function onDataPacketSend(DataPacketSendEvent $event): void{
        // Defensive by design, same reasoning as the other raw-protocol
        // listeners in this plugin: any mismatch here must never be able
        // to break packet sending for a real player, so a failure here
        // just leaves the original (heavier) particle packet untouched.
        try{
            $this->tryHandle($event);
        }catch(\Throwable){
            // Swallow - worst case, that one explosion keeps its normal
            // particle intensity instead of being thinned out.
        }
    }

    private function tryHandle(DataPacketSendEvent $event): void{
        // This listener is only ever registered inside the BedWarsGame
        // plugin (see BedWarsGame::onEnable()), whose worlds are match
        // arenas exclusively populated by tracked Bedwars sessions - so
        // every LevelEventPacket seen here already belongs to an in-game
        // TNT/Fireball explosion. No further per-player scoping is needed
        // (or even possible: the packet object below is shared as-is
        // across every target in the batch, so there is nothing session-
        // specific to check before mutating it).
        foreach($event->getPackets() as $packet){
            if(!$packet instanceof LevelEventPacket){
                continue;
            }

            if($packet->eventId === self::HUGE_EXPLODE_SEED_EVENT_ID || $packet->eventId === self::HUGE_EXPLODE_EVENT_ID){
                $packet->eventId = self::LIGHT_EXPLODE_EVENT_ID;
            }
        }
    }
}
