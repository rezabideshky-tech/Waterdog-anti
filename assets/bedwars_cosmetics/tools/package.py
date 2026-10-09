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

def bundle():
 target=ROOT/'Arvan_Bedwars_Cosmetics_V2_Full.zip';entries={}
 for name in ['README_FA.md','CATALOG.md','TEST_REPORT.md','PATCH_REPORT.json','migration_aliases.json','catalog.json']:
  entries[name]=ROOT/name
 for kind,core,app in [('lobby','BedWarsCore-lobby','BedWarsLobby'),('game','BedWarsCore-game','BedWarsGame')]:
  entries[f'deploy/{kind}/plugins/BedWarsCore.phar']=ROOT/'compiled'/(core+'.phar')
  entries[f'deploy/{kind}/plugins/{app}.phar']=ROOT/'compiled'/(app+'.phar')
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
   assert z.read(f'deploy/{kind}/plugins/BedWarsCore.phar')==(ROOT/'compiled'/f'BedWarsCore-{kind}.phar').read_bytes()
 outputs=['Arvan_Bedwars_Cosmetics_V2_Full.zip','ArvanCosmeticsV2.zip','ArvanCosmeticsV2.mcpack']
 (ROOT/'DOWNLOAD_SHA256SUMS.txt').write_text('\n'.join(hashlib.sha256((ROOT/name).read_bytes()).hexdigest()+'  '+name for name in outputs)+'\n')
 print(f'PASS: {target.name}: {len(entries)+1} verified entries; {target.stat().st_size/1024/1024:.2f} MiB')

if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--pack-only',action='store_true');args=parser.parse_args()
 pack_resources()
 if not args.pack_only:bundle()
