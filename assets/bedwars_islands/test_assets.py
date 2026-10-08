"""Artifact/geometry regression tests. Run python -m unittest test_assets.py."""
import base64
import io
import json
from pathlib import Path
import unittest
import zipfile
import numpy as np
from PIL import Image
import generate as g
import engine as e


class IslandAssets(unittest.TestCase):
    def test_four_distinct_character_counts_and_one_bed_each(self):
        for spec in g.MODES:
            m=g.make_model(*spec)
            g.validate(m,e.OUT/m.name)
            vertices=np.concatenate([e.world_vertices(m,c) for c in m.cubes])
            size=vertices.max(0)-vertices.min(0)
            self.assertLess(size[0],27)
            self.assertLess(size[2],32)
            self.assertLess(size[1],33)

    def test_embedded_textures_and_uvs(self):
        for mode,*_ in g.MODES:
            name='arvan_bw_'+mode
            folder=e.OUT/name
            bb=json.loads((folder/(name+'.bbmodel')).read_text())
            raw=base64.b64decode(bb['textures'][0]['source'].split(',',1)[1])
            self.assertEqual(raw,(folder/(name+'.png')).read_bytes())
            self.assertEqual(Image.open(io.BytesIO(raw)).size,(512,512))
            for cube in bb['elements']:
                for face in cube['faces'].values():
                    self.assertTrue(all(0<=x<=512 for x in face['uv']))
                    self.assertEqual(face['texture'],0)

    def test_resource_links_and_animation_mirroring(self):
        rp=g.HERE/'ArvanBedwarsIslands_RP'
        manifest=json.loads((rp/'manifest.json').read_text())
        self.assertNotEqual(manifest['header']['uuid'],manifest['modules'][0]['uuid'])
        for mode,*_ in g.MODES:
            name='arvan_bw_'+mode
            desc=json.loads((rp/'entity'/(name+'.entity.json')).read_text())['minecraft:client_entity']['description']
            self.assertEqual(desc['identifier'],'arvan:bw_'+mode+'_island')
            geo=json.loads((rp/'models/entity'/(name+'.geo.json')).read_text())['minecraft:geometry'][0]
            self.assertEqual(desc['geometry']['default'],geo['description']['identifier'])
            self.assertTrue((rp/(desc['textures']['default']+'.png')).is_file())
            bones={b['name'] for b in geo['bones']}
            animations=json.loads((rp/'animations'/(name+'.animation.json')).read_text())['animations']
            bb=json.loads((e.OUT/name/(name+'.bbmodel')).read_text())
            animation=bb['animations'][0]
            exported=animations[animation['name']]
            self.assertEqual(desc['animations']['idle'],animation['name'])
            self.assertTrue(exported['loop'])
            for animator in animation['animators'].values():
                self.assertIn(animator['name'],bones)
                for key in animator['keyframes']:
                    channel=key['channel']
                    values=[float(key['data_points'][0][axis]) for axis in 'xyz']
                    signs={'rotation':[-1,-1,1],'position':[-1,1,1],'scale':[1,1,1]}[channel]
                    np.testing.assert_allclose(np.array(values)*signs,exported['bones'][animator['name']][channel][str(key['time'])])

    def test_zip_and_gif_durations(self):
        with zipfile.ZipFile(g.HERE/'ArvanBedwarsIslands_RP.zip') as z:
            self.assertIsNone(z.testzip())
            self.assertIn('manifest.json',z.namelist())
        with zipfile.ZipFile(g.HERE/'Arvan_Bedwars_Islands.zip') as z:
            self.assertIsNone(z.testzip())
            self.assertEqual(sum(n.endswith('.bbmodel') for n in z.namelist()),4)
            self.assertIn('plugins/ArvanBedwarsIslands.phar',z.namelist())
        for mode,*_ in g.MODES:
            with Image.open(e.OUT/('arvan_bw_'+mode)/'animation_preview.gif') as gif:
                duration=0
                for i in range(gif.n_frames):
                    gif.seek(i);duration+=gif.info['duration']
                self.assertEqual(duration,4000)


if __name__=='__main__':unittest.main()
