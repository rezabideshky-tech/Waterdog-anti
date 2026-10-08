#!/usr/bin/env python3
"""Deterministic Blockbench RP props, textures, Bedrock geometry and actual-geometry previews.
Run: python -m pip install -r requirements.txt && python generate.py
No external art, meshes, or runtime dependencies are needed to open the models.
"""
from pathlib import Path
import base64
import io
import json
import math
import uuid
import zipfile
import numpy as np
from PIL import Image, ImageDraw, ImageFont

OUT = Path(__file__).resolve().parent
NS = uuid.UUID('ab70143d-f43d-45ef-8e66-240fe59e0984')
FACES = ('north', 'south', 'east', 'west', 'up', 'down')


def uid(s):
    return str(uuid.uuid5(NS, s))


def font(size):
    return ImageFont.load_default(size=size)


class Atlas:
    def __init__(self):
        self.img = Image.new('RGB', (512, 512), '#20272c')
        self.d = ImageDraw.Draw(self.img)
        self.regions = {}
        self.count = 0

    def material(self, name, color, grain=3):
        x = (self.count % 16) * 32
        y = (self.count // 16) * 32
        self.count += 1
        self.regions[name] = [x, y, x+32, y+32]
        c = tuple(bytes.fromhex(color.lstrip('#')))
        for j in range(32):
            for i in range(32):
                n = ((i*37+j*71) % (grain*2+1)) - grain
                self.d.point((x+i, y+j), fill=tuple(max(0, min(255, v+n)) for v in c))

    def panel(self, name, box, background):
        self.regions[name] = box
        self.d.rectangle((box[0], box[1], box[2]-1, box[3]-1), fill=background)
        return self.d

    def text(self, xy, text, size=14, fill='#ffffff'):
        self.d.text(xy, text, font=font(size), fill=fill)


class Model:
    def __init__(self, name, atlas):
        self.name, self.atlas = name, atlas
        self.groups = {}
        self.cubes = []
        self.group('root')

    def group(self, name, parent='root', origin=(0, 0, 0), rotation=(0, 0, 0)):
        self.groups[name] = dict(name=name, parent=None if name == 'root' else parent,
                                 origin=list(origin), rotation=list(rotation), uuid=uid(self.name+'/'+name))

    def cube(self, name, a, b, mat, group='root', faces=None, rotation=(0, 0, 0), origin=(0, 0, 0)):
        assert all(b[i] > a[i] for i in range(3)), name
        mats = dict.fromkeys(FACES, mat)
        mats.update(faces or {})
        self.cubes.append(dict(name=name, group=group, a=list(a), b=list(b), mats=mats,
                               rotation=list(rotation), origin=list(origin)))

    def rod(self, name, a, b, width, mat, group='root'):
        # Cylindrical-looking voxel hose, approximated by short square-cross-section segments.
        v = np.array(b)-np.array(a)
        mid = (np.array(a)+np.array(b))/2
        length = float(np.linalg.norm(v))
        assert abs(v[2]) < 1e-8
        rot = [0, 0, math.degrees(math.atan2(-v[0], v[1]))]
        self.cube(name, mid+[-width/2, -length/2-width*.15, -width/2],
                  mid+[width/2, length/2+width*.15, width/2], mat, group, rotation=rot, origin=mid)

    def export(self):
        folder = OUT/self.name
        folder.mkdir(exist_ok=True)
        self.atlas.img.save(folder/(self.name+'.png'))
        data = io.BytesIO()
        self.atlas.img.save(data, format='PNG')
        elements, children = [], {k: [] for k in self.groups}
        for i, c in enumerate(self.cubes):
            id_ = uid(self.name+'/cube/'+str(i))
            children[c['group']].append(id_)
            elements.append(dict(name=c['name'], type='cube', uuid=id_, box_uv=False,
                rescale=False, locked=False, render_order='default', allow_mirror_modeling=True,
                **{'from': c['a'], 'to': c['b']}, origin=c['origin'], rotation=c['rotation'],
                faces={k: {'uv': self.atlas.regions[v], 'texture': 0} for k,v in c['mats'].items()}))
        def outline(name):
            g = self.groups[name]
            return {k: g[k] for k in ('name', 'origin', 'rotation', 'uuid')} | dict(
                export=True, isOpen=name=='root', visibility=True,
                children=children[name]+[outline(n) for n,v in self.groups.items() if v['parent']==name])
        bb = dict(meta={'format_version': '4.10', 'model_format': 'bedrock', 'box_uv': False},
            name=self.name, model_identifier=self.name, visible_box=[6, 6, 2],
            resolution={'width':512, 'height':512}, elements=elements, outliner=[outline('root')],
            textures=[dict(path='', name=self.name+'.png', id='0', uuid=uid(self.name+'/texture'),
                width=512, height=512, uv_width=512, uv_height=512, render_mode='default',
                visible=True, internal=True, saved=True, source='data:image/png;base64,'+base64.b64encode(data.getvalue()).decode())])
        if 'lid_hinge' in self.groups:
            # The rest pose is open. The animation adds -105 degrees, closing the lid.
            bb['animations'] = [dict(uuid=uid(self.name+'/close'), name='animation.'+self.name+'.close',
                loop='hold', override=False, length=1, snapping=24,
                animators={self.groups['lid_hinge']['uuid']: dict(name='lid_hinge', type='bone', keyframes=[
                    dict(channel='rotation', data_points=[dict(x=str(x),y='0',z='0')],
                         uuid=uid(self.name+'/key/'+str(t)), time=t, color=-1, interpolation='linear')
                    for t,x in [(0,0),(1,-105)]])})]
        (folder/(self.name+'.bbmodel')).write_text(json.dumps(bb, indent=2))
        bones=[]
        mirror=lambda a: [-a[0],a[1],a[2]]
        for name,g in self.groups.items():
            bone=dict(name=name, pivot=mirror(g['origin']), rotation=[-g['rotation'][0],-g['rotation'][1],g['rotation'][2]], cubes=[])
            if g['parent']: bone['parent']=g['parent']
            for c in self.cubes:
                if c['group']!=name: continue
                uvs={}
                for face,mat in c['mats'].items():
                    x,y,x1,y1=self.atlas.regions[mat]
                    # Bedrock up/down faces use reversed UV sizes (Blockbench exporter convention).
                    uvs[face] = dict(uv=[x1,y1] if face in ('up','down') else [x,y],
                        uv_size=[x-x1,y-y1] if face in ('up','down') else [x1-x,y1-y])
                bone['cubes'].append(dict(origin=[-c['b'][0],c['a'][1],c['a'][2]],
                    size=[round(c['b'][i]-c['a'][i],6) for i in range(3)], pivot=mirror(c['origin']),
                    rotation=[-c['rotation'][0],-c['rotation'][1],c['rotation'][2]], uv=uvs))
            bones.append(bone)
        geo={'format_version':'1.12.0','minecraft:geometry':[dict(description=dict(identifier='geometry.'+self.name,
            texture_width=512, texture_height=512, visible_bounds_width=6, visible_bounds_height=6,
            visible_bounds_offset=[0,2,0]), bones=bones)]}
        (folder/(self.name+'.geo.json')).write_text(json.dumps(geo,indent=2))
        if 'lid_hinge' in self.groups:
            anim={'format_version':'1.8.0','animations':{'animation.'+self.name+'.close':{
                'loop':'hold_on_last_frame','animation_length':1,'bones':{'lid_hinge':{
                    'rotation':{'0.0':[0,0,0],'1.0':[105,0,0]}}}}}}
            (folder/(self.name+'.animation.json')).write_text(json.dumps(anim,indent=2))
        return folder


def palette():
    a=Atlas()
    for n,c,g in [('white','#e1e9e8',2),('edge','#b6c7ca',3),('dark','#202a31',2),
        ('black','#0e151c',2),('rubber','#141b20',2),('metal','#819497',5),
        ('cyan','#44f4dc',0),('green','#36b883',2),('blue','#458fe0',2),('red','#dc5b48',2),
        ('gold','#ebba58',2),('olive','#505d45',4),('olive_light','#788267',4),
        ('olive_dark','#323f34',3),('foam','#242c2c',5),('steel','#455257',3),
        ('sand','#b6a77f',3),('orange','#f3a34d',0)]:
        a.material(n,c,g)
    return a


def pump():
    a=palette()
    d=a.panel('touch',[0,96,208,288],'#081f2b')
    a.text((12,107),'NOVA / TOUCH',18,'#6bf4dd')
    d.line((12,134,194,134),fill='#284550',width=2)
    a.text((12,144),'SELECT FUEL',12,'#a2b8c5')
    for i,(name,col) in enumerate([('95  PREMIUM','#36b883'),('98  SUPER','#458fe0'),('D   DIESEL','#ebba58')]):
        y=167+i*28
        d.rounded_rectangle((12,y,194,y+23),radius=3,fill='#173744',outline=col)
        a.text((22,y+4),name,12,col)
    a.text((12,258),'READY  /  TAP TO START',11,'#7df6dd')
    a.panel('totals',[216,96,496,176],'#091820')
    a.text((228,104),'TOTAL              LITRES',13,'#8bb2b4')
    a.text((227,125),'048.90     32.60',28,'#63f7db')
    a.panel('brand',[216,184,496,236],'#192b33')
    a.text((233,191),'NOVA',32,'#eef8f7'); a.text((354,207),'ENERGY / 04',14,'#67edcf')
    a.panel('payment',[216,244,336,364],'#15242c')
    a.text((224,253),'PAY / NFC',16,'#f3f8f5')
    for r in [14,22,30]:
        d.arc((260-r,307-r,260+r,307+r),-55,55,fill='#69edd3',width=3)
    a.text((224,344),'CONTACTLESS',10,'#a9babe')
    a.panel('service',[344,244,504,324],'#c4d0ce')
    a.text((356,253),'NOVA  //  04',17,'#26383e')
    a.text((356,279),'24H SELF SERVICE',11,'#33494e')
    a.text((356,299),'NO SMOKING',11,'#9e433b')
    a.panel('fuel',[0,304,208,350],'#12242b')
    a.text((12,314),'95   /   98   /   D',20,'#ecf6eb')
    m=Model('nova_digital_pump',a)
    for g in ['cabinet','interface','lighting','service_panel','hoses','nozzles']:
        m.group(g)
    c=m.cube
    c('foot plinth',(-10,0,-6.5),(10,1.2,6.5),'dark','cabinet')
    c('brushed base reveal',(-9.7,1.2,-6.1),(9.7,1.7,6.1),'metal','cabinet')
    c('lower fuel cabinet',(-8.5,1.7,-5),(8.5,18,5),'white','cabinet')
    c('graphite shoulder',(-9,17.5,-5.4),(9,19.5,5.4),'dark','cabinet')
    c('upper console',(-8.5,19,-5),(8.5,34.8,5),'white','cabinet')
    c('top canopy lip',(-9,34.8,-5.7),(9,36.4,5.7),'dark','cabinet')
    c('top silver edge',(-8.6,36.4,-5.2),(8.6,36.8,5.2),'edge','cabinet')
    c('front glass panel',(-7.8,20,-5.35),(7.8,31.5,-5.05),'black','interface')
    c('large touch display',(-7.1,21,-5.43),(1.2,29.7,-5.36),'black','interface',{'north':'touch'})
    c('totals LCD',(-7.2,31.7,-5.4),(7.2,34.3,-5.1),'black','interface',{'north':'totals'})
    c('payment terminal',(2,23.4,-5.85),(7,29.8,-5.38),'dark','interface',{'north':'payment'})
    c('card slot silver frame',(2.5,22.5,-5.94),(6.5,23.2,-5.5),'metal','interface')
    c('card insertion slit',(2.8,22.75,-6.02),(6.2,22.97,-5.95),'black','interface')
    for x in range(3):
        for y in range(2):
            c('payment key %s %s'%(x,y),(2.9+x*1.05,20.8+y*.65,-5.8),(3.55+x*1.05,21.2+y*.65,-5.42),'edge','interface')
    c('status light',(6.5,21,-5.82),(6.9,22,-5.5),'green','lighting')
    c('front identity plate',(-7,13.5,-5.2),(7,16.3,-5.02),'dark','service_panel',{'north':'brand'})
    c('service door seam',(-6.9,3,-5.13),(6.9,12.6,-5.01),'dark','service_panel')
    c('service door',(-6.65,3.25,-5.22),(6.65,12.35,-5.14),'white','service_panel')
    c('service label',(-4.4,7.8,-5.27),(4.4,11.6,-5.23),'edge','service_panel',{'north':'service'})
    c('keyhole',(5.5,7.8,-5.31),(5.95,8.5,-5.23),'metal','service_panel')
    for i in range(5):
        c('lower ventilation grille %d'%i,(-5.2,4+i*.48,-5.3),(4.5,4.18+i*.48,-5.23),'dark','service_panel')
    for x in [-8,7.7]:
        c('vertical cyan edge',(x,19.9,-5.46),(x+.3,31.3,-5.22),'cyan','lighting')
    c('canopy LED lightbar',(-8.5,35,-5.82),(8.5,35.34,-5.69),'cyan','lighting')
    c('waist LED lightbar',(-8.6,18.2,-5.52),(8.6,18.52,-5.41),'cyan','lighting')
    for x in [-7.6,7.2]:
        for y in [2.3,17]:
            c('cabinet fastener',(x,y,-5.17),(x+.32,y+.32,-5.04),'metal','service_panel')
    # Rear access and side vents are modeled, not just painted on the front.
    c('rear access panel',(-7,3,5.01),(7,30,5.15),'edge','service_panel')
    for y in range(8):
        c('rear cooling fin %d'%y,(-5,22+y*.75,5.15),(5,22.25+y*.75,5.24),'dark','service_panel')
    for side in [-1,1]:
        for j in range(2):
            x=side*(10.5+j*3.1)
            z=-2.9+j*3.8
            g=('left' if side<0 else 'right')+'_nozzle_'+str(j+1)
            m.group(g,'nozzles',origin=(x,20,z))
            color=['green','blue','gold','red'][(0 if side<0 else 2)+j]
            c('nozzle dock',(x-.9,17.4,z-.9),(x+.9,23.4,z+.65),'dark',g)
            c('fuel grade color badge',(x-.82,22.1,z-1.03),(x+.82,23.1,z-.91),color,g)
            c('nozzle body',(x-.65,19.8,z-1.65),(x+.65,21.6,z-.8),color,g)
            c('handle grip',(x-.65,17.6,z-1.5),(x-.18,20.25,z-.84),color,g)
            c('trigger guard',(x+.5,17.6,z-1.5),(x+.82,20.4,z-.84),'metal',g)
            c('trigger guard base',(x-.4,17.4,z-1.5),(x+.82,17.8,z-.84),'metal',g)
            c('trigger',(x+.04,18.2,z-1.45),(x+.2,19.7,z-.9),'black',g)
            c('metal spout',(x-.23,21.5,z-1.6),(x+.23,23.8,z-1.1),'metal',g)
            c('spout outlet',(x-.23,23.4,z-1.1),(x+.23,23.8,z+.8),'metal',g)
            # A drooping U loop per nozzle with 18 articulated cuboid segments.
            points=[(x,17.5,z-1.1),(x,11,z-1.1)]
            center=x+side*1.6
            for i in range(13):
                theta=math.pi+i*math.pi/12
                points.append((center+side*1.6*math.cos(theta),7.2+3.3*math.sin(theta),z-1.1))
            points.extend([(x+side*3.2,15,z-1.1),(x+side*2.9,24.5,z-1.1),(side*8.6,26,z-1.1)])
            for k,(p,q) in enumerate(zip(points,points[1:])):
                m.rod(g+' flexible hose %02d'%k,p,q,.42,'rubber','hoses')
            c('hose connector',(x-.34,16.8,z-1.44),(x+.34,17.6,z-.76),'metal',g)
    return m


def case():
    a=palette()
    d=a.panel('case_label',[0,96,256,168],'#424f3d')
    a.text((12,103),'FIELD / 07',28,'#e2d9ac')
    a.text((12,140),'RP EQUIPMENT  -  SECURE STORAGE',11,'#c2caaa')
    a.panel('lid_label',[0,184,256,288],'#323f34')
    a.text((16,197),'FIELD EQUIPMENT',24,'#d3dabb')
    a.text((16,233),'LOADOUT  /  02',19,'#9da98a')
    a.text((16,264),'INVENTORY VERIFIED',12,'#c5cdaa')
    a.panel('warning',[272,96,496,144],'#e2b75b')
    a.text((284,106),'CAUTION / RP PROP',20,'#323629')
    m=Model('field_open_ammo_case',a)
    for g in ['case_shell','hardware','foam_insert','rifle_prop','pistol_prop','spare_magazines']:
        m.group(g)
    m.group('lid_hinge',origin=(0,7,8),rotation=(105,0,0))
    c=m.cube
    c('reinforced case bottom',(-15,0,-8),(15,1.4,8),'olive_dark','case_shell')
    c('front wall',(-15,1.4,-8),(15,6.8,-6.85),'olive','case_shell')
    c('rear wall',(-15,1.4,6.85),(15,7,8),'olive','case_shell')
    c('left wall',(-15,1.4,-6.85),(-13.85,7,6.85),'olive','case_shell')
    c('right wall',(13.85,1.4,-6.85),(15,7,6.85),'olive','case_shell')
    for z in [-8,6.9]:
        c('top sealing rim',(-15,6.8,z),(15,7.15,z+1.1),'olive_light','case_shell')
    for x in [-15,13.9]:
        c('side sealing rim',(x,6.8,-6.9),(x+1.1,7.15,6.9),'olive_light','case_shell')
    for x in [-15.25,13.95]:
        for z in [-8.2,6.9]:
            c('steel corner protector',(x,.2,z),(x+1.3,5.8,z+1.3),'dark','hardware')
            c('corner rivet',(x+.35,4.8,z-.07),(x+.77,5.22,z),'metal','hardware')
    for x in [-11,-7,7,11]:
        c('front structural rib',(x,1.5,-8.3),(x+.45,5.9,-8.01),'olive_light','case_shell')
    c('front equipment label',(-5.8,2.2,-8.14),(5.8,5.5,-8.01),'olive','case_shell',{'north':'case_label'})
    for x in [-9.5,8]:
        c('latch backplate',(x,4,-8.52),(x+1.5,7.55,-8.25),'steel','hardware')
        c('latch lever',(x+.23,4.3,-8.8),(x+1.27,6.4,-8.53),'metal','hardware')
        c('latch catch',(x+.35,6.5,-8.82),(x+1.15,7.1,-8.54),'sand','hardware')
    for s in [-1,1]:
        x=s*15.35
        for z in [-3,2.3]:
            c('side carry handle foot',(x-.3,3,z),(x+.3,4.6,z+.7),'metal','hardware')
        c('side carry handle',(x-.4,3,-2.7),(x+.4,3.7,2.7),'dark','hardware')
    # Foam is partitioned into raised rails with lower beds, exposing genuine recesses.
    c('foam bottom insert',(-13.8,1.4,-6.8),(13.8,3.55,6.8),'foam','foam_insert')
    for z in [-6.75,5.9]:
        c('foam perimeter rail',(-13.7,3.55,z),(13.7,4.35,z+.75),'foam','foam_insert')
    for x in [-13.7,12.8]:
        c('foam end rail',(x,3.55,-6),(x+.8,4.35,6),'foam','foam_insert')
    c('foam divider rear',(-12.9,3.55,2.4),(12.8,4.3,3),'foam','foam_insert')
    c('magazine bay divider',(7.6,3.55,-5.9),(8.15,4.4,2.4),'foam','foam_insert')
    # Stylized, non-functional game weapon props. All parts are independently editable.
    c('rifle stock',(-12.5,3.8,-1.4),(-8.8,5.15,1.1),'sand','rifle_prop')
    c('stock buttpad',(-12.8,3.7,-1.6),(-12.35,5.2,1.3),'rubber','rifle_prop')
    c('stock neck',(-9,4,-.6),(-6.8,4.85,.5),'steel','rifle_prop')
    c('rifle receiver',(-7.5,3.9,-1.15),(-2.1,5.25,1.05),'dark','rifle_prop')
    c('rifle handguard',(-2.1,3.95,-.9),(3.7,5.1,.8),'sand','rifle_prop')
    c('barrel',(3.7,4.2,-.35),(6.4,4.8,.25),'steel','rifle_prop')
    c('muzzle end',(6.15,4.08,-.48),(7.05,4.92,.38),'dark','rifle_prop')
    c('pistol grip rifle',(-6.7,3.9,-3.9),(-5.15,5,-1.1),'sand','rifle_prop',rotation=(0,-15,0),origin=(-6,4,-1.1))
    c('rifle magazine',(-3.7,3.95,-4.4),(-1.7,5.05,-1.1),'steel','rifle_prop',rotation=(0,10,0),origin=(-2.7,4,-1.1))
    for x in [-3.25,-2.75,-2.25]:
        c('magazine stamped rib',(x,5.06,-4.1),(x+.15,5.16,-1.5),'metal','rifle_prop')
    c('trigger guard back',(-5.15,4.05,-2.65),(-4.85,4.9,-1.1),'steel','rifle_prop')
    c('trigger guard bottom',(-5.15,4.05,-2.8),(-3.85,4.9,-2.5),'steel','rifle_prop')
    c('optic mount',(-5.5,5.25,-.5),(-3.2,5.6,.45),'metal','rifle_prop')
    c('optic housing',(-5.2,5.6,-.48),(-3.55,6.35,.45),'black','rifle_prop')
    c('optic glass',(-3.54,5.78,-.33),(-3.49,6.18,.3),'blue','rifle_prop')
    for i in range(9):
        c('top rail tooth %d'%i,(-1.7+i*.57,5.1,-.48),(-1.4+i*.57,5.38,.4),'dark','rifle_prop')
    for i in range(5):
        c('handguard vent %d'%i,(-1.5+i*.96,4.25,-.98),(-.95+i*.96,4.75,-.91),'black','rifle_prop')
    # Compact sidearm in the rear foam pocket.
    c('pistol slide',(-11.8,4.35,3.6),(-6.4,5.25,4.75),'steel','pistol_prop')
    c('pistol slide top',(-11.4,5.25,3.76),(-6.8,5.4,4.55),'dark','pistol_prop')
    c('pistol grip',(-11.5,3.85,4.6),(-10,4.8,5.8),'sand','pistol_prop')
    c('pistol trigger frame',(-10,4,4.75),(-8.4,4.7,5.15),'dark','pistol_prop')
    for i in range(4):
        c('slide serration %d'%i,(-11.4+i*.35,5.4,3.75),(-11.22+i*.35,5.49,4.55),'metal','pistol_prop')
    # Four removable spare magazines, with visible brass-colored top rounds.
    for i in range(3):
        x=8.45+i*1.48
        c('spare magazine %d'%i,(x,3.8,-4.8),(x+1.1,4.85,.4),'steel','spare_magazines')
        c('magazine floorplate %d'%i,(x-.07,3.75,-5.05),(x+1.17,5,-4.55),'dark','spare_magazines')
        for j in range(2):
            c('magazine groove',(x+.2+j*.45,4.85,-4.2),(x+.33+j*.45,4.95,-.1),'metal','spare_magazines')
        c('visible top cartridge',(x+.23,4.1,.4),(x+.85,4.67,1.05),'gold','spare_magazines')
    c('rear spare magazine',(-4.5,3.8,3.4),(.7,4.8,4.7),'steel','spare_magazines')
    c('rear spare magazine base',(-4.7,3.75,3.3),(-4.2,4.95,4.8),'dark','spare_magazines')
    # Hinged lid: modeled CLOSED locally and opened 105 degrees at the real rear seam.
    c('lid exterior shell',(-15,7.2,-8),(15,8.6,8),'olive','lid_hinge')
    c('lid rubber gasket',(-14.7,7,-7.7),(14.7,7.2,7.7),'rubber','lid_hinge')
    c('lid inner padding',(-13.8,6.65,-6.8),(13.8,7,6.8),'foam','lid_hinge')
    c('lid inventory plate',(-8.5,6.56,-3.5),(8.5,6.65,3.5),'olive_dark','lid_hinge',{'down':'lid_label'})
    for x in [-13.2,11.7]:
        c('lid inside retaining strap',(x,6.4,-6),(x+1.5,6.65,6),'dark','lid_hinge')
        c('strap buckle',(x-.08,6.25,-1),(x+1.58,6.4,1),'metal','lid_hinge')
    for x in [-12,-6,6,12]:
        c('lid exterior reinforcement',(x,8.6,-7.3),(x+.6,8.95,7.3),'olive_light','lid_hinge')
    for x in [-10,8]:
        c('hinge fixed leaf',(x,5.5,8.02),(x+2,7,8.28),'metal','hardware')
        c('hinge barrel',(x-.2,6.7,7.7),(x+2.2,7.35,8.55),'steel','hardware')
        c('hinge moving leaf',(x,7.2,7.7),(x+2,8.5,8.25),'metal','lid_hinge')
    return m


def rotation(v):
    x,y,z=np.radians(v)
    rx=np.array([[1,0,0],[0,math.cos(x),-math.sin(x)],[0,math.sin(x),math.cos(x)]])
    ry=np.array([[math.cos(y),0,math.sin(y)],[0,1,0],[-math.sin(y),0,math.cos(y)]])
    rz=np.array([[math.cos(z),-math.sin(z),0],[math.sin(z),math.cos(z),0],[0,0,1]])
    return rz@ry@rx


def world_vertices(m,c):
    x,y,z=c['a']; X,Y,Z=c['b']
    v=np.array([[x,y,z],[X,y,z],[X,Y,z],[x,Y,z],[x,y,Z],[X,y,Z],[X,Y,Z],[x,Y,Z]])
    def apply(v,origin,angles):
        return (v-np.array(origin))@rotation(angles).T+origin
    v=apply(v,c['origin'],c['rotation'])
    g=c['group']
    while g:
        b=m.groups[g]
        v=apply(v,b['origin'],b['rotation'])
        g=b['parent']
    return v


def render(m,folder):
    # Software textured orthographic rasterizer: previews are the actual deliverable geometry.
    w,h=1100,1120
    pixels=np.zeros((h,w,3),dtype=np.uint8)
    for y in range(h):
        t=y/h
        pixels[y,:]=[int(13+10*t),int(23+11*t),int(31+12*t)]
    zbuffer=np.full((h,w),-np.inf)
    elev=math.radians(23 if m.name.startswith('nova') else 42)
    az=math.radians(24)
    eye=np.array([math.sin(az)*math.cos(elev), math.sin(elev),-math.cos(az)*math.cos(elev)])
    right=np.cross(eye,[0,1,0]); right/=np.linalg.norm(right)
    up=np.cross(right,eye)
    basis=np.stack([right,up,eye],axis=1)
    vv=[world_vertices(m,c) for c in m.cubes]
    proj=[v@basis for v in vv]
    allp=np.concatenate(proj)
    lo,hi=allp[:,:2].min(0),allp[:,:2].max(0)
    scale=min((w-155)/(hi[0]-lo[0]),(h-250)/(hi[1]-lo[1]))
    center=(lo+hi)/2
    atlas=np.asarray(m.atlas.img)
    faceids={'north':[0,1,2,3],'south':[5,4,7,6], 'east':[1,5,6,2],
             'west':[4,0,3,7],'up':[3,2,6,7],'down':[4,5,1,0]}
    light=np.array([-.35,.85,-.4]); light/=np.linalg.norm(light)
    for c,v,p in zip(m.cubes,vv,proj):
        screen=np.column_stack(((p[:,0]-center[0])*scale+w/2, -(p[:,1]-center[1])*scale+h/2+25,p[:,2]))
        for face,ids in faceids.items():
            points=screen[ids]; world=v[ids]
            n=np.cross(world[1]-world[0],world[2]-world[0]); n/=np.linalg.norm(n)
            # Listed quads wind inward, so negate for lighting and backface culling.
            n=-n
            if n@eye<=0: continue
            region=m.atlas.regions[c['mats'][face]]
            x0,y0,x1,y1=region
            uv=np.array([[x0,y1-1],[x1-1,y1-1],[x1-1,y0],[x0,y0]])
            shade=.67+.33*max(0,float(n@light))
            if c['mats'][face] in ['cyan','touch','totals']: shade=1.05
            for tri in [[0,1,2],[0,2,3]]:
                q=points[tri]; tex=uv[tri]
                xmin=max(0,int(np.floor(q[:,0].min()))); xmax=min(w-1,int(np.ceil(q[:,0].max())))
                ymin=max(0,int(np.floor(q[:,1].min()))); ymax=min(h-1,int(np.ceil(q[:,1].max())))
                if xmin>xmax or ymin>ymax: continue
                xx,yy=np.meshgrid(np.arange(xmin,xmax+1)+.5,np.arange(ymin,ymax+1)+.5)
                den=(q[1,1]-q[2,1])*(q[0,0]-q[2,0])+(q[2,0]-q[1,0])*(q[0,1]-q[2,1])
                if abs(den)<1e-9:continue
                a=((q[1,1]-q[2,1])*(xx-q[2,0])+(q[2,0]-q[1,0])*(yy-q[2,1]))/den
                b=((q[2,1]-q[0,1])*(xx-q[2,0])+(q[0,0]-q[2,0])*(yy-q[2,1]))/den
                cc=1-a-b
                dep=a*q[0,2]+b*q[1,2]+cc*q[2,2]
                zb=zbuffer[ymin:ymax+1,xmin:xmax+1]
                mask=(a>=-1e-6)&(b>=-1e-6)&(cc>=-1e-6)&(dep>zb)
                if not np.any(mask):continue
                tx=np.clip(np.rint(a*tex[0,0]+b*tex[1,0]+cc*tex[2,0]).astype(int),0,511)
                ty=np.clip(np.rint(a*tex[0,1]+b*tex[1,1]+cc*tex[2,1]).astype(int),0,511)
                rgb=np.clip(atlas[ty,tx]*shade,0,255).astype(np.uint8)
                pixels[ymin:ymax+1,xmin:xmax+1][mask]=rgb[mask];zb[mask]=dep[mask]
    image=Image.fromarray(pixels)
    d=ImageDraw.Draw(image)
    d.text((48,28),'NOVA / DIGITAL PUMP' if m.name.startswith('nova') else 'FIELD / OPEN EQUIPMENT CASE',font=font(30),fill='#e8f4ee')
    d.text((50,70),'ROLEPLAY PROPS     /     BLOCKBENCH EDITABLE',font=font(15),fill='#64cbbb')
    d.line((50,h-83,w-50,h-83),fill='#3e575c',width=1)
    subtitle='4 NOZZLES   /   TOUCHSCREEN   /   NFC   /   LED' if m.name.startswith('nova') else 'HINGED LID   /   FOAM INSERT   /   2 WEAPON PROPS   /   4 MAGAZINES'
    d.text((50,h-65),subtitle,font=font(16),fill='#b3c4c4')
    d.text((50,h-38),'Actual textured model preview - no generated concept art',font=font(13),fill='#718f9a')
    image.save(folder/'preview.png')
    return image


def validate(m,folder):
    bb=json.loads((folder/(m.name+'.bbmodel')).read_text())
    ids={e['uuid'] for e in bb['elements']}
    refs=[]
    def walk(group):
        for c in group['children']:
            if isinstance(c,str):refs.append(c)
            else:walk(c)
    for g in bb['outliner']:walk(g)
    assert len(ids)==len(bb['elements'])==len(refs)
    assert ids==set(refs)
    for e in bb['elements']:
        assert all(e['to'][i]>e['from'][i] for i in range(3))
        for f in e['faces'].values():
            assert all(0<=x<=512 for x in f['uv']) and f['texture']==0
    embedded=base64.b64decode(bb['textures'][0]['source'].split(',')[1])
    assert embedded==(folder/(m.name+'.png')).read_bytes()
    geo=json.loads((folder/(m.name+'.geo.json')).read_text())['minecraft:geometry'][0]
    assert sum(len(b['cubes']) for b in geo['bones'])==len(ids)
    if 'lid_hinge' in m.groups:
        lid=next(c for c in m.cubes if c['name']=='lid exterior shell')
        v=world_vertices(m,lid)
        assert v[:,1].max()>22 and v[:,2].min()>7
    print(f'PASS {m.name}: {len(ids)} cubes, {len(m.groups)} groups, embedded 512px texture, UVs and hierarchy validated')


def main():
    images=[]
    for m in [pump(),case()]:
        folder=m.export();images.append(render(m,folder));validate(m,folder)
    sheet=Image.new('RGB',(2200,1120))
    for i,img in enumerate(images):sheet.paste(img,(1100*i,0))
    sheet.save(OUT/'PREVIEW.jpg',quality=93)
    archive=OUT/'Roleplay_Props_Blockbench.zip'
    with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
        for f in sorted(OUT.rglob('*')):
            if f.is_file() and f.suffix not in ('.zip','.pyc') and '__pycache__' not in f.parts:
                z.write(f,Path('Roleplay_Props')/f.relative_to(OUT))
    with zipfile.ZipFile(archive) as z:
        assert z.testzip() is None
    print(f'ZIP verified: {archive.name} ({archive.stat().st_size:,} bytes)')


if __name__=='__main__':
    main()
