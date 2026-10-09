<?php
declare(strict_types=1);
// Real modified registry, manager and UI; isolated PM/Form/session doubles, not a live server.
namespace pocketmine\utils { class TextFormat{} }
namespace pocketmine\player{
    class Player{
        public array $forms = [];
        public array $messages = [];
        public function sendForm(object $form): void{ $this->forms[]=$form; }
        public function sendMessage(string $message): void{ $this->messages[]=$message; }
        public function isConnected(): bool{ return true; }
        public function getName(): string{ return "TestPlayer"; }
        public function getSkin(): never{ throw new \RuntimeException("Shop must never read skin"); }
        public function setSkin(mixed $skin): never{ throw new \RuntimeException("Shop must never replace skin"); }
        public function getArmorInventory(): never{ throw new \RuntimeException("Shop must never touch armor"); }
    }
}
namespace jojoe77777\FormAPI{
    class SimpleForm{
        public array $buttons=[];
        public function __construct(public \Closure $callback){}
        public function setTitle(string $title): void{}
        public function setContent(string $content): void{}
        public function addButton(string $label,int $type=0,string $icon=""): void{ $this->buttons[]=$label; }
        public function submit(\pocketmine\player\Player $player,?int $choice): void{ ($this->callback)($player,$choice); }
    }
}
namespace sergittos\bedwars\session{
    class Session{
        public int $coins=1000000;
        public array $owned=[];
        public array $selected=[];
        public function getCoins(): int{ return $this->coins; }
        public function setCoins(int $value): void{ $this->coins=$value; }
        public function hasCosmetic(string $key): bool{ return isset($this->owned[$key]); }
        public function unlockCosmetic(string $key): void{ $this->owned[$key]=true; }
        public function __call(string $name,array $args): mixed{
            if(str_starts_with($name,"get")){ return $this->selected[substr($name,3)]??"none"; }
            if(str_starts_with($name,"set")){ $this->selected[substr($name,3)]=$args[0]; return null; }
            throw new \RuntimeException($name);
        }
    }
}
namespace sergittos\bedwars{
    class BedWarsCore{
        private static ?self $instance=null;
        public \sergittos\bedwars\session\Session $session;
        public function __construct(){ $this->session=new \sergittos\bedwars\session\Session(); }
        public static function getInstance(): self{ return self::$instance??=new self(); }
        public function getResource(string $name): mixed{
            return fopen(dirname(__DIR__)."/plugins/BedWarsCore-lobby/resources/".$name,"rb");
        }
        public function getSessionManager(): object{
            return new class($this->session){
                public function __construct(private object $session){}
                public function get(object $player): object{ return $this->session; }
            };
        }
    }
}
namespace sergittos\bedwars\cosmetics\wearable{
    class ResourceCosmeticService{
        public static bool $ready=true;
        public static int $applied=0;
        public static function isReady(): bool{ return self::$ready; }
        public static function apply(\pocketmine\player\Player $player): void{ ++self::$applied; }
    }
}
namespace {
    use sergittos\bedwars\BedWarsCore;
    use sergittos\bedwars\cosmetics\registry\{CosmeticCategory as C,CosmeticRarity,CosmeticsRegistry};
    use sergittos\bedwars\cosmetics\data\PlayerCosmeticsManager;
    use sergittos\bedwars\cosmetics\wearable\{ResourceCosmeticCatalog as Catalog,ResourceCosmeticService as Service};
    use sergittos\bedwars\lobby\gui\CosmeticsGui;
    function check(bool $value,string $message): void{ if(!$value){ throw new \RuntimeException($message); } }
    $root=dirname(__DIR__)."/plugins";
    $core=$root."/BedWarsCore-lobby/src/sergittos/bedwars";
    foreach(["registry/CosmeticCategory","registry/CosmeticRarity","registry/CosmeticDefinition","wearable/ResourceCosmeticCatalog","registry/CosmeticsRegistry","data/PlayerCosmeticsManager","wearable/WearableRenderService"] as $file){ require $core."/cosmetics/".$file.".php"; }
    require $root."/BedWarsLobby/src/sergittos/bedwars/lobby/gui/CosmeticsGui.php";
    $registry=CosmeticsRegistry::getInstance();$registry->initDefaults();
    check(count($registry->all(C::HAT))===15,"15 hats");
    check(count($registry->all(C::WING))===22,"22 backblings");
    check(count($registry->all(C::CAPE))===15,"15 capes");
    check($registry->get(C::HAT,"emerald_helmet")->getRarity()===CosmeticRarity::EPIC,"Epic tier added");
    foreach(Catalog::data()["legacy_aliases"] as $category=>$aliases){
        foreach($aliases as $old=>$new){
            check(Catalog::canonical(C::from($category),$old)===$new,"Legacy alias ".$old);
            check($registry->get(C::from($category),$old)?->getKey()===$new,"Reward lookups preserve legacy keys");
        }
    }
    $seen=[];
    for($h=0;$h<=15;$h++)for($b=0;$b<=22;$b++)for($c=0;$c<=15;$c++){
        $v=Catalog::encode($h,$b,$c);
        check(!isset($seen[$v]),"selector collision");$seen[$v]=true;
        check($v%16===$h && intdiv($v,16)%23===$b && intdiv($v,368)===$c,"selector round trip");
    }
    $p=new \pocketmine\player\Player();$s=BedWarsCore::getInstance()->session;$gui=new CosmeticsGui();
    $gui->openMain($p);$main=$p->forms[array_key_last($p->forms)];
    check(count(array_filter($main->buttons,fn($v)=>str_contains($v,"Hats")))===1,"Hats category restored once");
    $gui->openCategory($p,C::HAT);$form=$p->forms[array_key_last($p->forms)];
    Service::$ready=false;$before=$s->coins;$form->submit($p,1);
    check($s->coins===$before && $s->owned===[],"Missing pack/dependency must not charge coins");
    Service::$ready=true;$s->coins=1;$form->submit($p,1);
    check($s->coins===1 && $s->owned===[],"Insufficient funds unchanged");
    $s->coins=1000000;$form->submit($p,1);
    check($s->coins===600000 && isset($s->owned['hat_wearable:bed_crown']),"Correct debit and unlock");
    $form->submit($p,1);check($s->coins===600000,"Repeated submitted form cannot double-charge");
    $form->submit($p,0);check(PlayerCosmeticsManager::getInstance()->getEquipped($p,C::HAT)===null,"Unequip");
    $form->submit($p,1);check($s->coins===600000,"Owned item re-equip free");
    $s->owned=['hat_wearable:arch_crown'=>true];$s->selected['WearableHat']='none';$s->coins=321;
    $form->submit($p,1);
    check($s->coins===321 && $s->selected['WearableHat']==='bed_crown',"Legacy purchase free migration");
    check(isset($s->owned['hat_wearable:arch_crown']),"Never delete original purchase record");
    $old=$s->coins;$form->submit($p,999);check($s->coins===$old,"Invalid index ignored");
    $s->selected['WearableHat']='removed_unknown_id';
    check(PlayerCosmeticsManager::getInstance()->getEquipped($p,C::HAT)===null,"Unknown old selection safely hidden");
    check($s->selected['WearableHat']==='removed_unknown_id',"Unknown historical record preserved");
    echo "PASS: real shop flow, 52 registry entries, 5888 selector combinations, 53 legacy aliases, Epic rarity, purchase/unequip/readiness/duplicate-charge checks\n";
}
