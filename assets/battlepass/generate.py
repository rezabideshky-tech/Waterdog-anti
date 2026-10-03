#!/usr/bin/env python3
"""Generates the Battle Pass Blockbench model, Bedrock geometry, animation and texture."""
import base64, io, json, math, os, uuid
from PIL import Image, ImageDraw

OUT = os.path.dirname(os.path.abspath(__file__))
S = 128  # texture size
img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
d = ImageDraw.Draw(img)

GOLD = [(255, 236, 140), (250, 204, 60), (222, 160, 30), (160, 100, 20)]
PURP = [(40, 10, 80), (90, 30, 160), (150, 70, 230), (210, 150, 255)]


def lerp(a, b, t):
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))


def gradient(box, top, bot):
    x0, y0, x1, y1 = box
    for y in range(y0, y1):
        d.line([(x0, y), (x1 - 1, y)], fill=lerp(top, bot, (y - y0) / max(1, y1 - y0 - 1)))


def frame(box, w=3):
    x0, y0, x1, y1 = box
    for i in range(w):
        c = GOLD[min(i, 3)] if i < w - 1 else GOLD[3]
        d.rectangle([x0 + i, y0 + i, x1 - 1 - i, y1 - 1 - i], outline=c)
    d.line([(x0, y0), (x1 - 1, y0)], fill=GOLD[0])
    d.line([(x0, y0), (x0, y1 - 1)], fill=GOLD[0])


def star(cx, cy, r, fill, outline):
    pts = []
    for i in range(10):
        a = -math.pi / 2 + i * math.pi / 5
        rr = r if i % 2 == 0 else r * 0.45
        pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
    d.polygon(pts, fill=fill, outline=outline)


# pixel font 3x5
FONT = {
    "B": ["110", "101", "110", "101", "110"], "A": ["010", "101", "111", "101", "101"],
    "T": ["111", "010", "010", "010", "010"], "L": ["100", "100", "100", "100", "111"],
    "E": ["111", "100", "110", "100", "111"], "P": ["110", "101", "110", "100", "100"],
    "S": ["011", "100", "010", "001", "110"], "I": ["111", "010", "010", "010", "111"],
    "O": ["010", "101", "101", "101", "010"], "N": ["101", "111", "111", "101", "101"],
    "1": ["010", "110", "010", "010", "111"], " ": ["000"] * 5,
}


def text(s, cx, y, col, shadow, scale=1):
    w = len(s) * 4 * scale - scale
    x = cx - w // 2
    for ch in s:
        g = FONT[ch]
        for ry, row in enumerate(g):
            for rx, v in enumerate(row):
                if v == "1":
                    px, py = x + rx * scale, y + ry * scale
                    d.rectangle([px + 1, py + 1, px + scale, py + scale], fill=shadow)
                    d.rectangle([px, py, px + scale - 1, py + scale - 1], fill=col)
        x += 4 * scale


# ---- FRONT (0,0)-(48,64)
gradient((0, 0, 48, 64), PURP[2], PURP[0])
for i in range(0, 120, 6):  # diagonal shine stripes
    d.line([(i - 60, 64), (i, 0)], fill=(120, 50, 200, 255))
frame((0, 0, 48, 64), 3)
text("BATTLE", 24, 6, GOLD[0], GOLD[3])
text("PASS", 24, 13, GOLD[0], GOLD[3], 1)
d.ellipse([10, 21, 38, 49], fill=PURP[0], outline=GOLD[2])
d.ellipse([12, 23, 36, 47], outline=PURP[3])
star(24, 35, 11, GOLD[1], GOLD[3])
star(24, 35, 5, GOLD[0], GOLD[0])
text("SEASON 1", 24, 53, (255, 255, 255), PURP[0])
for (x, y) in [(6, 22), (41, 24), (8, 46), (40, 45)]:  # sparkles
    d.point([(x, y)], fill=(255, 255, 255)); d.point([(x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)], fill=PURP[3])

# ---- BACK (48,0)-(96,64)
gradient((48, 0, 96, 64), PURP[1], PURP[0])
for yy in range(4, 64, 8):
    for xx in range(52, 96, 8):
        star(xx + (4 if (yy // 8) % 2 else 0), yy, 2, PURP[2], None)
frame((48, 0, 96, 64), 3)
text("PASS", 72, 29, GOLD[1], GOLD[3], 2)

# ---- EDGE gold (96,0)-(128,64)
gradient((96, 0, 128, 64), GOLD[0], GOLD[2])
for y in range(0, 64, 4):
    d.line([(96, y), (127, y)], fill=GOLD[3])

# ---- GEM (0,64)-(32,96)
gradient((0, 64, 32, 96), (120, 255, 255), (20, 120, 220))
d.line([(0, 64), (31, 95)], fill=(220, 255, 255)); d.line([(31, 64), (0, 95)], fill=(60, 180, 240))
d.rectangle([0, 64, 31, 95], outline=(10, 60, 140))

# ---- PEDESTAL (32,64)-(96,96)
gradient((32, 64, 96, 96), (70, 60, 90), (30, 25, 45))
for y in range(64, 96, 8):
    d.line([(32, y), (95, y)], fill=(20, 15, 30))
d.rectangle([32, 64, 95, 65], fill=GOLD[1])
for x in range(36, 96, 10):
    d.point([(x, 70 + (x % 7))], fill=PURP[3])

# ---- RIBBON (96,64)-(128,96)
gradient((96, 64, 128, 96), (255, 80, 120), (170, 20, 60))
d.line([(96, 66), (127, 66)], fill=(255, 170, 190))

png = os.path.join(OUT, "battle_pass.png")
img.save(png)

# ---------- geometry (units = pixels, texture uv in 128 space) ----------
REG = {  # name: (x, y, w, h)
    "front": (0, 0, 48, 64), "back": (48, 0, 48, 64), "edge": (96, 0, 32, 64),
    "gem": (0, 64, 32, 32), "ped": (32, 64, 64, 32), "rib": (96, 64, 32, 32),
}


def faces(n, s, e, w, u, dn):
    return dict(north=n, south=s, east=e, west=w, up=u, down=dn)


cubes = [  # name, bone, from, to, face regions
    ("card", "pass", (-6, 10, -0.75), (6, 26, 0.75), faces("front", "back", "edge", "edge", "edge", "edge")),
    ("gem", "pass", (-1.5, 26, -1.5), (1.5, 29, 1.5), faces(*["gem"] * 6)),
    ("ribbon_l", "pass", (-8, 18, -0.5), (-6, 22, 0.5), faces(*["rib"] * 6)),
    ("ribbon_r", "pass", (6, 18, -0.5), (8, 22, 0.5), faces(*["rib"] * 6)),
    ("base", "pedestal", (-6, 0, -6), (6, 2, 6), faces(*["ped"] * 6)),
    ("column", "pedestal", (-2.5, 2, -2.5), (2.5, 7, 2.5), faces(*["ped"] * 6)),
    ("top", "pedestal", (-4, 7, -4), (4, 8, 4), faces(*["ped"] * 6)),
]

# Blockbench project
elements, bone_children = [], {"pedestal": [], "pass": []}
for name, bone, f, t, fc in cubes:
    uid = str(uuid.uuid4())
    bone_children[bone].append(uid)
    elements.append({
        "name": name, "type": "cube", "uuid": uid, "box_uv": False, "rescale": False,
        "from": list(f), "to": list(t), "origin": [0, 0, 0], "color": 0,
        "faces": {k: {"uv": [REG[v][0], REG[v][1], REG[v][0] + REG[v][2], REG[v][1] + REG[v][3]], "texture": 0}
                  for k, v in fc.items()},
    })

with open(png, "rb") as fh:
    b64 = base64.b64encode(fh.read()).decode()

anim_uuid = str(uuid.uuid4())
bone_uuid = {"pedestal": str(uuid.uuid4()), "pass": str(uuid.uuid4())}
bb = {
    "meta": {"format_version": "4.10", "model_format": "bedrock", "box_uv": False},
    "name": "battle_pass", "model_identifier": "battle_pass",
    "visible_box": [2, 3, 1], "variable_placeholders": "", "variable_placeholder_buttons": [],
    "resolution": {"width": S, "height": S},
    "elements": elements,
    "outliner": [
        {"name": "pedestal", "origin": [0, 0, 0], "uuid": bone_uuid["pedestal"], "export": True,
         "isOpen": True, "visibility": True, "children": bone_children["pedestal"]},
        {"name": "pass", "origin": [0, 18, 0], "uuid": bone_uuid["pass"], "export": True,
         "isOpen": True, "visibility": True, "children": bone_children["pass"]},
    ],
    "textures": [{
        "path": "", "name": "battle_pass.png", "folder": "", "namespace": "", "id": "0",
        "width": S, "height": S, "uv_width": S, "uv_height": S, "particle": False,
        "render_mode": "default", "render_sides": "auto", "frame_time": 1, "frame_order_type": "loop",
        "visible": True, "internal": True, "saved": True, "uuid": str(uuid.uuid4()),
        "source": "data:image/png;base64," + b64,
    }],
    "animations": [{
        "uuid": anim_uuid, "name": "animation.battle_pass.idle", "loop": "loop", "override": False,
        "length": 4, "snapping": 24, "selected": False, "anim_time_update": "", "blend_weight": "",
        "start_delay": "", "loop_delay": "",
        "animators": {bone_uuid["pass"]: {"name": "pass", "type": "bone", "keyframes": [
            {"channel": "rotation", "data_points": [{"x": "0", "y": "query.anim_time * 90", "z": "0"}],
             "uuid": str(uuid.uuid4()), "time": 0, "color": -1, "interpolation": "linear"},
            {"channel": "position", "data_points": [{"x": "0", "y": "math.sin(query.anim_time * 90) * 1.5", "z": "0"}],
             "uuid": str(uuid.uuid4()), "time": 0, "color": -1, "interpolation": "linear"},
        ]}},
    }],
}
json.dump(bb, open(os.path.join(OUT, "battle_pass.bbmodel"), "w"), indent=1)

# Bedrock geometry (north = -Z is the front face)
geo_bones = []
for bone, pivot in (("pedestal", [0, 0, 0]), ("pass", [0, 18, 0])):
    cs = []
    for name, b, f, t, fc in cubes:
        if b != bone:
            continue
        cs.append({
            "origin": list(f), "size": [t[i] - f[i] for i in range(3)],
            "uv": {k: {"uv": [REG[v][0], REG[v][1]], "uv_size": [REG[v][2], REG[v][3]]} for k, v in fc.items()},
        })
    geo_bones.append({"name": bone, "pivot": pivot, "cubes": cs})
geo = {"format_version": "1.12.0", "minecraft:geometry": [{
    "description": {"identifier": "geometry.battle_pass", "texture_width": S, "texture_height": S,
                    "visible_bounds_width": 3, "visible_bounds_height": 3, "visible_bounds_offset": [0, 1, 0]},
    "bones": geo_bones}]}
json.dump(geo, open(os.path.join(OUT, "battle_pass.geo.json"), "w"), indent=2)

anim = {"format_version": "1.8.0", "animations": {"animation.battle_pass.idle": {
    "loop": True, "bones": {"pass": {
        "rotation": [0, "query.anim_time * 90", 0],
        "position": [0, "math.sin(query.anim_time * 90) * 1.5", 0]}}}}}
json.dump(anim, open(os.path.join(OUT, "battle_pass.animation.json"), "w"), indent=2)

# preview (texture x4)
img.resize((S * 4, S * 4), Image.NEAREST).save(os.path.join(OUT, "preview_texture.png"))
print("done")
