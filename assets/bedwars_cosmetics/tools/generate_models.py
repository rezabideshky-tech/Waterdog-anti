#!/usr/bin/env python3
"""Original voxel cosmetics, independent editable Blockbench files and Bedrock assets."""
from pathlib import Path
import math,json,zipfile,shutil,hashlib
import numpy as np
from PIL import Image,ImageDraw
import engine as e
from catalog import entries,THEMES,PACK_UUID,MODULE_UUID
ROOT=Path(__file__).resolve().parent.parent
RP=ROOT/'ArvanCosmeticsV2_RP'


def make_atlas(row):
 a=e.Atlas();primary,light,edge=THEMES[row['theme']]
 for name,col,grain in [('primary',primary,2),('light',light,1),('edge',edge,1),('dark','#18243a',2),
  ('black','#0b1222',0),('white','#edf4f2',1),('gold','#eabb54',2),('wood','#815133',3),
  ('steel','#8b9da9',2),('pink','#ff77c0',1),('cyan','#60e4ed',1),('red','#b52c49',2),
  ('green','#3ab481',2),('bone','#e0d5b8',2),('orange','#f88432',2),('purple','#8750c8',1)]:a.material(name,col,grain)
 a.panel('glass',[0,40,32,72],(145,218,244,42));a.d.line((2,42,28,68),fill=(245,255,255,120),width=2)
 a.panel('screen',[40,40,104,88],'#102032')
 for y in range(44,84,6):a.d.line((44,y,99,y),fill=light,width=1)
 for x,y in [(48,66),(60,57),(74,69),(83,53)]:a.d.rectangle((x,y,x+6,y+6),fill=edge)
 a.panel('skull',[112,40,144,72],'#121b29')
 d=a.d;d.rectangle((120,45,136,59),fill='#eee8d5');d.rectangle((124,59,132,64),fill='#eee8d5')
 d.rectangle((122,49,126,53),fill='#121b29');d.rectangle((130,49,134,53),fill='#121b29')
 d.line((117,65,139,43),fill='#eee8d5',width=2);d.line((117,43,139,65),fill='#eee8d5',width=2)
 a.panel('eye',[152,40,184,72],'#476345');d.ellipse((154,45,181,66),fill='#9cedbd');d.ellipse((164,43,174,68),fill='#392d50');d.rectangle((168,46,171,64),fill='#cf86f2')
 # A star-field panel for glass reflections and night-sky wings.
 a.panel('night',[192,40,256,104],'#182747')
 for i in range(22):
  x=194+(i*17)%59;y=43+(i*29)%57
  d.line((x-1,y,x+1,y),fill=light);d.line((x,y-1,x,y+1),fill=light)
 return a


class Cosmetic(e.Model):
 def __init__(self,row):
  super().__init__(row['model'],make_atlas(row));self.row=row;self.title=row['name'].upper()
  self.subtitle=row['rarity'].upper()+' / '+row['kind'].upper()+' / EDITABLE / ANIMATED'
  self.back_view=row['kind']!='hat';self.anchor='head_anchor' if row['kind']=='hat' else ('cape_anchor' if row['kind']=='cape' else 'back_anchor')
  self.group(self.anchor,origin=(0,24,0));self.tracks={};self.effects=[]
 def group_(self,name,pivot=(0,0,0),parent=None,rotation=(0,0,0)):
  name=self.row['key']+'_'+name
  self.group(name,parent or self.anchor,origin=pivot,rotation=rotation);return name
 def c(self,name,a,b,mat='primary',group=None,faces=None,rotation=(0,0,0),origin=(0,0,0)):
  self.cube(name,a,b,mat,group or self.anchor,faces,rotation,origin)
 def seg(self,name,a,b,w,mat='primary',group=None):
  a=np.array(a);b=np.array(b);v=b-a;L=float(np.linalg.norm(v));mid=(a+b)/2
  rx=math.degrees(math.asin(v[2]/L));rz=math.degrees(math.atan2(-v[0],v[1]))
  self.c(name,mid+[-w/2,-L/2,-w/2],mid+[w/2,L/2,w/2],mat,group,rotation=(rx,0,rz),origin=mid)
 def ring(self,name,r,y,z=0,w=.5,mat='gold',group=None,segments=12):
  for i in range(segments):
   a=2*math.pi*i/segments;b=2*math.pi*(i+1)/segments
   self.seg(name,(r*math.cos(a),y,z+r*math.sin(a)),(r*math.cos(b),y,z+r*math.sin(b)),w,mat,group)
 def wave(self,bone,axis='x',amount=8,channel='rotation',base=0):
  vals=[]
  for t,value in [(0,base),(1,base+amount),(2,base),(3,base-amount),(4,base)]:
   v=[1,1,1] if channel=='scale' else [0,0,0];v['xyz'.index(axis)]=value;vals.append((t,v))
  self.tracks.setdefault(bone,{})[channel]=vals
 def spin(self,bone):self.tracks.setdefault(bone,{})['rotation']=[(0,[0,0,0]),(4,[0,360,0])]
 def star(self,name,x,y,z,size=1,mat='edge',group=None):
  self.c(name+' horizontal',(x-size,y-size*.22,z-.15),(x+size,y+size*.22,z+.15),mat,group)
  self.c(name+' vertical',(x-size*.22,y-size,z-.16),(x+size*.22,y+size,z+.16),mat,group)
 def flame(self,x,y,z,size=1,parent=None):
  g=self.group_('flame_'+str(len(self.groups)),(x,y,z),parent)
  for dx,dy,s,mat in [(0,0,1,'orange'),(-.6,.4,.6,'red'),(.5,.6,.65,'primary'),(0,-.2,.45,'edge')]:
   self.c('voxel flame',(x+dx*size-s*size/2,y+dy*size-size*.9,z-size*.35),(x+dx*size+s*size/2,y+dy*size+size*.9,z+size*.35),mat,g)
  self.wave(g,'y',.22,'scale',1);return g
 def fx(self,effect,pivot):self.effects.append((effect,pivot))
 def finish(self):
  if not self.tracks:
   g=self.group_('shine',(0,34,0) if self.row['kind']=='hat' else (0,23,5))
   x,y,z=self.groups[g]['origin'];self.star('glint',x,y,z,.35,'edge',g);self.wave(g,'x',.5,'scale',.65)
  self.animations=[dict(name='idle',length=4,loop='loop',tracks=self.tracks)]
  return self


def rim(m,y=31.5,mat='primary',h=1):
 for a,b in [((-4.65,y,-4.65),(4.65,y+h,-4.15)),((-4.65,y,4.15),(4.65,y+h,4.65)),((-4.65,y,-4.15),(-4.15,y+h,4.15)),((4.15,y,-4.15),(4.65,y+h,4.15))]:m.c('head rim',a,b,mat)


def helmet(m,mat='primary',lower=28.5):
 rim(m,31.8,mat,.9)
 m.c('helmet dome',(-4.6,32,-4.6),(4.6,34,4.6),mat)
 for x in [-4.8,4.2]:m.c('side shell',(x,lower,-3.7),(x+.6,33.3,4.6),mat)
 m.c('rear shell',(-4.2,lower,4.15),(4.2,33.3,4.8),mat)
 m.c('brow trim',(-4.7,31.7,-4.8),(4.7,32.2,-4.6),'gold')


def hat(row):
 m=Cosmetic(row);k=row['key']
 if k=='bed_crown':
  rim(m,31.8,'gold',1.3)
  for x in [-4,0,4]:
   for z in [-4.4,4.4]:m.c('crown battlement',(x-.5,33,z-.3),(x+.5,35.4,z+.3),'gold')
  m.c('mini bed frame',(-3.4,33,-3.9),(3.4,33.8,3.9),'wood')
  m.c('team bedsheet',(-3.25,33.8,-3.75),(3.25,34.65,1.9),'primary')
  m.c('white pillow',(-2.8,33.8,2),(2.8,34.75,3.45),'white')
  for x in [-3.15,2.7]:m.c('bed headboard foot',(x,33.1,3.45),(x+.5,35.8,4),'wood')
  g=m.group_('gem',(0,33,-4.9));m.c('crown front jewel',(-.55,32.5,-5.13),(.55,33.6,-4.73),'cyan',g,rotation=(0,0,45),origin=(0,33,-4.9));m.wave(g,'y',.2,'position')
 elif k=='emerald_helmet':
  helmet(m)
  for x in [-3.3,2.5]:m.c('faceguard',(x,27.5,-4.8),(x+.8,31.9,-4.2),'primary')
  m.c('gold crest',(-.4,33.8,-3.5),(.4,38,2.7),'gold')
  for z in [-2,0,2]:m.c('crest bevel',(-.5,35.5,z),(.5,38.7,z+.65),'edge')
  g=m.group_('gem',(0,32,-5));m.star('emerald gleam',0,32,-5,.7,'light',g);m.wave(g,'x',.3,'scale',.7)
 elif k=='pumpkin_head':
  # Hollow head, not a solid cube replacing the player's face.
  helmet(m,'orange',25.5);rim(m,25,'orange',1)
  for x in [-4.9,4.25]:m.c('pumpkin rib',(x,26,-3.5),(x+.65,33.4,3.5),'primary')
  m.c('carved front',(-4.7,26,-4.9),(4.7,31.8,-4.6),'orange')
  eyes=m.group_('lit_eyes',(0,29,-5))
  for x in [-2.8,1.2]:m.c('glowing carved eye',(x,28.7,-5.02),(x+1.7,30.1,-4.9),'edge',eyes)
  for x in [-2,-.5,1]:m.c('carved grin',(x,26.9,-5.03),(x+1,27.65,-4.9),'black')
  m.c('pumpkin stem',(-.5,33.5,-.5),(.6,35,.6),'green');m.flame(0,36,0,.75);m.wave(eyes,'z',.06,'scale',1);m.fx('ember',(0,36,0))
 elif k=='cyber_visor':
  rim(m,28.7,'dark',1.1)
  m.c('visor housing',(-4.8,27.6,-5.1),(4.8,31.3,-4.4),'dark')
  m.c('visor glass',(-4.4,28,-5.22),(4.4,30.9,-5.11),'cyan',faces={'north':'screen'})
  for x in [-5.2,4.4]:m.c('ear receiver',(x,28.1,-1),(x+.8,31.2,1.8),'steel')
  g=m.group_('scan',(-3.8,29,-5.3));m.c('neon scanner',(-4.1,28.2,-5.38),(-3.65,30.6,-5.24),'pink',g)
  m.tracks[g]={'position':[(0,[0,0,0]),(2,[7.6,0,0]),(4,[0,0,0])]}
 elif k=='samurai_kabuto':
  helmet(m,'dark',28);rim(m,31,'red',.6)
  for y in [27.5,29,30.5]:
   for x in [-5.6,4.5]:m.c('layered neck guard',(x,y,-1),(x+1.1,y+.9,4.7),'primary')
  for s in [-1,1]:
   m.seg('gold kabuto horn',(s*1,33,-4.8),(s*4.5,36.5,-4.8),.8,'gold');m.seg('gold horn tip',(s*4.5,36.5,-4.8),(s*5.2,39,-4.8),.55,'edge')
  m.c('crest badge',(-.6,32.5,-5.1),(.6,34.3,-4.8),'gold')
 elif k=='pirate_captain':
  rim(m,31.5,'dark',1);m.c('tricorn crown',(-3.7,32,-3.5),(3.7,35.6,3.5),'dark')
  for s in [-1,1]:
   m.seg('tricorn rising brim',(0,32,-5.5),(s*6,35,-1.5),1,'dark');m.seg('gold brim piping',(0,32.5,-5.7),(s*6,35.5,-1.5),.22,'gold')
  m.seg('rear tricorn brim',(-6,35,-1.5),(0,34.5,5.5),.8,'dark');m.seg('rear tricorn brim',(6,35,-1.5),(0,34.5,5.5),.8,'dark')
  m.c('pirate emblem',(-1.4,32.7,-3.61),(1.4,35.3,-3.51),'dark',faces={'north':'skull'})
  g=m.group_('feather',(4,34,1));m.seg('red feather quill',(4,34,1),(6.3,40,1),.25,'gold',g)
  for i in range(6):m.seg('red feather barb',(4+i*.38,34+i*.9,1),(5.7+i*.32,35.2+i*.9,1),.55,'red',g)
  m.wave(g,'z',6)
 elif k=='viking_horns':
  helmet(m,'steel',29);rim(m,31,'wood',1.3)
  m.c('central helmet band',(-.35,32,-4.8),(.35,34.3,4.7),'gold')
  for s in [-1,1]:
   for a,b,w in [((s*4,32,0),(s*6.3,32.6,0),1.6),((s*6.3,32.6,0),(s*7,35,0),1.1),((s*7,35,0),(s*6.4,36.5,0),.6)]:m.seg('curved horn',a,b,w,'bone')
  for x in [-3,-1.5,1.5,3]:m.c('band rivet',(x-.15,31.3,-4.9),(x+.15,31.7,-4.65),'gold')
 elif k=='wizard_star':
  m.c('wizard brim',(-6,31.6,-6),(6,32.3,6),'primary')
  for i in range(6):
   r=4.1-i*.57;m.c('tapered wizard crown',(-r+i*.2,32.3+i*1.25,-r),(r+i*.2,33.65+i*1.25,r),'primary')
  rim(m,32.5,'gold',.6);g=m.group_('star_orbit',(0,35,0));m.spin(g)
  for i in range(5):
   angle=i*math.tau/5;m.star('orbiting star',6.3*math.cos(angle),35+(i%2),6.3*math.sin(angle),.55,'edge',g)
  m.fx('star',(0,39,0))
 elif k=='ninja_headband':
  rim(m,29.8,'red',1.4)
  m.c('headband steel emblem',(-1.15,29.9,-4.79),(1.15,31.1,-4.65),'steel')
  for i,x in enumerate([-1,1]):
   g=m.group_('tail_'+str(i),(x,30.5,4.7));m.c('long cloth tail',(x-.45,24.5,4.65),(x+.45,30.8,5),'red',g,rotation=(-16,0,x*8),origin=(x,30.5,4.7));m.wave(g,'x',12)
 elif k=='space_helmet':
  rim(m,24.7,'white',1);rim(m,33.3,'white',.9)
  m.c('upper pressure shell',(-4.9,33.6,-4.9),(4.9,34.5,4.9),'white')
  m.c('transparent visor',(-4.95,25.6,-5),(4.95,33.3,-4.82),'glass')
  for x in [-5,4.65]:m.c('helmet side window',(x,25.7,-4.7),(x+.35,33.3,4.7),'glass')
  m.c('rear pressure shell',(-4.9,25.7,4.6),(4.9,33.5,5),'white')
  for x in [-5.6,4.95]:m.c('ear seal',(x,27.3,-1.6),(x+.65,31.5,1.7),'steel')
  g=m.group_('reflection',(-2,31,-5.1));m.star('star reflection',-2,31,-5.1,.6,'white',g);m.wave(g,'x',.25,'scale',.7)
 elif k=='cat_ears':
  rim(m,31.7,'dark',.5)
  for s in [-1,1]:
   g=m.group_('ear_'+str(s),(s*3.1,32.1,0))
   for i in range(3):
    w=2.7-i*.7;m.c('cat ear tier',(s*3.1-w/2,32.1+i*.9,-1.1),(s*3.1+w/2,33.1+i*.9,1),'primary',g)
    m.c('pink inner ear',(s*3.1-w*.3,32.35+i*.9,-1.16),(s*3.1+w*.3,32.95+i*.9,-1.1),'light',g)
   m.tracks[g]={'rotation':[(0,[0,0,0]),(2.6,[0,0,0]),(2.8,[0,0,s*13]),(3,[0,0,0]),(4,[0,0,0])]}
 elif k=='dragon_skull':
  helmet(m,'bone',29);m.c('dragon forehead',(-4.6,31.9,-6),(4.6,34,-4.4),'bone')
  m.c('upper snout',(-2.6,31.3,-8.1),(2.6,32.5,-4.6),'bone')
  for x in [-2,1.1]:m.c('nostril',(x,32.45,-7.5),(x+.9,32.58,-6.4),'black')
  for s in [-1,1]:
   m.seg('skull horn',(s*3.4,33.5,1),(s*5,36.5,3),1,'bone');m.seg('horn tip',(s*5,36.5,3),(s*5.2,38.5,4),.5,'bone')
   m.c('upper fang',(s*2.1-.25,29.8,-6.2),(s*2.1+.25,31.4,-5.6),'white')
   g=m.group_('eye_'+str(s),(s*2.4,32.2,-6.1));m.c('flaming eye',(s*2.4-.65,32,-6.12),(s*2.4+.65,32.65,-6),'orange',g);m.wave(g,'y',.25,'scale',1)
  # No faceplate: the player's face remains visible under the skull.
  m.fx('ember',(0,35,0))
 elif k=='ice_crown':
  rim(m,31.8,'steel',.6);rim(m,32.4,'primary',.6)
  for i in range(8):
   t=i*math.tau/8;x=4.4*math.cos(t);z=4.4*math.sin(t);h=2.3+(i%3)*.7
   m.c('ice prism',(x-.42,32.8,z-.42),(x+.42,32.8+h,z+.42),'light',rotation=(0,0,10*math.sin(t)),origin=(x,32.8,z))
   m.c('white prism tip',(x-.23,32.8+h,z-.23),(x+.23,33.5+h,z+.23),'white')
  m.fx('snow',(0,37,0))
 elif k=='pixel_sunglasses':
  for s in [-1,1]:
   x=s*2.35;m.c('pixel sunglass frame',(x-2.15,27.8,-4.85),(x+2.15,30.15,-4.5),'black')
   for i in range(4):m.c('pink lens gradient',(x-1.8,28.1+i*.4,-4.94),(x+1.8,28.5+i*.4,-4.86),['primary','purple','pink','light'][i])
  m.c('sunglass bridge',(-.4,29.1,-4.9),(.4,29.7,-4.5),'black')
  for x in [-4.7,4.4]:m.c('sunglass arm',(x,29.4,-4.5),(x+.3,29.8,3.5),'black')
 elif k=='golden_laurel':
  m.ring('laurel band',4.7,31,mat='gold')
  for s in [-1,1]:
   for i in range(6):
    z=-3.8+i*1.4;x=s*math.sqrt(max(0,4.6**2-z*z))
    m.c('golden olive leaf',(x-.4,31,z-.65),(x+.4,32.5,z+.65),'edge' if i%2 else 'gold',rotation=(0,s*25,s*25),origin=(x,31,z))
  m.c('laurel red tie',(-.8,30.4,4.5),(.8,31.5,4.9),'red')
 return m.finish()


def pack_chest(m):
 for x in [-2.8,2.3]:m.c('chest binding',(x,12.5,3.4),(x+.5,19.8,8),'gold')
 m.c('wooden treasure chest',(-3,12.5,3.6),(3,17.6,8),'wood')
 g=m.group_('lid',(0,17.6,3.6));m.c('rounded chest lid',(-3.1,17.6,3.5),(3.1,19.5,8.1),'wood',g)
 for x in [-2.8,2.3]:m.c('lid bands',(x,17.6,3.4),(x+.5,19.6,8.2),'gold',g)
 m.c('gold latch',(-.5,16.6,8.02),(.5,18.2,8.35),'gold')
 for x in [-1.8,0,1.8]:m.c('treasure ingot',(x-.6,16.7,5),(x+.6,17.8,6.8),'gold')
 m.tracks[g]={'rotation':[(0,[0,0,0]),(1,[-55,0,0]),(2,[-55,0,0]),(3,[0,0,0]),(4,[0,0,0])]}


def back(row):
 m=Cosmetic(row);k=row['key']
 if k=='jetpack':
  m.c('jetpack spine',(-1,13,3.3),(1,23,5),'dark')
  for x in [-2.6,2.6]:
   m.c('fuel cylinder',(x-1.4,14.5,3.5),(x+1.4,22.5,7.2),'steel')
   m.c('tank shoulder',(x-1,22.5,3.9),(x+1,23.4,6.8),'primary')
   for y in [16,20]:m.c('tank band',(x-1.5,y,3.4),(x+1.5,y+.65,7.3),'dark')
   m.c('thruster nozzle',(x-1,13.3,4.1),(x+1,14.5,6.7),'dark');m.flame(x,11.8,5.4,1.2);m.fx('ember',(x,12,5.4))
 elif k=='space_rocket':
  m.c('rocket cylinder',(-2.1,12,3.5),(2.1,23.5,7.7),'white')
  for i in range(3):m.c('nose cone',(-1.8+i*.5,23.5+i*.8,3.8+i*.5),(1.8-i*.5,24.3+i*.8,7.4-i*.5),'red')
  m.c('rocket porthole',(-.9,19.3,7.71),(.9,21.4,7.95),'cyan')
  for s in [-1,1]:m.seg('rocket fin',(s*1.8,13,5.6),(s*4,11.2,5.6),1.2,'red')
  m.c('rocket nozzle',(-1.2,11,4.4),(1.2,12,6.8),'steel');m.flame(0,9.7,5.6,1.2);m.fx('smoke',(0,9,5.6))
 elif k=='treasure_chest':pack_chest(m)
 elif k=='guardian_drone':
  g=m.group_('drone',(6.5,26,2.5));m.c('drone shell',(4.6,25,1),(8.4,27,4),'dark',g)
  m.c('drone eye',(5.4,25.5,.79),(7.6,26.5,1),'cyan',g)
  for x,z in [(3.6,.2),(9.4,.2),(3.6,4.8),(9.4,4.8)]:
   m.seg('drone arm',(6.5,26,2.5),(x,26,z),.35,'steel',g)
   r=m.group_('rotor'+str(len(m.groups)),(x,26.3,z),g)
   m.c('rotor blades',(x-1.3,26.2,z-.2),(x+1.3,26.4,z+.2),'light',r);m.spin(r)
  m.wave(g,'y',.5,'position')
 elif k=='pumpkin_spirit':
  g=m.group_('pumpkin',(0,18,6));m.c('pumpkin body',(-3,15,3.5),(3,21,8.5),'orange',g)
  for x in [-2,1]:m.c('pumpkin eye',(x,18.4,8.51),(x+1,19.6,8.7),'edge',g)
  m.c('pumpkin stem',(-.5,21,5.6),(.5,22.5,6.4),'green',g);m.wave(g,'y',.45,'position')
  orbit=m.group_('ghost_orbit',(0,18,6));m.spin(orbit)
  for x,y,z in [(-4.3,20,5.5),(3.7,17,7),(0,23,5.5)]:
   m.c('little ghost',(x-.65,y-.7,z-.5),(x+.65,y+.7,z+.5),'white',orbit)
   for dx in [-.3,.15]:m.c('ghost eyes',(x+dx,y,z+.51),(x+dx+.15,y+.25,z+.6),'black',orbit)
 elif k=='mini_castle':
  m.c('castle base',(-3.8,12.5,3.5),(3.8,14,8.5),'steel')
  m.c('castle keep',(-2.5,14,4),(2.5,20,8),'primary')
  m.c('castle gate',(-.8,14,8.01),(.8,17,8.2),'dark')
  for x in [-3.1,3.1]:
   m.c('castle turret',(x-.75,14,4.1),(x+.75,21,7.4),'steel')
   for dx in [-.6,.4]:m.c('turret battlement',(x+dx,21,4.1),(x+dx+.3,21.8,7.4),'steel')
  m.seg('flagpole',(0,20,6),(0,25,6),.25,'gold');g=m.group_('flag',(0,23.7,6))
  m.c('castle red flag',(.1,22.5,5.85),(3,24.6,6.15),'red',g);m.wave(g,'y',14)
 elif k=='samurai_katana':
  m.c('katana backplate',(-2.8,13,3.2),(2.8,22,4),'dark')
  for i,angle in enumerate([-28,0,28]):
   g=m.group_('katana'+str(i),(0,18,5+i*.35),rotation=(0,0,angle));z=5+i*.35
   m.c('katana sheath',(-.4,9,z-.35),(.4,22,z+.35),'dark',g)
   m.c('katana gold guard',(-1,22,z-.6),(1,22.4,z+.6),'gold',g)
   m.c('wrapped katana handle',(-.35,22.4,z-.3),(.35,26,z+.3),'red',g)
   for y in [23,24,25]:m.c('handle wrap',(-.4,y,z-.35),(.4,y+.2,z+.35),'gold',g)
  m.seg('banner pole',(3,14,4),(3,27,4),.25,'wood');g=m.group_('banner',(3,26,4));m.c('samurai banner',(3.1,21,3.9),(6.2,26,4.15),'red',g);m.wave(g,'y',10)
 elif k=='cloud_pet':
  g=m.group_('cloud',(0,21,6))
  for x,y,s in [(-2.5,20,2.7),(0,21,3.5),(2.6,20,2.8)]:m.c('cloud puff',(x-s/2,y-s/2,4.5),(x+s/2,y+s/2,7.7),'white',g)
  for i,mat in enumerate(['red','orange','gold','green','cyan','purple']):
   r=4.8-i*.45
   for j in range(16):
    a=math.pi*j/16;b=math.pi*(j+1)/16;m.seg('rainbow arc',(r*math.cos(a),22+r*math.sin(a),5),(r*math.cos(b),22+r*math.sin(b),5),.4,mat,g)
  m.wave(g,'y',.4,'position');m.fx('rain',(0,18,6))
 elif k=='phoenix_feathers':
  for i in range(7):
   x=(i-3)*1.2;g=m.group_('feather'+str(i),(x,22,4.8))
   m.seg('fiery quill',(x,22,4.8),(x*1.5,11+abs(i-3),5.7),.8,'orange',g)
   for j in range(5):m.seg('gold feather barb',(x*1.2,13+j*1.5,5.2),(x*1.2+1.2,14+j*1.5,5.2),.7,'edge' if j%2 else 'primary',g)
   m.wave(g,'z',4)
  m.fx('ember',(0,14,6))
 elif k=='skull_crown_halo':
  g=m.group_('halo',(0,25,5));m.ring('skull halo',5,25,5,.4,'gold',g);m.spin(g)
  for i in range(6):
   t=i*math.tau/6;x=5*math.cos(t);z=5+5*math.sin(t)
   m.c('halo skull',(x-.75,24.2,z-.65),(x+.75,25.8,z+.65),'bone',g,{'south':'skull','north':'skull'})
   m.c('skull crown tooth',(x-.3,25.8,z-.3),(x+.3,26.7,z+.3),'gold',g)
 elif k=='arcade_cabinet':
  m.c('arcade cabinet',(-3.3,11.5,3.4),(3.3,23,7.9),'dark')
  m.c('marquee',(-3.4,21.4,7.9),(3.4,23.3,8.4),'pink')
  m.c('arcade screen',(-2.65,16.9,7.91),(2.65,21,8.15),'cyan',faces={'south':'screen'})
  m.c('control deck',(-3.2,15.6,7.9),(3.2,16.3,9.4),'purple')
  m.seg('joystick',(-1.5,16.3,8.8),(-1.5,17.3,8.8),.2,'steel');m.c('joystick knob',(-1.8,17.1,8.5),(-1.2,17.6,9.1),'red')
  for x in [.7,1.7]:m.c('arcade button',(x,16.3,8.6),(x+.45,16.5,9),'gold')
  g=m.group_('screen_flash',(0,19,8.2));m.star('arcade blink',0,19,8.2,.6,'edge',g);m.wave(g,'x',.6,'scale',.7)
 elif k=='ender_eye_orbit':
  g=m.group_('orbit',(0,20,0));m.spin(g)
  for i in range(3):
   t=i*math.tau/3;x=10*math.cos(t);z=10*math.sin(t)
   m.c('orbiting ender eye',(x-1.3,18.7,z-.35),(x+1.3,21.3,z+.35),'green',g,{'north':'eye','south':'eye'})
 else: wing(m)
 return m.finish()


def wing(m):
 k=m.row['key'];m.c('back mount',(-1.4,16,3.2),(1.4,23,4.3),'dark')
 tiers=3 if k=='void_seraph' else 1
 for tier in range(tiers):
  for side in [-1,1]:
   pivot=(side*1,22-tier*3,4.3);g=m.group_('wing_'+str(tier)+'_'+str(side),pivot)
   angle=side*(7+tier*4);m.wave(g,'y',angle)
   span=1-tier*.15;base=22-tier*3
   m.seg('leading wing spar',pivot,(side*10*span,base+4,5.8),.6,'gold' if k=='emerald_guardian' else 'primary',g)
   if k in ['celestial_constellation','neon_synthwave']:
    points=[(side*1,base,4.3),(side*7,base+5,5),(side*14*span,base+3,5.6),(side*11*span,base-5,5.8),(side*5,base-10,5)]
    for a,b in zip(points,points[1:]+points[:1]):m.seg('luminous constellation line',a,b,.22,'cyan' if k=='neon_synthwave' else 'light',g)
    for i,p in enumerate(points):
     node=m.group_('node_'+str(tier)+'_'+str(side)+'_'+str(i),p,g)
     m.star('constellation node',*p,.45,'pink' if i%2 else 'edge',node)
     m.tracks[node]={'scale':[(j*.5,[.85+.35*math.sin(j*math.pi/4-i)]*3) for j in range(9)]}
    if k=='celestial_constellation':
     for j in range(5):
      x=side*(2.5+j*2.1);m.c('night-sky wing panel',(x-1.04,base-9+j*1.5,4.6),(x+1.04,base+4.5-j*.25,4.9),'primary',g,{'south':'night','north':'night'})
    for i in range(1,5):m.seg('geometric wing web',points[0],(side*(4+i*1.6),base+4-i*2,5.4),.16,'primary',g)
   elif k in ['frozen_wyvern','crystal_prism']:
    for i in range(5):
     x=side*(2.5+i*1.8)*span;y=base+3-i*1.65
     m.seg('crystal wing rib',(side*2,base,4.5),(x+side*3,y+2,5.5),.5,'light',g)
     m.c('translucent crystal',(min(x,x+side*2),y-5,5),(max(x,x+side*2),y+1.9,5.5),'glass',g,rotation=(0,0,side*-12),origin=(x,y,5))
     m.seg('crystal pointed blade',(x,y+1,5.2),(x+side*2,y-6,5.4),.6,'light' if i%2 else 'primary',g)
   else:
    m.seg('wing shoulder',pivot,(side*7*span,base+5,5),1.3,'primary',g)
    m.seg('outer wing elbow',(side*7*span,base+5,5),(side*14*span,base+2,5.7),.9,'primary',g)
    for i in range(7):
     x=side*(2.3+i*1.65)*span;y=base+1.5+min(i,3)*1.2-max(0,i-3)*.6;length=6.8+i*.4-tier*.5
     mat='white' if k=='sakura_crane' else ('dark' if k=='storm_raven' else 'primary')
     if k=='phoenix_ascend':mat=['red','orange','primary','light','edge','gold','orange'][i]
     if k=='mecha_falcon':mat='steel' if i%2 else 'dark'
     feather=m.group_('flight_'+str(tier)+'_'+str(side)+'_'+str(i),(x,y,5+i*.12),g,rotation=(0,0,side*(8+i*2)))
     m.c('layered flight feather',(x-.86,y-length,4.7+i*.12),(x+.86,y,5.4+i*.12),mat,feather)
     m.c('feather tip',(x-.52,y-length-.7,4.72+i*.12),(x+.52,y-length,5.36+i*.12),'pink' if k=='sakura_crane' else 'light',feather)
     if k=='mecha_falcon':m.wave(feather,'y',side*(3+i))
     if k in ['mecha_falcon','emerald_guardian']:m.seg('feather inlay',(x,y-.5,5.55+i*.12),(x,y-length+.5,5.55+i*.12),.14,'cyan' if k=='mecha_falcon' else 'gold',feather)
     if k=='void_seraph' and i in [2,5]:
      m.c('seraph eye',(x-.6,y-3.4,5.51+i*.12),(x+.6,y-2.1,5.7+i*.12),'purple',feather,{'south':'eye'})
      lid=m.group_('eyelid_'+str(tier)+'_'+str(side)+'_'+str(i),(x,y-2.75,5.75+i*.12),feather)
      m.c('blinking eyelid',(x-.61,y-3.4,5.71+i*.12),(x+.61,y-2.1,5.8+i*.12),'primary',lid)
      m.tracks[lid]={'scale':[(0,[1,.01,1]),(2.5,[1,.01,1]),(2.65,[1,1,1]),(2.8,[1,.01,1]),(4,[1,.01,1])]}
   if k=='storm_raven':
    bolt=m.group_('bolt_'+str(side),(side*6,23,5.9),g)
    for a,b in [((side*3,25,6),(side*6,22,6)),((side*6,22,6),(side*5,20,6)),((side*5,20,6),(side*9,17,6))]:m.seg('electric wing arc',a,b,.25,'cyan',bolt)
    m.tracks[bolt]={'scale':[(0,[.02,.02,.02]),(1.7,[.02,.02,.02]),(1.8,[1,1,1]),(2,[.02,.02,.02]),(4,[.02,.02,.02])]}
 if k=='void_seraph':
  g=m.group_('dark_halo',(0,28,4));m.ring('void halo',3.8,28,4,.5,'dark',g);m.spin(g)
 if k=='celestial_constellation':
  g=m.group_('comet',(-12,25,6));m.star('shooting star',-12,25,6,.6,'white',g)
  m.tracks[g]={'position':[(0,[0,0,0]),(3,[0,0,0]),(3.8,[24,-4,0]),(4,[0,0,0])],
                'scale':[(0,[.01,.01,.01]),(3,[.01,.01,.01]),(3.1,[1,1,1]),(3.8,[1,1,1]),(3.9,[.01,.01,.01]),(4,[.01,.01,.01])]}
 if k in ['phoenix_ascend','storm_raven']:m.fx('ember',(0,21,6))
 if k=='frozen_wyvern':m.fx('snow',(0,23,6))
 if k=='sakura_crane':m.fx('petal',(0,23,6))
 if k=='celestial_constellation':m.fx('star',(0,25,6))


def textile(m):
 """128x192 pixel-art fabric with hand-drawn, theme-specific motifs."""
 k=m.row['key'];primary,light,edge=THEMES[m.row['theme']]
 img=Image.new('RGBA',(128,192),primary);d=ImageDraw.Draw(img)
 base=tuple(bytes.fromhex(primary[1:]));rng=np.random.default_rng(int(hashlib.sha256(k.encode()).hexdigest()[:8],16))
 for y in range(192):
  for x in range(128):
   fold=.72+.24*math.cos((x-64)/64*math.pi/2);grain=int(rng.integers(-3,4))
   img.putpixel((x,y),tuple(max(0,min(255,int(c*fold)+grain)) for c in base)+(255,))
 d.rectangle((3,3,124,188),outline=edge,width=3);d.rectangle((8,8,119,183),outline=light,width=1)
 def skull(cx,cy,s=1,col='#ebe0bc'):
  d.rectangle((cx-12*s,cy-12*s,cx+12*s,cy+8*s),fill=col)
  d.rectangle((cx-7*s,cy+8*s,cx+7*s,cy+15*s),fill=col)
  for dx in [-7,3]:d.rectangle((cx+dx*s,cy-5*s,cx+(dx+5)*s,cy+2*s),fill='#142133')
  for dx in [-5,1]:d.rectangle((cx+dx*s,cy+9*s,cx+(dx+2)*s,cy+15*s),fill='#142133')
 def star(cx,cy,r=5,col=edge):
  d.rectangle((cx-1,cy-r,cx+1,cy+r),fill=col);d.rectangle((cx-r,cy-1,cx+r,cy+1),fill=col)
 if k=='bed_wars_bed':
  d.rectangle((27,30,101,160),fill='#815133');d.rectangle((33,36,95,64),fill='#f4f3eb');d.rectangle((33,68,95,153),fill='#cf385a')
  d.rectangle((33,70,95,78),fill='#ff8798')
 elif k=='emerald_shield':
  d.polygon([(24,35),(104,35),(98,113),(64,149),(30,113)],fill=edge)
  d.polygon([(32,43),(96,43),(90,110),(64,136),(38,110)],fill='#148b69')
  d.rectangle((60,56,67,112),fill='#e4f3e7');d.rectangle((47,101,80,108),fill='#edc96a');d.rectangle((61,111,66,126),fill='#856347')
 elif k in ['void_walker','aurora_borealis']:
  for i in range(9):
   pts=[(x,35+i*13+int(14*math.sin(x/25+i*.3))) for x in range(12,117)]
   d.line(pts,fill=['#45d4c1','#697be1','#b578ea'][i%3] if k=='aurora_borealis' else ['#342348','#69348a','#a05cc5'][i%3],width=4)
  for i in range(22):star(15+(i*37)%96,15+(i*53)%159,1,'#eadffc')
 elif k=='lava_flow':
  d.rectangle((11,11,116,180),fill='#252530')
  for i in range(8):
   pts=[(17+i*13+int(7*math.sin(y/16+i)),y) for y in range(12,181)]
   d.line(pts,fill='#ee6425',width=5);d.line(pts,fill='#ffc268',width=2)
 elif k=='frozen_kingdom':
  for i in range(11):
   x=12+i*10;d.polygon([(x,13),(x+8,13),(x+4,34+(i%4)*9)],fill='#e9fdff')
  for x,y,r in [(35,68,13),(85,113,17),(46,151,9),(92,48,7)]:
   star(x,y,r,'#e2fbff');d.line((x-r,y-r,x+r,y+r),fill='#e2fbff',width=2);d.line((x-r,y+r,x+r,y-r),fill='#e2fbff',width=2)
 elif k=='golden_emperor':
  d.polygon([(63,55),(72,72),(107,48),(99,71),(117,71),(92,94),(77,95),(82,137),(64,123),(46,137),(51,95),(36,94),(11,71),(29,71),(21,48),(56,72)],fill='#f2d175')
  d.rectangle((61,57,68,102),fill='#fff0b5');d.polygon([(64,44),(80,51),(67,58)],fill='#fff0b5')
  for x in [48,60,72]:d.rectangle((x,30,x+7,42),fill='#fff0b5')
 elif k=='pirate_flag':
  d.rectangle((11,11,116,180),fill='#162034');d.line((27,52,100,135),fill='#e9e0c5',width=7);d.line((100,52,27,135),fill='#e9e0c5',width=7);skull(64,82,2)
 elif k=='cyber_glitch':
  d.rectangle((11,11,116,180),fill='#15172d')
  for i in range(32):
   x=13+(i*29)%76;y=17+(i*17)%151;d.rectangle((x,y,min(115,x+9+(i%4)*5),y+2),fill=['#47f2c4','#ff72c5','#797aef'][i%3])
  d.rectangle((36,64,93,117),outline='#62f3df',width=4)
 elif k=='sakura_storm':
  d.rectangle((11,11,116,180),fill='#29283e');d.line((25,167,77,40,100,22),fill='#876777',width=4)
  for i in range(18):
   x=19+(i*31)%91;y=23+(i*47)%141
   for dx,dy in [(0,-4),(4,0),(2,4),(-3,3),(-4,-1)]:d.ellipse((x+dx-3,y+dy-3,x+dx+3,y+dy+3),fill='#f3adca')
   d.rectangle((x-1,y-1,x+1,y+1),fill='#fff0ca')
 elif k=='thunder_god':
  for i in range(6):d.rectangle((15+i*14,27+(i%2)*6,42+i*12,45+(i%2)*6),fill='#596680')
  d.polygon([(69,47),(44,103),(62,103),(45,159),(90,88),(68,91),(84,47)],fill='#ffe76f')
 elif k=='pixel_heart':
  grid=['01100110','11111111','11111111','01111110','00111100','00011000']
  for y,line in enumerate(grid):
   for x,v in enumerate(line):
    if v=='1':d.rectangle((24+x*10,63+y*10,33+x*10,72+y*10),fill=['#ffd2e7','#ffaccf','#ff83b6','#ef5b9f','#c74085','#9d3375'][y])
 elif k=='wither_storm':
  d.rectangle((11,11,116,180),fill='#151627');d.rectangle((57,91,71,145),fill='#403454');d.rectangle((28,85,101,97),fill='#403454')
  for x,y in [(31,78),(64,62),(97,78)]:
   skull(x,y,1,'#514467')
   for dx in [-7,3]:d.rectangle((x+dx,y-5,x+dx+5,y+2),fill='#d184fc')
 elif k=='toxic_waste':
  d.polygon([(64,49),(107,123),(21,123)],fill='#d8f969');d.polygon([(64,61),(97,116),(31,116)],fill='#263f29')
  d.rectangle((61,77,67,99),fill='#dcfc75');d.rectangle((61,105,67,111),fill='#dcfc75')
  for i in range(10):
   x=18+(i*31)%92;y=24+(i*41)%145;d.ellipse((x,y,x+6,y+6),outline='#b6eb67',width=2)
 elif k=='royal_banner':
  # Crown + stylized upright lion with a curled tail.
  d.polygon([(38,39),(35,21),(50,30),(63,17),(76,30),(92,21),(88,39)],fill='#f5d174')
  d.polygon([(64+int((17 if i%2==0 else 13)*math.cos(i*math.pi/8)),67+int((17 if i%2==0 else 13)*math.sin(i*math.pi/8))) for i in range(16)],fill='#dca957');d.ellipse((54,57,74,78),fill='#ffdf88');d.rectangle((69,66,81,73),fill='#ffdf88');d.rectangle((63,61,65,64),fill='#5e3928')
  d.polygon([(55,80),(78,84),(73,121),(86,150),(69,150),(61,123),(51,146),(35,146),(49,114)],fill='#f5d174')
  d.line((76,112,101,99,104,79,95,72),fill='#f5d174',width=5);d.rectangle((87,65,98,76),fill='#f5d174')
 if k=='void_walker':
  for yy in range(155,192):
   for xx in range(128):
    pixel=img.getpixel((xx,yy));img.putpixel((xx,yy),pixel[:3]+(max(0,round(255*(191-yy)/36)),))
 for y in range(17,179,12):d.rectangle((5,y,6,y+3),fill='#f8df9c')
 m.atlas.img.paste(img,(0,128));m.atlas.regions['cloth']=[0,128,128,320]
 return img


def cape(row):
 m=Cosmetic(row);textile(m);previous=m.anchor
 for i in range(3):
  top=23-i*5.1;g=m.group_('cloth'+str(i),(0,top,3.9),previous,rotation=(-5,0,0));previous=g
  region='cloth'+str(i);m.atlas.regions[region]=[0,128+i*64,128,192+i*64]
  m.c('woven cape panel '+str(i),(-5.1,top-5.1,3.9),(5.1,top,4.22),'primary',g,{'north':region,'south':region})
  m.wave(g,'x',2.6+i*1.1);m.wave(g,'z',1.0)
  for x in [-5.12,4.88]:m.c('cape edge embroidery',(x,top-5.1,4.23),(x+.24,top,4.4),'edge',g)
 for x in [-4,4]:m.c('shoulder clasp',(x-.45,22.5,3.8),(x+.45,23.4,4.55),'gold')
 k=row['key']
 if k=='bed_wars_bed':
  m.c('cape pillow',(-3.5,20.8,4.35),(3.5,22.6,4.7),'white')
 elif k in ['frozen_kingdom','golden_emperor','royal_banner']:
  for x in [-4,-2,0,2,4]:m.c('cape tassel',(x-.22,6.8,5.4),(x+.22,8.1,5.75),'white' if k=='frozen_kingdom' else 'gold',previous)
 if k in ['lava_flow','thunder_god','cyber_glitch','toxic_waste','wither_storm']:
  for i in range(3):
   x=-3+i*3;g=m.group_('glow'+str(i),(x,15,4.6));m.star('animated emblem glow',x,15+(i%2)*2,4.6,.25,'light',g);m.wave(g,'x',.65,'scale',.7)
 if k in ['lava_flow','void_walker']:m.fx('ember' if k=='lava_flow' else 'star',(0,10,5))
 if k=='frozen_kingdom':m.fx('snow',(0,23,5))
 if k=='sakura_storm':m.fx('petal',(0,20,5))
 return m.finish()


def save_json(path,obj):
 path.parent.mkdir(parents=True,exist_ok=True);path.write_text(json.dumps(obj,indent=1))


def export(m):
 folder=m.export();row=m.row;geo_path=folder/(m.name+'.geo.json');geo=json.loads(geo_path.read_text())
 # Effects are attached to real model locators. Client-only, no per-particle network traffic.
 if m.effects:
  anchor=next(b for b in geo['minecraft:geometry'][0]['bones'] if b['name']==m.anchor)
  anchor['locators']={f'fx_{i}':[-v[0],v[1],v[2]] for i,(_,v) in enumerate(m.effects)}
  save_json(geo_path,geo)
  ap=folder/(m.name+'.animation.json');a=json.loads(ap.read_text());anim=next(iter(a['animations'].values()))
  anim['particle_effects']={str(t):[{'effect':effect,'locator':f'fx_{i}'} for i,(effect,v) in enumerate(m.effects)] for t in [0,1,2,3]}
  save_json(ap,a)
 for directory,ext in [('models/entity','.geo.json'),('animations','.animation.json'),('textures/entity','.png')]:
  target=RP/directory/(m.name+ext);target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(folder/(m.name+ext),target)
 image=e.render(m,folder)
 icon=image.crop((50,90,590,580)).resize((128,128),Image.Resampling.LANCZOS)
 icon_path=RP/(row['icon']+'.png');icon_path.parent.mkdir(parents=True,exist_ok=True);icon.save(icon_path)
 return image


def particle(effect):
 colors={'ember':([1,.7,.2,1],[.5,.1,.4,0]),'star':([.9,.8,1,1],[.5,.6,1,0]),'snow':([.8,.96,1,1],[.9,.99,1,0]),'petal':([1,.65,.8,1],[1,.85,.95,0]),'rain':([.3,.7,1,.8],[.2,.5,1,0]),'smoke':([.65,.7,.77,.55],[.35,.38,.45,0])}
 c0,c1=colors[effect];down=effect in ['snow','petal','rain'];size=.04 if effect!='smoke' else .09
 return {'format_version':'1.10.0','particle_effect':{'description':{'identifier':'arvan:cos_'+effect,'basic_render_parameters':{'material':'particles_alpha','texture':'textures/particle/arvan_cosmetic_dot'}},'components':{
 'minecraft:emitter_rate_instant':{'num_particles':2},'minecraft:emitter_lifetime_once':{'active_time':.05},
 'minecraft:emitter_shape_sphere':{'radius':.13,'direction':[0,-1 if down else 1,0]},
 'minecraft:particle_lifetime_expression':{'max_lifetime':.8},'minecraft:particle_initial_speed':.3,
 'minecraft:particle_motion_dynamic':{'linear_acceleration':[.1,-.5 if down else .12,.04]},
 'minecraft:particle_appearance_billboard':{'size':[size,size],'facing_camera_mode':'lookat_xyz','uv':{'texture_width':16,'texture_height':16,'uv':[0,0],'uv_size':[16,16]}},
 'minecraft:particle_appearance_tinting':{'color':{'interpolant':'variable.particle_age / variable.particle_lifetime','gradient':{'0.0':c0,'1.0':c1}}}
 }}}


def texture_variants(rows,textures,controllers,expressions):
 colors=['#b9324c','#3557ce','#319550','#e9cb4b','#5abbea','#edf4ef','#eb8fb3','#5a626a','#8d4bbb','#7ebc39','#202633','#e98435','#7c5034','#29989f','#b8c2c5','#c85da6']
 for row in rows:
  k=row['key'];kind=row['kind'];ctrl=controllers['controller.render.arvan_cos_'+kind]
  base=Image.open(RP/'textures/entity'/(row['model']+'.png')).convert('RGBA')
  target_array=None;paths=[];sample=''
  if k in ['bed_crown','royal_banner']:
   target_array='Array.team_'+k;sample='math.clamp(query.mark_variant,0,15)'
   for i,col in enumerate(colors):
    im=base.copy();data=np.asarray(im).copy();color=np.array(tuple(bytes.fromhex(col[1:])))
    # Only cloth/team-color pixels are recolored; gold, skin, pillow and emblems stay intact.
    data[:32,:32,:3]=color
    if k=='royal_banner':
     cloth=data[128:320,:128,:3].astype(float);mask=(cloth[:,:,1]<cloth[:,:,0]*.6)&(cloth[:,:,2]<cloth[:,:,0]*.65)&(cloth[:,:,2]>cloth[:,:,0]*.25)
     tint=np.clip((cloth[:,:,0:1]/185)*color,0,255).astype('uint8')
     data[128:320,:128,:3][mask]=tint[mask]
    alias=k+'_team_'+str(i);name=row['model']+'_team_'+str(i)
    Image.fromarray(data).save(RP/'textures/entity'/(name+'.png'));textures[alias]='textures/entity/'+name;paths.append('Texture.'+alias)
  elif k in ['phoenix_ascend','crystal_prism','neon_synthwave','lava_flow','cyber_glitch','aurora_borealis']:
   import colorsys
   target_array='Array.frames_'+k;sample='math.mod(math.floor(query.life_time*4),16)'
   for i in range(16):
    data=np.asarray(base).copy()
    if k=='lava_flow':data[140:308,12:116]=np.roll(data[140:308,12:116],round(i*168/16),axis=0)
    elif k=='cyber_glitch':
     for band in range(5):
      y=145+band*30;data[y:y+5,12:116]=np.roll(data[y:y+5,12:116],(i*7+band*9)%30,axis=1)
    elif k=='phoenix_ascend':
     mix=(1-math.cos(i*math.tau/16))/2
     for slot,cool in [(0,(103,99,230)),(1,(108,176,249)),(2,(167,228,255)),(6,(111,131,239)),(11,(116,71,191)),(14,(75,168,226))]:
      warm=data[16,slot*32+16,:3].astype(float);data[:32,slot*32:(slot+1)*32,:3]=np.round(warm*(1-mix)+np.array(cool)*mix).astype('uint8')
    else:
     for j,offset in enumerate([0,.12,.24]):
      rgb=tuple(round(v*255) for v in colorsys.hsv_to_rgb((.52+.2*math.sin(i*math.tau/16)+offset)%1,.5,1))
      data[:32,j*32:(j+1)*32,:3]=rgb
     if k=='aurora_borealis':
      data[140:308,12:116]=np.roll(data[140:308,12:116],round(i*104/16),axis=1)
     if k=='crystal_prism':data[40:72,:32,:3]=tuple(round(v*255) for v in colorsys.hsv_to_rgb((.55+i/40)%1,.3,1))
    alias=k+'_frame_'+str(i);name=row['model']+'_frame_'+str(i)
    Image.fromarray(data).save(RP/'textures/entity'/(name+'.png'));textures[alias]='textures/entity/'+name;paths.append('Texture.'+alias)
  if target_array:
   ctrl['arrays']['textures'][target_array]=paths
   previous=ctrl['textures'][0];ctrl['textures'][0]=f"({expressions[kind]} == {row['selector']}) ? {target_array}[{sample}] : ({previous})"


def pack(rows):
 save_json(RP/'manifest.json',{'format_version':2,'header':{'name':'Arvan Cosmetics V2 | 52 Wearables','description':'15 hats + 22 backblings + 15 capes; resource-driven overlay; keep existing UI packs.',
 'uuid':PACK_UUID,'version':[2,0,0],'min_engine_version':[1,20,0]},'modules':[{'type':'resources','uuid':MODULE_UUID,'version':[2,0,0]}]})
 transparent=Image.new('RGBA',(16,16));(RP/'textures/entity').mkdir(parents=True,exist_ok=True);transparent.save(RP/'textures/entity/arvan_cos_empty.png')
 save_json(RP/'models/entity/arvan_cos_empty.geo.json',{'format_version':'1.12.0','minecraft:geometry':[{'description':{'identifier':'geometry.arvan_cos_empty','texture_width':16,'texture_height':16,'visible_bounds_width':4,'visible_bounds_height':4,'visible_bounds_offset':[0,1.5,0]},'bones':[{'name':'empty','pivot':[0,0,0]}]}]})
 geometries={'empty':'geometry.arvan_cos_empty'};textures={'empty':'textures/entity/arvan_cos_empty'};animations={};animate=[];controllers={}
 expressions={'hat':'math.mod(query.variant,16)','backbling':'math.mod(math.floor(query.variant / 16),23)','cape':'math.floor(query.variant / 368)'}
 for row in rows:
  alias=row['kind']+'_'+row['key'];geometries[alias]='geometry.'+row['model'];textures[alias]='textures/entity/'+row['model']
  animations[alias]='animation.'+row['model']+'.idle';animate.append({alias:expressions[row['kind']]+' == '+str(row['selector'])})
 for kind in expressions:
  subset=[v for v in rows if v['kind']==kind];gs=['Geometry.empty']+['Geometry.'+kind+'_'+v['key'] for v in subset];ts=['Texture.empty']+['Texture.'+kind+'_'+v['key'] for v in subset]
  controllers['controller.render.arvan_cos_'+kind]={'arrays':{'geometries':{'Array.cos_geo':gs},'textures':{'Array.cos_tex':ts}},
    'part_visibility':[{'*':'query.distance_from_camera > 2.15'}],
    'geometry':'Array.cos_geo['+expressions[kind]+']','materials':[{'*':'Material.default'}],'textures':['Array.cos_tex['+expressions[kind]+']']}
 texture_variants(rows,textures,controllers,expressions)
 animations['pose']='animation.arvan_cos.pose';animate.insert(0,'pose')
 save_json(RP/'animations/arvan_cos_pose.animation.json',{'format_version':'1.8.0','animations':{'animation.arvan_cos.pose':{'loop':True,'bones':{
 'root':{'rotation':['query.is_sneaking ? 28 : 0',0,0],'position':[0,'query.is_sneaking ? 1.25 : 0','query.is_sneaking ? 9 : 0']},
 'head_anchor':{'relative_to':{'rotation':'entity'},'rotation':['query.target_x_rotation',0,0],'position':[0,'query.is_sneaking ? -3 : 0',0]},
 'back_anchor':{'position':[0,'query.is_sneaking ? -2 : 0',0]},'cape_anchor':{'position':[0,'query.is_sneaking ? -2 : 0',0]}}}}})
 effects=['ember','star','snow','petal','rain','smoke']
 save_json(RP/'entity/arvan_cosmetic_overlay.entity.json',{'format_version':'1.10.0','minecraft:client_entity':{'description':{
 'identifier':'arvan:cosmetic_overlay_v2','materials':{'default':'entity_alphablend'},'textures':textures,'geometry':geometries,
 'animations':animations,'scripts':{'scale':'0.9375','animate':animate},'particle_effects':{k:'arvan:cos_'+k for k in effects},'render_controllers':list(controllers)}}})
 save_json(RP/'render_controllers/arvan_cosmetics.render_controllers.json',{'format_version':'1.8.0','render_controllers':controllers})
 for effect in effects:save_json(RP/'particles'/('arvan_cos_'+effect+'.json'),particle(effect))
 dot=Image.new('RGBA',(16,16));d=ImageDraw.Draw(dot);d.rectangle((6,1,9,14),fill='white');d.rectangle((1,6,14,9),fill='white')
 (RP/'textures/particle').mkdir(parents=True,exist_ok=True);dot.save(RP/'textures/particle/arvan_cosmetic_dot.png')
 icon=Image.new('RGB',(256,256),'#172034');d=ImageDraw.Draw(icon);d.rectangle((8,8,247,247),outline='#e6bf7b',width=4)
 d.text((27,50),'ARVAN',font=e.font(43),fill='#e6bf7b');d.text((26,112),'COSMETICS',font=e.font(29),fill='white');d.text((30,166),'52 / COLLECTION 02',font=e.font(18),fill='#9ee0e7');icon.save(RP/'pack_icon.png')
 # Preserve 512px authoring atlases, but quarter runtime texture memory with 256px atlases.
 for p in (RP/'textures/entity').glob('*.png'):
  im=Image.open(p)
  if im.size==(512,512):im.resize((256,256),Image.Resampling.NEAREST).save(p)
 for p in (RP/'models/entity').glob('*.geo.json'):
  obj=json.loads(p.read_text())
  for geo in obj['minecraft:geometry']:
   desc=geo['description']
   if desc['texture_width']!=512:continue
   desc['texture_width']=desc['texture_height']=256
   for bone in geo['bones']:
    for cube in bone.get('cubes',[]):
     for face in cube['uv'].values():
      face['uv']=[v/2 for v in face['uv']];face['uv_size']=[v/2 for v in face['uv_size']]
  save_json(p,obj)
 with zipfile.ZipFile(ROOT/'ArvanCosmeticsV2.zip','w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for p in sorted(RP.rglob('*')):
   if p.is_file():z.write(p,p.relative_to(RP))
 shutil.copyfile(ROOT/'ArvanCosmeticsV2.zip',ROOT/'ArvanCosmeticsV2.mcpack')


def main():
 rows=entries();e.OUT.mkdir(exist_ok=True,parents=True);(ROOT/'previews').mkdir(exist_ok=True)
 images={kind:[] for kind in ['hat','backbling','cape']}
 for row in rows:
  m={'hat':hat,'backbling':back,'cape':cape}[row['kind']](row)
  assert len(m.cubes)<220,(row['key'],len(m.cubes))
  image=export(m);images[row['kind']].append(image.resize((320,340),Image.Resampling.LANCZOS))
  print(row['kind'],row['key'],len(m.cubes),'cubes')
 for kind,tiles in images.items():
  cols=5;sheet=Image.new('RGB',(cols*320,math.ceil(len(tiles)/cols)*340),'#131f2c')
  for i,tile in enumerate(tiles):sheet.paste(tile,((i%cols)*320,(i//cols)*340))
  sheet.save(ROOT/'previews'/(kind+'_collection.jpg'),quality=94)
 pack(rows)
 save_json(ROOT/'catalog.json',{'schema_version':2,'pack_uuid':PACK_UUID,'actor_identifier':'arvan:cosmetic_overlay_v2','items':rows})
 print('Generated 52 editable models + texture atlases + animations + resource pack')


if __name__=='__main__':main()
