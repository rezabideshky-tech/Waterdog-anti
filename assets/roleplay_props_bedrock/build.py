#!/usr/bin/env python3
"""Build a Bedrock resource pack and a PocketMine-MP 5 deployment bundle.
Requires Pillow. First compile the plugin: php -d phar.readonly=0 build_phar.php
Does not rebuild or alter the source Blockbench models.
"""
from pathlib import Path
import hashlib
import json
import shutil
import uuid
import zipfile
from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).resolve().parent
SOURCE = HERE.parent / 'roleplay_props'
RP = HERE / 'ArvanRoleplayProps_RP'
NS = uuid.UUID('08b8b5c2-6882-4449-9464-b145b9496e89')
VERSION = [1, 0, 0]
PROPS = [
    ('nova_digital_pump', 'arvan:rp_digital_pump', ['idle', 'nozzle_demo']),
    ('field_open_ammo_case', 'arvan:rp_ammo_case', ['open_close']),
]


def write_json(path, obj):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(obj, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')


def archive_files(target, entries):
    with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for path, name in entries:
            # Stable ZIP timestamps for reproducible resource archives.
            info = zipfile.ZipInfo(str(name).replace('\\', '/'), (2026, 10, 8, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            z.writestr(info, path.read_bytes())
    with zipfile.ZipFile(target) as z:
        assert z.testzip() is None


def validate_pack():
    manifest = json.loads((RP / 'manifest.json').read_text())
    assert manifest['header']['uuid'] != manifest['modules'][0]['uuid']
    assert manifest['modules'][0]['type'] == 'resources'
    geometries, animations = {}, {}
    for path in (RP / 'models/entity').glob('*.json'):
        data = json.loads(path.read_text())
        for geo in data['minecraft:geometry']:
            geometries[geo['description']['identifier']] = geo
    for path in (RP / 'animations').glob('*.json'):
        animations.update(json.loads(path.read_text())['animations'])
    controllers = json.loads((RP / 'render_controllers/arvan_props.render_controllers.json').read_text())['render_controllers']
    for model, identifier, active in PROPS:
        desc = json.loads((RP / f'entity/{model}.entity.json').read_text())['minecraft:client_entity']['description']
        assert desc['identifier'] == identifier
        geo = geometries[desc['geometry']['default']]
        bones = {b['name'] for b in geo['bones']}
        for b in geo['bones']:
            assert b.get('parent', 'root') in bones
        image_path = RP / (desc['textures']['default'] + '.png')
        with Image.open(image_path) as im:
            assert im.size == (geo['description']['texture_width'], geo['description']['texture_height'])
        assert desc['scripts']['animate'] == active
        for alias in active:
            assert alias in desc['animations']
        for name in desc['animations'].values():
            a = animations[name]
            assert set(a['bones']) <= bones
            for channels in a['bones'].values():
                for keys in channels.values():
                    assert min(map(float, keys)) >= 0
                    assert max(map(float, keys)) <= a['animation_length']
        assert all(c in controllers for c in desc['render_controllers'])
        cls = 'PumpProp' if model.startswith('nova') else 'AmmoCaseProp'
        php = (HERE / f'plugin/ArvanRoleplayProps/src/arvan/props/{cls}.php').read_text()
        assert f'"{identifier}"' in php
        # Resources must be exactly the already validated model outputs.
        assert image_path.read_bytes() == (SOURCE / model / (model + '.png')).read_bytes()
        assert (RP / 'models/entity' / (model + '.geo.json')).read_bytes() == (SOURCE / model / (model + '.geo.json')).read_bytes()
        assert (RP / 'animations' / (model + '.animation.json')).read_bytes() == (SOURCE / model / (model + '.animation.json')).read_bytes()
    print('PASS: pack manifest, textures, geometry, animation bones, controllers and plugin identifiers')


def main():
    plugin = HERE / 'ArvanRoleplayProps.phar'
    if not plugin.is_file():
        raise SystemExit('First run: php -d phar.readonly=0 build_phar.php')
    RP.mkdir(exist_ok=True)
    write_json(RP / 'manifest.json', {
        'format_version': 2,
        'header': {'name': 'Arvan Gaming | Animated Roleplay Props',
                   'description': 'Digital pump and hinged ammo case. Requires server-side custom entities.',
                   'uuid': str(uuid.uuid5(NS, 'pack-header')), 'version': VERSION,
                   'min_engine_version': [1, 20, 0]},
        'modules': [{'type': 'resources', 'uuid': str(uuid.uuid5(NS, 'pack-resources')), 'version': VERSION}],
        'metadata': {'authors': ['ArvanGaming']}
    })
    for model, identifier, active in PROPS:
        src = SOURCE / model
        for sub, suffix in [('models/entity', '.geo.json'), ('animations', '.animation.json'), ('textures/entity', '.png')]:
            dest = RP / sub / (model + suffix)
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(src / (model + suffix), dest)
        animations = json.loads((src / (model + '.animation.json')).read_text())['animations']
        write_json(RP / f'entity/{model}.entity.json', {
            'format_version': '1.10.0',
            'minecraft:client_entity': {'description': {
                'identifier': identifier,
                'materials': {'default': 'entity_alphatest'},
                'textures': {'default': 'textures/entity/' + model},
                'geometry': {'default': 'geometry.' + model},
                'animations': {name.rsplit('.', 1)[1]: name for name in animations},
                'scripts': {'animate': active},
                'render_controllers': ['controller.render.arvan_props']
            }}
        })
    write_json(RP / 'render_controllers/arvan_props.render_controllers.json', {
        'format_version': '1.8.0', 'render_controllers': {'controller.render.arvan_props': {
            'geometry': 'Geometry.default', 'materials': [{'*': 'Material.default'}], 'textures': ['Texture.default']}}
    })
    write_json(RP / 'texts/languages.json', ['en_US'])
    (RP / 'texts/en_US.lang').write_text(
        'pack.name=Arvan Gaming | Animated Roleplay Props\n'
        'pack.description=Digital fuel pump and hinged ammo case\n'
        'entity.arvan:rp_digital_pump.name=Arvan Gaming Digital Pump\n'
        'entity.arvan:rp_ammo_case.name=Arvan Gaming Ammo Case\n', encoding='utf-8')
    icon = Image.new('RGB', (256, 256), '#13252d')
    d = ImageDraw.Draw(icon)
    d.rounded_rectangle((8, 8, 247, 247), radius=18, outline='#64efcf', width=5)
    d.text((29, 43), 'ARVAN', font=ImageFont.load_default(size=43), fill='#e8f7ef')
    d.text((29, 94), 'GAMING', font=ImageFont.load_default(size=37), fill='#64efcf')
    d.line((28, 146, 225, 146), fill='#64efcf', width=3)
    d.text((28, 168), 'ROLEPLAY', font=ImageFont.load_default(size=27), fill='#ffffff')
    d.text((28, 205), 'ANIMATED PROPS', font=ImageFont.load_default(size=18), fill='#a8c8cb')
    icon.save(RP / 'pack_icon.png')
    validate_pack()
    rp_zip = HERE / 'ArvanRoleplayProps_RP.zip'
    entries = [(p, p.relative_to(RP)) for p in sorted(RP.rglob('*')) if p.is_file()]
    archive_files(rp_zip, entries)
    shutil.copyfile(rp_zip, HERE / 'ArvanRoleplayProps.mcpack')
    with zipfile.ZipFile(rp_zip) as z:
        assert 'manifest.json' in z.namelist(), 'Manifest must be at ZIP root, not in a parent folder'
    entries = [(rp_zip, 'resource_packs/' + rp_zip.name),
               (plugin, 'plugins/' + plugin.name),
               (HERE / 'resource_packs.example.yml', 'resource_packs/resource_packs.example.yml'),
               (HERE / 'README_FA.md', 'README_FA.md'),
               (SOURCE / 'PREVIEW.jpg', 'PREVIEW.jpg')]
    for p in sorted((HERE / 'plugin/ArvanRoleplayProps').rglob('*')):
        if p.is_file(): entries.append((p, 'plugin-source/ArvanRoleplayProps/' + str(p.relative_to(HERE / 'plugin/ArvanRoleplayProps'))))
    archive_files(HERE / 'ArvanRoleplayProps_PocketMine.zip', entries)
    names = ['ArvanRoleplayProps_RP.zip', 'ArvanRoleplayProps.mcpack', 'ArvanRoleplayProps.phar', 'ArvanRoleplayProps_PocketMine.zip']
    (HERE / 'SHA256SUMS.txt').write_text(''.join(hashlib.sha256((HERE / n).read_bytes()).hexdigest()+'  '+n+'\n' for n in names))
    for name in names:
        print(f'Built: {name} ({(HERE / name).stat().st_size:,} bytes)')


if __name__ == '__main__':
    main()
