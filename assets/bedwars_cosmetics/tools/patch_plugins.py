#!/usr/bin/env python3
"""Narrow, reproducible patch of the user's bedwars_v7_fixed(3).zip.
Unrelated SQL, currency, matchmaking, effects, and plugin resources remain byte-for-byte identical.
"""
from pathlib import Path
import json,re,shutil,zipfile,hashlib
ROOT=Path(__file__).resolve().parent.parent
BASE=ROOT/'baseline/bedwars_v7_fixed(3).zip'
if not BASE.exists():BASE=ROOT.parents[1]/'bedwars_v7_fixed(3).zip'
PLUGINS=ROOT/'plugins'
ALIASES={
 'hat_wearable':dict(zip(
 ['arch_crown','cacti_shoots','cowboy','cussy','cussycolor','ember_hat','ent_hat','evoc_hat','foreigner_hat','fungus_hat','gasmask','goldglass','miner_hat','mohair','plag','plaghat','hat_birthday_present','hat_chicken_jockey','hat_boombox','hat_crown_cake','hat_jester','hat_party','hat_propeller'],
 ['bed_crown','golden_laurel','pirate_captain','cat_ears','cat_ears','pumpkin_head','golden_laurel','wizard_star','samurai_kabuto','pumpkin_head','space_helmet','pixel_sunglasses','emerald_helmet','ninja_headband','dragon_skull','dragon_skull','bed_crown','viking_horns','cyber_visor','bed_crown','wizard_star','wizard_star','cyber_visor'])),
 'wing_wearable':dict(zip(
 ['angelkiller','angelwhite','axolotl_plush','blazingelectro','cuddle_bear','davinci','devil','diamond','enderdragon','fairy','firedragon','kraken_tentacles','monarchbutterfly','poisondragon','robotic','soaring_heart'],
 ['void_seraph','sakura_crane','cloud_pet','storm_raven','guardian_drone','mecha_falcon','phoenix_feathers','crystal_prism','frozen_wyvern','celestial_constellation','phoenix_ascend','skull_crown_halo','sakura_crane','emerald_guardian','jetpack','ender_eye_orbit'])),
 'cape_wearable':dict(zip(
 ['aceee','blue_creeper','day_dream','demon','enderman','eva','fire','firework','iron_golem','orangest','pickaxe','red_creeper','snake','turtle'],
 ['golden_emperor','frozen_kingdom','aurora_borealis','wither_storm','void_walker','sakura_storm','lava_flow','thunder_god','emerald_shield','royal_banner','bed_wars_bed','pirate_flag','toxic_waste','pixel_heart']))}


def main():
 with zipfile.ZipFile(BASE) as source:
  original={n:source.read(n) for n in source.namelist() if not n.endswith('/')}
  # Rehydrate baseline sources; then apply only the targeted integration changes.
  for n,data in original.items():
   p=PLUGINS/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
 catalog=json.loads((ROOT/'catalog.json').read_text());catalog['legacy_aliases']=ALIASES
 (ROOT/'migration_aliases.json').write_text(json.dumps(ALIASES,indent=2))
 shim='''<?php
declare(strict_types=1);
namespace sergittos\\bedwars\\cosmetics\\wearable;
use pocketmine\\player\\Player;
use pocketmine\\plugin\\Plugin;
/** Compatibility hooks: V2 never reads, composites, stores, or replaces player skins. */
final class WearableSkinService{
    public static function captureOriginal(Player $player): void{}
    public static function forget(Player $player): void{ ResourceCosmeticService::forget($player); }
    public static function restore(Player $player): void{ ResourceCosmeticService::apply($player); }
    public static function hasGd(): bool{ return true; } // V2 has no GD dependency
    public static function apply(Plugin $plugin, Player $player, ?string $wingKey, ?string $capeKey): void{
        ResourceCosmeticService::apply($player);
    }
}
'''
 render='''<?php
declare(strict_types=1);
namespace sergittos\\bedwars\\cosmetics\\wearable;
use pocketmine\\player\\Player;
/** Resource-only visuals. Keep the established API for lobby/game/session callers. */
final class WearableRenderService{
    public static function applyAll(Player $player): void{ ResourceCosmeticService::apply($player); }
}
'''
 for variant in ['lobby','game']:
  root=PLUGINS/('BedWarsCore-'+variant);src=root/'src/sergittos/bedwars';wear=src/'cosmetics/wearable'
  for p in (ROOT/'integration').glob('*.php'):shutil.copyfile(p,wear/p.name)
  (wear/'WearableSkinService.php').write_text(shim);(wear/'WearableRenderService.php').write_text(render)
  for name in ['WearableAssets.php','HatCatalog.php','HatWearService.php']:
   (wear/name).unlink(missing_ok=True)
  shutil.rmtree(root/'resources/wearables',ignore_errors=True)
  (root/'resources/resource_cosmetics.json').write_text(json.dumps(catalog,indent=1))
  p=src/'BedWarsCore.php';s=p.read_text()
  s=re.sub(r'^.*WearableAssets::install\([^\n]+\n','',s,flags=re.M)
  s=s.replace('protected function onDisable(): void{','protected function onDisable(): void{\n        \\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticService::shutdown();')
  p.write_text(s)
  p=src/'cosmetics/registry/CosmeticsRegistry.php';s=p.read_text()
  # Delete only active WING/CAPE/HAT definitions, not other cosmetic families.
  s=re.sub(r'^\s*\$this->add\(new CosmeticDefinition\([^\n]+CosmeticCategory::(?:HAT|WING|CAPE),[^\n]+\n','\n',s,flags=re.M)
  start=s.find('        // --- Wings')
  if start<0:start=s.find('        // --- Backbling')
  # Drop obsolete wearable-only comments immediately before the final defaults brace.
  marker='    }\n\n    private function add'
  idx=s.index(marker)
  pre=s[:idx]
  last_add=pre.rfind('$this->add(');end=pre.find('\n',last_add)
  pre=pre[:end+1]+'''
        // One shared, versioned catalog supplies both lobby and game servers.
        foreach(\\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticCatalog::all() as $row){
            $this->add(new CosmeticDefinition($row["key"], CosmeticCategory::from($row["category"]),
                $row["name"], (int) $row["price"], CosmeticRarity::from($row["rarity"]), $row["icon"]));
        }
'''
  s=pre+s[idx:]
  s=s.replace('return $this->items[$category->value][$key] ?? null;', '''$key = \\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticCatalog::canonical($category, $key);
        return $key === null ? null : ($this->items[$category->value][$key] ?? null);''')
  p.write_text(s)
  p=src/'cosmetics/registry/CosmeticRarity.php';s=p.read_text().replace('    case LEGENDARY', '    case EPIC = "Epic";\n    case LEGENDARY').replace('            self::LEGENDARY =>','            self::EPIC => "§5",\n            self::LEGENDARY =>');p.write_text(s)
  p=src/'cosmetics/data/PlayerCosmeticsManager.php';s=p.read_text()
  s=s.replace('        return $val;','        return \\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticCatalog::canonical($category, $val);',1)
  s=s.replace('        $val = $key ?? "none";','''        $resolved = \\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticCatalog::canonical($category, $key);
        if($key !== null && $resolved === null){ return; }
        $val = $resolved ?? "none";''')
  s=s.replace('        return $s->hasCosmetic($this->purchaseId($category, $key));','''        foreach(\\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticCatalog::ownedKeys($category, $key) as $candidate){
            if($s->hasCosmetic($this->purchaseId($category, $candidate))){ return true; }
        }
        return false;''')
  s=s.replace('        $s->unlockCosmetic($this->purchaseId($category, $key));','''        $key = \\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticCatalog::canonical($category, $key);
        if($key !== null){ $s->unlockCosmetic($this->purchaseId($category, $key)); }''')
  p.write_text(s)
  p=src/'cosmetics/registry/CosmeticCategory.php';s=p.read_text()
  for name,kind in [('HAT','hat'),('WING','backbling'),('CAPE','cape')]:
   s=re.sub(r'self::'+name+r' => "textures/[^\"]+"',f'self::{name} => "textures/ui/arvan_cosmetics/category_{kind}"',s)
  p.write_text(s)
 # Real gameplay armor is always supplied, regardless of a purely visual hat.
 p=PLUGINS/'BedWarsCore-game/src/sergittos/bedwars/session/settings/GameSettings.php';s=p.read_text()
 s=s.replace('use sergittos\\bedwars\\cosmetics\\wearable\\HatWearService;','use sergittos\\bedwars\\cosmetics\\wearable\\WearableRenderService;')
 start=s.index("        // Team identity doesn't live")
 end=s.index('        $armorInv->setChestplate',start)
 s=s[:start]+'''        // V2 hats are resource overlays: never trade armor protection for a cosmetic.
        $armorInv->setHelmet($this->getLeatherArmor(VanillaItems::LEATHER_CAP()));
'''+s[end:]
 s=s.replace('        if ($hatKey !== null) {\n            HatWearService::equip($player, $hatKey);\n        }','        WearableRenderService::applyAll($player);')
 p.write_text(s)
 for module,file in [('BedWarsLobby','lobby/BedWarsLobby.php'),('BedWarsGame','game/BedWarsGame.php')]:
  src=PLUGINS/module/'src/sergittos/bedwars'
  p=src/file;s=p.read_text().replace('\\sergittos\\bedwars\\cosmetics\\wearable\\HatItemRegistrar::register($this);','\\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticService::initialize($this);')
  if 'protected function onDisable(): void{' in s:
   s=s.replace('protected function onDisable(): void{','protected function onDisable(): void{\n        \\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticService::shutdown();')
  else:
   pos=s.rfind('}');s=s[:pos]+'    protected function onDisable(): void{\n        \\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticService::shutdown();\n    }\n'+s[pos:]
  p.write_text(s)
  shutil.rmtree(src/'cosmetics/wearable',ignore_errors=True)
 p=PLUGINS/'BedWarsLobby/src/sergittos/bedwars/lobby/gui/CosmeticsGui.php';s=p.read_text()
 a=s.index('    /**');b=s.index('    private array $categories',a)
 s=s[:a]+'    /** Hats are visible again. Pets retain their previous visibility setting. */\n'+s[b:]
 s=s.replace('        CosmeticCategory::WING,','        CosmeticCategory::HAT,\n        CosmeticCategory::WING,',1)
 # Re-check readiness and the current authoritative registry at click time, before any debit.
 s=s.replace('            $def = $items[$i];','''            if(\\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticCatalog::isWearable($category)
                && !\\sergittos\\bedwars\\cosmetics\\wearable\\ResourceCosmeticService::isReady()){
                $p->sendMessage("§cWearables are temporarily unavailable. No coins were charged.");
                return;
            }
            $def = CosmeticsRegistry::getInstance()->get($category, $items[$i]->getKey());
            if($def === null){ return; }''')
 p.write_text(s)
 # The baseline omits these optional UI ZIPs. Preserve existing packs, but never crash startup when absent.
 for rel,filename in [('clan/ClanCrestResourcePackInstaller.php','ClanCrests.zip'),('gui/LockerIconResourcePackInstaller.php','LockerIcons.zip'),('profile/ProfileUiResourcePackInstaller.php','BedWarsProfileUI.zip')]:
  p=PLUGINS/'BedWarsLobby/src/sergittos/bedwars/lobby'/rel
  text=p.read_text();namespace=re.search(r'namespace ([^;]+);',text).group(1);cls=p.stem
  template="""<?php
declare(strict_types=1);
namespace @NS@;
use pocketmine\\plugin\\Plugin;
use pocketmine\\resourcepacks\\ZippedResourcePack;
/** Optional legacy UI pack: retain existing art; missing archive must not stop BedWars. */
final class @CLASS@{
    private static bool $installed = false;
    public static function install(Plugin $plugin): void{
        if(self::$installed){ return; }
        $stream = $plugin->getResource("@ZIP@");
        if($stream !== null){ fclose($stream); $plugin->saveResource("@ZIP@", false); }
        $path = $plugin->getDataFolder() . "@ZIP@";
        if(!is_file($path)){
            $plugin->getLogger()->warning("Optional @ZIP@ was not supplied. Existing resource stack is unchanged; retain your original UI pack.");
            return;
        }
        try{
            $pack = new ZippedResourcePack($path);
            $manager = $plugin->getServer()->getResourcePackManager();
            if($manager->getPackById($pack->getPackId()) === null){
                $stack = $manager->getResourceStack(); $stack[] = $pack;
                $manager->setResourceStack($stack);
            }
            $manager->setResourcePacksRequired(true);
            self::$installed = true;
        }catch(\\Throwable $e){ $plugin->getLogger()->warning("Optional UI pack could not be loaded: " . $e->getMessage()); }
    }
}
"""
  p.write_text(template.replace('@NS@',namespace).replace('@CLASS@',cls).replace('@ZIP@',filename))
 # Categorization icons are included in the new pack, independent of missing old hat UI assets.
 from PIL import Image,ImageDraw,ImageFont
 for kind in ['hat','backbling','cape']:
  row=next(v for v in catalog['items'] if v['kind']==kind)
  icon=ROOT/'ArvanCosmeticsV2_RP'/(row['icon']+'.png');target=icon.parent/('category_'+kind+'.png')
  shutil.copyfile(icon,target)
 changes=[]
 for n,old in original.items():
  p=PLUGINS/n
  if not p.exists():changes.append({'path':n,'status':'removed','before':hashlib.sha256(old).hexdigest()})
  elif p.read_bytes()!=old:changes.append({'path':n,'status':'modified','before':hashlib.sha256(old).hexdigest(),'after':hashlib.sha256(p.read_bytes()).hexdigest()})
 for p in PLUGINS.rglob('*'):
  if p.is_file() and str(p.relative_to(PLUGINS)) not in original:changes.append({'path':str(p.relative_to(PLUGINS)),'status':'added','after':hashlib.sha256(p.read_bytes()).hexdigest()})
 (ROOT/'PATCH_REPORT.json').write_text(json.dumps({'baseline':'bedwars_v7_fixed(3).zip','baseline_sha256':hashlib.sha256(BASE.read_bytes()).hexdigest(),'changes':changes},indent=2))
 print('Patched both core variants and both apps. Changes:',len(changes),'(including removed legacy art).')

if __name__=='__main__':main()
