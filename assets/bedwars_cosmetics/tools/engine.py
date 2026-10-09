#!/usr/bin/env python3
"""Shared deterministic cuboid exporter and textured preview renderer.
Standalone copy for the Bedwars Islands collection.
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

OUT = Path(__file__).resolve().parent.parent / "models"
NS = uuid.UUID('ab70143d-f43d-45ef-8e66-240fe59e0984')
FACES = ('north', 'south', 'east', 'west', 'up', 'down')
BRAND = 'ARVAN GAMING'


def uid(s):
    return str(uuid.uuid5(NS, s))


def font(size):
    return ImageFont.load_default(size=size)


class Atlas:
    def __init__(self):
        self.img = Image.new('RGBA', (512, 512), '#20272c')
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

    def centered_text(self, box, text, size=26, fill='#ffffff', padding=8):
        """Fit the entire brand inside its label without clipping or distorting it."""
        x0, y0, x1, y1 = box
        while size > 1:
            f = font(size)
            l, t, r, b = self.d.textbbox((0, 0), text, font=f)
            if r-l <= x1-x0-2*padding and b-t <= y1-y0-2*padding:
                break
            size -= 1
        assert r-l <= x1-x0-2*padding and b-t <= y1-y0-2*padding
        self.d.text(((x0+x1-r+l)/2-l, (y0+y1-b+t)/2-t), text, font=f, fill=fill)


class Model:
    def __init__(self, name, atlas):
        self.name, self.atlas = name, atlas
        self.groups = {}
        self.cubes = []
        self.animations = []
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
        bb['animations'] = []
        for a in self.animations:
            animators = {}
            for bone, channels in a['tracks'].items():
                keys = []
                for channel, track in channels.items():
                    for time, values in track:
                        keys.append(dict(channel=channel,
                            data_points=[dict(zip('xyz', map(str, values)))],
                            uuid=uid(self.name+'/'+a['name']+'/'+bone+'/'+channel+'/'+str(time)),
                            time=time, color=-1, interpolation='linear'))
                animators[self.groups[bone]['uuid']] = dict(name=bone, type='bone', keyframes=keys)
            bb['animations'].append(dict(uuid=uid(self.name+'/'+a['name']),
                name='animation.'+self.name+'.'+a['name'], loop=a['loop'], override=False,
                length=a['length'], snapping=24, animators=animators))
        (folder/(self.name+'.bbmodel')).write_text(json.dumps(bb, separators=(',',':')))
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
        (folder/(self.name+'.geo.json')).write_text(json.dumps(geo,separators=(',',':')))
        animations = {}
        for a in self.animations:
            tracks = {}
            for bone, channels in a['tracks'].items():
                tracks[bone] = {}
                for channel, keys in channels.items():
                    def bedrock_values(v):
                        if channel == 'rotation': return [-v[0], -v[1], v[2]]
                        if channel == 'position': return [-v[0], v[1], v[2]]
                        return v
                    tracks[bone][channel] = {str(t): bedrock_values(v) for t,v in keys}
            animations['animation.'+self.name+'.'+a['name']] = dict(
                loop={'loop':True, 'hold':'hold_on_last_frame', 'once':False}[a['loop']],
                animation_length=a['length'], bones=tracks)
        (folder/(self.name+'.animation.json')).write_text(json.dumps(
            {'format_version':'1.8.0', 'animations':animations}, indent=2))
        return folder


def eased_keys(start,end,a,b,steps=12):
    """Sample a cosine ease for identical smooth motion in both output formats."""
    return [(round(start+(end-start)*i/steps,6),
             [round(x+(y-x)*(1-math.cos(math.pi*i/steps))/2,6) for x,y in zip(a,b)])
            for i in range(steps+1)]


def sample_track(keys,time):
    if time <= keys[0][0]: return np.array(keys[0][1],dtype=float)
    for (t0,v0),(t1,v1) in zip(keys,keys[1:]):
        if time <= t1:
            k=(time-t0)/(t1-t0)
            return np.array(v0)*(1-k)+np.array(v1)*k
    return np.array(keys[-1][1],dtype=float)


def animation_pose(m,names,time):
    pose={}
    for a in m.animations:
        if a['name'] not in names: continue
        t=time % a['length'] if a['loop']=='loop' else min(time,a['length'])
        for bone,channels in a['tracks'].items():
            target=pose.setdefault(bone,{})
            for channel,keys in channels.items():
                value=sample_track(keys,t)
                if channel=='scale':target[channel]=target.get(channel,np.ones(3))*value
                else:target[channel]=target.get(channel,np.zeros(3))+value
    return pose


def rotation(v):
    x,y,z=np.radians(v)
    rx=np.array([[1,0,0],[0,math.cos(x),-math.sin(x)],[0,math.sin(x),math.cos(x)]])
    ry=np.array([[math.cos(y),0,math.sin(y)],[0,1,0],[-math.sin(y),0,math.cos(y)]])
    rz=np.array([[math.cos(z),-math.sin(z),0],[math.sin(z),math.cos(z),0],[0,0,1]])
    return rz@ry@rx


def world_vertices(m,c,pose=None):
    x,y,z=c['a']; X,Y,Z=c['b']
    v=np.array([[x,y,z],[X,y,z],[X,Y,z],[x,Y,z],[x,y,Z],[X,y,Z],[X,Y,Z],[x,Y,Z]])
    def apply(v,origin,angles):
        return (v-np.array(origin))@rotation(angles).T+origin
    v=apply(v,c['origin'],c['rotation'])
    g=c['group']
    while g:
        b=m.groups[g]
        track=(pose or {}).get(g,{})
        origin=np.array(b['origin'])
        v=(v-origin)*track.get('scale',np.ones(3))+origin
        v=apply(v,origin,np.array(b['rotation'])+track.get('rotation',np.zeros(3)))
        v=v+track.get('position',np.zeros(3))
        g=b['parent']
    return v


def render(m,folder,pose=None):
    # Software textured orthographic rasterizer: previews are the actual deliverable geometry.
    w,h=640,680
    pixels=np.zeros((h,w,3),dtype=np.uint8)
    for y in range(h):
        t=y/h
        pixels[y,:]=[int(13+10*t),int(23+11*t),int(31+12*t)]
    zbuffer=np.full((h,w),-np.inf)
    elev=math.radians(37)
    az=math.radians(150 if getattr(m,"back_view",False) else 24)
    eye=np.array([math.sin(az)*math.cos(elev), math.sin(elev),-math.cos(az)*math.cos(elev)])
    right=np.cross(eye,[0,1,0]); right/=np.linalg.norm(right)
    up=np.cross(right,eye)
    basis=np.stack([right,up,eye],axis=1)
    vv=[world_vertices(m,c,pose) for c in m.cubes]
    proj=[v@basis for v in vv]
    # Keep the camera fixed at rest-pose bounds throughout animated previews.
    allp=np.concatenate([world_vertices(m,c)@basis for c in m.cubes])
    lo,hi=allp[:,:2].min(0),allp[:,:2].max(0)
    scale=min((w-105)/(hi[0]-lo[0]),(h-205)/(hi[1]-lo[1]))
    center=(lo+hi)/2
    atlas=np.asarray(m.atlas.img.convert("RGBA"))
    faceids={'north':[0,1,2,3],'south':[5,4,7,6], 'east':[1,5,6,2],
             'west':[4,0,3,7],'up':[3,2,6,7],'down':[4,5,1,0]}
    light=np.array([-.35,.85,-.4]); light/=np.linalg.norm(light)
    for c,v,p in sorted(zip(m.cubes,vv,proj),key=lambda item: float(item[2][:,2].mean())):
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
                rgba=atlas[ty,tx]; mask=mask & (rgba[:,:,3]>20)
                rgb=np.clip(rgba[:,:,:3]*shade,0,255).astype(np.uint8)
                alpha=rgba[:,:,3:4]/255.0
                target=pixels[ymin:ymax+1,xmin:xmax+1]
                blended=(rgb*alpha+target*(1-alpha)).astype(np.uint8)
                target[mask]=blended[mask];zb[mask]=dep[mask]
    image=Image.fromarray(pixels)
    d=ImageDraw.Draw(image)
    d.text((28,22),m.title,font=font(23),fill='#e8f4ee')
    d.text((30,56),'ARVAN / COSMETICS COLLECTION 02',font=font(12),fill='#64cbbb')
    d.line((50,h-83,w-50,h-83),fill='#3e575c',width=1)
    subtitle=m.subtitle
    d.text((30,h-65),subtitle,font=font(12),fill='#b3c4c4')
    d.text((30,h-38),'Actual textured model preview - no generated concept art',font=font(10),fill='#718f9a')
    if pose is None: image.save(folder/'preview.png')
    return image
