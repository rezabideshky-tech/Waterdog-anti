#!/usr/bin/env python3
"""Four compact, animated Bedwars lobby island dioramas. No downloaded art.
Run: pip install -r requirements.txt; python generate.py
Use --gifs for optional animated previews (slower).
"""
from pathlib import Path
import argparse
import json
import math
import shutil
import uuid
import zipfile
import numpy as np
from PIL import Image, ImageDraw
import engine as e

HERE=Path(__file__).resolve().parent
MODES=[
    ('solo','SOLO',1,'#369cff','#a2e3ff','THE LAST DEFENDER'),
    ('doubles','DOUBLES',2,'#38c887','#a7f9cd','BUILD & DEFEND'),
    ('triples','TRIPLES',3,'#ab6bff','#e2c4ff','THREE ROLES / ONE TEAM'),
    ('squads','SQUADS',4,'#efb93d','#fff0a9','THE FOUR PLAYER FORTRESS'),
]


def atlas(mode,title,n,color,light):
    a=e.Atlas()
    materials=[('stone','#55687b',6),('stone_dark','#354655',5),('stone_light','#7e8e99',4),
        ('dirt','#6c4c39',6),('grass','#75a84d',5),('grass_dark','#597f3b',4),
        ('wood','#977045',5),('wood_dark','#604734',4),('planks','#bf965a',4),
        ('endstone','#d6d6ab',4),('white','#e9f2eb',2),('team',color,4),('accent',light,1),
        ('dark','#1c2a3d',3),('steel','#a1bbc6',3),('gold','#f5cc72',2),
        ('diamond','#61e9ec',2),('diamond_dark','#238fbd',2),('skin','#d9a77f',2),
        ('hair','#3d2c29',3),('obsidian','#322b4e',4),('boots','#334656',3),('black','#121c2a',1)]
    for name,col,grain in materials:a.material(name,col,grain)
    # Material details: voxel wool, stone blocks, planks and ore are original textures.
    for name in ['team','white']:
        x,y,X,Y=a.regions[name]
        base_color=tuple(bytes.fromhex(color.lstrip('#')))
        highlight=tuple(bytes.fromhex(light.lstrip('#')))
        stitch=tuple(round(v*.8+w*.2) for v,w in zip(base_color,highlight)) if name=='team' else '#dce6e1'
        for j in range(0,32,4):
            for i in range(0,32,4):
                if (i+j)%8==0:a.d.rectangle((x+i,y+j,x+i+2,y+j+1),fill=stitch)
    x,y,X,Y=a.regions['planks']
    for j in [7,15,23]:a.d.line((x,y+j,X-1,y+j),fill='#795631',width=2)
    for i,j in [(8,0),(22,8),(13,16),(26,24)]:a.d.line((x+i,y+j,x+i,y+j+6),fill='#997242')
    a.panel('ore',[0,96,32,128],'#526576')
    for x,y in [(3,3),(19,5),(10,16),(22,24)]:
        a.d.rectangle((x,96+y,x+6,96+y+4),fill=color)
        a.d.rectangle((x,96+y,x+3,96+y+1),fill=light)
    a.panel('face',[40,96,72,128],'#d9a77f')
    a.d.rectangle((40,96,71,102),fill='#3d2c29')
    a.d.rectangle((40,101,44,108),fill='#3d2c29')
    a.d.rectangle((67,101,71,108),fill='#3d2c29')
    for x in [46,60]:
        a.d.rectangle((x,108,x+6,111),fill='#f2f6ee')
        a.d.rectangle((x+3,108,x+5,111),fill='#203249')
        a.d.line((x,105,x+6,105),fill='#614132')
    a.d.rectangle((54,115,58,118),fill='#b87a58')
    a.d.line((51,122,61,122),fill='#794f3e',width=2)
    a.panel('shirt',[80,96,112,128],color)
    a.d.rectangle((91,96,100,101),fill='#d9a77f')
    a.d.line((95,103,95,125),fill=light,width=2)
    a.d.rectangle((82,113,87,118),fill='#e9f2eb')
    a.d.rectangle((80,123,111,127),fill='#25354a')
    a.d.rectangle((93,123,99,127),fill='#f5cc72')
    a.panel('mode_label',[0,144,320,224],'#142536')
    a.centered_text([6,146,314,194],title,39,light)
    a.centered_text([8,195,312,221],f'BEDWARS  /  {n} PLAYER'+('S' if n>1 else ''),15,'#dce9ea',padding=3)
    a.panel('flag',[328,144,408,240],color)
    a.d.rectangle((332,148,403,235),outline=light,width=3)
    a.centered_text([332,152,404,230],str(n),58,'#ffffff')
    a.panel('brand',[0,240,320,278],'#142536')
    a.centered_text([4,242,316,275],'ARVAN GAMING',25,'#c4d7df',padding=3)
    a.panel('shield',[416,144,480,224],color)
    a.d.rectangle((419,147,476,220),outline='#f5cc72',width=4)
    a.d.rectangle((444,158,451,207),fill='#e9f2eb')
    a.d.rectangle((431,177,463,185),fill='#e9f2eb')
    return a


def fighter(m,index,pos,role,scale=1,yaw=0):
    x,y,z=pos
    prefix=f'fighter_{index}'
    m.group(prefix,'island',origin=pos,rotation=(-10 if role=='builder' else 0,yaw,0))
    def pt(v):return [round(pos[i]+v[i]*scale,5) for i in range(3)]
    def cube(name,a,b,mat,group=None,faces=None):
        m.cube(prefix+' '+name,pt(a),pt(b),mat,group or prefix,faces)
    # Short, square Minecraft-like characters, not realistic humanoids.
    for side in [-1,1]:
        cx=side*.68
        cube('trouser',(cx-.54,.6,-.58),(cx+.54,3.4,.58),'dark')
        cube('boot',(cx-.61,0,-.91),(cx+.61,.9,.65),'boots')
        cube('boot trim',(cx-.6,.75,-.93),(cx+.6,1.04,.67),'steel')
    cube('team torso',(-1.28,3.1,-.72),(1.28,6.25,.72),'team',faces={'north':'shirt'})
    cube('neck',(-.55,6.2,-.48),(.55,6.65,.48),'skin')
    head=prefix+'_head';m.group(head,prefix,origin=pt((0,7.8,0)))
    cube('head',(-1.5,6.6,-1.45),(1.5,9.6,1.45),'skin',head,{'north':'face'})
    cube('hair top',(-1.56,9.25,-1.5),(1.56,9.8,1.5),'hair',head)
    cube('hair back',(-1.55,7.5,1.42),(1.55,9.45,1.62),'hair',head)
    cube('team headband',(-1.59,8.93,-1.52),(1.59,9.27,1.63),'team',head)
    cube('headband metal crest',(-.34,8.89,-1.61),(.34,9.38,-1.52),'gold',head)
    for side in [-1,1]:
        arm=prefix+('_right' if side<0 else '_left')
        origin=pt((side*1.82,5.95,0))
        # Builder leans their tool arm toward the bridge; other roles hold weapons upright.
        angle=-38 if role=='builder' and side<0 else (-12 if side<0 else 6)
        m.group(arm,prefix,origin=origin,rotation=(angle,0,-side*6))
        cx=side*1.82
        cube('sleeve',(cx-.52,4.65,-.56),(cx+.52,6.17,.56),'team',arm)
        cube('pauldron',(cx-.65,5.8,-.65),(cx+.65,6.35,.65),'steel',arm)
        cube('forearm',(cx-.46,3.4,-.51),(cx+.46,4.65,.51),'skin',arm)
        cube('wrist wrap',(cx-.49,3.55,-.54),(cx+.49,3.94,.54),'dark',arm)
        if side<0:
            if role=='builder':
                cube('held wool block',(-3.15,2.8,-2.2),(-.65,5.3,.3),'team',arm)
            elif role=='archer':
                for k,(bx,by) in enumerate([(-2.0,2.2),(-2.55,2.8),(-2.95,3.5),(-3.05,4.3),(-2.95,5.1),(-2.55,5.8),(-2.0,6.4)]):
                    cube('bow segment '+str(k),(bx-.23,by,-.9),(bx+.23,by+.7,-.45),'wood',arm)
                cube('bow string',(-1.87,2.3,-.78),(-1.77,7,-.64),'white',arm)
                cube('arrow shaft',(-3.5,4.48,-.74),(-.8,4.6,-.6),'wood_dark',arm)
            else:
                cube('sword grip',(-2.04,3.3,-.83),(-1.59,4.7,-.38),'wood_dark',arm)
                cube('sword crossguard',(-2.78,4.55,-.99),(-.86,4.92,-.22),'gold',arm)
                cube('sword blade rim',(-2.22,4.9,-.84),(-1.43,8.15,-.39),'diamond_dark',arm)
                cube('sword blade shine',(-2.01,5.02,-.91),(-1.63,8.18,-.83),'diamond',arm)
                cube('sword point',(-2.06,8.15,-.82),(-1.58,8.67,-.4),'diamond',arm)
        elif role in ['defender','captain']:
            cube('shield rim',(1.05,2.65,-1.25),(3.48,5.8,-.73),'gold',arm)
            cube('shield face',(1.18,2.77,-1.36),(3.34,5.67,-1.26),'team',arm,{'north':'shield'})
            cube('shield tip',(1.55,2.15,-1.19),(2.98,2.67,-.78),'gold',arm)
        elif role=='solo':
            cube('wool supply',(1.25,2.9,-1.6),(3.1,4.75,.25),'team',arm)
    # A little cape makes the silhouettes legible from the back as well.
    cube('cape',(-1.28,2.7,.78),(1.28,6.1,1.04),'team')
    return prefix


def bed(m,x,z):
    m.group('team_bed','island')
    c=m.cube
    for dx in [0,4.1]:
        for dz in [0,6.7]:
            c('bed foot',(x+dx,14.6,z+dz),(x+dx+.8,16.1,z+dz+.8),'wood_dark','team_bed')
    c('bed oak frame',(x-.18,15.4,z-.2),(x+5.08,16.05,z+7.9),'wood','team_bed')
    c('bed white mattress',(x,16.05,z),(x+4.9,16.55,z+7.7),'white','team_bed')
    c('bed team blanket',(x-.04,16.5,z-.04),(x+4.94,17.18,z+5.9),'team','team_bed')
    c('bed folded blanket edge',(x-.05,17.17,z+5.05),(x+4.95,17.39,z+5.55),'accent','team_bed')
    c('bed pillow',(x+.4,16.56,z+6),(x+4.5,17.32,z+7.35),'white','team_bed')
    c('bed headboard',(x-.3,15.9,z+7.7),(x+5.2,18.05,z+8.15),'wood','team_bed')
    c('bed headboard inset',(x+.1,16.5,z+7.62),(x+4.8,17.65,z+7.71),'planks','team_bed')


def chest(m,x,z):
    c=m.cube
    c('team resource chest',(x,14.7,z),(x+3,16.8,z+2.3),'wood','island')
    c('chest lid',(x-.1,16.7,z-.1),(x+3.1,17.35,z+2.4),'planks','island')
    for dx in [.35,2.3]:c('chest metal band',(x+dx,14.8,z-.08),(x+dx+.35,17.37,z+2.46),'dark','island')
    c('chest latch',(x+1.2,15.8,z-.23),(x+1.8,16.8,z-.09),'gold','island')


def make_model(mode,title,n,color,light,subtitle):
    m=e.Model('arvan_bw_'+mode,atlas(mode,title,n,color,light))
    m.title=title+' / '+subtitle
    m.subtitle=f'{n} PLAYER'+('S' if n>1 else '')+' PER TEAM   /   ONE BED   /   ANIMATED ISLAND'
    m.mode=mode;m.player_count=n
    m.group('island')
    c=m.cube
    # Tapered voxel terrain. Top surfaces are a 4x4 voxel grid, trimmed per mode.
    cells=[]
    for iz in range(5):
        for ix in range(6):
            if n==3:
                # Broad back and a distinct pointed/triangular front.
                if (iz==0 and ix not in [2,3]) or (iz==1 and ix in [0,5]):continue
            elif n<4 and (ix,iz) in [(0,0),(5,0),(0,4),(5,4)]:continue
            cells.append((ix,iz))
    for ix,iz in cells:
        x=-12+ix*4;z=-10+iz*4
        low=8.1+((ix*7+iz*3)%4)*.5
        c('stone island voxel',(x,low,z),(x+4,12.4,z+4),'stone' if (ix+iz)%2 else 'stone_light','island')
        c('soil top',(x,12.4,z),(x+4,14.35,z+4),'dirt','island')
        c('grass top',(x,14.35,z),(x+4,14.75,z+4),'grass','island')
    c('lower rock shelf',(-8.7,5.1,-6.8),(8.7,9.1,6.9),'stone_dark','island')
    c('lower rock ledge',(-6.2,2.8,-4.9),(6,6.7,4.9),'stone','island')
    c('island taper',(-3.8,.5,-2.9),(3.6,4.1,3),'stone_dark','island')
    c('pointed lower voxel',(-1.7,-1,-1.4),(1.6,1.8,1.4),'stone','island')
    for x,y,z in [(-10,7,-5),(7,6,3),(-5,3,3),(4,3,-4)]:
        c('protruding crag',(x,y,z),(x+3.4,y+3,z+3),'stone_light','island')
    for x,y,z in [(-7,8,-7.05),(5,6,-7.06),(-9,10,-6.1),(8.75,8,2)]:
        c('team ore vein',(x,y,z),(x+2,y+1.9,z+.25),'stone','island',{'north':'ore','east':'ore'})
    # Front identity plaque: unobstructed below the bridge.
    c('plaque metal surround',(-8.2,3,-10.9),(8.2,7.5,-10.25),'gold','island')
    c('mode plaque',(-7.9,3.9,-11.01),(7.9,7.2,-10.91),'dark','island',{'north':'mode_label'})
    c('brand plaque',(-7.9,3.1,-11.02),(7.9,3.94,-10.92),'dark','island',{'north':'brand'})
    # One short attack bridge per model, made from readable miniature wool blocks.
    bx=1 if n==1 else -1
    for i in range(3):
        z=-12.8-i*2.6
        group='bridge_tip' if i==2 else 'island'
        if group=='bridge_tip':m.group(group,'island',origin=(bx+1.25,14.1,z+1.25))
        c('wool attack bridge',(bx,12.95,z),(bx+2.5,15.45,z+2.5),'team',group)
    # The shared team bed is intentionally visible through the cut-away defenses.
    bedx,bedz=(1.6,1.3) if n<3 else (-2.5,.8)
    bed(m,bedx,bedz)
    defense='team' if n==1 else ('planks' if n==2 else 'endstone')
    for dx,dz in [(-2.4,2.7),(5.2,2.7),(-2.4,5.4)]:
        c('cutaway bed defense',(bedx+dx,14.75,bedz+dz),(bedx+dx+2.3,17.05,bedz+dz+2.3),defense,'island')
    if n>=3:
        c('rear bed reinforcement',(bedx+5.2,14.75,bedz+5.4),(bedx+7.5,17.05,bedz+7.7),'team','island')
    # Compact generator island fixture, not a functional resource generator.
    gx,gz=(-8,4.7) if n<3 else (-9,5.5)
    c('iron generator platform',(gx,14.75,gz),(gx+3.4,15.15,gz+3.4),'dark','island')
    c('generator luminous rim',(gx+.2,15.15,gz+.2),(gx+3.2,15.36,gz+3.2),'accent','island')
    m.group('resource_ingot','island',origin=(gx+1.7,17,gz+1.7))
    c('floating resource ingot',(gx+.65,16.55,gz+1.1),(gx+2.75,17.05,gz+2.3),'gold' if n==4 else 'steel','resource_ingot')
    c('ingot highlight',(gx+.82,17.05,gz+1.2),(gx+2.58,17.25,gz+2.15),'white','resource_ingot')
    # Flag stays at the back: the cloth is separately rigged.
    fx,fz=(9,6.5) if n<3 else (8.9,8.2)
    c('flagpole socket',(fx-.65,14.75,fz-.65),(fx+.65,15.7,fz+.65),'stone_light','island')
    c('flagpole',(fx-.19,15.7,fz-.19),(fx+.19,28.2,fz+.19),'wood_dark','island')
    c('flag finial',(fx-.4,28.1,fz-.4),(fx+.4,28.9,fz+.4),'gold','island')
    m.group('team_flag','island',origin=(fx,25,fz))
    c('player count banner',(fx-5.4,22.8,fz-.18),(fx-.15,27.8,fz+.12),'team','team_flag',{'north':'flag','south':'flag'})
    # A few grass tufts and flowers, only around the outer rim.
    for x,z in [(-10,-2),(9,-5),(-6,8)]:
        c('grass tuft',(x,14.74,z),(x+.35,16.1,z+.35),'grass_dark','island')
        c('grass tuft short',(x+.55,14.74,z+.1),(x+.9,15.65,z+.45),'grass','island')
    if n==1:
        fighter(m,1,(-4,14.75,-4),'solo',1.05,-12)
    elif n==2:
        fighter(m,1,(-3,14.75,-6.6),'builder',.95,-12)
        fighter(m,2,(7.2,14.75,-1.9),'defender',.95,12)
        chest(m,-9,1)
    elif n==3:
        fighter(m,1,(-1,15.45,-10),'attacker',.85,-8)
        fighter(m,2,(8,14.75,-3.6),'builder',.87,14)
        fighter(m,3,(-7.3,14.75,.1),'defender',.85,-15)
        chest(m,7,2)
        # Diamond outcrop fits within the shared 1.5-block width envelope.
        c('diamond side ledge',(-11.5,11.8,-4.2),(-7.3,13.5,-.1),'stone_dark','island')
        c('diamond pad',(-11.4,13.5,-4.1),(-7.4,14,-.2),'endstone','island')
        m.group('diamond_relic','island',origin=(-9.5,16,-2.1))
        c('diamond relic',(-10.25,15,-2.8),(-8.75,17,-1.4),'diamond','diamond_relic',rotation=(0,0,45),origin=(-9.5,16,-2.1))
    else:
        # Low four-corner fort walls; leave front and central bed visible.
        for x,z in [(-11,-8),(8,-8),(-11,7),(8,7)]:
            c('corner fort block',(x,14.75,z),(x+2.5,16.15,z+2.5),'endstone','island')
            c('corner team cap',(x-.07,16.15,z-.07),(x+2.57,16.65,z+2.57),'team','island')
        fighter(m,1,(-4.2,14.75,-6.1),'captain',.81,-12)
        fighter(m,2,(5.6,14.75,-6.1),'builder',.81,10)
        fighter(m,3,(-8,14.75,2.5),'defender',.8,-16)
        c('rear archer tower',(6,14.75,2.4),(10.4,18.2,6.8),'endstone','island')
        c('tower coping',(5.8,18.2,2.2),(10.6,18.65,7),'team','island')
        fighter(m,4,(8.1,18.65,4.4),'archer',.74,5)
        chest(m,-2, -3.8)
    # Animation: restrained buoyancy, banner movement and independent role gestures.
    tracks={
        'island':{'position':[(0,[0,0,0]),(1,[0,.27,0]),(2,[0,.4,0]),(3,[0,.27,0]),(4,[0,0,0])]},
        'team_flag':{'rotation':[(0,[0,-5,0]),(1,[0,7,0]),(2,[0,-5,0]),(3,[0,7,0]),(4,[0,-5,0])]},
        'resource_ingot':{'rotation':[(0,[0,0,0]),(1,[0,90,0]),(2,[0,180,0]),(3,[0,270,0]),(4,[0,360,0])],
                          'position':[(0,[0,0,0]),(2,[0,.6,0]),(4,[0,0,0])]},
        'bridge_tip':{'scale':[(0,[1,1,1]),(2.8,[1,1,1]),(3.2,[.12,.12,.12]),(3.55,[.12,.12,.12]),(4,[1,1,1])]}}
    if 'diamond_relic' in m.groups:
        tracks['diamond_relic']={'rotation':[(0,[0,0,0]),(4,[0,360,0])]}
    for i in range(1,n+1):
        tracks[f'fighter_{i}_right']={'rotation':[(0,[0,0,0]),(1,[-8-i*2,0,0]),(2,[0,0,0]),(3,[4,0,0]),(4,[0,0,0])]}
        tracks[f'fighter_{i}_head']={'rotation':[(0,[0,-5,0]),(2,[0,5,0]),(4,[0,-5,0])]}
    m.animations.append(dict(name='lobby_idle',length=4,loop='loop',tracks=tracks))
    return m


def validate(m,folder):
    bb=json.loads((folder/(m.name+'.bbmodel')).read_text())
    refs=[]
    def walk(node):
        for child in node['children']:
            if isinstance(child,str):refs.append(child)
            else:walk(child)
    walk(bb['outliner'][0])
    assert len(refs)==len(set(refs))==len(m.cubes)
    assert {v['uuid'] for v in bb['elements']}==set(refs)
    assert sum(g.startswith('fighter_') and g.count('_')==1 for g in m.groups)==m.player_count
    assert sum(c['name']=='bed team blanket' for c in m.cubes)==1
    for c in m.cubes:
        assert all(c['b'][i]>c['a'][i] for i in range(3))
        assert c['group'] in m.groups
    for a in m.animations:
        for bone,channels in a['tracks'].items():
            assert bone in m.groups
            for channel,keys in channels.items():
                assert [t for t,v in keys]==sorted(set(t for t,v in keys))
                assert keys[0][0]==0 and keys[-1][0]==a['length']
                if channel!='rotation':np.testing.assert_allclose(keys[0][1],keys[-1][1])
    # Every animation bone has the same start/end transform, including 360 degree loops.
    for c in m.cubes:
        np.testing.assert_allclose(e.world_vertices(m,c,e.animation_pose(m,['lobby_idle'],0)),
                                  e.world_vertices(m,c,e.animation_pose(m,['lobby_idle'],4-1e-9)),atol=1e-6)
    print(f'PASS {m.mode}: {len(m.cubes)} cubes, {m.player_count} characters, one bed, seamless 4s loop')


def zipfiles(path,entries):
    with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for source,name in entries:z.write(source,str(name))
    with zipfile.ZipFile(path) as z:assert z.testzip() is None


def resources(models):
    rp=HERE/'ArvanBedwarsIslands_RP'
    def js(path,obj):
        p=rp/path;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(obj,indent=2))
    ns=uuid.UUID('d5c66ef0-1f37-407f-b70c-d5a6d75149e4')
    js('manifest.json',{'format_version':2,'header':{'name':'Arvan Gaming | Bedwars Islands',
        'description':'Solo / Doubles / Triples / Squads animated lobby NPCs','uuid':str(uuid.uuid5(ns,'header')),
        'version':[1,0,0],'min_engine_version':[1,20,0]},'modules':[{'type':'resources','uuid':str(uuid.uuid5(ns,'resources')),'version':[1,0,0]}]})
    for m in models:
        for sub,ext in [('models/entity','.geo.json'),('animations','.animation.json'),('textures/entity','.png')]:
            dest=rp/sub/(m.name+ext);dest.parent.mkdir(parents=True,exist_ok=True)
            shutil.copyfile(e.OUT/m.name/(m.name+ext),dest)
        js(f'entity/{m.name}.entity.json',{'format_version':'1.10.0','minecraft:client_entity':{'description':{
            'identifier':'arvan:bw_'+m.mode+'_island','materials':{'default':'entity_alphatest'},
            'textures':{'default':'textures/entity/'+m.name},'geometry':{'default':'geometry.'+m.name},
            'animations':{'idle':'animation.'+m.name+'.lobby_idle'},'scripts':{'animate':['idle']},
            'render_controllers':['controller.render.arvan_bw_islands']}}})
    js('render_controllers/islands.render_controllers.json',{'format_version':'1.8.0','render_controllers':{
        'controller.render.arvan_bw_islands':{'geometry':'Geometry.default','materials':[{'*':'Material.default'}],'textures':['Texture.default']}}})
    Image.open(HERE/'PREVIEW.jpg').resize((256,256)).save(rp/'pack_icon.png')
    zipfiles(HERE/'ArvanBedwarsIslands_RP.zip',[(p,p.relative_to(rp)) for p in sorted(rp.rglob('*')) if p.is_file()])
    shutil.copyfile(HERE/'ArvanBedwarsIslands_RP.zip',HERE/'ArvanBedwarsIslands.mcpack')


def bundle():
    files=[]
    for root in [HERE/'models',HERE/'plugin']:
        for p in sorted(root.rglob('*')):
            if p.is_file():files.append((p,p.relative_to(HERE)))
    for name in ['PREVIEW.jpg','README_FA.md','engine.py','generate.py','requirements.txt','ArvanBedwarsIslands_RP.zip',
                 'ArvanBedwarsIslands.mcpack','resource_packs.example.yml','build_phar.php','test_assets.py']:
        if (HERE/name).exists():files.append((HERE/name,name))
    if (HERE/'ArvanBedwarsIslands.phar').exists():files.append((HERE/'ArvanBedwarsIslands.phar','plugins/ArvanBedwarsIslands.phar'))
    zipfiles(HERE/'Arvan_Bedwars_Islands.zip',files)
    print('ZIP verified:',HERE/'Arvan_Bedwars_Islands.zip')


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--gifs',action='store_true');parser.add_argument('--bundle-only',action='store_true')
    args=parser.parse_args()
    if args.bundle_only:bundle();return
    e.OUT.mkdir(parents=True,exist_ok=True)
    models=[make_model(*v) for v in MODES]
    sheet=Image.new('RGB',(2200,2240),'#14222c')
    for i,m in enumerate(models):
        folder=m.export();validate(m,folder)
        image=e.render(m,folder)
        sheet.paste(image,((i%2)*1100,(i//2)*1120))
        if args.gifs:
            frames=[e.render(m,folder,e.animation_pose(m,['lobby_idle'],j*.25)).resize((440,448),Image.Resampling.LANCZOS) for j in range(16)]
            frames[0].save(folder/'animation_preview.gif',save_all=True,append_images=frames[1:],duration=250,loop=0,disposal=2)
    sheet.save(HERE/'PREVIEW.jpg',quality=94)
    resources(models);bundle()


if __name__=='__main__':main()
