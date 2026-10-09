<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\block\Bed;
use pocketmine\block\Chest;
use pocketmine\block\StainedGlass;
use pocketmine\block\TNT;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ItemMergeEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\world\ChunkUnloadEvent;
use pocketmine\item\MilkBucket;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\particle\SmokeParticle;
use sergittos\bedwars\cosmetics\api\CosmeticsAPI;
use sergittos\bedwars\game\BedWarsGame as BedWars;
use sergittos\bedwars\game\entity\shop\Villager;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\stage\PlayingStage;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\game\stage\WaitingStage;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\session\SessionFactory;
use sergittos\bedwars\utils\GameUtils;
use sergittos\bedwars\utils\MathUtils;
use function array_shift;
use function abs;
use function fmod;
use function mt_rand;
use function strtolower;

final class GameListener implements Listener{

    private const VILLAGER_UPDATE_EVERY_TICKS = 5;
    private const VILLAGER_MIN_MOVE_SQ = 0.0009;
    private const VILLAGER_MIN_YAW_DIFF = 4.0;
    private const VILLAGER_MIN_PITCH_DIFF = 4.0;

    /** @var array<string, int> */
    private array $villagerLastTick = [];

    /**
     * Guards Iron Golem summoning against double-processing. The item is a
     * VILLAGER_SPAWN_EGG under the hood (see MobSummonItems), and a single
     * right click on a block can end up reaching us through BOTH
     * onInteract() (PlayerInteractEvent, block path) and onItemUse()
     * (PlayerItemUseEvent, air path) within the same server tick, since the
     * client's interaction packet can be processed on both paths before
     * either handler runs. Previously each handler independently called
     * summonIronGolem() + removeItem(), so holding 2+ Iron Golem items and
     * clicking once removed 2 items (one per handler) while only 1 golem
     * ended up spawned/visible (the second summon's chunk-population
     * callback raced the first and its entity got lost). Recording the tick
     * we last handled a summon for this player, and skipping if we're
     * already inside that same tick, makes the action idempotent no matter
     * which handler(s) fire for a given click.
     * @var array<string, int>
     */
    private array $ironGolemLastUseTick = [];

    /**
     * pocketmine\entity\projectile\EnderPearl::onHit() hard-codes a
     * CAUSE_FALL hit of 5 damage against the thrower right after
     * teleporting them - that's vanilla ender pearl "fall damage" and is
     * why buying a pearl from the shop and using it to teleport somewhere
     * always hurt the player, even on a flat teleport. Bedwars shop pearls
     * are meant to be a pure mobility item with no downside, so we flag the
     * owner the moment their pearl lands (onProjectileHit(), which runs
     * before the built-in onHit() applies the damage) and swallow the very
     * next CAUSE_FALL hit against them.
     * @var array<string, true>
     */
    private array $pendingPearlFallDamage = [];

    public function __construct(private Server $server){}

    public function onReceiveDamage(EntityDamageEvent $event): void{
        $entity = $event->getEntity();
        if(!$entity instanceof Player){
            return;
        }

        if(!SessionFactory::hasSession($entity)){
            return;
        }

        if($event->getCause() === EntityDamageEvent::CAUSE_FALL){
            $key = strtolower($entity->getName());
            if(isset($this->pendingPearlFallDamage[$key])){
                unset($this->pendingPearlFallDamage[$key]);
                $event->cancel();
                return;
            }
        }

        $session = SessionFactory::getSession($entity);

        if($session->isFrozen()){
            // Winner being held for the victory countdown - immune to
            // any incidental damage (fall damage from the freeze
            // teleport, stray splash potions, etc.) while frozen.
            $event->cancel();
            return;
        }

        if(!$session->isPlaying()){
            return;
        }

        $game = $session->getGame();
        if($game === null){
            return;
        }

        $stage = $game->getStage();
        if($stage instanceof WaitingStage || $stage instanceof StartingStage){
            $event->cancel();
            return;
        }

        if($session->isSpawnProtected()){
            // Brief grace window right after this session's initial
            // team-spawn teleport - see Session::$spawnProtected's doc
            // comment. Covers exactly the moment the WaitingStage/
            // StartingStage check above no longer applies (the stage has
            // just flipped to PlayingStage) but the player may still be
            // settling into a chunk that only just finished loading.
            $event->cancel();
            return;
        }

        $effects = $entity->getEffects();
        if($effects->has($effect = VanillaEffects::INVISIBILITY())){
            $effects->remove($effect);
            $session->message("§cYou took damage.");
        }

        if($event->getCause() === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION){
            $event->setBaseDamage(0);
        }

        if($event->getFinalDamage() >= $entity->getHealth()){
            $viewers = [];
            foreach($game->getPlayersAndSpectators() as $s){
                $p = $s->getPlayer();
                if($p->isConnected()){
                    $viewers[] = $p;
                }
            }

            $deathPos = $entity->getPosition()->asVector3();
            $killer = $session->getLastSessionHit();
            $isFinal = $session->hasTeam() && $session->getTeam()->isBedDestroyed();

            CosmeticsAPI::triggerDeathCry($entity, $deathPos, $viewers);

            if($killer !== null && $killer->getPlayer()->isConnected()){
                CosmeticsAPI::triggerKillSound($killer->getPlayer());
                if($isFinal){
                    CosmeticsAPI::triggerFinalKillEffect($killer->getPlayer(), $deathPos, $viewers);
                }
            }

            $session->kill($event->getCause());
            $event->cancel();
        }
    }

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void{
        $damager = $event->getDamager();
        $entity = $event->getEntity();
        if(!$damager instanceof Player || !$entity instanceof Player){
            return;
        }

        if(!SessionFactory::hasSession($damager) || !SessionFactory::hasSession($entity)){
            return;
        }

        $damager_session = SessionFactory::getSession($damager);
        $entity_session = SessionFactory::getSession($entity);

        if($damager_session->isFrozen() || $entity_session->isFrozen()){
            // Frozen winners neither deal nor take damage during the
            // victory countdown.
            $event->cancel();
            return;
        }

        $game = $damager_session->getGame();
        if($game !== null){
            $stage = $game->getStage();
            if($stage instanceof WaitingStage || $stage instanceof StartingStage){
                $event->cancel();
                return;
            }
        }

        if(
            $damager_session->isPlaying() && $entity_session->isPlaying() &&
            $damager_session->hasTeam() && $entity_session->hasTeam() &&
            $damager_session->getTeam()->hasMember($entity_session)
        ){
            // Friendly fire: the damage itself is cancelled below, but
            // knockback from projectiles (bow arrows, fireballs) still gets
            // applied by the engine even though the damage event ends up
            // cancelled. setLastSessionHit() must NOT run for this hit -
            // otherwise if that knockback later pushes the teammate into
            // the void (or off a ledge into any other death), the kill
            // would be wrongly credited to their own teammate.
            $event->cancel();
            return;
        }

        $entity_session->setLastSessionHit($damager_session);
    }

    public function onInteract(PlayerInteractEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);

        if($session->isFrozen()){
            $event->cancel();
            return;
        }

        if(!$session->isPlaying()){
            return;
        }

        $game = $session->getGame();
        if($game !== null){
            $stage = $game->getStage();
            if($stage instanceof WaitingStage || $stage instanceof StartingStage){
                $event->cancel();
                return;
            }
        }

        // Iron Golem item is a VILLAGER_SPAWN_EGG under the hood (see
        // MobSummonItems::createIronGolemItem) so it keeps the vanilla spawn
        // egg texture/name without a resource pack. The downside: spawn eggs
        // have built-in client/server behaviour that spawns their vanilla
        // entity (a Villager, here) the instant they're used ON A BLOCK -
        // that path goes through PlayerInteractEvent -> Item::onInteractBlock(),
        // never through PlayerItemUseEvent (which only fires for a right
        // click in the air). Since this listener previously only handled the
        // golem item in onItemUse(), right-clicking a block with it left the
        // vanilla spawn-egg logic completely unhandled and it silently spawned
        // a real, motionless Villager instead of running summonIronGolem().
        // Cancelling here - before the engine gets to Item::onInteractBlock() -
        // stops that vanilla spawn from ever happening.
        if($session->isPlaying() && $session->hasTeam() && $game !== null){
            $item = $event->getItem();
            if(\sergittos\bedwars\item\game\MobSummonItems::isIronGolemItem($item)){
                $event->cancel();
                $this->handleIronGolemUse($player, $session->getTeam(), $game);
                return;
            }
        }

        $block = $event->getBlock();
        if(!$block instanceof Chest){
            return;
        }

        if($game === null){
            return;
        }

        $team = $this->getTeamByPosition($game, $block->getPosition());
        if($team !== null && $team->isAlive() && $session->hasTeam() && $team->getName() !== $session->getTeam()->getName()){
            $session->message("§c§lAccess Denied§r §7- Enemy chest.");
            $event->cancel();
        }
    }

    public function onBreak(BlockBreakEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);

        if($session->isFrozen()){
            $event->cancel();
            return;
        }

        if(!$session->isPlaying()){
            return;
        }

        $game = $session->getGame();
        if($game === null){
            return;
        }

        $stage = $game->getStage();
        if($stage instanceof WaitingStage || $stage instanceof StartingStage){
            $event->cancel();
            return;
        }

        $block = $event->getBlock();
        $position = $block->getPosition();

        if($game->checkBlock($position)){
            return;
        }

        if($block instanceof Bed){
            $event->cancel();
            $event->setDrops([]);

            $other = $block->getOtherHalf();
            $otherPos = $other?->getPosition();

            $team = $this->getTeamByBed($game, $position, $otherPos);
            if($team === null){
                $session->message("§cYou can't break beds.");
                return;
            }

            if($team->isBedDestroyed()){
                return;
            }

            if($session->hasTeam() && $session->getTeam()->getName() === $team->getName()){
                $session->message("§cYou can't break your own bed.");
                return;
            }

            $world = $position->getWorld();
            foreach($block->getAffectedBlocks() as $b){
                $world->setBlock($b->getPosition(), VanillaBlocks::AIR());
            }

            $team->destroyBed($game, $session, false);
            $game->setLastBedBreakPosition($position->asVector3());
            $game->setLastBedBreakTeam($team);
            $game->getBountyManager()->registerBedBreak($session);

            $viewers = [];
            foreach($game->getPlayersAndSpectators() as $s){
                $p = $s->getPlayer();
                if($p->isConnected()){
                    $viewers[] = $p;
                }
            }
            CosmeticsAPI::triggerBedBreakEffect($player, $position->asVector3(), $viewers);

            $msg = "{BOLD}{WHITE}BED DESTRUCTION > {RESET}" . $team->getColoredName() . " Bed {GRAY}was destroyed by " . $session->getColoredUsername() . "{GRAY}!";
            $this->messageAliveTeamsAndSpectators($game, $msg);
            return;
        }

        $session->message("§cOnly player-placed blocks can be broken.");
        $event->cancel();
    }

    private function messageAliveTeamsAndSpectators(Game $game, string $message): void{
        foreach($game->getPlayersAndSpectators() as $s){
            if(!$s->getPlayer()->isConnected()){
                continue;
            }
            if($s->isSpectator()){
                $s->message($message);
                continue;
            }
            if(!$s->hasTeam()){
                $s->message($message);
                continue;
            }
            if($s->getTeam()->isAlive()){
                $s->message($message);
            }
        }
    }

    public function onPlace(BlockPlaceEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);

        if($session->isFrozen()){
            $event->cancel();
            return;
        }

        if(!$session->isPlaying()){
            return;
        }

        $game = $session->getGame();
        if($game === null){
            return;
        }

        $stage = $game->getStage();
        if($stage instanceof WaitingStage || $stage instanceof StartingStage){
            $event->cancel();
            return;
        }

        $positionAgainst = $event->getBlockAgainst()->getPosition();

        $placedBlock = null;
        foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
            $placedBlock = $block;
            break;
        }
        if($placedBlock === null){
            return;
        }

        if($placedBlock->getPosition()->getY() > 230){
            $session->message("§c§lBuild Limit§r §7- You can't build this high.");
            $event->cancel();
            return;
        }

        $teamAt = $this->getTeamByPosition($game, $positionAgainst);
        if($teamAt !== null && $teamAt->getClaim()->isInside($positionAgainst)){
            $session->message("§cProtected area.");
            $event->cancel();
            return;
        }

        $handItem = $player->getInventory()->getItemInHand();
        if(\sergittos\bedwars\item\game\CastleBlock::isCastleBlock($handItem)){
            $event->cancel();

            $team = $session->getTeam();
            if($team !== null){
                $yaw = $player->getLocation()->getYaw();
                $yaw = fmod(($yaw % 360) + 360, 360);

                $face = match(true){
                    $yaw >= 45 && $yaw < 135 => Facing::WEST,
                    $yaw >= 135 && $yaw < 225 => Facing::NORTH,
                    $yaw >= 225 && $yaw < 315 => Facing::EAST,
                    default => Facing::SOUTH,
                };

                $entrance = Facing::opposite($face);

                \sergittos\bedwars\item\game\CastleBlock::build(
                    $game,
                    $positionAgainst->add(0, 1, 0)->floor(),
                    $team->getDyeColor(),
                    $entrance
                );

                $player->getInventory()->removeItem($handItem->setCount(1));
                $session->playSound("random.explode", 0.5, 2.0);
            }
            return;
        }

        foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
            if($block instanceof TNT){
                BedWars::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($block): void{
                    $world = $block->getPosition()->world;
                    if($world !== null && $world->isLoaded()){
                        $block->ignite(60);
                    }
                }), 1);

                $game->addBlock($block->getPosition());
                continue;
            }

            $game->addBlock($block->getPosition());
        }

        if($placedBlock->getTypeId() === BlockTypeIds::SPONGE){
            $w = $placedBlock->getPosition()->getWorld();
            $p = $placedBlock->getPosition();
            BedWars::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($w, $p): void{
                $this->absorbWater($w, $p);
            }), 1);
        }
    }

    public function onMove(PlayerMoveEvent $event): void{
        $player = $event->getPlayer();
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);

        if($session->isFrozen()){
            // Winner being held for the victory countdown: rotation
            // (looking around) is left alone, only actual translation is
            // reverted, so they stay put but can still watch the arena.
            $to = $event->getTo();
            $freezePosition = $session->getFreezePosition();

            if($to !== null && $freezePosition !== null){
                $movedSq = $to->asVector3()->distanceSquared($freezePosition->asVector3());
                if($movedSq > 0.0025){
                    $event->cancel();
                    if($player->isConnected()){
                        $player->teleport($freezePosition, $to->getYaw(), $to->getPitch());
                    }
                }
            }
            return;
        }

        if(!$session->isPlaying() || !$session->hasTeam()){
            return;
        }

        $game = $session->getGame();
        if($game !== null){
            $stage = $game->getStage();
            if($stage instanceof WaitingStage || $stage instanceof StartingStage){
                return;
            }
        }

        if($this->shouldUpdateVillagers($event)){
            $this->checkEntities($player);
        }

        if($session->getGameSettings()->isUnderMagicMilkEffect()){
            return;
        }

        if($game === null){
            return;
        }

        $team = $this->getTeamByPosition($game, $player->getPosition());
        if($team === null || $team->isBedDestroyed() || $session->getTeam()->getName() === $team->getName()){
            return;
        }

        $upgrades = $team->getUpgrades();
        if($upgrades->canTriggerTrap()){
            $upgrades->triggerPrimaryTrap($session, $team);
        }
    }

    private function shouldUpdateVillagers(PlayerMoveEvent $event): bool{
        $from = $event->getFrom();
        $to = $event->getTo();
        if($to === null){
            return false;
        }

        $movedSq = $from->asVector3()->distanceSquared($to->asVector3());
        $yawDiff = abs($to->getYaw() - $from->getYaw());
        $pitchDiff = abs($to->getPitch() - $from->getPitch());

        if(
            $movedSq < self::VILLAGER_MIN_MOVE_SQ &&
            $yawDiff < self::VILLAGER_MIN_YAW_DIFF &&
            $pitchDiff < self::VILLAGER_MIN_PITCH_DIFF
        ){
            return false;
        }

        $name = strtolower($event->getPlayer()->getName());
        $tick = $this->server->getTick();
        $last = $this->villagerLastTick[$name] ?? 0;

        if(($tick - $last) < self::VILLAGER_UPDATE_EVERY_TICKS){
            return false;
        }

        $this->villagerLastTick[$name] = $tick;
        return true;
    }

    public function onEffectAdd(EntityEffectAddEvent $event): void{
        $entity = $event->getEntity();
        if(!$entity instanceof Player){
            return;
        }

        if(!SessionFactory::hasSession($entity)){
            return;
        }

        $session = SessionFactory::getSession($entity);
        if(!$session->isPlaying()){
            return;
        }

        $effect = $event->getEffect();
        $duration = GameUtils::getEffectDuration($effect);

        if($duration !== 0){
            $effect->setDuration($duration);
            $effect->setAmplifier(GameUtils::getEffectAmplifier($effect));
            $effect->setVisible(false);
        }
    }

    public function onEntityDamageByChildEntity(EntityDamageByChildEntityEvent $event): void{
        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if(!$damager instanceof Player || !$entity instanceof Player){
            return;
        }

        if(!SessionFactory::hasSession($damager) || !SessionFactory::hasSession($entity)){
            return;
        }

        if(!SessionFactory::getSession($damager)->isPlaying() || !SessionFactory::getSession($entity)->isPlaying()){
            return;
        }

        $child = $event->getChild();
        if($child instanceof Arrow){
            SessionFactory::getSession($damager)->playSound("random.orb");
        }
    }

    public function onExplode(EntityExplodeEvent $event): void{
        $world = $event->getPosition()->getWorld();
        $game = BedWars::getInstance()->getGameManager()->getGameByWorld($world);
        if($game === null){
            return;
        }

        $block_list = [];
        foreach($event->getBlockList() as $block){
            if($block instanceof StainedGlass){
                continue;
            }
            if(!$game->checkBlock($block->getPosition())){
                continue;
            }
            $block_list[] = $block;
        }

        $event->setBlockList($block_list);
    }

    private static ?ItemStackWrapper $invisibilityAirWrapper = null;

    public function onDataPacketSend(DataPacketSendEvent $event): void{
        // This listener runs on every packet batch sent to every player (hundreds of
        // thousands of times per game), but only ever needs to act on the rare
        // MobEquipmentPacket/MobArmorEquipmentPacket (plus, see below, AddPlayerPacket).
        // Filter those out first, with no session lookups at all, so the overwhelming
        // majority of calls (movement, chunk, sound, etc. packets) return immediately
        // instead of paying the cost of a SessionFactory lookup per viewer.
        $relevantPackets = [];
        $spawnPackets = [];
        foreach($event->getPackets() as $packet){
            if($packet instanceof MobEquipmentPacket || $packet instanceof MobArmorEquipmentPacket){
                $relevantPackets[] = $packet;
            }elseif($packet instanceof AddPlayerPacket){
                // AddPlayerPacket (sent the moment a spectator first enters
                // another player's view - on join, respawn-into-spectator,
                // world change, or simply walking into render distance)
                // carries its own "item" field with whatever is currently
                // held. That's a separate packet from MobEquipmentPacket,
                // so the masking below never touched it - a spectator's
                // held item would flash visible for anyone who had just
                // come into view of them, even though every *later* item
                // switch was correctly hidden. Queued separately so it can
                // be corrected with a follow-up packet instead of edited
                // in place (no writable "item" masking is reliable across
                // protocol versions here).
                $spawnPackets[] = $packet;
            }
        }
        if($relevantPackets === [] && $spawnPackets === []){
            return;
        }

        foreach($event->getTargets() as $target){
            $viewer = $target->getPlayer();
            // Only needs a bedwars session to be worth masking for - this used
            // to also require isPlaying(), which meant spectators watching the
            // match never had another spectator's held item hidden from them.
            if($viewer === null || !SessionFactory::hasSession($viewer)){
                continue;
            }

            foreach($relevantPackets as $packet){
                $targetEntity = $viewer->getWorld()->getEntity($packet->actorRuntimeId);
                if(!$targetEntity instanceof Player){
                    continue;
                }
                // The isPlaying() requirement here was the actual bug: a dead
                // player who becomes a spectator is no longer "playing", so
                // their equipped/held spectator item (compass, teleporter,
                // etc.) never got masked and stayed visible to everyone else.
                // The invisibility-effect check right below is what should
                // gate this, not the player's playing/spectating status.
                if(!SessionFactory::hasSession($targetEntity)){
                    continue;
                }
                if(!$targetEntity->getEffects()->has(VanillaEffects::INVISIBILITY())){
                    continue;
                }

                $air = self::$invisibilityAirWrapper ??= ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaItems::AIR()));

                if($packet instanceof MobEquipmentPacket){
                    $packet->item = $air;
                }else{
                    $packet->head = $air;
                    $packet->chest = $air;
                    $packet->legs = $air;
                    $packet->feet = $air;
                }
            }

            foreach($spawnPackets as $packet){
                $targetEntity = $viewer->getWorld()->getEntity($packet->actorRuntimeId);
                if(!$targetEntity instanceof Player || !SessionFactory::hasSession($targetEntity)){
                    continue;
                }
                if(!$targetEntity->getEffects()->has(VanillaEffects::INVISIBILITY())){
                    continue;
                }

                // Force a correction right behind the spawn packet in the
                // same send, so the client never has a frame where the
                // held item is shown - it applies the equipment update a
                // moment after the spawn instead of ever rendering the
                // real item first.
                $air = self::$invisibilityAirWrapper ??= ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaItems::AIR()));
                $slot = $targetEntity->getInventory()->getHeldItemIndex();
                $viewer->getNetworkSession()->sendDataPacket(
                    MobEquipmentPacket::create($packet->actorRuntimeId, $air, $slot, $slot, ContainerIds::INVENTORY)
                );
                $viewer->getNetworkSession()->sendDataPacket(
                    MobArmorEquipmentPacket::create($packet->actorRuntimeId, $air, $air, $air, $air, $air)
                );
            }
        }
    }

    public function onCraft(CraftItemEvent $event): void{
        if(!SessionFactory::hasSession($event->getPlayer())){
            return;
        }

        $session = SessionFactory::getSession($event->getPlayer());
        if($session->isPlaying()){
            $event->cancel();
        }
    }

    public function onConsume(PlayerItemConsumeEvent $event): void{
        if(!SessionFactory::hasSession($event->getPlayer())){
            return;
        }

        $session = SessionFactory::getSession($event->getPlayer());
        if($session->isPlaying() && $event->getItem() instanceof MilkBucket){
            $session->getGameSettings()->setMagicMilk();
        }
    }

    public function onItemSpawn(ItemSpawnEvent $event): void{
        $entity = $event->getEntity();
        if($entity->getOwner() === "generator"){
            $entity->setPickupDelay(0);
        }
    }

    public function onPickup(EntityItemPickupEvent $event): void{
        $picker = $event->getEntity();

        // Spectators (eliminated players, or anyone in the fake ADVENTURE-mode
        // spectator state - see Session::giveSpectatorItems(), which keeps them
        // in ADVENTURE rather than real GameMode::SPECTATOR() for Bedrock hotbar
        // reasons) must never be able to pick up ANYTHING dropped in the map:
        // generator resources, arrows, block drops, whatever. Previously only
        // generator-owned items were guarded below, so a spectator standing near
        // a dropped arrow or a mined block's drops would still receive it into
        // their spectator inventory - and standing near a generator resource
        // would silently despawn it (flagForDespawn() ran unconditionally,
        // regardless of whether a real player actually received the item)
        // without ever handing it to anyone. Cancelling here and returning
        // before any of that runs leaves the drop untouched in the world for an
        // actual player to collect later, and keeps the spectator's inventory
        // limited to their spectator items.
        if($picker instanceof Player && SessionFactory::hasSession($picker)){
            $pickerSession = SessionFactory::getSession($picker);
            if($pickerSession->isSpectator() || !$pickerSession->isPlaying()){
                $event->cancel();
                return;
            }
        }

        $origin = $event->getOrigin();
        if(!$origin instanceof ItemEntity){
            return;
        }

        if($origin->getOwner() === "generator_display"){
            $event->cancel();
            return;
        }

        if($origin->getOwner() !== "generator"){
            return;
        }

        $event->cancel();

        $world = $origin->getWorld();
        foreach($world->getNearbyEntities($origin->getBoundingBox()->expandedCopy(1, 0.5, 1), $origin) as $entity){
            if(!$entity instanceof Player){
                continue;
            }

            // Spectators (including players who died and are now watching the
            // match, or anyone in spectator gamemode) must never be able to
            // pick up generator resources (iron/gold/emerald/diamond) - they
            // aren't playing anymore and shouldn't be able to collect or hand
            // off resources to teammates.
            if(SessionFactory::hasSession($entity)){
                $entitySession = SessionFactory::getSession($entity);
                if($entitySession->isSpectator() || !$entitySession->isPlaying()){
                    continue;
                }
            }

            NetworkBroadcastUtils::broadcastEntityEvent(
                $origin->getViewers(),
                fn(EntityEventBroadcaster $broadcaster, array $recipients) => $broadcaster->onPickUpItem($recipients, $entity, $origin)
            );

            foreach($entity->getInventory()->addItem($event->getItem()) as $remains){
                $world->dropItem($origin->getLocation(), $remains, new Vector3(0, 0, 0));
            }
        }

        $origin->flagForDespawn();
    }

    public function onProjectileHit(ProjectileHitEvent $event): void{
        $projectile = $event->getEntity();
        if(!$projectile instanceof EnderPearl){
            return;
        }

        $owner = $projectile->getOwningEntity();
        if(!$owner instanceof Player){
            return;
        }

        if(!SessionFactory::hasSession($owner)){
            return;
        }

        $this->pendingPearlFallDamage[strtolower($owner->getName())] = true;
    }

    public function onMerge(ItemMergeEvent $event): void{
        $item = $event->getTarget()->getItem();
        if($item->getCount() >= GameUtils::getCountById($item->getTypeId())){
            $event->getEntity()->flagForDespawn();
            $event->cancel();
        }
    }

    /**
     * Generator holograms (diamond/emerald floating text) used to keep
     * showing at the same map coordinates in every other world while a game
     * was running, because nothing ever told a game world to stop
     * broadcasting to a player once they'd actually left it (e.g. teleported
     * to spectate elsewhere, went back to the lobby, or joined a different
     * concurrent game on the same map). Whenever a player's *origin* world
     * belonged to a running game, explicitly despawn that game's generator
     * text from them on the way out.
     */
    public function onWorldChange(EntityTeleportEvent $event): void{
        $player = $event->getEntity();
        if(!$player instanceof Player) return;
        if(!SessionFactory::hasSession($player)){
            return;
        }

        $from = $event->getFrom()->getWorld();
        $to = $event->getTo()->getWorld();
        if($from === $to){
            return;
        }

        $game = BedWars::getInstance()->getGameManager()->getGameByWorld($from);
        if($game === null){
            return;
        }

        $game->despawnGeneratorsFrom(SessionFactory::getSession($player));
    }

    public function onQuit(PlayerQuitEvent $event): void{
        $player = $event->getPlayer();
        $key = strtolower($player->getName());
        unset($this->villagerLastTick[$key]);
        unset($this->ironGolemLastUseTick[$key]);
        unset($this->pendingPearlFallDamage[$key]);

        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        $game = $session->getGame();
        if($game === null){
            return;
        }

        $stage = $game->getStage();

        // NOTE: disconnects for a playing session that still has a team during
        // PlayingStage are handled exclusively by RejoinDisconnectListener, which
        // owns the rejoin database record, the pending-rejoin timer and the chat
        // announcement for this case. Do not duplicate that logic here - having
        // two listeners race on PlayerQuitEvent was the cause of rejoin breaking
        // (they disagreed on the grace window: 45s here vs 60s/120s elsewhere).
        if($session->hasTeam() && $stage instanceof PlayingStage){
            return;
        }

        if($session->isPlaying()){
            $game->removePlayer($session, false);
            return;
        }

        if($session->isSpectator()){
            $game->leaveSpectating($session);
        }
    }

    public function onChunkUnload(ChunkUnloadEvent $event): void{
        $game = BedWars::getInstance()->getGameManager()->getGameByWorld($event->getWorld());
        if($game !== null && $game->getStage() instanceof PlayingStage){
            $event->cancel();
        }
    }

    public function onExplosionPrime(EntityPreExplodeEvent $event): void{
        $event->setRadius(5);
    }

    public function onExhaust(PlayerExhaustEvent $event): void{
        $event->cancel();
    }

    public function onBedEnter(PlayerBedEnterEvent $event): void{
        $event->cancel();
    }

    public function onItemUse(PlayerItemUseEvent $event): void{
        $player = $event->getPlayer();

        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        if($session->isSpectator()){
            return;
        }

        if($session->isFrozen()){
            $event->cancel();
            return;
        }

        if(!$session->isPlaying() || !$session->hasTeam()){
            return;
        }

        $item = $event->getItem();
        $team = $session->getTeam();
        $game = $session->getGame();

        if($team === null || $game === null){
            return;
        }

        if(\sergittos\bedwars\item\game\MobSummonItems::isIronGolemItem($item)){
            $event->cancel();
            $this->handleIronGolemUse($player, $team, $game);
        }
    }

    /**
     * Single entry point for actually summoning an Iron Golem from the item.
     * Both onInteract() and onItemUse() route here instead of duplicating
     * the summon + item-removal logic, and this method is idempotent per
     * player per tick - see $ironGolemLastUseTick for why that matters.
     */
    private function handleIronGolemUse(Player $player, Team $team, Game $game): void{
        $key = strtolower($player->getName());
        $tick = $this->server->getTick();

        if(($this->ironGolemLastUseTick[$key] ?? -1) === $tick){
            return;
        }
        $this->ironGolemLastUseTick[$key] = $tick;

        if(!SessionFactory::hasSession($player)){
            return;
        }
        $session = SessionFactory::getSession($player);

        $item = $player->getInventory()->getItemInHand();
        if(!\sergittos\bedwars\item\game\MobSummonItems::isIronGolemItem($item)){
            return;
        }

        \sergittos\bedwars\item\game\MobSummonItems::summonIronGolem($player, $team, $game);
        $player->getInventory()->removeItem($item->setCount(1));
        $session->playSound("mob.irongolem.spawn", 1.0, 1.0);
        $session->message("§aYour Iron Golem is now active.");
    }

    private function checkEntities(Player $player): void{
        $network_session = $player->getNetworkSession();
        $position = $player->getPosition();

        foreach($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy(12, 12, 12), $player) as $entity){
            if(!$entity instanceof Villager){
                continue;
            }

            $yaw = MathUtils::calculateYaw($position, $location = $entity->getLocation());
            $pitch = MathUtils::calculatePitch($position, $location);

            $network_session->sendDataPacket(MoveActorAbsolutePacket::create(
                $entity->getId(),
                $location,
                $pitch,
                $yaw,
                $yaw,
                0
            ));
        }
    }

    private function getTeamByPosition(Game $game, Vector3 $position): ?Team{
        foreach($game->getTeams() as $team){
            if($team->getZone()->isInside($position)){
                return $team;
            }
        }
        return null;
    }

    private function sameBlock(Vector3 $a, Vector3 $b): bool{
        return $a->getFloorX() === $b->getFloorX()
            && $a->getFloorY() === $b->getFloorY()
            && $a->getFloorZ() === $b->getFloorZ();
    }

    private function getTeamByBed(Game $game, Position $pos, ?Position $other): ?Team{
        foreach($game->getTeams() as $team){
            $bed = $team->getBedPosition();
            if($this->sameBlock($bed, $pos) || ($other !== null && $this->sameBlock($bed, $other))){
                return $team;
            }
        }

        if($other !== null){
            foreach($game->getTeams() as $team){
                $b = $team->getBedPosition();
                if($b->distance($pos) <= 1.2 || $b->distance($other) <= 1.2){
                    return $team;
                }
            }
        }

        return null;
    }

    private function absorbWater(World $world, Position $spongePos): void{
        $origin = $spongePos->asVector3();

        $maxDistance = 6;
        $maxBlocks = 128;

        $queue = [[$origin->getFloorX(), $origin->getFloorY(), $origin->getFloorZ(), 0]];
        $visited = [];
        $removed = 0;

        while($queue !== [] && $removed < $maxBlocks){
            [$x, $y, $z, $d] = array_shift($queue);
            $key = $x . ":" . $y . ":" . $z;
            if(isset($visited[$key])){
                continue;
            }
            $visited[$key] = true;

            if($d > $maxDistance){
                continue;
            }

            $id = $world->getBlockAt($x, $y, $z)->getTypeId();
            if($id === BlockTypeIds::WATER){
                $world->setBlockAt($x, $y, $z, VanillaBlocks::AIR());
                $removed++;
            }

            if($d === $maxDistance){
                continue;
            }

            $nd = $d + 1;
            $queue[] = [$x + 1, $y, $z, $nd];
            $queue[] = [$x - 1, $y, $z, $nd];
            $queue[] = [$x, $y + 1, $z, $nd];
            $queue[] = [$x, $y - 1, $z, $nd];
            $queue[] = [$x, $y, $z + 1, $nd];
            $queue[] = [$x, $y, $z - 1, $nd];
        }

        if($removed > 0){
            for($i = 0; $i < 32; $i++){
                $px = $origin->x + (mt_rand(-70, 70) / 100);
                $py = $origin->y + 0.2 + (mt_rand(0, 140) / 100);
                $pz = $origin->z + (mt_rand(-70, 70) / 100);
                $world->addParticle(new Vector3($px, $py, $pz), new SmokeParticle(0));
            }
        }
    }
}