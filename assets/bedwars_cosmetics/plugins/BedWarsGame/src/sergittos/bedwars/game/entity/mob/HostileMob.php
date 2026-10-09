<?php

declare(strict_types=1);

namespace sergittos\bedwars\game\entity\mob;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use sergittos\bedwars\game\Game;
use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\session\SessionFactory;

abstract class HostileMob extends Entity{

    /** Number of segments drawn in the remaining-lifetime bar on the nametag. */
    private const LIFE_BAR_SEGMENTS = 10;

    protected ?Team $team = null;
    protected ?Game $game = null;

    protected int $attackCooldown = 0;
    protected int $aiInterval = 1;
    protected int $aiTicker = 0;

    private int $lifeTicksRemaining = 0;
    private int $lifeLastShownSeconds = -1;

    /** Horizontal position the last time we checked for movement progress, for stuck detection. */
    private ?Vector3 $lastProgressPos = null;
    /** Ticks since the mob last made meaningful horizontal progress while trying to move. */
    private int $stuckTicks = 0;

    /**
     * How often (in ticks) tickAi() is allowed to re-run findNearestEnemy()'s
     * full O(players-in-game) scan to pick/switch the chase target. Movement
     * and attacking still happen every single AI tick exactly as before -
     * only the "who is the nearest enemy" lookup itself is throttled, since
     * that's the one part of tickAi() whose cost scales with lobby size
     * instead of being a handful of constant-time vector ops.
     *
     * With several Iron Golems/Silverfish alive at once (easily the case in
     * a full Bedwars lobby with squads spamming summon items), re-deriving
     * "nearest enemy out of every connected player" from scratch 20 times a
     * second per mob is the single most expensive thing HostileMob does -
     * a real mob doesn't need frame-perfect re-targeting to chase and hit
     * someone convincingly, so this cuts that scan to a fraction of its
     * previous frequency with no visible change to movement smoothness or
     * attack timing (both keep updating every tick against the cached
     * target). The cached target is still dropped and immediately re-scanned
     * the instant it stops being valid (dies, disconnects, leaves detection
     * range, becomes friendly) - see tickAi() - so this never causes a mob
     * to keep chasing a target it shouldn't.
     */
    private const TARGET_RESCAN_INTERVAL = 8;

    private ?Player $cachedTarget = null;
    private int $targetRescanTicker = 0;

    public function setup(Team $team, Game $game) : void{
        $this->team = $team;
        $this->game = $game;

        $seconds = $this->getLifetimeSeconds();
        $this->lifeTicksRemaining = max(0, $seconds * 20);
        $this->lifeLastShownSeconds = -1;

        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->updateNameTag(true);
    }

    public function getTeam(): ?Team{
        return $this->team;
    }

    protected function getInitialDragMultiplier() : float{
        return 0.02;
    }

    protected function getInitialGravity() : float{
        return 0.08;
    }

    abstract protected function getMobName() : string;
    abstract protected function getAttackDamage() : float;
    abstract protected function getMoveSpeed() : float;
    abstract protected function getAttackRange() : float;
    abstract protected function getDetectionRange() : float;
    abstract protected function getLifetimeSeconds() : int;

    protected function initEntity(CompoundTag $nbt) : void{
        parent::initEntity($nbt);

        $max = (int) round($this->getMobMaxHealth());
        if($max < 1){
            $max = 1;
        }

        $this->setMaxHealth($max);
        $this->setHealth((float) $max);
    }

    protected function getMobMaxHealth() : float{
        return 20.0;
    }

    public function attack(EntityDamageEvent $source) : void{
        $attacker = $this->resolveAttackingPlayer($source);
        if($attacker !== null && $this->isFriendly($attacker)){
            $source->cancel();
            return;
        }

        parent::attack($source);

        if($this->isAlive()){
            $this->updateNameTag(true);
        }
    }

    public function onUpdate(int $currentTick) : bool{
        $hasUpdate = parent::onUpdate($currentTick);

        if(!$this->isAlive() || $this->isFlaggedForDespawn() || $this->game === null){
            return $hasUpdate;
        }

        if($this->lifeTicksRemaining > 0){
            $this->lifeTicksRemaining--;
            if($this->lifeTicksRemaining <= 0){
                $this->flagForDespawn();
                return true;
            }

            $sec = (int) ceil($this->lifeTicksRemaining / 20);
            if($sec !== $this->lifeLastShownSeconds){
                $this->lifeLastShownSeconds = $sec;
                $this->updateNameTag();
            }
        }

        if($this->attackCooldown > 0){
            $this->attackCooldown--;
        }

        $this->aiTicker++;
        if($this->aiTicker >= $this->aiInterval){
            $this->aiTicker = 0;
            $this->tickAi();
        }

        return true;
    }

    /**
     * Remaining-lifetime nametag, drawn as "§f[§a■■■■■■■■■■§f] §e150s" -
     * white brackets around a 10-segment bar (green = time left, dark gray
     * = spent) that empties out as the mob's lifetime runs down, then the
     * remaining seconds in yellow. No mob name, team line, or hearts -
     * just the bar + timer, matching the client's cooldown-bar HUD style.
     */
    protected function updateNameTag(bool $force = false) : void{
        if($this->lifeTicksRemaining <= 0){
            $this->setNameTag("");
            return;
        }

        $totalTicks = max(1, $this->getLifetimeSeconds() * 20);
        $filled = (int) round((self::LIFE_BAR_SEGMENTS * $this->lifeTicksRemaining) / $totalTicks);
        if($filled > self::LIFE_BAR_SEGMENTS){
            $filled = self::LIFE_BAR_SEGMENTS;
        }elseif($filled < 0){
            $filled = 0;
        }
        $empty = self::LIFE_BAR_SEGMENTS - $filled;

        $bar = "§a" . str_repeat("\xE2\x96\xA0", $filled) . "§8" . str_repeat("\xE2\x96\xA0", $empty);

        $sec = (int) ceil($this->lifeTicksRemaining / 20);
        if($force){
            $this->lifeLastShownSeconds = $sec;
        }

        $this->setNameTag("§f[" . $bar . "§f] §e" . $sec . "s");
    }

    private function tickAi() : void{
        $target = $this->resolveTarget();
        if($target === null){
            if($this->isOnGround()){
                $this->setMotion(new Vector3(0, 0, 0));
            }else{
                $this->setMotion(new Vector3(0, $this->getMotion()->y, 0));
            }
            $this->stuckTicks = 0;
            $this->lastProgressPos = null;
            return;
        }

        $myPos = $this->getPosition();
        $targetPos = $target->getPosition();

        $dx = $targetPos->x - $myPos->x;
        $dz = $targetPos->z - $myPos->z;

        $attackRange = $this->getAttackRange();
        $range2 = $attackRange * $attackRange;

        $dist3d2 = $myPos->distanceSquared($targetPos);
        if($dist3d2 <= $range2){
            $this->setMotion(new Vector3(0, $this->getMotion()->y, 0));
            $this->tryAttack($target);
            $this->stuckTicks = 0;
            $this->lastProgressPos = null;
            return;
        }

        $horizontal = new Vector3($dx, 0, $dz);
        $len = $horizontal->length();
        if($len <= 0.001){
            if($this->isOnGround()){
                $this->setMotion(new Vector3(0, 0, 0));
            }
            return;
        }

        $dir = $horizontal->divide($len);
        $stuck = $this->updateStuckTracking();

        $yMotion = $this->getMotion()->y;
        if($this->isOnGround()){
            $yMotion = 0.0;
            if($this->shouldJump($dir) || $stuck){
                $yMotion = 0.42;
            }
        }

        $speed = $this->getMoveSpeed();
        $this->setMotion(new Vector3($dir->x * $speed, $yMotion, $dir->z * $speed));

        $yaw = rad2deg(atan2(-$dir->x, $dir->z));
        $this->setRotation($yaw, 0);
    }

    /**
     * Tracks whether the mob is actually making horizontal progress while
     * it's trying to move. shouldJump() alone only catches the case where
     * the block directly ahead (rounded to the nearest of the 8 compass
     * directions) is a 1-block step - it misses corners, half-slabs, and
     * diagonal approaches where the "front" block it checks isn't actually
     * what's blocking movement. If the mob hasn't covered meaningful
     * horizontal distance in half a second despite trying to, we just force
     * a jump outright rather than trying to enumerate every geometry case.
     * Prevents golems/silverfish from silently getting stuck pressed against
     * a ledge or corner for their whole lifetime.
     */
    private function updateStuckTracking(): bool{
        $pos = $this->getPosition();
        $flat = new Vector3($pos->x, 0, $pos->z);

        if($this->lastProgressPos === null){
            $this->lastProgressPos = $flat;
            $this->stuckTicks = 0;
            return false;
        }

        if($flat->distanceSquared($this->lastProgressPos) >= 0.01){
            $this->lastProgressPos = $flat;
            $this->stuckTicks = 0;
            return false;
        }

        $this->stuckTicks++;
        if($this->stuckTicks >= 10){
            $this->stuckTicks = 0;
            $this->lastProgressPos = $flat;
            return true;
        }

        return false;
    }

    private function shouldJump(Vector3 $dir): bool{
        $world = $this->getWorld();
        if($world === null){
            return false;
        }

        $pos = $this->getPosition();
        $fy = $pos->getFloorY();

        // Check the block along the combined diagonal-ish direction, plus
        // each individual axis the mob is moving along - a corner can block
        // the axis-aligned step even when the rounded diagonal cell is clear.
        $candidates = [[(int) round($dir->x), (int) round($dir->z)]];
        if($dir->x !== 0.0){
            $candidates[] = [$dir->x > 0 ? 1 : -1, 0];
        }
        if($dir->z !== 0.0){
            $candidates[] = [0, $dir->z > 0 ? 1 : -1];
        }

        foreach($candidates as [$ox, $oz]){
            if($ox === 0 && $oz === 0){
                continue;
            }

            $fx = $pos->getFloorX() + $ox;
            $fz = $pos->getFloorZ() + $oz;

            $front = $world->getBlockAt($fx, $fy, $fz);
            if($front->getTypeId() === BlockTypeIds::AIR){
                continue;
            }

            $above = $world->getBlockAt($fx, $fy + 1, $fz);
            if($above->getTypeId() === BlockTypeIds::AIR){
                return true;
            }
        }

        return false;
    }

    private function tryAttack(Player $target) : void{
        if($this->attackCooldown > 0){
            return;
        }
        if(!$target->isAlive() || !$target->isConnected()){
            return;
        }

        $myPos = $this->getPosition();
        $targetPos = $target->getPosition();

        $range = $this->getAttackRange();
        if($myPos->distanceSquared($targetPos) > ($range * $range)){
            return;
        }

        $this->attackCooldown = 15;

        $ev = new EntityDamageByEntityEvent(
            $this,
            $target,
            EntityDamageEvent::CAUSE_ENTITY_ATTACK,
            $this->getAttackDamage()
        );
        $target->attack($ev);

        if(!$ev->isCancelled()){
            $diff = $targetPos->subtractVector($myPos->asVector3());
            $diff = new Vector3($diff->x, 0.2, $diff->z);
            $len = $diff->length();

            if($len > 0.01){
                $knockback = $diff->normalize()->multiply(0.55);
                $target->setMotion(new Vector3($knockback->x, 0.35, $knockback->z));
            }
        }
    }

    /**
     * Returns the mob's current chase target, reusing the cached one for
     * most ticks and only paying for a fresh findNearestEnemy() scan when
     * the cache is actually due for a refresh or has gone stale - see
     * TARGET_RESCAN_INTERVAL above for why this exists.
     */
    private function resolveTarget() : ?Player{
        if($this->cachedTarget !== null && !$this->isValidTarget($this->cachedTarget)){
            // Stale immediately, regardless of the ticker - never keep
            // chasing/attacking someone who's dead, disconnected, out of
            // detection range, or (in the rare case a player switches team
            // mid-life of the mob) no longer an enemy.
            $this->cachedTarget = null;
            $this->targetRescanTicker = 0;
        }

        if($this->cachedTarget === null || $this->targetRescanTicker <= 0){
            $this->cachedTarget = $this->findNearestEnemy();
            $this->targetRescanTicker = self::TARGET_RESCAN_INTERVAL;
        }else{
            $this->targetRescanTicker--;
        }

        return $this->cachedTarget;
    }

    private function isValidTarget(Player $player) : bool{
        if(!$player->isConnected() || !$player->isAlive()){
            return false;
        }

        if($this->getPosition()->distanceSquared($player->getPosition()) > ($this->getDetectionRange() ** 2)){
            return false;
        }

        if($this->team !== null && SessionFactory::hasSession($player)){
            $team = SessionFactory::getSession($player)->getTeam();
            if($team !== null && $team->getName() === $this->team->getName()){
                return false;
            }
        }

        return true;
    }

    private function findNearestEnemy() : ?Player{
        if($this->game === null){
            return null;
        }

        $nearest = null;
        $nearestDistance2 = $this->getDetectionRange() ** 2;

        foreach($this->game->getPlayers() as $session){
            $player = $session->getPlayer();

            if(!$player->isConnected() || !$player->isAlive()){
                continue;
            }
            if($this->team !== null && $session->getTeam() !== null && $session->getTeam()->getName() === $this->team->getName()){
                continue;
            }

            $d2 = $this->getPosition()->distanceSquared($player->getPosition());
            if($d2 < $nearestDistance2){
                $nearestDistance2 = $d2;
                $nearest = $player;
            }
        }

        return $nearest;
    }

    private function resolveAttackingPlayer(EntityDamageEvent $source): ?Player{
        if($source instanceof EntityDamageByChildEntityEvent){
            $damager = $source->getDamager();
            if($damager instanceof Player){
                return $damager;
            }

            $child = $source->getChild();
            if($child instanceof Projectile){
                $owner = $child->getOwningEntity();
                if($owner instanceof Player){
                    return $owner;
                }
            }
            return null;
        }

        if($source instanceof EntityDamageByEntityEvent){
            $damager = $source->getDamager();
            if($damager instanceof Player){
                return $damager;
            }
            if($damager instanceof Projectile){
                $owner = $damager->getOwningEntity();
                if($owner instanceof Player){
                    return $owner;
                }
            }
            return null;
        }

        return null;
    }

    private function isFriendly(Player $attacker): bool{
        if($this->team === null || $this->game === null){
            return false;
        }

        if(SessionFactory::hasSession($attacker)){
            $s = SessionFactory::getSession($attacker);
            $t = $s->getTeam();
            return $t !== null && $t->getName() === $this->team->getName();
        }

        return false;
    }

    public function getDrops() : array{
        return [];
    }

    public function getXpDropAmount() : int{
        return 0;
    }
}