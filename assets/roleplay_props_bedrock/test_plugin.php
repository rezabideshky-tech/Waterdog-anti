<?php
declare(strict_types=1);
/** Isolated command/PHAR tests with PM-shaped stubs. Not a live PocketMine test. */
namespace pocketmine\command{
    interface CommandSender{
        public function hasPermission(string $permission) : bool;
        public function sendMessage(string $message) : void;
    }
    class Command{}
}
namespace pocketmine\nbt\tag{ class CompoundTag{} }
namespace pocketmine\event\entity{
    class EntityDamageEvent{
        public bool $cancelled = false;
        public function cancel() : void{ $this->cancelled = true; }
    }
}
namespace pocketmine\entity{
    use pocketmine\nbt\tag\CompoundTag;
    class EntitySizeInfo{
        public function __construct(public float $height, public float $width){}
    }
    class Location{
        public function __construct(public float $x, public float $y, public float $z, private object $world, public float $yaw = 0, public float $pitch = 0){}
        public function getWorld() : object{ return $this->world; }
        public function distanceSquared(Location $other) : float{
            return ($this->x-$other->x)**2 + ($this->y-$other->y)**2 + ($this->z-$other->z)**2;
        }
    }
    abstract class Entity{
        public static array $created = [];
        public bool $despawn = false;
        public bool $spawned = false;
        public bool $save = false;
        abstract public static function getNetworkTypeId() : string;
        abstract protected function getInitialSizeInfo() : EntitySizeInfo;
        abstract protected function getInitialDragMultiplier() : float;
        abstract protected function getInitialGravity() : float;
        public function __construct(private Location $location){
            self::$created[] = $this;
            $this->initEntity(new CompoundTag());
        }
        protected function initEntity(CompoundTag $nbt) : void{}
        public function setNameTag(string $name) : void{}
        public function setNameTagVisible(bool $visible = true) : void{}
        public function setHasGravity(bool $value = true) : void{}
        public function setNoClientPredictions(bool $value = true) : void{}
        public function setCanSaveWithChunk(bool $value) : void{ $this->save = $value; }
        public function spawnToAll() : void{ $this->spawned = true; }
        public function getPosition() : Location{ return $this->location; }
        public function isFlaggedForDespawn() : bool{ return $this->despawn; }
        public function flagForDespawn() : void{ $this->despawn = true; }
        public function canBeMovedByCurrents() : bool{ return true; }
        public function attack(\pocketmine\event\entity\EntityDamageEvent $source) : void{}
    }
}
namespace pocketmine\player{
    use pocketmine\entity\Location;
    class Player implements \pocketmine\command\CommandSender{
        public array $messages = [];
        public function __construct(public object $world, public bool $permission = true){}
        public function hasPermission(string $permission) : bool{ return $this->permission; }
        public function sendMessage(string $message) : void{ $this->messages[] = $message; }
        public function getLocation() : Location{ return new Location(0,64,0,$this->world,90,40); }
        public function getPosition() : Location{ return $this->getLocation(); }
        public function getWorld() : object{ return $this->world; }
        public function getBoundingBox() : object{
            return new class{public function expandedCopy(float $x, float $y, float $z) : self{return $this;}};
        }
    }
}
namespace pocketmine\plugin{
    class PluginBase{
        public function enableForTest() : void{ $this->onEnable(); }
        public function getLogger() : object{ return new class{public function info(string $message) : void{}}; }
    }
}
namespace customiesdevs\customies\entity{
    class CustomiesEntityFactory{
        private static ?self $instance = null;
        public array $registered = [];
        public static function getInstance() : self{ return self::$instance ??= new self(); }
        public function registerEntity(string $class, string $id) : void{ $this->registered[$id] = $class; }
    }
}
namespace{
    use arvan\props\{Main, PropEntity, PumpProp, AmmoCaseProp};
    use pocketmine\entity\{Entity, Location};
    use pocketmine\player\Player;
    use pocketmine\command\{Command, CommandSender};
    function check(bool $ok, string $message) : void{
        if(!$ok){ throw new RuntimeException($message); }
    }
    foreach(['PropEntity','PumpProp','AmmoCaseProp','Main'] as $class){
        require 'phar://' . __DIR__ . '/ArvanRoleplayProps.phar/src/arvan/props/' . $class . '.php';
    }
    $world = new class{
        public array $entities = [];
        public function getNearbyEntities(object $bounds) : array{ return $this->entities; }
    };
    $main = new Main();
    $main->enableForTest();
    $factory = \customiesdevs\customies\entity\CustomiesEntityFactory::getInstance();
    check(count($factory->registered) === 2, 'Both identifiers must be registered');
    check($factory->registered[PumpProp::NETWORK_ID] === PumpProp::class, 'Pump identifier');
    check($factory->registered[AmmoCaseProp::NETWORK_ID] === AmmoCaseProp::class, 'Ammo identifier');
    $command = new Command();
    $player = new Player($world, false);
    $main->onCommand($player,$command,'arvanprops',['spawn','pump']);
    check(count(Entity::$created) === 0, 'Permission denied must not spawn');
    $console = new class implements CommandSender{
        public function hasPermission(string $permission) : bool{ return true; }
        public function sendMessage(string $message) : void{}
    };
    $main->onCommand($console,$command,'arvanprops',['spawn','pump']);
    check(count(Entity::$created) === 0, 'Console must not spawn');
    $player->permission = true;
    $main->onCommand($player,$command,'arvanprops',['spawn','invalid']);
    check(count(Entity::$created) === 0, 'Invalid type must not spawn');
    foreach(['pump'=>PumpProp::class,'ammo'=>AmmoCaseProp::class] as $type=>$class){
        $main->onCommand($player,$command,'arvanprops',['spawn',$type]);
        $entity = Entity::$created[array_key_last(Entity::$created)];
        check($entity instanceof $class && $entity->spawned && $entity->save, 'Spawn/persistence setup');
        check($entity->getPosition()->pitch === 0.0 && $entity->getPosition()->yaw === 90.0, 'Horizontal placement');
        $damage = new \pocketmine\event\entity\EntityDamageEvent();
        $entity->attack($damage);
        check($damage->cancelled && !$entity->canBeMovedByCurrents(), 'Stationary and invulnerable props');
    }
    $near = new PumpProp(new Location(2,64,0,$world));
    $far = new AmmoCaseProp(new Location(4,64,0,$world));
    $outside = new AmmoCaseProp(new Location(7,64,0,$world));
    $world->entities = [$outside,$far,$near];
    $main->onCommand($player,$command,'arvanprops',['remove']);
    check($near->despawn && !$far->despawn && !$outside->despawn, 'Remove nearest only');
    $main->onCommand($player,$command,'arvanprops',['remove']);
    check($far->despawn && !$outside->despawn, 'Skip already removed props');
    $main->onCommand($player,$command,'arvanprops',['remove']);
    check(!$outside->despawn, 'Enforce six-block radius');
    echo "PASS: PHAR class loading, identifiers, permissions, console guard, spawn, protection and nearest-only removal (isolated stubs)\n";
}
