#!/bin/bash
# Rebuild server bundles with the PROTECTED (encrypted + obfuscated + watermarked) resource pack.
set -e
cd "$(dirname "$0")"
python3 protect_pack.py ArvanLobby_RP ArvanLobby_RP_protected.zip "$(cat ArvanLobby_RP_protected.zip.key 2>/dev/null || true)" 2>/dev/null
SEC='
--- RESOURCE PACK PROTECTION ---
The pack is ENCRYPTED. Keep resource_packs/ArvanLobby_RP.zip.key next to the zip (PocketMine reads it
automatically). Never share the .key file. plugins/ArvanPackGuard bans accounts/IPs that download the
packs without joining (pack stealers). Admin: /packguard status | list | unban <xuid|ip|name>
Check a stolen texture for the hidden ArvanGaming watermark:  python3 protect_pack.py --check texture.png'
for B in ArvanLobby_Server ArvanRolePlay_Server; do
  rm -rf bundle; mkdir bundle; (cd bundle && unzip -q ../$B.zip)
  cp ArvanLobby_RP_protected.zip bundle/resource_packs/ArvanLobby_RP.zip
  cp ArvanLobby_RP_protected.zip.key bundle/resource_packs/ArvanLobby_RP.zip.key
  rm -rf bundle/plugins/ArvanPackGuard; cp -r plugin/ArvanPackGuard bundle/plugins/
  [ $B = ArvanLobby_Server ] && { rm -rf bundle/plugins/BedWarsBattlePass; cp -r ../battlepass/plugin/BedWarsBattlePass bundle/plugins/; }
  python3 - "$SEC" <<'PY'
import sys; p='bundle/README.txt'; s=open(p).read().split('\n--- RESOURCE PACK PROTECTION ---')[0].rstrip('\n')
open(p,'w').write(s+'\n'+sys.argv[1].lstrip('\n')+'\n')
PY
  rm $B.zip; (cd bundle && zip -qr ../$B.zip .)
done
rm -rf bundle
echo bundles rebuilt
