#!/usr/bin/env python3
"""Repack resources and produce a deployable ZIP; never touches server/plugin_data files."""
from pathlib import Path
import argparse,hashlib,json,shutil,zipfile
ROOT=Path(__file__).resolve().parent.parent
RP=ROOT/'ArvanCosmeticsV2_RP'
BASE=ROOT/'baseline/bedwars_v7_fixed(3).zip'
if not BASE.exists():BASE=ROOT.parents[1]/'bedwars_v7_fixed(3).zip'

def pack_resources():
 with zipfile.ZipFile(ROOT/'ArvanCosmeticsV2.zip','w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for p in sorted(RP.rglob('*')):
   if p.is_file():z.write(p,p.relative_to(RP))
 shutil.copyfile(ROOT/'ArvanCosmeticsV2.zip',ROOT/'ArvanCosmeticsV2.mcpack')
 print('Resource ZIP/MCPACK rebuilt, including all category icons.')

def pack_plugins():
 folder=ROOT/'plugin_zips';folder.mkdir(exist_ok=True)
 for name in ['BedWarsCore-lobby','BedWarsCore-game','BedWarsLobby','BedWarsGame']:
  source=ROOT/'plugins'/name
  plugin_name='BedWarsCore' if name.startswith('BedWarsCore-') else name
  files={str(Path(plugin_name)/p.relative_to(source)):p for p in source.rglob('*') if p.is_file()}
  target=folder/(name+'.zip')
  with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
   for entry,p in sorted(files.items()):z.write(p,entry)
  with zipfile.ZipFile(target) as z:
   assert z.testzip() is None
   assert set(z.namelist())==set(files)
   assert plugin_name+'/plugin.yml' in z.namelist()
   assert not any(n.lower().endswith('.phar') for n in z.namelist())
   for entry,p in files.items():assert z.read(entry)==p.read_bytes()
  print('PASS: source ZIP matches plugin files:',target.name)
 with zipfile.ZipFile(ROOT/'Arvan_Bedwars_Plugins_Source.zip','w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for p in sorted(folder.glob('*.zip')):z.write(p,p.name)
  z.write(ROOT/'README_FA.md','README_FA.md')

def bundle():
 target=ROOT/'Arvan_Bedwars_Cosmetics_V2_Full.zip';entries={}
 for name in ['README_FA.md','CATALOG.md','TEST_REPORT.md','PATCH_REPORT.json','migration_aliases.json','catalog.json']:
  entries[name]=ROOT/name
 for kind,core,app in [('lobby','BedWarsCore-lobby','BedWarsLobby'),('game','BedWarsCore-game','BedWarsGame')]:
  entries[f'deploy/{kind}/plugins/BedWarsCore.zip']=ROOT/'plugin_zips'/(core+'.zip')
  entries[f'deploy/{kind}/plugins/{app}.zip']=ROOT/'plugin_zips'/(app+'.zip')
 entries['shared/resource_packs/ArvanCosmeticsV2.zip']=ROOT/'ArvanCosmeticsV2.zip'
 entries['shared/ArvanCosmeticsV2.mcpack']=ROOT/'ArvanCosmeticsV2.mcpack'
 for folder in ['previews','models','plugins','integration','tools','tests','ArvanCosmeticsV2_RP']:
  for p in (ROOT/folder).rglob('*'):
   if p.is_file() and '__pycache__' not in p.parts and p.suffix!='.pyc':
    name=str(p.relative_to(ROOT));entries[name if folder=='previews' else 'editable/'+name]=p
 for name in ['catalog.json','migration_aliases.json','PATCH_REPORT.json','README_FA.md','CATALOG.md','TEST_REPORT.md']:
  entries['editable/'+name]=ROOT/name
 entries['editable/baseline/bedwars_v7_fixed(3).zip']=BASE
 checks=[]
 with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for name,p in sorted(entries.items()):
   data=p.read_bytes();z.writestr(name,data);checks.append(hashlib.sha256(data).hexdigest()+'  '+name)
  z.writestr('SHA256SUMS.txt','\n'.join(checks)+'\n')
 with zipfile.ZipFile(target) as z:
  assert z.testzip() is None
  assert len(z.namelist())==len(entries)+1
  for name,p in entries.items():assert z.read(name)==p.read_bytes(),name
  for kind in ['lobby','game']:
   assert z.read(f'deploy/{kind}/plugins/BedWarsCore.zip')==(ROOT/'plugin_zips'/f'BedWarsCore-{kind}.zip').read_bytes()
  assert not any(n.lower().endswith('.phar') for n in z.namelist())
 outputs=['Arvan_Bedwars_Cosmetics_V2_Full.zip','Arvan_Bedwars_Plugins_Source.zip','ArvanCosmeticsV2.zip','ArvanCosmeticsV2.mcpack'] + ['plugin_zips/'+p.name for p in sorted((ROOT/'plugin_zips').glob('*.zip'))]
 (ROOT/'DOWNLOAD_SHA256SUMS.txt').write_text('\n'.join(hashlib.sha256((ROOT/name).read_bytes()).hexdigest()+'  '+name for name in outputs)+'\n')
 print(f'PASS: {target.name}: {len(entries)+1} verified entries; {target.stat().st_size/1024/1024:.2f} MiB')

if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--pack-only',action='store_true');args=parser.parse_args()
 pack_resources()
 if not args.pack_only:
  pack_plugins()
  bundle()
