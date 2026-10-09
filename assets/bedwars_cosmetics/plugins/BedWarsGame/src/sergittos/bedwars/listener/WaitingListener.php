<?php

declare(strict_types=1);

namespace sergittos\bedwars\listener;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\world\Position;
use sergittos\bedwars\game\stage\StartingStage;
use sergittos\bedwars\game\stage\WaitingStage;
use sergittos\bedwars\session\SessionFactory;
use function microtime;
use function strtolower;

final class WaitingListener implements Listener{

    private const MAX_RADIUS = 29.0;
    private const MAX_RADIUS_SQ = self::MAX_RADIUS * self::MAX_RADIUS;
    private const MAX_DROP_BELOW_SPAWN = 8.0;

    /** @var array<string, float> */
    private array $tpCooldown = [];

    public function onBreak(BlockBreakEvent $event): void{
        if($this->shouldCancel($event->getPlayer())){
            $event->cancel();
        }
    }

    public function onPlace(BlockPlaceEvent $event): void{
        if($this->shouldCancel($event->getPlayer())){
            $event->cancel();
        }
    }

    public function onInteract(PlayerInteractEvent $event): void{
        if($this->shouldCancel($event->getPlayer())){
            $event->cancel();
        }
    }

    public function onReceiveDamage(EntityDamageEvent $event): void{
        $entity = $event->getEntity();
        if($entity instanceof Player && $this->shouldCancel($entity)){
            $event->cancel();
        }
    }

    public function onMove(PlayerMoveEvent $event): void{
        $player = $event->getPlayer();

        if($this->bypassWaitingLock($player)){
            return;
        }

        if(!SessionFactory::hasSession($player)){
            return;
        }

        $session = SessionFactory::getSession($player);
        if(!$session->isPlaying()){
            return;
        }

        $game = $session->getGame();
        if($game === null){
            return;
        }

        $stage = $game->getStage();
        if(!($stage instanceof WaitingStage) && !($stage instanceof StartingStage)){
            return;
        }

        $world = $game->getMap()->getWaitingWorld();
        if($player->getWorld() !== $world){
            return;
        }

        $spawn = Position::fromObject($game->getMap()->getWaitingSpawnPosition(), $world);
        $to = $event->getTo();
        if($to === null){
            return;
        }

        $name = strtolower($player->getName());
        $now = microtime(true);
        if(isset($this->tpCooldown[$name]) && ($now - $this->tpCooldown[$name]) < 0.6){
            return;
        }

        $distSq = $to->asVector3()->distanceSquared($spawn->asVector3());
        $tooFar = $distSq > self::MAX_RADIUS_SQ;
        $tooLow = $to->getY() < ($spawn->getY() - self::MAX_DROP_BELOW_SPAWN);

        if($tooFar || $tooLow){
            $this->tpCooldown[$name] = $now;
            $player->teleport($spawn);
            $player->setMotion(Vector3::zero());
        }
    }

    private function bypassWaitingLock(Player $player): bool{
        return
            $player->hasPermission(DefaultPermissions::ROOT_OPERATOR) ||
            $player->hasPermission("bedwars.admin") ||
            $player->hasPermission("bedwars.setup");
    }

    private function shouldCancel(Player $player): bool{
        if(!SessionFactory::hasSession($player)){
            return false;
        }

        $session = SessionFactory::getSession($player);
        if(!$session->isPlaying()){
            return false;
        }

        $game = $session->getGame();
        if($game === null){
            return false;
        }

        $stage = $game->getStage();
        return $stage instanceof WaitingStage || $stage instanceof StartingStage;
    }
}