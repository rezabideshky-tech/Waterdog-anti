#!/usr/bin/env python3
"""BedWars Battle Pass: builds texture, Blockbench model, and a Bedrock resource pack (.mcpack + .zip)."""
import base64, json, math, os, shutil, uuid, zipfile
from PIL import Image, ImageDraw

OUT = os.path.dirname(os.path.abspath(__file__))
S = 128
img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
d = ImageDraw.Draw(img)

GOLD = [(255, 240, 150), (252, 206, 60), (220, 150, 25), (140, 85, 15)]
RED = [(255, 110, 100), (220, 40, 45), (140, 15, 25), (60, 5, 12)]
BLUE = [(120, 190, 255), (40, 110, 230), (20, 50, 150), (8, 18, 60)]
WHITE, BLACK = (255, 255, 255), (15, 10, 20)


def lerp(a, b, t):
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))


def grad(box, top, bot):
    x0, y0, x1, y1 = box
    for y in range(y0, y1):
        d.line([(x0, y), (x1 - 1, y)], fill=lerp(top, bot, (y - y0) / max(1, y1 - y0 - 1)))


def frame(box):
    x0, y0, x1, y1 = box
    d.rectangle([x0, y0, x1 - 1, y1 - 1], outline=GOLD[3])
    d.rectangle([x0 + 1, y0 + 1, x1 - 2, y1 - 2], outline=GOLD[1])
    d.rectangle([x0 + 2, y0 + 2, x1 - 3, y1 - 3], outline=GOLD[2])
    d.line([(x0 + 1, y0 + 1), (x1 - 2, y0 + 1)], fill=GOLD[0])
    for cx, cy in [(x0 + 1, y0 + 1), (x1 - 4, y0 + 1), (x0 + 1, y1 - 4), (x1 - 4, y1 - 4)]:
        d.rectangle([cx, cy, cx + 2, cy + 2], fill=(90, 255, 140))  # emerald corners
        d.point([(cx, cy)], fill=WHITE)


def noise(box, cols, step=3):
    x0, y0, x1, y1 = box
    for y in range(y0, y1):
        for x in range(x0, x1):
            h = (x * 73856093 ^ y * 19349663) & 0xFF
            if h % step == 0:
                d.point([(x, y)], fill=cols[h % len(cols)])


FONT = {
    "B": ["110", "101", "110", "101", "110"], "A": ["010", "101", "111", "101", "101"],
    "T": ["111", "010", "010", "010", "010"], "L": ["100", "100", "100", "100", "111"],
    "E": ["111", "100", "110", "100", "111"], "P": ["110", "101", "110", "100", "100"],
    "S": ["011", "100", "010", "001", "110"], "D": ["110", "101", "101", "101", "110"],
    "W": ["101", "101", "111", "111", "101"], "R": ["110", "101", "110", "101", "101"],
    "O": ["010", "101", "101", "101", "010"], "N": ["101", "111", "111", "101", "101"],
    "I": ["111", "010", "010", "010", "111"], "G": ["011", "100", "101", "101", "011"],
    "M": ["101", "111", "111", "101", "101"], "V": ["101", "101", "101", "101", "010"],
    "1": ["010", "110", "010", "010", "111"], " ": ["000"] * 5,
}


def text(s, cx, y, col, sh, sc=1):
    x = cx - (len(s) * 4 * sc - sc) // 2
    for ch in s:
        for ry, row in enumerate(FONT[ch]):
            for rx, v in enumerate(row):
                if v == "1":
                    px, py = x + rx * sc, y + ry * sc
                    d.rectangle([px + 1, py + 1, px + sc, py + sc], fill=sh)
                    d.rectangle([px, py, px + sc - 1, py + sc - 1], fill=col)
        x += 4 * sc


def bed_icon(x, y):  # 20x10 pixel-art bed
    d.rectangle([x, y + 3, x + 19, y + 7], fill=RED[1])
    d.rectangle([x, y + 3, x + 19, y + 3], fill=RED[0])
    d.rectangle([x, y + 1, x + 6, y + 5], fill=WHITE)
    d.rectangle([x, y + 1, x + 6, y + 1], fill=(230, 230, 240))
    d.rectangle([x + 7, y + 4, x + 19, y + 7], fill=RED[1])
    d.line([(x + 7, y + 7), (x + 19, y + 7)], fill=RED[2])
    for lx in (x, x + 19):
        d.rectangle([lx, y + 8, lx, y + 9], fill=(110, 70, 35))
    d.rectangle([x - 1, y, x + 20, y + 10], outline=BLACK)


def sword(x0, y0, x1, y1, blade, hilt):
    n = max(abs(x1 - x0), abs(y1 - y0))
    for i in range(n + 1):
        px, py = x0 + (x1 - x0) * i // n, y0 + (y1 - y0) * i // n
        c = blade if i < n * 0.72 else hilt
        d.rectangle([px, py, px + 1, py + 1], fill=c)
        if i < n * 0.72:
            d.point([(px, py)], fill=WHITE)
    gx, gy = x0 + (x1 - x0) * 72 // 100, y0 + (y1 - y0) * 72 // 100
    d.line([(gx - 3, gy + 3 * (1 if x1 > x0 else -1) * -1), (gx + 3, gy - 3 * (1 if x1 > x0 else -1) * -1)], fill=GOLD[2], width=2)



SW_OUT, SW_HI, SW_MID, SW_DK = (10, 40, 50), (220, 255, 255), (90, 230, 230), (30, 140, 160)
SW_H, SW_HD = (120, 75, 35), (70, 40, 20)


def sword_pixels():
    """Classic 16x16 diamond sword sprite -> {(x,y): color}, blade pointing to top-right."""
    px = {}
    for i in range(10):  # blade
        x, y = 5 + i, 10 - i
        for (dx, dy), c in {(0, 0): SW_MID, (1, 0): SW_HI, (0, 1): SW_DK, (-1, 0): SW_OUT,
                            (0, -1): SW_OUT, (1, 1): SW_OUT, (-1, 1): SW_OUT}.items():
            k = (x + dx, y + dy)
            if c != SW_OUT or k not in px:
                px[k] = c
    px[(15, 0)] = SW_OUT
    for k, c in {(2, 9): SW_OUT, (3, 10): SW_HD, (4, 11): SW_HD, (5, 12): SW_HD, (6, 13): SW_OUT,
                 (3, 9): SW_OUT, (6, 12): SW_OUT, (4, 10): SW_HD, (5, 11): SW_HD}.items():
        px[k] = c  # crossguard
    for k, c in {(3, 12): SW_H, (2, 13): SW_H, (4, 13): SW_OUT, (3, 14): SW_OUT, (1, 13): SW_OUT,
                 (2, 12): SW_OUT, (1, 14): SW_HD, (0, 15): SW_OUT, (0, 14): SW_OUT, (1, 15): SW_OUT}.items():
        px[k] = c  # handle + pommel
    return px


SWORD = sword_pixels()


def draw_sprite(ox, oy, pix, mirror=False, scale=1):
    for (x, y), c in pix.items():
        if 0 <= x < 16 and 0 <= y < 16:
            xx = (15 - x) if mirror else x
            d.rectangle([ox + xx * scale, oy + y * scale, ox + xx * scale + scale - 1, oy + y * scale + scale - 1], fill=c)


def gemtex(box, light, mid, dark):
    x0, y0, x1, y1 = box
    grad(box, light, dark)
    cx, cy = (x0 + x1) // 2, (y0 + y1) // 2
    d.polygon([(cx, y0 + 2), (x1 - 3, cy), (cx, y1 - 3), (x0 + 2, cy)], fill=mid, outline=light)
    d.line([(x0 + 4, y0 + 4), (cx - 1, y0 + 4)], fill=WHITE)
    d.rectangle([x0, y0, x1 - 1, y1 - 1], outline=dark)


# ---------- FRONT (0,0,48,64) : red team ----------
grad((0, 0, 48, 64), RED[1], RED[3])
for i in range(-64, 64, 8):
    d.line([(i, 64), (i + 64, 0)], fill=(170, 25, 40), width=2)
# glow behind logo
for r, c in [(18, (170, 35, 50)), (14, (210, 70, 60)), (9, (250, 140, 70))]:
    d.ellipse([24 - r, 34 - r * 0.75, 24 + r, 34 + r * 0.75], fill=c)


def outlined(s_, cx, y, col, sc):
    for ox, oy in [(-1, 0), (1, 0), (0, -1), (0, 1), (-1, -1), (1, 1), (-1, 1), (1, -1)]:
        text(s_, cx + ox, y + oy, BLACK, BLACK, sc)
    text(s_, cx, y, col, GOLD[3], sc)


outlined("ARVAN", 24, 25, GOLD[0], 2)
d.line([(6, 37), (41, 37)], fill=GOLD[1])
d.rectangle([7, 39, 40, 46], fill=BLACK)
text("GAMING", 24, 40, WHITE, RED[2])
d.line([(6, 48), (41, 48)], fill=GOLD[1])
star_pts = [(24, 20)]
d.rectangle([3, 3, 44, 18], fill=BLACK)
d.rectangle([3, 18, 44, 18], fill=GOLD[2])
text("BATTLE", 24, 5, GOLD[0], GOLD[3])
text("PASS", 24, 11, WHITE, RED[2])
d.rectangle([3, 50, 44, 60], fill=BLACK)
d.rectangle([3, 50, 44, 50], fill=GOLD[2])
text("SEASON 1", 24, 53, (110, 255, 150), (10, 70, 30))
for x, y in [(6, 21), (42, 22), (5, 44), (42, 44)]:
    d.point([(x, y)], fill=WHITE)
    d.point([(x - 1, y), (x + 1, y), (x, y - 1), (x, y + 1)], fill=GOLD[0])
frame((0, 0, 48, 64))

# ---------- BACK (48,0,96,64) : blue team wool ----------
grad((48, 0, 96, 64), BLUE[1], BLUE[3])
for yy in range(0, 64, 8):
    for xx in range(48, 96, 8):
        if ((xx // 8) + (yy // 8)) % 2:
            d.rectangle([xx, yy, xx + 7, yy + 7], fill=(30, 80, 190))
noise((48, 0, 96, 64), [(60, 130, 240), (20, 50, 140)], 5)
d.rectangle([52, 22, 91, 42], fill=BLACK)
text("PASS", 72, 27, GOLD[0], GOLD[3], 2)
frame((48, 0, 96, 64))

# ---------- EDGE (96,0,128,64) ----------
grad((96, 0, 128, 64), GOLD[0], GOLD[2])
for y in range(0, 64, 4):
    d.line([(96, y), (127, y)], fill=GOLD[3])
    d.point([(96 + (y * 7) % 32, y + 2)], fill=WHITE)

# ---------- small textures (16x16) row y=64 ----------
gemtex((0, 64, 16, 80), (170, 255, 190), (40, 210, 100), (5, 90, 40))     # emerald
gemtex((16, 64, 32, 80), (220, 255, 255), (80, 220, 230), (20, 110, 140))  # diamond
# end stone (top of island) 32,64
grad((32, 64, 48, 80), (238, 236, 175), (215, 210, 150)); noise((32, 64, 48, 80), [(200, 195, 130), (250, 250, 200)], 3)
# grass top 48,64
grad((48, 64, 64, 80), (110, 190, 70), (85, 160, 50)); noise((48, 64, 64, 80), [(70, 140, 40), (140, 210, 90)], 2)
# grass side 64,64
grad((64, 64, 80, 80), (135, 95, 60), (100, 70, 45)); noise((64, 64, 80, 80), [(80, 55, 35), (160, 120, 80)], 3)
for x in range(64, 80):
    d.line([(x, 64), (x, 66 + (x * 5) % 3)], fill=(100, 180, 60))
# red wool 80,64 / blue wool 96,64 / white wool 112,64
for x0, pal in [(80, RED), (96, BLUE), (112, [(250, 250, 250), (235, 235, 240), (210, 210, 220)])]:
    grad((x0, 64, x0 + 16, 80), pal[0], pal[2]); noise((x0, 64, x0 + 16, 80), [pal[1], pal[2]], 2)
# row y=80: wood 0, gold block 16, iron blade 32, hilt 48, bed top 64 (32x16)
grad((0, 80, 16, 96), (160, 110, 60), (110, 75, 40))
for y in range(80, 96, 4): d.line([(0, y), (15, y)], fill=(90, 60, 30))
grad((16, 80, 32, 96), GOLD[0], GOLD[2]); d.rectangle([16, 80, 31, 95], outline=GOLD[3]); d.line([(18, 82), (24, 82)], fill=WHITE)
grad((32, 80, 48, 96), (230, 255, 255), (90, 210, 220))
grad((48, 80, 64, 96), (130, 80, 40), (80, 50, 25))
# bed top: pillow left 10px, red blanket
d.rectangle([64, 80, 95, 95], fill=RED[1]); noise((64, 80, 96, 96), [RED[0], RED[2]], 3)
d.rectangle([64, 80, 75, 95], fill=WHITE); d.line([(76, 80), (76, 95)], fill=RED[0])
# sword sprites (16,96) normal (32,96) mirrored
draw_sprite(16, 96, SWORD); draw_sprite(32, 96, SWORD, True)
# TNT side (48,96), TNT top (64,96)
d.rectangle([48, 96, 63, 111], fill=(210, 40, 30)); d.rectangle([48, 101, 63, 106], fill=(235, 235, 225))
for x in range(48, 64, 4): d.line([(x, 96), (x, 100)], fill=(150, 20, 15)); d.line([(x, 107), (x, 111)], fill=(150, 20, 15))
text("TNT", 56, 101, BLACK, (235, 235, 225))
d.rectangle([64, 96, 79, 111], fill=(200, 45, 35)); d.rectangle([68, 100, 75, 107], fill=(230, 220, 200)); d.rectangle([70, 102, 73, 105], fill=BLACK)
# red team flag (80,96)
grad((80, 96, 96, 112), RED[0], RED[2]);
d.rectangle([84, 100, 91, 103], fill=WHITE); d.rectangle([84, 103, 91, 106], fill=(255, 220, 220)); d.rectangle([80, 96, 95, 111], outline=RED[3])
# shine for orbit (row 96): glow tile
grad((0, 96, 16, 112), (255, 255, 200), (255, 190, 60))

TEX = os.path.join(OUT, "battle_pass.png")
img.save(TEX)

# ---------- geometry ----------
R = {"front": (0, 0, 48, 64), "back": (48, 0, 48, 64), "edge": (96, 0, 32, 64),
     "emerald": (0, 64, 16, 16), "diamond": (16, 64, 16, 16), "endstone": (32, 64, 16, 16),
     "grass": (48, 64, 16, 16), "grass_side": (64, 64, 16, 16), "red": (80, 64, 16, 16),
     "blue": (96, 64, 16, 16), "white": (112, 64, 16, 16), "wood": (0, 80, 16, 16),
     "goldb": (16, 80, 16, 16), "blade": (32, 80, 16, 16), "hilt": (48, 80, 16, 16),
     "bedtop": (64, 80, 32, 16), "sw": (16, 96, 16, 16), "swm": (32, 96, 16, 16),
     "tnt": (48, 96, 16, 16), "tnt_top": (64, 96, 16, 16), "flag": (80, 96, 16, 16), "none": (112, 96, 16, 16)}


def F(n, s=None, e=None, w=None, u=None, dn=None):
    s = s or n; e = e or n; w = w or e; u = u or n; dn = dn or u
    return dict(north=n, south=s, east=e, west=w, up=u, down=dn)


# (name, bone, from, to, faces, rotation(optional), pivot)
C = [
    # island
    ("island", "island", (-8, 0, -8), (8, 4, 8), F("grass_side", u="grass", dn="grass_side")),
    ("island_low", "island", (-6, -2, -6), (6, 0, 6), F("grass_side")),
    ("wool_r", "island", (-8, 4, -8), (-5, 7, -5), F("red")),
    ("wool_b", "island", (5, 4, -8), (8, 7, -5), F("blue")),
    ("wool_w", "island", (5, 4, 5), (8, 6, 8), F("white")),
    ("gold_blk", "island", (-8, 4, 5), (-5, 7, 8), F("goldb")),
    ("bed", "island", (-4, 4, 2), (4, 6, 7), F("red", u="bedtop", dn="wood")),
    ("bed_leg1", "island", (-4, 4, 2), (-3, 5, 3), F("wood")),
    ("stand", "island", (-2, 4, -3), (2, 9, 1), F("endstone")),
    # pass
    ("card", "pass", (-6, 12, -0.75), (6, 28, 0.75), F("front", s="back", e="edge", u="edge")),
    ("emerald", "pass", (-1.5, 28.5, -1.5), (1.5, 31.5, 1.5), F("emerald"), (0, 45, 0), (0, 30, 0)),
    ("sword_l", "pass", (-13, 13, 1.0), (1, 27, 1.4), F("none", ) | dict(north="sw", south="swm"),),
    ("sword_r", "pass", (-1, 13, 1.0), (13, 27, 1.4), F("none") | dict(north="swm", south="sw"),),
    ("tnt", "island", (-7.5, 4, -3), (-3.5, 8, 1), F("tnt", u="tnt_top")),
    ("pole", "island", (6, 4, 0), (6.6, 15, 0.6), F("wood")),
    ("flag", "island", (2, 11, 0.1), (6, 14, 0.5), F("flag")),
    # orbit gems
    ("orb_diamond", "orbit", (8, 19, -1), (10, 21, 1), F("diamond"), (45, 0, 45), (9, 20, 0)),
    ("orb_emerald", "orbit", (-10, 19, -1), (-8, 21, 1), F("emerald"), (45, 0, 45), (-9, 20, 0)),
    ("orb_gold", "orbit", (-1, 19, 8), (1, 21, 10), F("goldb"), (45, 0, 45), (0, 20, 9)),
]
# sword positions offset: shift blades sideways
OFF = {}
C = [(n, b, (f[0] + OFF.get(n, 0), f[1], f[2]), (t[0] + OFF.get(n, 0), t[1], t[2]), fc, *rest)
     for (n, b, f, t, fc, *rest) in C]
BONES = [("island", [0, 0, 0], None), ("pass", [0, 20, 0], None), ("orbit", [0, 20, 0], None)]

# --- Blockbench project
els, kids = [], {b[0]: [] for b in BONES}
for n, b, f, t, fc, *rest in C:
    u = str(uuid.uuid4()); kids[b].append(u)
    e = {"name": n, "type": "cube", "uuid": u, "box_uv": False, "from": list(f), "to": list(t),
         "origin": list(rest[1]) if rest else [0, 0, 0],
         "faces": {k: {"uv": [R[v][0], R[v][1], R[v][0] + R[v][2], R[v][1] + R[v][3]], "texture": 0}
                   for k, v in fc.items()}}
    if rest:
        e["rotation"] = list(rest[0])
    els.append(e)
buid = {b[0]: str(uuid.uuid4()) for b in BONES}
ANIM = {"pass": {"rotation": ["0", "math.sin(query.anim_time * 60) * 25", "0"],
                 "position": ["0", "math.sin(query.anim_time * 120) * 1.2", "0"]},
        "orbit": {"rotation": ["0", "query.anim_time * -120", "0"],
                  "position": ["0", "math.cos(query.anim_time * 120) * 1", "0"]}}
b64 = base64.b64encode(open(TEX, "rb").read()).decode()
bb = {
    "meta": {"format_version": "4.10", "model_format": "bedrock", "box_uv": False},
    "name": "bedwars_battle_pass", "model_identifier": "bedwars_battle_pass", "visible_box": [3, 3, 1],
    "resolution": {"width": S, "height": S}, "elements": els,
    "outliner": [{"name": n, "origin": p, "uuid": buid[n], "export": True, "isOpen": True,
                  "visibility": True, "children": kids[n]} for n, p, _ in BONES],
    "textures": [{"path": "", "name": "battle_pass.png", "id": "0", "width": S, "height": S,
                  "uv_width": S, "uv_height": S, "render_mode": "default", "visible": True,
                  "internal": True, "saved": True, "uuid": str(uuid.uuid4()),
                  "source": "data:image/png;base64," + b64}],
    "animations": [{"uuid": str(uuid.uuid4()), "name": "animation.bedwars_battle_pass.idle", "loop": "loop",
                    "override": False, "length": 6, "snapping": 24,
                    "animators": {buid[bn]: {"name": bn, "type": "bone", "keyframes": [
                        {"channel": ch, "data_points": [dict(zip("xyz", v))], "uuid": str(uuid.uuid4()),
                         "time": 0, "color": -1, "interpolation": "linear"} for ch, v in chans.items()]}
                        for bn, chans in ANIM.items()}}],
}
json.dump(bb, open(os.path.join(OUT, "battle_pass.bbmodel"), "w"), indent=1)

# --- Bedrock geometry
bones = []
for bn, piv, _ in BONES:
    cs = []
    for n, b, f, t, fc, *rest in C:
        if b != bn:
            continue
        c = {"origin": list(f), "size": [round(t[i] - f[i], 3) for i in range(3)],
             "uv": {k: {"uv": [R[v][0], R[v][1]], "uv_size": [R[v][2], R[v][3]]} for k, v in fc.items()}}
        if rest:  # bedrock rotation sign: x,y negated vs blockbench
            rx, ry, rz = rest[0]
            c["rotation"] = [-rx, -ry, rz]; c["pivot"] = [-rest[1][0], rest[1][1], rest[1][2]]
            c["origin"] = [-t[0], f[1], f[2]]
        else:
            c["origin"] = [-t[0], f[1], f[2]]
        cs.append(c)
    bones.append({"name": bn, "pivot": piv, "cubes": cs})
GEO = {"format_version": "1.12.0", "minecraft:geometry": [{
    "description": {"identifier": "geometry.bedwars_battle_pass", "texture_width": S, "texture_height": S,
                    "visible_bounds_width": 10, "visible_bounds_height": 10, "visible_bounds_offset": [0, 1.5, 0]},
    "bones": bones}]}
ANIMJ = {"format_version": "1.8.0", "animations": {"animation.bedwars_battle_pass.idle": {
    "loop": True, "bones": {bn: {ch: [v if v != "0" else 0 for v in vals] for ch, vals in chans.items()}
                            for bn, chans in ANIM.items()}}}}
json.dump(GEO, open(os.path.join(OUT, "battle_pass.geo.json"), "w"), indent=2)
json.dump(ANIMJ, open(os.path.join(OUT, "battle_pass.animation.json"), "w"), indent=2)

# ---------- Resource pack ----------
RP = os.path.join(OUT, "BedWarsBattlePass_RP")
shutil.rmtree(RP, ignore_errors=True)
for sub in ["models/entity", "animations", "textures/entity", "entity", "render_controllers", "texts"]:
    os.makedirs(os.path.join(RP, sub))
NS = uuid.UUID("267e26ea-85b2-4ab9-8410-0292e2f469d8")  # stable uuids across rebuilds
manifest = {"format_version": 2, "header": {
    "name": "§l§bArvan§fGaming §6Battle Pass", "description": "§eArvanGaming Battle Pass NPC §7- Season 1",
    "uuid": str(uuid.uuid5(NS, "header")), "version": [1, 0, 0], "min_engine_version": [1, 20, 0]},
    "modules": [{"type": "resources", "uuid": str(uuid.uuid5(NS, "module")), "version": [1, 0, 0]}]}
json.dump(manifest, open(os.path.join(RP, "manifest.json"), "w"), indent=2)
shutil.copy(TEX, os.path.join(RP, "textures/entity/bedwars_battle_pass.png"))
json.dump(GEO, open(os.path.join(RP, "models/entity/bedwars_battle_pass.geo.json"), "w"), indent=2)
json.dump(ANIMJ, open(os.path.join(RP, "animations/bedwars_battle_pass.animation.json"), "w"), indent=2)
json.dump({"format_version": "1.10.0", "minecraft:client_entity": {"description": {
    "identifier": "bedwars:battle_pass",
    "materials": {"default": "entity_alphatest"},
    "textures": {"default": "textures/entity/bedwars_battle_pass"},
    "geometry": {"default": "geometry.bedwars_battle_pass"},
    "animations": {"idle": "animation.bedwars_battle_pass.idle"},
    "scripts": {"animate": ["idle"], "scale": "2.6"},
    "render_controllers": ["controller.render.bedwars_battle_pass"],
    "spawn_egg": {"base_color": "#DC282D", "overlay_color": "#2864E6"}}}},
    open(os.path.join(RP, "entity/bedwars_battle_pass.entity.json"), "w"), indent=2)
json.dump({"format_version": "1.8.0", "render_controllers": {"controller.render.bedwars_battle_pass": {
    "geometry": "Geometry.default", "materials": [{"*": "Material.default"}],
    "textures": ["Texture.default"]}}},
    open(os.path.join(RP, "render_controllers/bedwars_battle_pass.render_controllers.json"), "w"), indent=2)
open(os.path.join(RP, "texts/en_US.lang"), "w").write(
    "entity.bedwars:battle_pass.name=§l§6Battle Pass\nitem.spawn_egg.entity.bedwars:battle_pass.name=Spawn Battle Pass\n")
json.dump(["en_US"], open(os.path.join(RP, "texts/languages.json"), "w"))
# pack icon: front of card on dark background
icon = Image.new("RGBA", (256, 256), (20, 10, 30, 255))
ImageDraw.Draw(icon).ellipse([20, 20, 236, 236], fill=(120, 20, 35))
card = img.crop((0, 0, 48, 64)).resize((144, 192), Image.NEAREST)
icon.paste(card, (56, 32), card)
icon.save(os.path.join(RP, "pack_icon.png"))
icon.save(os.path.join(OUT, "preview_icon.png"))
img.resize((512, 512), Image.NEAREST).save(os.path.join(OUT, "preview_texture.png"))


def zipdir(path, dest):
    with zipfile.ZipFile(dest, "w", zipfile.ZIP_DEFLATED) as z:
        for root, _, files in os.walk(path):
            for fn in files:
                full = os.path.join(root, fn)
                z.write(full, os.path.relpath(full, path))


zipdir(RP, os.path.join(OUT, "BedWarsBattlePass.mcpack"))
zipdir(RP, os.path.join(OUT, "BedWarsBattlePass_RP.zip"))
print("done")
