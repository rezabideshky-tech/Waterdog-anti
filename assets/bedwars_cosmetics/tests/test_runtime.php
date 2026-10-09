<?php
declare(strict_types=1);
// Deterministic lifecycle/network doubles. This is not an in-game or server load test.
namespace pocketmine\nbt\tag { class CompoundTag{} }
namespace pocketmine\event { interface Listener{} }
namespace pocketmine\event\entity { class EntityDamageEvent{ public bool $cancelled=false; public function cancel(): void{ $this->cancelled=true; } } }
namespace pocketmine\event\player {
 class PlayerQuitEvent{ public function __construct(private \pocketmine\player\Player $player){} public function getPlayer(): \pocketmine\player\Player{ return $this->player; } }
 class PlayerDeathEvent extends PlayerQuitEvent{}
}
namespace pocketmine\network\mcpe\protocol\types\entity {
 class EntityMetadataFlags{ const SNEAKING=1; }
 class EntityMetadataProperties{ const VARIANT=2,MARK_VARIANT=43; }
 class Metadata{
  public array $values=[];public int $writes=0;
  public function setInt(int $k,int $v): void{ $this->values[$k]=$v;++$this->writes; }
  public function setGenericFlag(int $k,bool $v): void{ $this->values['flag'.$k]=$v;++$this->writes; }
 }
}
namespace pocketmine\entity {
 class EntitySizeInfo{ public function __construct(public float $height,public float $width){} }
 class Location{
  public function __construct(public float $x,public float $y,public float $z,private object $world,public float $yaw=0,public float $pitch=0){}
  public function getWorld(): object{ return $this->world; }
  public function distanceSquared(self $at): float{ return ($this->x-$at->x)**2+($this->y-$at->y)**2+($this->z-$at->z)**2; }
 }
 abstract class Entity{
  public array $viewers=[];public bool $closed=false;public bool $persistent=true;
  public int $movementPackets=0;public bool $lastTeleport=false;private float $scale=1;
  private \pocketmine\network\mcpe\protocol\types\entity\Metadata $metadata;
  public function __construct(private Location $location){ $this->metadata=new \pocketmine\network\mcpe\protocol\types\entity\Metadata();$this->initEntity(new \pocketmine\nbt\tag\CompoundTag()); }
  protected function initEntity(\pocketmine\nbt\tag\CompoundTag $tag): void{}
  public function setCanSaveWithChunk(bool $v): void{ $this->persistent=$v; }
  public function setHasGravity(bool $v): void{}
  public function setSilent(bool $v): void{}
  public function setNameTagVisible(bool $v): void{}
  public function setNoClientPredictions(bool $v): void{}
  public function getViewers(): array{ return $this->viewers; }
  public function getLocation(): Location{ return clone $this->location; }
  public function getWorld(): object{ return $this->location->getWorld(); }
  protected function setPosition(Location $at): void{ if($this->getWorld()!==$at->getWorld()){ $this->viewers=[]; }$this->location=clone $at; }
  public function moveForTest(Location $at): void{ $this->setPosition($at); }
  public function setRotation(float $yaw,float $pitch): void{ $this->location->yaw=$yaw;$this->location->pitch=$pitch; }
  public function getScale(): float{ return $this->scale; }
  public function setScale(float $v): void{ $this->scale=$v; }
  public function getNetworkProperties(): \pocketmine\network\mcpe\protocol\types\entity\Metadata{ return $this->metadata; }
  public function spawnTo(\pocketmine\player\Player $p): void{ if($this->getWorld()===$p->getWorld()){ $this->viewers[spl_object_id($p)]=$p; } }
  public function despawnFrom(\pocketmine\player\Player $p): void{ unset($this->viewers[spl_object_id($p)]); }
  protected function updateMovement(bool $teleport=false): void{ ++$this->movementPackets;$this->lastTeleport=$teleport; }
  public function scheduleUpdate(): void{}
  public function close(): void{ $this->closed=true;$this->viewers=[]; }
  public function isClosed(): bool{ return $this->closed; }
 }
}
namespace pocketmine\player {
 class Player extends \pocketmine\entity\Entity{
  public bool $connected=true,$alive=true,$invisible=false,$spectator=false,$sneaking=false,$swimming=false,$gliding=false,$sleeping=false,$sees=true;
  public function getId(): int{ return spl_object_id($this); }
  public function isConnected(): bool{ return $this->connected; }
  public function isAlive(): bool{ return $this->alive; }
  public function isInvisible(): bool{ return $this->invisible; }
  public function isSpectator(): bool{ return $this->spectator; }
  public function isSneaking(): bool{ return $this->sneaking; }
  public function isSwimming(): bool{ return $this->swimming; }
  public function isGliding(): bool{ return $this->gliding; }
  public function isSleeping(): bool{ return $this->sleeping; }
  public function canSee(Player $p): bool{ return $this->sees; }
  public function getName(): string{ return 'Test'; }
  public function getSkin(): never{ throw new \RuntimeException('Never read a skin'); }
  public function setSkin(mixed $s): never{ throw new \RuntimeException('Never overwrite a skin'); }
  public function getArmorInventory(): never{ throw new \RuntimeException('Never alter armor'); }
 }
}
namespace pocketmine\plugin { interface Plugin{ public function getServer(): object; public function getLogger(): object; public function getScheduler(): object; } }
namespace pocketmine\scheduler { class ClosureTask{ public function __construct(public \Closure $callback){} } }
namespace pocketmine\resourcepacks {
 class ZippedResourcePack{
  public static string $uuid='848cf7bf-dc90-47f3-8259-2607c81252ec';
  public function __construct(string $path){}
  public function getPackId(): string{ return self::$uuid; }
  public function getPackVersion(): string{ return '2.0.0'; }
 }
}
namespace customiesdevs\customies\entity {
 class CustomiesEntityFactory{
  private static ?self $instance=null;public int $registered=0;
  public static function getInstance(): self{ return self::$instance??=new self(); }
  public function registerEntity(string $class,string $identifier): void{ ++$this->registered; }
 }
}
namespace sergittos\bedwars\session {
 class Session{
  public bool $loaded=true,$spectator=false;public array $selected=[];
  public function isLoaded(): bool{ return $this->loaded; }
  public function isSpectator(): bool{ return $this->spectator; }
  public function getTeam(): object{ return new class{ public function getDyeColor(): object{ return new class{ public function getDisplayName(): string{ return 'Blue'; } }; } }; }
  public function __call(string $name,array $args): mixed{ return $this->selected[substr($name,3)]??'none'; }
 }
}
namespace sergittos\bedwars {
 class BedWarsCore{
  private static ?self $instance=null;public \sergittos\bedwars\session\Session $session;
  public function __construct(){ $this->session=new \sergittos\bedwars\session\Session(); }
  public static function getInstance(): self{ return self::$instance??=new self(); }
  public function getResource(string $name): mixed{ return fopen(dirname(__DIR__).'/plugins/BedWarsCore-game/resources/'.$name,'rb'); }
  public function getSessionManager(): object{ return new class($this->session){ public function __construct(private object $s){} public function get(object $p): object{ return $this->s; } }; }
  public function getLogger(): object{ return new \LogDouble(); }
 }
}
namespace {
 use pocketmine\entity\Location;use pocketmine\player\Player;
 use sergittos\bedwars\cosmetics\wearable\{ResourceCosmeticActor as Actor,ResourceCosmeticService as Service,ResourceCosmeticListener};
 function check(bool $v,string $why): void{ if(!$v){ throw new \RuntimeException($why); } }
 class LogDouble{ public array $messages=[];public function __call(string $method,array $args): void{ $this->messages[]=$args[0]; } }
 class PackManagerDouble{
  public array $stack;public bool $required=false;
  public function __construct(){ $this->stack=[new class{ public function getPackId(): string{ return 'original-ui'; } }]; }
  public function getPath(): string{ return dirname(__DIR__); }
  public function getPackById(string $id): ?object{ foreach($this->stack as $p){ if($p->getPackId()===$id){ return $p; } }return null; }
  public function getResourceStack(): array{ return $this->stack; }
  public function setResourceStack(array $stack): void{ $this->stack=$stack; }
  public function setResourcePacksRequired(bool $v): void{ $this->required=$v; }
 }
 class ServerDouble{
  public bool $customies=false;public array $online=[];public PackManagerDouble $packs;
  public function __construct(){ $this->packs=new PackManagerDouble(); }
  public function getPluginManager(): object{ return new class($this){
   public function __construct(private ServerDouble $s){}
   public function getPlugin(string $name): ?object{ return !$this->s->customies?null:new class{ public function isEnabled(): bool{ return true; } }; }
   public function registerEvents(object $listener,object $plugin): void{}
  }; }
  public function getResourcePackManager(): object{ return $this->packs; }
  public function getOnlinePlayers(): array{ return $this->online; }
 }
 class PluginDouble implements \pocketmine\plugin\Plugin{
  public array $tasks=[];public ServerDouble $server;public LogDouble $logger;
  public function __construct(){ $this->server=new ServerDouble();$this->logger=new LogDouble(); }
  public function getServer(): object{ return $this->server; }
  public function getLogger(): object{ return $this->logger; }
  public function getScheduler(): object{ return new class($this){
   public function __construct(private PluginDouble $p){}
   public function scheduleRepeatingTask(object $task,int $ticks): void{ if($ticks!==2){ throw new \RuntimeException('10 Hz cadence'); }$this->p->tasks[]=$task; }
  }; }
 }
 $src=dirname(__DIR__).'/plugins/BedWarsCore-game/src/sergittos/bedwars/cosmetics';
 foreach(['registry/CosmeticCategory','wearable/ResourceCosmeticCatalog','data/PlayerCosmeticsManager','wearable/ResourceCosmeticActor','wearable/ResourceCosmeticListener','wearable/ResourceCosmeticService'] as $file){ require $src.'/'.$file.'.php'; }
 $world=new \stdClass();$owner=new Player(new Location(0,64,0,$world));$viewer=new Player(new Location(2,64,0,$world));$stranger=new Player(new Location(3,64,0,$world));
 $owner->viewers=[spl_object_id($viewer)=>$viewer];$a=new Actor($owner->getLocation());$a->bind($owner);$a->synchronize(123,true,1);
 check(!$a->persistent && !$a->canBeCollidedWith() && !$a->canCollideWith($owner),'No persistence/collision');
 check(count($a->viewers)===2,'Owner and tracking viewer only');$a->spawnTo($stranger);check(count($a->viewers)===2,'Non-tracking viewer rejected');
 $writes=$a->getNetworkProperties()->writes;$a->synchronize(123,true,1);
 check($a->getNetworkProperties()->writes===$writes && $a->movementPackets===0,'No idle metadata/movement spam');
 $owner->setRotation(60,30);$owner->sneaking=true;$owner->setScale(1.2);$a->synchronize(124,true,2);
 check($a->getLocation()->yaw===60.0 && $a->getScale()===1.2 && $a->getNetworkProperties()->values['flag1'],'Pose and scale sync');
 $viewer->sees=false;$a->synchronize(124,true,2);check(count($a->viewers)===1,'hidePlayer privacy');
 $owner->invisible=true;$a->synchronize(124,true,2);check($a->viewers===[],'Invisibility cleanup');$owner->invisible=false;
 $otherWorld=new \stdClass();$owner->moveForTest(new Location(40,80,10,$otherWorld));$a->synchronize(124,true,2);
 check($a->getWorld()===$otherWorld && $a->lastTeleport && count($a->viewers)===1,'Cross-world teleport, old viewers removed');
 $damage=new \pocketmine\event\entity\EntityDamageEvent();$a->attack($damage);check($damage->cancelled,'No damage target');
 $owner->connected=false;$a->synchronize(1,true);check($a->isClosed(),'Disconnect closes actor');$owner->connected=true;
 $plugin=new PluginDouble();Service::initialize($plugin);check(!Service::isReady(),'Missing Customies fails closed');
 $plugin->server->customies=true;\pocketmine\resourcepacks\ZippedResourcePack::$uuid='wrong';Service::initialize($plugin);
 check(!Service::isReady() && count($plugin->server->packs->stack)===1,'Wrong UUID rejects pack without replacing stack');
 \pocketmine\resourcepacks\ZippedResourcePack::$uuid='848cf7bf-dc90-47f3-8259-2607c81252ec';Service::initialize($plugin);Service::initialize($plugin);
 check(Service::isReady() && count($plugin->server->packs->stack)===2 && $plugin->server->packs->required && count($plugin->tasks)===1,'Append pack, force acceptance, idempotent startup');
 $session=\sergittos\bedwars\BedWarsCore::getInstance()->session;$session->selected=['WearableHat'=>'bed_crown','WearableWing'=>'jetpack','WearableCape'=>'royal_banner'];
 $actors=new \ReflectionProperty(Service::class,'actors');$get=static fn()=>$actors->getValue();
 Service::apply($owner);check(count($get())===1,'Three selections share one actor');$actor=array_values($get())[0];
 check($actor->getNetworkProperties()->values[43]===1,'Team color mapping');
 foreach(['invisible','spectator','swimming','gliding','sleeping'] as $flag){
  $owner->$flag=true;Service::apply($owner);check($actor->viewers===[],'Hidden for '.$flag);$owner->$flag=false;Service::apply($owner);
 }
 $session->spectator=true;Service::apply($owner);check($actor->viewers===[],'Bedwars spectator hidden');$session->spectator=false;
 $listener=new ResourceCosmeticListener();$listener->onDeath(new \pocketmine\event\player\PlayerDeathEvent($owner));check($get()===[] && $actor->isClosed(),'Death cleanup');
 $plugin->server->online=[$owner];for($i=0;$i<10;$i++){ ($plugin->tasks[0]->callback)(); }
 check(count($get())===1,'Periodic respawn/late-session discovery');
 $listener->onQuit(new \pocketmine\event\player\PlayerQuitEvent($owner));check($get()===[],'Quit cleanup');
 Service::apply($owner);$session->selected=[];Service::apply($owner);check($get()===[],'Empty loadout removes actor');
 $session->selected=['WearableHat'=>'bed_crown'];$session->loaded=false;Service::apply($owner);check($get()===[],'No actor before data load');
 $session->loaded=true;Service::apply($owner);Service::shutdown();check(!Service::isReady() && $get()===[],'Shutdown cleanup');
 echo "PASS: actor/pack/service lifecycle doubles; transform, privacy, team color, death, reconnect, pose suppression, idle traffic, no skin/armor access\n";
}
