"""Stable versioned IDs: do not reorder selector indices in released packs."""
HATS=[
('bed_crown','Bed Crown','Legendary','red'),('emerald_helmet','Emerald Helmet','Epic','emerald'),
('pumpkin_head','Pumpkin Head','Rare','fire'),('cyber_visor','Cyber Visor','Rare','neon'),
('samurai_kabuto','Samurai Kabuto','Epic','red'),('pirate_captain','Pirate Captain','Rare','gold'),
('viking_horns','Viking Horns','Rare','steel'),('wizard_star','Wizard Star','Epic','void'),
('ninja_headband','Ninja Headband','Common','red'),('space_helmet','Space Helmet','Epic','space'),
('cat_ears','Cat Ears','Common','sakura'),('dragon_skull','Dragon Skull','Legendary','bone'),
('ice_crown','Ice Crown','Epic','ice'),('pixel_sunglasses','Pixel Sunglasses','Common','neon'),
('golden_laurel','Golden Laurel','Rare','gold')]
BACKS=[
('jetpack','Jetpack','Rare','steel'),('space_rocket','Space Rocket','Epic','space'),
('treasure_chest','Treasure Chest','Rare','gold'),('guardian_drone','Guardian Drone','Epic','neon'),
('pumpkin_spirit','Pumpkin Spirit','Rare','fire'),('mini_castle','Mini Castle','Epic','steel'),
('samurai_katana','Samurai Katana','Epic','red'),('cloud_pet','Cloud Pet','Rare','ice'),
('phoenix_feathers','Phoenix Feathers','Epic','fire'),('skull_crown_halo','Skull Crown Halo','Legendary','bone'),
('arcade_cabinet','Arcade Cabinet','Rare','neon'),('ender_eye_orbit','Ender Eye Orbit','Legendary','emerald'),
('phoenix_ascend','Phoenix Ascend','Legendary','fire'),('celestial_constellation','Celestial Constellation','Legendary','space'),
('crystal_prism','Crystal Prism','Rare','prism'),('storm_raven','Storm Raven','Epic','storm'),
('frozen_wyvern','Frozen Wyvern','Epic','ice'),('mecha_falcon','Mecha Falcon','Rare','steel'),
('sakura_crane','Sakura Crane','Rare','sakura'),('void_seraph','Void Seraph','Legendary','void'),
('emerald_guardian','Emerald Guardian','Epic','emerald'),('neon_synthwave','Neon Synthwave','Rare','neon')]
CAPES=[
('bed_wars_bed','Bed Wars Bed','Rare','red'),('emerald_shield','Emerald Shield','Epic','emerald'),
('void_walker','Void Walker','Legendary','void'),('lava_flow','Lava Flow','Epic','fire'),
('frozen_kingdom','Frozen Kingdom','Epic','ice'),('golden_emperor','Golden Emperor','Legendary','gold'),
('pirate_flag','Pirate Flag','Rare','bone'),('cyber_glitch','Cyber Glitch','Epic','neon'),
('sakura_storm','Sakura Storm','Rare','sakura'),('thunder_god','Thunder God','Epic','storm'),
('pixel_heart','Pixel Heart','Common','sakura'),('wither_storm','Wither Storm','Legendary','void'),
('toxic_waste','Toxic Waste','Rare','toxic'),('royal_banner','Royal Banner','Epic','red'),
('aurora_borealis','Aurora Borealis','Epic','prism')]
THEMES={
 'red':('#b9324c','#ff7182','#ffc875'), 'emerald':('#178d70','#75ffd4','#ffe59b'),
 'fire':('#e15c2e','#ffd37d','#ffecaf'), 'neon':('#262b62','#ff66cc','#52e4f0'),
 'gold':('#a8782f','#ffdd7e','#fff0ba'), 'steel':('#61758e','#cbdce3','#6dfff0'),
 'void':('#493367','#b373ff','#f17fff'), 'space':('#263f72','#8eabff','#ffe49b'),
 'sakura':('#d978a2','#ffcfdf','#fff1ec'), 'bone':('#b1a388','#eee3c3','#ff9a3d'),
 'ice':('#429bcc','#a9f4ff','#f1fcff'), 'prism':('#6488de','#d2a5ff','#ff9edd'),
 'storm':('#303751','#637fff','#fff18a'), 'toxic':('#497138','#b2fc5f','#edff9a')}
PRICE={'hat':{'Common':80000,'Rare':180000,'Epic':280000,'Legendary':400000},
       'backbling':{'Common':18000,'Rare':28000,'Epic':34000,'Legendary':40000},
       'cape':{'Common':14000,'Rare':22000,'Epic':27000,'Legendary':30000}}
CATEGORY={'hat':'hat_wearable','backbling':'wing_wearable','cape':'cape_wearable'}
PACK_UUID='848cf7bf-dc90-47f3-8259-2607c81252ec'
MODULE_UUID='68b26e1f-de6e-4de6-ae32-d5d78619d822'

def entries():
 out=[]
 for kind,rows in [('hat',HATS),('backbling',BACKS),('cape',CAPES)]:
  for i,(key,name,rarity,theme) in enumerate(rows,1):
   out.append(dict(key=key,name=name,rarity=rarity,theme=theme,category=CATEGORY[kind],kind=kind,
                   selector=i,price=PRICE[kind][rarity],model='arvan_cos_'+kind+'_'+key,
                   icon='textures/ui/arvan_cosmetics/'+kind+'_'+key))
 return out
