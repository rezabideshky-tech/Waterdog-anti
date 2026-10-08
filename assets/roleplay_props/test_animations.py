"""Run python -m unittest discover -s assets/roleplay_props -p 'test_*.py'."""
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import numpy as np
import generate as g


class AnimationTests(unittest.TestCase):
    def setUp(self):
        self.models = [g.pump(), g.case()]

    def test_tracks_are_valid_and_loop_seams_match(self):
        for model in self.models:
            self.assertGreaterEqual(len(model.animations), 2)
            names = [a['name'] for a in model.animations]
            self.assertEqual(len(names), len(set(names)))
            for a in model.animations:
                for bone, channels in a['tracks'].items():
                    self.assertIn(bone, model.groups)
                    for channel, keys in channels.items():
                        self.assertIn(channel, ['rotation', 'position', 'scale'])
                        times = [k[0] for k in keys]
                        self.assertEqual(times, sorted(set(times)))
                        self.assertEqual(times[0], 0)
                        self.assertEqual(times[-1], a['length'])
                        self.assertTrue(np.isfinite([v for _, v in keys]).all())
                        if a['loop'] == 'loop':
                            np.testing.assert_allclose(keys[0][1], keys[-1][1])

    def test_both_exports_have_identical_animation_tracks(self):
        with tempfile.TemporaryDirectory() as directory, patch.object(g, 'OUT', Path(directory)):
            for m in self.models:
                folder = m.export()
                bb = json.loads((folder/(m.name+'.bbmodel')).read_text())
                bedrock = json.loads((folder/(m.name+'.animation.json')).read_text())['animations']
                self.assertEqual(len(bb['animations']), len(m.animations))
                for animation in bb['animations']:
                    other = bedrock[animation['name']]
                    self.assertEqual(animation['length'], other['animation_length'])
                    self.assertEqual(other['loop'], {'loop': True, 'hold': 'hold_on_last_frame', 'once': False}[animation['loop']])
                    for bone_id, animator in animation['animators'].items():
                        self.assertEqual(bone_id, m.groups[animator['name']]['uuid'])
                        tracks = other['bones'][animator['name']]
                        for key in animator['keyframes']:
                            values = [float(key['data_points'][0][axis]) for axis in 'xyz']
                            channel = key['channel']
                            signs = {'rotation': [-1,-1,1], 'position': [-1,1,1], 'scale': [1,1,1]}[channel]
                            np.testing.assert_allclose(np.array(values)*signs, tracks[channel][str(key['time'])])

    def test_lid_opens_and_closes_at_rear_hinge(self):
        m = self.models[1]
        lid = next(c for c in m.cubes if c['name'] == 'lid exterior shell')
        rest = g.world_vertices(m, lid)
        closed = g.world_vertices(m, lid, g.animation_pose(m, ['close'], 1.2))
        opened = g.world_vertices(m, lid, g.animation_pose(m, ['open'], 1.4))
        np.testing.assert_allclose(rest, opened, atol=1e-8)
        np.testing.assert_allclose(closed.min(0), [-15, 7.2, -8], atol=1e-8)
        np.testing.assert_allclose(closed.max(0), [15, 8.6, 8], atol=1e-8)
        self.assertGreater(rest[:,1].max(), 22)
        for c in m.cubes:
            if c['group'] != 'lid_hinge':
                np.testing.assert_allclose(g.world_vertices(m,c), g.world_vertices(m,c,g.animation_pose(m,['close'],.6)))

    def test_nozzle_moves_but_hose_attachment_and_dock_do_not(self):
        m = self.models[0]
        pose = g.animation_pose(m, ['nozzle_demo'], .6)
        body = next(c for c in m.cubes if c['group']=='left_nozzle_1' and c['name']=='nozzle body')
        self.assertGreater(np.linalg.norm(g.world_vertices(m,body,pose)-g.world_vertices(m,body)), 1)
        # Every rotation is around the fixed hose connector, with no translation.
        for bone in ['left_nozzle_1','left_nozzle_2','right_nozzle_1','right_nozzle_2']:
            self.assertNotIn('position', pose.get(bone, {}))
        for c in m.cubes:
            if c['group'] in ['hoses','cabinet']:
                np.testing.assert_allclose(g.world_vertices(m,c), g.world_vertices(m,c,pose))

    def test_idle_is_visibly_animated_and_lid_stays_unlettered(self):
        m = self.models[0]
        for bone, time in [('status_indicator',1.1), ('led_scan',1.2)]:
            c = next(c for c in m.cubes if c['group']==bone)
            self.assertFalse(np.allclose(g.world_vertices(m,c),g.world_vertices(m,c,g.animation_pose(m,['idle'],time))))
        m = self.models[1]
        self.assertEqual(m.atlas.img.crop(m.atlas.regions['lid_label']).getextrema(), ((50,50),(63,63),(52,52)))


if __name__ == '__main__':
    unittest.main()
