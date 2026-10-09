"""Asset/reference/regression tests; no Bedrock renderer or live PM server is involved."""
import base64, collections, hashlib, io, json, math, re, unittest, zipfile
from pathlib import Path
from PIL import Image
ROOT=Path(__file__).resolve().parents[1]
RP=ROOT/'ArvanCosmeticsV2_RP'
CAT=json.loads((ROOT/'catalog.json').read_text())
ITEMS=CAT['items']
BASE=ROOT/'baseline/bedwars_v7_fixed(3).zip'
if not BASE.exists():BASE=ROOT.parents[1]/'bedwars_v7_fixed(3).zip'

def read(p):return json.loads(p.read_text())

class Assets(unittest.TestCase):
 def test_01_exact_catalog_and_rarities(self):
  self.assertEqual(collections.Counter(r['kind'] for r in ITEMS),{'hat':15,'backbling':22,'cape':15})
  self.assertEqual(len({r['key'] for r in ITEMS}),52)
  for kind,total in [('hat',15),('backbling',22),('cape',15)]:
   self.assertEqual([r['selector'] for r in ITEMS if r['kind']==kind],list(range(1,total+1)))
  expected={'bed_crown':'Legendary','emerald_helmet':'Epic','pumpkin_head':'Rare','cyber_visor':'Rare','samurai_kabuto':'Epic','pirate_captain':'Rare','viking_horns':'Rare','wizard_star':'Epic','ninja_headband':'Common','space_helmet':'Epic','cat_ears':'Common','dragon_skull':'Legendary','ice_crown':'Epic','pixel_sunglasses':'Common','golden_laurel':'Rare','phoenix_ascend':'Legendary','celestial_constellation':'Legendary','crystal_prism':'Rare','storm_raven':'Epic','frozen_wyvern':'Epic','mecha_falcon':'Rare','sakura_crane':'Rare','void_seraph':'Legendary','emerald_guardian':'Epic','neon_synthwave':'Rare'}
  for key,rarity in expected.items():self.assertEqual(next(r for r in ITEMS if r['key']==key)['rarity'],rarity)

 def test_02_new_manifest_and_namespace(self):
  m=read(RP/'manifest.json');self.assertEqual(m['header']['uuid'],CAT['pack_uuid'])
  self.assertEqual(m['header']['version'],[2,0,0]);self.assertNotEqual(m['header']['uuid'],m['modules'][0]['uuid'])
  self.assertEqual(len(list((RP/'entity').glob('*.json'))),1)
  d=read(next((RP/'entity').glob('*.json')))['minecraft:client_entity']['description']
  self.assertEqual(d['identifier'],'arvan:cosmetic_overlay_v2');self.assertEqual(d['scripts']['scale'],'0.9375')
  self.assertFalse((RP/'ui').exists());self.assertFalse((RP/'skins').exists())

 def test_03_editable_models_embedded_atlases_and_animation_targets(self):
  files=list((ROOT/'models').rglob('*.bbmodel'));self.assertEqual(len(files),52)
  for p in files:
   m=read(p);self.assertGreater(len(m['elements']),0);self.assertTrue(m['animations'])
   groups={};elements=set()
   def walk(nodes):
    for node in nodes:
     if isinstance(node,str):elements.add(node)
     else:groups[node['uuid']]=node;walk(node.get('children',[]))
   walk(m['outliner'])
   self.assertEqual(elements,{e['uuid'] for e in m['elements']})
   for a in m['animations']:
    self.assertEqual(a['length'],4);self.assertEqual(a['loop'],'loop')
    for uuid,track in a['animators'].items():
     self.assertIn(uuid,groups);self.assertTrue(track['keyframes'])
     for frame in track['keyframes']:self.assertTrue(0<=frame['time']<=4)
   texture=m['textures'][0];png=base64.b64decode(texture['source'].split(',',1)[1])
   with Image.open(io.BytesIO(png)) as im:self.assertEqual(im.size,(512,512))

 def test_04_geometry_uv_bones_and_client_references(self):
  geometries={};animations={};particles={}
  for p in RP.rglob('*.json'):read(p)
  for p in (RP/'models/entity').glob('*.json'):
   for geo in read(p)['minecraft:geometry']:
    ident=geo['description']['identifier'];self.assertNotIn(ident,geometries);geometries[ident]=geo
    names={b['name'] for b in geo['bones']};self.assertEqual(len(names),len(geo['bones']))
    for bone in geo['bones']:
     if 'parent' in bone:self.assertIn(bone['parent'],names)
     for cube in bone.get('cubes',[]):
      self.assertTrue(all(math.isfinite(v) and v>0 for v in cube['size']))
      for face in cube['uv'].values():
       for a,n in zip(face['uv'],face['uv_size']):self.assertTrue(0<=a<=geo['description']['texture_width'] and 0<=a+n<=geo['description']['texture_width'])
  for p in (RP/'animations').glob('*.json'):animations.update(read(p)['animations'])
  for p in (RP/'particles').glob('*.json'):
   e=read(p)['particle_effect'];particles[e['description']['identifier']]=e
   self.assertLessEqual(e['components']['minecraft:emitter_rate_instant']['num_particles'],2)
   self.assertTrue((RP/(e['description']['basic_render_parameters']['texture']+'.png')).exists())
  d=read(next((RP/'entity').glob('*.json')))['minecraft:client_entity']['description']
  for ident in d['geometry'].values():self.assertIn(ident,geometries)
  for ident in d['animations'].values():self.assertIn(ident,animations)
  for path in d['textures'].values():self.assertTrue((RP/(path+'.png')).exists(),path)
  for ident in d['particle_effects'].values():self.assertIn(ident,particles)
  for row in ITEMS:
   geo=geometries['geometry.'+row['model']];bones={b['name']:b for b in geo['bones']}
   a=animations['animation.'+row['model']+'.idle']
   for name in a['bones']:self.assertIn(name,bones)
   locators={name for b in bones.values() for name in b.get('locators',{})}
   for effects in a.get('particle_effects',{}).values():
    for fx in effects:self.assertIn(fx['effect'],d['particle_effects']);self.assertIn(fx['locator'],locators)

 def test_05_controller_selectors_team_frames_and_near_camera_guard(self):
  cs=read(next((RP/'render_controllers').glob('*.json')))['render_controllers']
  d=read(next((RP/'entity').glob('*.json')))['minecraft:client_entity']['description']
  self.assertEqual(len(cs),3)
  for kind,total in [('hat',15),('backbling',22),('cape',15)]:
   c=cs['controller.render.arvan_cos_'+kind]
   self.assertEqual(len(c['arrays']['geometries']['Array.cos_geo']),total+1)
   self.assertEqual(c['part_visibility'],[{'*':'query.distance_from_camera > 2.15'}])
   for values in c['arrays']['textures'].values():
    for alias in values:self.assertIn(alias.split('.',1)[1],d['textures'])
  for key,kind in [('bed_crown','hat'),('royal_banner','cape')]:
   c=cs['controller.render.arvan_cos_'+kind];self.assertEqual(len(c['arrays']['textures']['Array.team_'+key]),16)
   self.assertIn('query.mark_variant',c['textures'][0])
  for key,kind in [('phoenix_ascend','backbling'),('crystal_prism','backbling'),('neon_synthwave','backbling'),('lava_flow','cape'),('cyber_glitch','cape'),('aurora_borealis','cape')]:
   c=cs['controller.render.arvan_cos_'+kind];frames=c['arrays']['textures']['Array.frames_'+key]
   self.assertEqual(len(frames),16)
   hashes={hashlib.sha256((RP/(d['textures'][v.split('.',1)[1]]+'.png')).read_bytes()).hexdigest() for v in frames}
   self.assertGreater(len(hashes),5)

 def test_06_aliases_are_complete_and_valid(self):
  aliases=read(ROOT/'migration_aliases.json');self.assertEqual([len(aliases[c]) for c in ['hat_wearable','wing_wearable','cape_wearable']],[23,16,14])
  for category,values in aliases.items():
   active={r['key'] for r in ITEMS if r['category']==category}
   self.assertTrue(set(values.values())<=active);self.assertFalse(set(values)&active)
  for variant in ['game','lobby']:
   c=read(ROOT/f'plugins/BedWarsCore-{variant}/resources/resource_cosmetics.json')
   self.assertEqual(c['items'],ITEMS);self.assertEqual(c['legacy_aliases'],aliases)

 def test_07_no_skin_or_armor_mutation_in_renderer(self):
  for variant in ['game','lobby']:
   folder=ROOT/f'plugins/BedWarsCore-{variant}/src/sergittos/bedwars/cosmetics/wearable'
   for p in folder.glob('*.php'):
    s=p.read_text();self.assertNotRegex(s,r'(?:getSkin|setSkin|setHelmet|getArmorInventory|new Skin|imagecreatefrompng)\s*\(')
   self.assertFalse((ROOT/f'plugins/BedWarsCore-{variant}/resources/wearables').exists())
   for p in (ROOT/'integration').glob('*.php'):self.assertEqual(p.read_bytes(),(folder/p.name).read_bytes())
  game=(ROOT/'plugins/BedWarsCore-game/src/sergittos/bedwars/session/settings/GameSettings.php').read_text()
  self.assertIn('$armorInv->setHelmet($this->getLeatherArmor(VanillaItems::LEATHER_CAP()));',game)
  self.assertNotIn('HatWearService',game);self.assertNotIn('$hatKey',game)

 def test_08_unrelated_baseline_files_and_catalog_entries_preserved(self):
  if not BASE.exists():self.skipTest('Original baseline ZIP needed for regression comparison')
  patch=read(ROOT/'PATCH_REPORT.json');changes={r['path']:r for r in patch['changes']}
  self.assertEqual(hashlib.sha256(BASE.read_bytes()).hexdigest(),patch['baseline_sha256'])
  unchanged=0
  with zipfile.ZipFile(BASE) as z:
   for n in z.namelist():
    if n.endswith('/'):continue
    old=z.read(n);p=ROOT/'plugins'/n
    if n not in changes:self.assertEqual(old,p.read_bytes(),n);unchanged+=1
    elif changes[n]['status']=='removed':self.assertFalse(p.exists());self.assertIn('/wearable',n)
    else:self.assertEqual(hashlib.sha256(p.read_bytes()).hexdigest(),changes[n]['after'])
   for variant in ['lobby','game']:
    path=f'BedWarsCore-{variant}/src/sergittos/bedwars/cosmetics/registry/CosmeticsRegistry.php'
    old=z.read(path).decode();new=(ROOT/'plugins'/path).read_text()
    for line in old.splitlines():
     if '$this->add(new CosmeticDefinition' in line and not re.search(r'CosmeticCategory::(?:HAT|WING|CAPE),',line):self.assertIn(line.strip(),new)
  with zipfile.ZipFile(BASE) as z:original_count=sum(not n.endswith('/') for n in z.namelist())
  self.assertEqual(unchanged,original_count-sum(r['status'] in ['modified','removed'] for r in changes.values()))
  self.assertGreater(unchanged,600)
  for item in changes.values():
   if item['status']=='modified':self.assertNotRegex(item['path'],r'/(?:database|shop|generator|game/stage|game/team)/')

 def test_09_pack_zip_exact_and_icons_present(self):
  archive=ROOT/'ArvanCosmeticsV2.zip'
  with zipfile.ZipFile(archive) as z:
   self.assertIsNone(z.testzip());self.assertIn('manifest.json',z.namelist())
   disk={str(p.relative_to(RP)):p for p in RP.rglob('*') if p.is_file()}
   self.assertEqual(set(z.namelist()),set(disk))
   for name,p in disk.items():self.assertEqual(z.read(name),p.read_bytes())
   for r in ITEMS:self.assertIn(r['icon']+'.png',z.namelist())
   for kind in ['hat','backbling','cape']:self.assertIn('textures/ui/arvan_cosmetics/category_'+kind+'.png',z.namelist())
  self.assertEqual(archive.read_bytes(),(ROOT/'ArvanCosmeticsV2.mcpack').read_bytes())

if __name__=='__main__':unittest.main(verbosity=2)
