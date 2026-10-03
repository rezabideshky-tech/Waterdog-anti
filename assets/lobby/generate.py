#!/usr/bin/env python3
"""ArvanGaming lobby portal models: BedWars Duos island + RolePlay mini city.
Outputs Blockbench projects, Bedrock geo/animations and a resource pack (.zip + .mcpack)."""
import base64, json, math, os, shutil, uuid, zipfile
from PIL import Image, ImageDraw

OUT = os.path.dirname(os.path.abspath(__file__))
NS = uuid.UUID("b7c41e2a-93f0-4d55-8a61-2f3e9c0d7a18")
WHITE, BLACK = (255, 255, 255), (15, 10, 20)
GOLD = [(255, 240, 150), (252, 206, 60), (220, 150, 25), (140, 85, 15)]
RED = [(255, 110, 100), (220, 40, 45), (140, 15, 25), (60, 5, 12)]
BLUE = [(130, 195, 255), (45, 115, 235), (20, 50, 150), (8, 18, 60)]

FONT = {
    "A": ["010", "101", "111", "101", "101"], "B": ["110", "101", "110", "101", "110"],
    "C": ["011", "100", "100", "100", "011"], "D": ["110", "101", "101", "101", "110"],
    "E": ["111", "100", "110", "100", "111"], "G": ["011", "100", "101", "101", "011"],
    "I": ["111", "010", "010", "010", "111"], "L": ["100", "100", "100", "100", "111"],
    "M": ["101", "111", "111", "101", "101"], "N": ["101", "111", "111", "101", "101"],
    "O": ["010", "101", "101", "101", "010"], "P": ["110", "101", "110", "100", "100"],
    "R": ["110", "101", "110", "101", "101"], "S": ["011", "100", "010", "001", "110"],
    "T": ["111", "010", "010", "010", "010"], "U": ["101", "101", "101", "101", "111"],
    "V": ["101", "101", "101", "101", "010"], "W": ["101", "101", "111", "111", "101"],
    "Y": ["101", "101", "010", "010", "010"], "H": ["101", "101", "111", "101", "101"],
    "K": ["101", "110", "100", "110", "101"], "F": ["111", "100", "110", "100", "100"],
    "2": ["110", "001", "010", "100", "111"], "1": ["010", "110", "010", "010", "111"],
    " ": ["000"] * 5, "!": ["1", "1", "1", "0", "1"],
}


class Atlas:
    """Simple shelf packer + drawing helpers on one texture."""

    def __init__(self, w, h):
        self.W, self.H = w, h
        self.img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        self.d = ImageDraw.Draw(self.img)
        self.x = self.y = self.row = 0
        self.R = {}

    def alloc(self, name, w, h):
        if self.x + w > self.W:
            self.x, self.y, self.row = 0, self.y + self.row, 0
        assert self.y + h <= self.H, f"atlas full at {name}"
        self.R[name] = (self.x, self.y, w, h)
        self.x += w
        self.row = max(self.row, h)
        return self.x - w, self.y, self.x, self.y + h

    # --- helpers
    def grad(self, box, top, bot):
        x0, y0, x1, y1 = box
        for y in range(y0, y1):
            t = (y - y0) / max(1, y1 - y0 - 1)
            self.d.line([(x0, y), (x1 - 1, y)], fill=tuple(int(top[i] + (bot[i] - top[i]) * t) for i in range(3)))

    def noise(self, box, cols, step=3, seed=1):
        x0, y0, x1, y1 = box
        for y in range(y0, y1):
            for x in range(x0, x1):
                h = ((x + seed * 31) * 73856093 ^ (y + seed * 17) * 19349663) & 0xFFFF
                if h % step == 0:
                    self.d.point([(x, y)], fill=cols[(h >> 4) % len(cols)])

    def text(self, s, cx, y, col, sh=None, sc=1):
        x = cx - (sum(len(FONT[c][0]) + 1 for c in s) * sc - sc) // 2
        for ch in s:
            g = FONT[ch]
            for ry, row in enumerate(g):
                for rx, v in enumerate(row):
                    if v == "1":
                        px, py = x + rx * sc, y + ry * sc
                        if sh:
                            self.d.rectangle([px + 1, py + 1, px + sc, py + sc], fill=sh)
                        self.d.rectangle([px, py, px + sc - 1, py + sc - 1], fill=col)
            x += (len(g[0]) + 1) * sc

    def outlined(self, s, cx, y, col, sc=1, out=BLACK):
        for ox, oy in [(-1, 0), (1, 0), (0, -1), (0, 1), (1, 1), (-1, -1), (1, -1), (-1, 1)]:
            self.text(s, cx + ox, y + oy, out, None, sc)
        self.text(s, cx, y, col, None, sc)

    def frame(self, box, cols=GOLD):
        x0, y0, x1, y1 = box
        self.d.rectangle([x0, y0, x1 - 1, y1 - 1], outline=cols[3])
        self.d.rectangle([x0 + 1, y0 + 1, x1 - 2, y1 - 2], outline=cols[1])
        self.d.line([(x0 + 1, y0 + 1), (x1 - 2, y0 + 1)], fill=cols[0])


# ------------------------------------------------------------------ model builder
def F(n, s=None, e=None, w=None, u=None, dn=None):
    s = s or n; e = e or n; w = w or e; u = u or n; dn = dn or u
    return dict(north=n, south=s, east=e, west=w, up=u, down=dn)


def build(name, ident, atlas, bones, cubes, anim, length, scale, bounds):
    """bones: [(name, pivot, parent)] ; cubes: [(name, bone, from, to, faces, rot?, pivot?)]"""
    R, S = atlas.R, atlas.W
    tex_path = os.path.join(OUT, f"{name}.png")
    atlas.img.save(tex_path)
    atlas.img.resize((atlas.W * 4, atlas.H * 4), Image.NEAREST).save(os.path.join(OUT, f"preview_{name}_texture.png"))

    # Blockbench
    els, kids = [], {b[0]: [] for b in bones}
    for n, b, f, t, fc, *rest in cubes:
        u = str(uuid.uuid4()); kids[b].append(u)
        e = {"name": n, "type": "cube", "uuid": u, "box_uv": False, "from": list(f), "to": list(t),
             "origin": list(rest[1]) if rest else [0, 0, 0],
             "faces": {k: {"uv": [R[v][0], R[v][1], R[v][0] + R[v][2], R[v][1] + R[v][3]], "texture": 0}
                       for k, v in fc.items()}}
        if rest:
            e["rotation"] = list(rest[0])
        els.append(e)
    buid = {b[0]: str(uuid.uuid4()) for b in bones}

    def outl(parent):
        res = []
        for bn, piv, par, *br in bones:
            if par == parent:
                o = {"name": bn, "origin": piv, "uuid": buid[bn], "export": True, "isOpen": True,
                     "visibility": True, "children": kids[bn] + outl(bn)}
                if br:
                    o["rotation"] = [-br[0][0], -br[0][1], br[0][2]]
                res.append(o)
        return res

    b64 = base64.b64encode(open(tex_path, "rb").read()).decode()
    aname = f"animation.{name}.idle"
    bb = {"meta": {"format_version": "4.10", "model_format": "bedrock", "box_uv": False},
          "name": name, "model_identifier": name, "visible_box": bounds,
          "resolution": {"width": atlas.W, "height": atlas.H}, "elements": els, "outliner": outl(None),
          "textures": [{"path": "", "name": f"{name}.png", "id": "0", "width": atlas.W, "height": atlas.H,
                        "uv_width": atlas.W, "uv_height": atlas.H, "render_mode": "default", "visible": True,
                        "internal": True, "saved": True, "uuid": str(uuid.uuid4()),
                        "source": "data:image/png;base64," + b64}],
          "animations": [{"uuid": str(uuid.uuid4()), "name": aname, "loop": "loop", "override": False,
                          "length": length, "snapping": 24,
                          "animators": {buid[bn]: {"name": bn, "type": "bone", "keyframes": [
                              {"channel": ch, "data_points": [dict(zip("xyz", [str(x) for x in v]))],
                               "uuid": str(uuid.uuid4()), "time": 0, "color": -1, "interpolation": "linear"}
                              for ch, v in chans.items()]} for bn, chans in anim.items()}}]}
    json.dump(bb, open(os.path.join(OUT, f"{name}.bbmodel"), "w"), indent=1)

    # Bedrock geometry (X mirrored like Blockbench export)
    gb = []
    for bn, piv, par, *br in bones:
        cs = []
        for n, b, f, t, fc, *rest in cubes:
            if b != bn:
                continue
            c = {"origin": [-t[0], f[1], f[2]], "size": [round(t[i] - f[i], 3) for i in range(3)],
                 "uv": {k: {"uv": [R[v][0], R[v][1]], "uv_size": [R[v][2], R[v][3]]} for k, v in fc.items()}}
            if rest:
                rx, ry, rz = rest[0]
                c["rotation"] = [-rx, -ry, rz]
                c["pivot"] = [-rest[1][0], rest[1][1], rest[1][2]]
            cs.append(c)
        bone = {"name": bn, "pivot": [-piv[0], piv[1], piv[2]], "cubes": cs}
        if par:
            bone["parent"] = par
        if br:
            bone["rotation"] = list(br[0])
        gb.append(bone)
    geo = {"format_version": "1.12.0", "minecraft:geometry": [{
        "description": {"identifier": f"geometry.{name}", "texture_width": atlas.W, "texture_height": atlas.H,
                        "visible_bounds_width": bounds[0], "visible_bounds_height": bounds[1],
                        "visible_bounds_offset": [0, bounds[1] / 2 - 0.5, 0]}, "bones": gb}]}
    an = {"format_version": "1.8.0", "animations": {aname: {"loop": True, "bones": {
        bn: {ch: [x if isinstance(x, (int, float)) else x for x in v] for ch, v in chans.items()}
        for bn, chans in anim.items()}}}}
    ent = {"format_version": "1.10.0", "minecraft:client_entity": {"description": {
        "identifier": ident, "materials": {"default": "entity_alphatest"},
        "textures": {"default": f"textures/entity/{name}"}, "geometry": {"default": f"geometry.{name}"},
        "animations": {"idle": aname}, "scripts": {"animate": ["idle"], "scale": str(scale)},
        "render_controllers": ["controller.render.arvan_lobby"]}}}
    return dict(name=name, ident=ident, geo=geo, anim=an, ent=ent, tex=tex_path, R=R, img=atlas.img)


# ================================================================== BEDWARS DUOS
def bedwars_duos():
    A = Atlas(256, 256)
    # ---------------- terrain / blocks
    b = A.alloc("grass", 16, 16); A.grad(b, (120, 200, 75), (90, 165, 55)); A.noise(b, [(70, 140, 40), (150, 220, 95)], 2)
    b = A.alloc("grass_side", 16, 16); A.grad(b, (140, 98, 62), (100, 70, 45)); A.noise(b, [(80, 55, 35), (165, 122, 82)], 3)
    for x in range(b[0], b[2]):
        A.d.line([(x, b[1]), (x, b[1] + 2 + (x * 7) % 3)], fill=(105, 185, 65))
    b = A.alloc("dirt", 16, 16); A.grad(b, (125, 88, 55), (85, 58, 38)); A.noise(b, [(70, 48, 30), (150, 110, 75)], 3, 2)
    b = A.alloc("stone", 16, 16); A.grad(b, (135, 135, 140), (95, 95, 105)); A.noise(b, [(80, 80, 90), (160, 160, 165)], 3, 3)
    b = A.alloc("endstone", 16, 16); A.grad(b, (240, 238, 180), (212, 208, 148)); A.noise(b, [(195, 190, 128), (252, 250, 205)], 3, 4)
    b = A.alloc("planks", 16, 16); A.grad(b, (178, 132, 78), (140, 100, 56))
    for y in range(b[1] + 3, b[3], 4): A.d.line([(b[0], y), (b[2] - 1, y)], fill=(100, 70, 38))
    for i, y in enumerate(range(b[1], b[3], 4)): A.d.point([(b[0] + (5 + i * 7) % 16, y + 1)], fill=(100, 70, 38))
    b = A.alloc("obsidian", 16, 16); A.grad(b, (40, 25, 60), (15, 8, 25)); A.noise(b, [(90, 60, 140), (60, 35, 95)], 4, 9)
    b = A.alloc("tnt_side", 16, 16); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(210, 45, 30))
    A.d.rectangle([b[0], b[1] + 5, b[2] - 1, b[1] + 10], fill=(238, 238, 228))
    for x in range(b[0], b[2], 4):
        A.d.line([(x, b[1]), (x, b[1] + 4)], fill=(150, 20, 15)); A.d.line([(x, b[1] + 11), (x, b[3] - 1)], fill=(150, 20, 15))
    A.text("TNT", (b[0] + b[2]) // 2, b[1] + 5, BLACK)
    b = A.alloc("tnt_top", 16, 16); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(200, 45, 35))
    A.d.rectangle([b[0] + 5, b[1] + 5, b[0] + 10, b[1] + 10], fill=(230, 220, 200)); A.d.rectangle([b[0] + 7, b[1] + 7, b[0] + 8, b[1] + 8], fill=BLACK)
    for nm, pal in [("wool_red", RED), ("wool_blue", BLUE)]:
        b = A.alloc(nm, 16, 16); A.grad(b, pal[0], pal[2]); A.noise(b, [pal[1], pal[2], pal[0]], 2, 7)
    b = A.alloc("diamond", 16, 16); A.grad(b, (220, 255, 255), (30, 130, 160))
    A.d.polygon([(b[0] + 8, b[1] + 2), (b[0] + 13, b[1] + 8), (b[0] + 8, b[1] + 13), (b[0] + 3, b[1] + 8)], fill=(90, 225, 235), outline=WHITE)
    b = A.alloc("emerald", 16, 16); A.grad(b, (170, 255, 190), (10, 100, 45))
    A.d.polygon([(b[0] + 8, b[1] + 2), (b[0] + 13, b[1] + 8), (b[0] + 8, b[1] + 13), (b[0] + 3, b[1] + 8)], fill=(40, 210, 100), outline=WHITE)
    b = A.alloc("gold", 16, 16); A.grad(b, GOLD[0], GOLD[2]); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=GOLD[3])
    b = A.alloc("iron", 16, 16); A.grad(b, (240, 240, 245), (170, 170, 180)); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(120, 120, 130))
    b = A.alloc("gen_side", 16, 16); A.grad(b, (90, 90, 100), (50, 50, 60))
    A.d.rectangle([b[0] + 2, b[1] + 6, b[2] - 3, b[1] + 9], fill=(80, 230, 240))
    b = A.alloc("fire", 16, 16); A.grad(b, (255, 240, 120), (230, 70, 20)); A.noise(b, [(255, 160, 40), (255, 255, 200)], 3, 11)
    b = A.alloc("none", 16, 16)
    b = A.alloc("trim", 16, 16); A.grad(b, GOLD[0], GOLD[2])

    # ---------------- realistic beds (Minecraft style)
    for nm, pal in [("red", RED), ("blue", BLUE)]:
        # top: pillow end (head, +z) white, blanket with fold
        b = A.alloc(f"bed_{nm}_top", 32, 64)
        x0, y0, x1, y1 = b
        A.d.rectangle([x0, y0, x1 - 1, y1 - 1], fill=pal[1]); A.noise(b, [pal[0], pal[2]], 5, 13)
        A.d.rectangle([x0, y0, x1 - 1, y0 + 19], fill=(245, 245, 250))           # sheet at head
        A.d.line([(x0, y0 + 19), (x1 - 1, y0 + 19)], fill=(205, 205, 215))
        A.d.rectangle([x0, y0 + 20, x1 - 1, y0 + 24], fill=pal[0])                # blanket fold
        A.d.line([(x0, y0 + 25), (x1 - 1, y0 + 25)], fill=pal[2])
        for y in range(y0 + 30, y1, 8):
            A.d.line([(x0 + 2, y), (x1 - 3, y)], fill=pal[2])
        A.d.rectangle([x0, y0, x1 - 1, y1 - 1], outline=pal[3])
        # side (long): blanket hangs over, white sheet line at top, wooden frame
        b = A.alloc(f"bed_{nm}_side", 64, 16)
        x0, y0, x1, y1 = b
        A.d.rectangle([x0, y0, x1 - 1, y1 - 1], fill=pal[1]); A.noise(b, [pal[0], pal[2]], 5, 15)
        A.d.rectangle([x0, y0, x0 + 19, y0 + 5], fill=(245, 245, 250))
        A.d.line([(x0, y0), (x1 - 1, y0)], fill=pal[0])
        A.d.rectangle([x0, y1 - 5, x1 - 1, y1 - 1], fill=(160, 112, 60))         # frame
        A.d.line([(x0, y1 - 5), (x1 - 1, y1 - 5)], fill=(110, 75, 40))
        b = A.alloc(f"bed_{nm}_end", 32, 16)
        x0, y0, x1, y1 = b
        A.d.rectangle([x0, y0, x1 - 1, y1 - 1], fill=pal[1]); A.noise(b, [pal[0], pal[2]], 5, 17)
        A.d.rectangle([x0, y1 - 5, x1 - 1, y1 - 1], fill=(160, 112, 60))
        A.d.line([(x0, y1 - 5), (x1 - 1, y1 - 5)], fill=(110, 75, 40))
        A.d.line([(x0, y0), (x1 - 1, y0)], fill=pal[0])
    b = A.alloc("pillow", 32, 16); A.grad(b, (255, 255, 255), (225, 225, 235))
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(200, 200, 212))
    A.d.line([(b[0] + 3, b[1] + 5), (b[2] - 4, b[1] + 5)], fill=(235, 235, 242))
    b = A.alloc("bed_leg", 8, 8); A.grad(b, (150, 105, 58), (100, 70, 38))
    b = A.alloc("bed_bottom", 32, 32); A.grad(b, (150, 105, 58), (110, 75, 40))

    # ---------------- Steve with team armor
    SKIN, SKIN_D = (196, 140, 106), (160, 108, 80)
    HAIR, HAIR_D = (64, 42, 22), (45, 28, 14)
    EYE_W, EYE = (255, 255, 255), (70, 60, 155)
    MOUTH, BEARD = (120, 60, 50), (110, 72, 48)
    SHIRT, PANTS, SHOE = (0, 170, 170), (60, 55, 150), (80, 80, 85)

    def px(box, gx, gy, col, cell):
        x0, y0 = box[0], box[1]
        A.d.rectangle([x0 + gx * cell, y0 + gy * cell, x0 + gx * cell + cell - 1, y0 + gy * cell + cell - 1], fill=col)

    def armor_noise(box, pal):
        A.noise(box, [pal[0], pal[2]], 6, 21)

    for t, pal in [("r", RED), ("b", BLUE)]:
        DARK = pal[3]
        # head (8x8 grid, 2px cells = 16px)
        b = A.alloc(f"h_front_{t}", 16, 16)
        face = ["HHHHHHHH", "HHHHHHHH", "HSSSSSSH", "SWESSEWS", "SSSddSSS", "SSMbbMSS", "SSbbbbSS", "SSSSSSSS"]
        cmap = {"H": HAIR, "S": SKIN, "W": EYE_W, "E": EYE, "d": SKIN_D, "M": MOUTH, "b": BEARD}
        for gy, row in enumerate(face):
            for gx, ch in enumerate(row):
                px(b, gx, gy, cmap[ch], 2)
        # helmet: top 2 rows + side columns down to row 5
        for gx in range(8):
            px(b, gx, 0, pal[1], 2); px(b, gx, 1, pal[2] if gx in (0, 7) else pal[1], 2)
        for gy in range(2, 6):
            px(b, 0, gy, pal[2], 2); px(b, 7, gy, pal[2], 2)
        A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=pal[0])
        A.d.line([(b[0] + 2, b[1] + 3), (b[2] - 3, b[1] + 3)], fill=DARK)
        b = A.alloc(f"h_side_{t}", 16, 16)
        A.d.rectangle([b[0], b[1], b[2] - 1, b[1] + 11], fill=pal[1]); armor_noise((b[0], b[1], b[2], b[1] + 12), pal)
        A.d.rectangle([b[0], b[1] + 12, b[2] - 1, b[3] - 1], fill=HAIR)
        A.d.rectangle([b[0], b[1] + 12, b[0] + 5, b[3] - 1], fill=SKIN)
        A.d.line([(b[0], b[1] + 11), (b[2] - 1, b[1] + 11)], fill=DARK)
        b = A.alloc(f"h_back_{t}", 16, 16)
        A.d.rectangle([b[0], b[1], b[2] - 1, b[1] + 11], fill=pal[1]); armor_noise((b[0], b[1], b[2], b[1] + 12), pal)
        A.d.rectangle([b[0], b[1] + 12, b[2] - 1, b[3] - 1], fill=HAIR_D)
        A.d.line([(b[0], b[1] + 11), (b[2] - 1, b[1] + 11)], fill=DARK)
        b = A.alloc(f"h_top_{t}", 16, 16); A.grad(b, pal[0], pal[1]); armor_noise(b, pal)
        A.d.line([(b[0] + 7, b[1]), (b[0] + 7, b[3] - 1)], fill=pal[2])
        b = A.alloc(f"h_bottom_{t}", 16, 16); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SKIN_D)
        # chestplate front 16x24, side 8x24, top 16x8
        b = A.alloc(f"b_front_{t}", 16, 24)
        A.grad(b, pal[0], pal[2]); armor_noise(b, pal)
        A.d.rectangle([b[0] + 5, b[1], b[0] + 10, b[1] + 3], fill=SKIN)  # neck V
        A.d.rectangle([b[0] + 6, b[1] + 4, b[0] + 9, b[1] + 4], fill=SHIRT)
        A.d.line([(b[0], b[1] + 15), (b[2] - 1, b[1] + 15)], fill=DARK)   # belt
        A.d.rectangle([b[0], b[1] + 16, b[2] - 1, b[1] + 17], fill=(90, 60, 30))
        A.d.rectangle([b[0] + 6, b[1] + 16, b[0] + 9, b[1] + 17], fill=GOLD[1])
        A.d.rectangle([b[0] + 6, b[1] + 7, b[0] + 9, b[1] + 11], fill=pal[3])  # emblem
        A.d.point([(b[0] + 7, b[1] + 8), (b[0] + 8, b[1] + 9), (b[0] + 7, b[1] + 10)], fill=GOLD[0])
        A.d.rectangle([b[0], b[1] + 18, b[2] - 1, b[3] - 1], fill=pal[2])
        A.d.line([(b[0] + 7, b[1] + 18), (b[0] + 7, b[3] - 1)], fill=DARK)
        b = A.alloc(f"b_back_{t}", 16, 24); A.grad(b, pal[1], pal[2]); armor_noise(b, pal)
        A.d.rectangle([b[0], b[1] + 16, b[2] - 1, b[1] + 17], fill=(90, 60, 30))
        b = A.alloc(f"b_side_{t}", 8, 24); A.grad(b, pal[1], pal[2]); armor_noise(b, pal)
        A.d.rectangle([b[0], b[1] + 16, b[2] - 1, b[1] + 17], fill=(90, 60, 30))
        b = A.alloc(f"b_top_{t}", 16, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=pal[1])
        A.d.rectangle([b[0] + 5, b[1] + 2, b[0] + 10, b[1] + 5], fill=SKIN)
        # arm 8x24: pauldron (armor) top 10px, then skin sleeve, hand
        b = A.alloc(f"arm_{t}", 8, 24)
        A.d.rectangle([b[0], b[1], b[2] - 1, b[1] + 9], fill=pal[1]); armor_noise((b[0], b[1], b[2], b[1] + 10), pal)
        A.d.line([(b[0], b[1] + 9), (b[2] - 1, b[1] + 9)], fill=DARK)
        A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=pal[0])
        A.d.rectangle([b[0], b[1] + 10, b[2] - 1, b[3] - 1], fill=SKIN)
        A.d.rectangle([b[0], b[1] + 10, b[2] - 1, b[1] + 12], fill=SHIRT)
        A.d.rectangle([b[0], b[3] - 4, b[2] - 1, b[3] - 1], fill=SKIN_D)
        b = A.alloc(f"arm_top_{t}", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=pal[0])
        b = A.alloc(f"hand_{t}", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SKIN_D)
        # leg 8x24: leggings + boots
        b = A.alloc(f"leg_{t}", 8, 24)
        A.grad((b[0], b[1], b[2], b[1] + 16), pal[1], pal[2]); armor_noise((b[0], b[1], b[2], b[1] + 16), pal)
        A.d.line([(b[0], b[1] + 6), (b[2] - 1, b[1] + 6)], fill=DARK)
        A.d.rectangle([b[0], b[1] + 16, b[2] - 1, b[3] - 1], fill=pal[3])
        A.d.line([(b[0], b[1] + 16), (b[2] - 1, b[1] + 16)], fill=pal[0])
        A.d.rectangle([b[0], b[3] - 2, b[2] - 1, b[3] - 1], fill=(30, 20, 15))
        b = A.alloc(f"leg_top_{t}", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=pal[2])
        b = A.alloc(f"boot_{t}", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(30, 20, 15))
    # diamond sword parts
    b = A.alloc("blade", 8, 32)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(90, 230, 230))
    A.d.rectangle([b[0] + 1, b[1], b[0] + 3, b[3] - 1], fill=(200, 255, 255))
    A.d.rectangle([b[0] + 6, b[1], b[2] - 1, b[3] - 1], fill=(30, 140, 160))
    A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=(10, 40, 50))
    b = A.alloc("hilt", 8, 8); A.grad(b, (130, 85, 40), (80, 50, 25))
    b = A.alloc("guard", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(30, 140, 160))
    A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=(200, 255, 255))

    # ---------------- sign
    b = A.alloc("sign", 64, 32)
    x0, y0, x1, y1 = b
    for x in range(x0, x1):
        A.d.line([(x, y0), (x, y1 - 1)], fill=RED[1] if x < (x0 + x1) // 2 else BLUE[1])
    A.d.line([((x0 + x1) // 2, y0), ((x0 + x1) // 2, y1 - 1)], fill=WHITE)
    A.grad((x0 + 3, y0 + 3, x1 - 3, y0 + 16), (25, 15, 35), (10, 5, 15))
    A.outlined("BEDWARS", (x0 + x1) // 2, y0 + 5, GOLD[0], 1)
    A.d.line([(x0 + 3, y0 + 16), (x1 - 4, y0 + 16)], fill=GOLD[1])
    A.outlined("DUOS", (x0 + x1) // 2, y0 + 19, WHITE, 2)
    A.frame(b)
    b = A.alloc("sign_back", 64, 32); A.grad(b, (60, 40, 25), (35, 22, 12)); A.frame(b)
    A.text("ARVAN GAMING", (b[0] + b[2]) // 2, b[1] + 13, GOLD[1], GOLD[3])

    # ---------------- geometry
    def steve(t, cx, cz):
        """Half-scale Steve (16 units tall) standing on y=4, built facing north (-Z). Bones are prefixed by team."""
        y = 4
        bones = [(f"fighter_{t}", [cx, y, cz], "root", [0, 60 if t == "r" else -60, 0]),
                 (f"body_{t}", [cx, y + 6, cz], f"fighter_{t}"),
                 (f"head_{t}", [cx, y + 12, cz], f"body_{t}"),
                 (f"rarm_{t}", [cx + 3, y + 11.5, cz], f"body_{t}"),
                 (f"larm_{t}", [cx - 3, y + 11.5, cz], f"body_{t}"),
                 (f"rleg_{t}", [cx + 1, y + 6, cz], f"fighter_{t}"),
                 (f"lleg_{t}", [cx - 1, y + 6, cz], f"fighter_{t}")]
        H = F(f"h_front_{t}", s=f"h_back_{t}", e=f"h_side_{t}", u=f"h_top_{t}", dn=f"h_bottom_{t}")
        B = F(f"b_front_{t}", s=f"b_back_{t}", e=f"b_side_{t}", u=f"b_top_{t}")
        ARM = F(f"arm_{t}", u=f"arm_top_{t}", dn=f"hand_{t}")
        LEG = F(f"leg_{t}", u=f"leg_top_{t}", dn=f"boot_{t}")
        cubes = [
            (f"head_{t}", f"head_{t}", (cx - 2, y + 12, cz - 2), (cx + 2, y + 16, cz + 2), H),
            (f"helmet_{t}", f"head_{t}", (cx - 2.25, y + 14.5, cz - 2.25), (cx + 2.25, y + 16.25, cz + 2.25),
             F(f"h_top_{t}", dn="none") | dict(north="none")),
            (f"body_{t}", f"body_{t}", (cx - 2, y + 6, cz - 1), (cx + 2, y + 12, cz + 1), B),
            (f"rarm_{t}", f"rarm_{t}", (cx + 2, y + 6, cz - 1), (cx + 4, y + 12, cz + 1), ARM),
            (f"larm_{t}", f"larm_{t}", (cx - 4, y + 6, cz - 1), (cx - 2, y + 12, cz + 1), ARM),
            (f"rleg_{t}", f"rleg_{t}", (cx, y, cz - 1), (cx + 2, y + 6, cz + 1), LEG),
            (f"lleg_{t}", f"lleg_{t}", (cx - 2, y, cz - 1), (cx, y + 6, cz + 1), LEG),
            # diamond sword in right hand, blade pointing forward (-Z) when arm hangs
            (f"hilt_{t}", f"rarm_{t}", (cx + 2.6, y + 6.4, cz - 1.6), (cx + 3.4, y + 7.2, cz + 1.2), F("hilt")),
            (f"guard_{t}", f"rarm_{t}", (cx + 2.2, y + 6.1, cz - 2.1), (cx + 3.8, y + 7.5, cz - 1.6), F("guard")),
            (f"blade_{t}", f"rarm_{t}", (cx + 2.75, y + 6.4, cz - 9.1), (cx + 3.25, y + 7.2, cz - 2.1),
             F("blade", e="blade", u="blade", dn="blade") | dict(north="guard", south="guard")),
        ]
        return bones, cubes

    bones = [("root", [0, 0, 0], None), ("island", [0, 0, 0], "root"), ("gen", [9, 7, -9], "root"),
             ("orbit", [0, 12, 0], "root"), ("sign", [0, 18, 13], "root"), ("fireball", [0, 22, 0], "root")]
    BED = lambda c, x0, x1: [  # realistic bed: legs, frame+mattress, pillow; head at +z
        (f"bed_{c}", "island", (x0, 5.5, -1), (x1, 8, 11), F(f"bed_{c}_end", e=f"bed_{c}_side", u=f"bed_{c}_top", dn="bed_bottom")),
        (f"bed_{c}_pillow", "island", (x0 + 0.75, 8, 7.5), (x1 - 0.75, 8.75, 10.25), F("pillow")),
        (f"bed_{c}_leg1", "island", (x0, 4, -1), (x0 + 1.5, 5.5, 0.5), F("bed_leg")),
        (f"bed_{c}_leg2", "island", (x1 - 1.5, 4, -1), (x1, 5.5, 0.5), F("bed_leg")),
        (f"bed_{c}_leg3", "island", (x0, 4, 9.5), (x0 + 1.5, 5.5, 11), F("bed_leg")),
        (f"bed_{c}_leg4", "island", (x1 - 1.5, 4, 9.5), (x1, 5.5, 11), F("bed_leg")),
    ]
    C = [
        ("top", "island", (-14, 0, -12), (14, 4, 13), F("grass_side", u="grass", dn="dirt")),
        ("under1", "island", (-12, -3, -10), (12, 0, 11), F("dirt")),
        ("under2", "island", (-9, -6, -7), (9, -3, 8), F("stone")),
        ("under3", "island", (-5, -9, -4), (5, -6, 5), F("stone")),
        ("tip", "island", (-2, -12, -1.5), (2, -9, 2.5), F("stone")),
        *BED("red", -11.5, -4), *BED("blue", 4, 11.5),
        # bed defense (wool + endstone + planks, classic BedWars)
        ("def_r_foot", "island", (-12.5, 4, -3), (-3, 6, -1), F("wool_red")),
        ("def_r_side", "island", (-14, 4, -3), (-12, 7, 12), F("endstone")),
        ("def_r_in", "island", (-3.5, 4, -1), (-2, 6.5, 12), F("planks")),
        ("def_b_foot", "island", (3, 4, -3), (12.5, 6, -1), F("wool_blue")),
        ("def_b_side", "island", (12, 4, -3), (14, 7, 12), F("endstone")),
        ("def_b_in", "island", (2, 4, -1), (3.5, 6.5, 12), F("planks")),
        ("obs_r", "island", (-14, 7, 10), (-12, 9, 12), F("obsidian")),
        ("obs_b", "island", (12, 7, 10), (14, 9, 12), F("obsidian")),
        # TNT + wool stack front-left
        ("tnt", "island", (-13, 4, -11.5), (-10, 7, -8.5), F("tnt_side", u="tnt_top")),
        ("wool_stack1", "island", (-9.5, 4, -11.5), (-7.5, 6, -9.5), F("wool_red")),
        ("wool_stack2", "island", (7.5, 4, -11.5), (9.5, 6, -9.5), F("wool_blue")),
        # diamond generator front-right
        ("gen_base", "island", (10.5, 4, -11.5), (13.5, 5.5, -8.5), F("gen_side", u="obsidian")),
        ("gen_dia", "gen", (11, 7, -11), (13, 9, -9), F("diamond"), (45, 45, 0), (12, 8, -10)),
        ("ingot_g", "island", (6, 4, -12), (7.5, 4.6, -11.2), F("gold")),
        ("ingot_i", "island", (6.2, 4.6, -11.9), (7.3, 5.2, -11.3), F("iron")),
        # orbiting resources
        ("o_dia", "orbit", (16, 11, -1), (18, 13, 1), F("diamond"), (45, 0, 45), (17, 12, 0)),
        ("o_eme", "orbit", (-18, 11, -1), (-16, 13, 1), F("emerald"), (45, 0, 45), (-17, 12, 0)),
        ("o_gld", "orbit", (-1, 11, 16), (1, 13, 18), F("gold"), (45, 0, 45), (0, 12, 17)),
        ("o_iron", "orbit", (-1, 11, -18), (1, 13, -16), F("iron"), (45, 0, 45), (0, 12, -17)),
        # fireball flying between fighters
        ("fireball", "fireball", (-1, 21, -6), (1, 23, -4), F("fire"), (45, 45, 0), (0, 22, -5)),
        # sign
        ("post_l", "sign", (-9, 8, 12.5), (-8, 18, 13.5), F("planks")),
        ("post_r", "sign", (8, 8, 12.5), (9, 18, 13.5), F("planks")),
        ("board", "sign", (-11, 17, 12), (11, 28, 13), F("sign", s="sign_back", e="trim", u="trim")),
    ]
    for t, cx in (("r", -4.5), ("b", 4.5)):
        bb_, cc = steve(t, cx, -5)
        bones += bb_
        C += cc
    swing = lambda ph: f"-60 + math.sin(query.anim_time * 360 + {ph}) * 50"
    anim = {
        "root": {"position": [0, "math.sin(query.anim_time * 90) * 0.8", 0]},
        "orbit": {"rotation": [0, "query.anim_time * -90", 0]},
        "gen": {"rotation": [0, "query.anim_time * 180", 0], "position": [0, "math.sin(query.anim_time * 180) * 0.6", 0]},
        "sign": {"rotation": ["math.sin(query.anim_time * 90) * 2", 0, 0]},
        "fireball": {"position": ["math.sin(query.anim_time * 90) * 9", "math.abs(math.cos(query.anim_time * 90)) * -3", 0],
                     "rotation": ["query.anim_time * 360", "query.anim_time * 360", 0]},
    }
    for t, ph in (("r", 0), ("b", 180)):
        anim[f"rarm_{t}"] = {"rotation": [swing(ph), 0, 0]}
        anim[f"larm_{t}"] = {"rotation": [f"-20 + math.sin(query.anim_time * 360 + {ph}) * -15", 0, f"{'-' if t == 'r' else ''}10"]}
        anim[f"body_{t}"] = {"rotation": [0, f"math.sin(query.anim_time * 360 + {ph}) * 15", 0]}
        anim[f"head_{t}"] = {"rotation": [f"math.sin(query.anim_time * 360 + {ph}) * 5", f"math.sin(query.anim_time * 360 + {ph}) * -10", 0]}
        anim[f"rleg_{t}"] = {"rotation": [f"math.sin(query.anim_time * 360 + {ph}) * 20", 0, 0]}
        anim[f"lleg_{t}"] = {"rotation": [f"math.sin(query.anim_time * 360 + {ph}) * -20", 0, 0]}
        anim[f"fighter_{t}"] = {"position": [f"math.sin(query.anim_time * 360 + {ph}) * {'0.6' if t == 'r' else '-0.6'}",
                                             f"math.abs(math.sin(query.anim_time * 360 + {ph})) * 0.5", 0]}
    return build("arvan_bedwars_duos", "arvan:bedwars_duos", A, bones, C, anim, 4, 1.2, [4, 4.5, 2])


# ================================================================== ROLEPLAY CITY
def roleplay_city():
    A = Atlas(128, 128)
    b = A.alloc("asphalt", 32, 32); A.grad(b, (70, 72, 80), (55, 56, 62)); A.noise(b, [(45, 45, 50), (90, 92, 100)], 3)
    for x in range(b[0] + 2, b[2], 8):
        A.d.rectangle([x, b[1] + 15, x + 3, b[1] + 16], fill=(250, 210, 50))
    b = A.alloc("asphalt_side", 32, 16); A.grad(b, (150, 150, 155), (90, 90, 95))
    A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=(200, 200, 205))

    def windows(nm, w, h, wall, lit_seed):
        bx = A.alloc(nm, w, h)
        A.grad(bx, wall[0], wall[1])
        for yy in range(bx[1] + 2, bx[3] - 3, 5):
            for xx in range(bx[0] + 2, bx[2] - 2, 4):
                lit = ((xx * 7 + yy * 13 + lit_seed) % 5) < 3
                A.d.rectangle([xx, yy, xx + 1, yy + 2], fill=(255, 225, 120) if lit else (40, 60, 95))
                if lit:
                    A.d.point([(xx, yy)], fill=(255, 250, 210))
        A.d.rectangle([bx[0], bx[1], bx[2] - 1, bx[1]], fill=(230, 230, 235))
        return bx

    windows("tower", 16, 48, ((80, 140, 210), (40, 75, 140)), 1)
    windows("mid", 16, 32, ((230, 215, 190), (180, 160, 130)), 3)
    b = windows("shop", 16, 16, ((215, 90, 80), (160, 50, 45)), 5)
    A.d.rectangle([b[0] + 5, b[1] + 9, b[0] + 10, b[3] - 1], fill=(60, 40, 25))
    b = A.alloc("roof", 16, 16); A.grad(b, (90, 90, 100), (60, 60, 70))
    A.d.rectangle([b[0] + 3, b[1] + 3, b[0] + 6, b[1] + 6], fill=(140, 140, 150))
    A.d.ellipse([b[0] + 8, b[1] + 8, b[0] + 13, b[1] + 13], outline=(250, 210, 50))  # helipad H
    b = A.alloc("awning", 16, 8)
    for x in range(b[0], b[2]):
        A.d.line([(x, b[1]), (x, b[3] - 1)], fill=WHITE if (x // 2) % 2 else RED[1])
    b = A.alloc("car", 16, 16); A.grad(b, (255, 90, 80), (170, 20, 30))
    A.d.line([(b[0], b[1] + 3), (b[2] - 1, b[1] + 3)], fill=(255, 190, 180))
    A.d.rectangle([b[0] + 1, b[1] + 6, b[0] + 3, b[1] + 8], fill=(255, 240, 160))
    A.d.rectangle([b[2] - 4, b[1] + 6, b[2] - 2, b[1] + 8], fill=(255, 240, 160))
    b = A.alloc("glass", 16, 16); A.grad(b, (180, 230, 255), (60, 120, 180))
    A.d.line([(b[0] + 2, b[1] + 12), (b[0] + 12, b[1] + 2)], fill=WHITE)
    b = A.alloc("tire", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(25, 25, 30))
    A.d.rectangle([b[0] + 2, b[1] + 2, b[0] + 5, b[1] + 5], fill=(170, 170, 180))
    b = A.alloc("taxi", 16, 16); A.grad(b, (255, 225, 70), (220, 170, 20))
    for x in range(b[0], b[2], 4):
        A.d.rectangle([x, b[1] + 7, x + 1, b[1] + 8], fill=BLACK); A.d.rectangle([x + 2, b[1] + 9, x + 3, b[1] + 10], fill=BLACK)
    b = A.alloc("leaves", 16, 16); A.grad(b, (90, 180, 70), (40, 120, 40)); A.noise(b, [(30, 100, 30), (130, 210, 90)], 2)
    b = A.alloc("trunk", 8, 8); A.grad(b, (120, 85, 50), (80, 55, 30))
    b = A.alloc("metal", 8, 8); A.grad(b, (60, 62, 70), (35, 36, 42))
    b = A.alloc("lamp", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(255, 245, 170))
    A.d.rectangle([b[0] + 2, b[1] + 2, b[0] + 5, b[1] + 5], fill=WHITE)
    b = A.alloc("siren_r", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(255, 40, 40))
    b = A.alloc("siren_b", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(40, 100, 255))
    b = A.alloc("none", 8, 8)
    b = A.alloc("trim", 16, 16); A.grad(b, GOLD[0], GOLD[2])
    # city sign 64x32
    b = A.alloc("sign", 64, 32)
    x0, y0, x1, y1 = b
    A.grad(b, (20, 110, 70), (8, 60, 35))
    A.d.rectangle([x0 + 2, y0 + 2, x1 - 3, y1 - 3], outline=WHITE)
    A.outlined("ROLEPLAY", (x0 + x1) // 2, y0 + 4, GOLD[0], 1)
    A.d.line([(x0 + 6, y0 + 10), (x1 - 7, y0 + 10)], fill=WHITE)
    A.outlined("ARVAN", (x0 + x1) // 2, y0 + 12, WHITE, 2)
    A.text("CITY", (x0 + x1) // 2, y0 + 23, (180, 255, 200))
    b = A.alloc("sign_back", 64, 32); A.grad(b, (15, 80, 50), (8, 45, 28))
    A.text("WELCOME", (b[0] + b[2]) // 2, b[1] + 13, WHITE)

    bones = [("root", [0, 0, 0], None), ("city", [0, 0, 0], "root"), ("car", [0, 2, -6], "root"),
             ("taxi", [0, 2, -3], "root"), ("sign", [0, 2, -9], "root"), ("siren", [0, 7.5, -7], "car")]
    C = [
        # base platform
        ("ground", "city", (-13, 0, -11), (13, 2, 11), F("asphalt_side", u="asphalt", dn="asphalt_side")),
        # buildings
        ("tower", "city", (-12, 2, 2), (-4, 26, 10), F("tower", u="roof", dn="roof")),
        ("tower_top", "city", (-9, 26, 5), (-7, 30, 7), F("metal")),
        ("tower_light", "city", (-8.5, 30, 5.5), (-7.5, 31, 6.5), F("siren_r")),
        ("mid", "city", (-3, 2, 3), (4, 16, 10), F("mid", u="roof", dn="roof")),
        ("shop", "city", (5, 2, 2), (12, 10, 10), F("shop", u="roof", dn="roof")),
        ("awning", "city", (5, 8, 0), (12, 9, 2), F("awning")),
        # tree + lamps
        ("trunk", "city", (8.5, 2, -9), (9.5, 7, -8), F("trunk")),
        ("leaves", "city", (7, 6, -10.5), (11, 10, -6.5), F("leaves")),
        ("leaves2", "city", (7.75, 10, -9.75), (10.25, 11.5, -7.25), F("leaves")),
        ("lamp_pole", "city", (-11.5, 2, -10), (-11, 11, -9.5), F("metal")),
        ("lamp_arm", "city", (-11.5, 10.5, -10), (-9, 11, -9.5), F("metal")),
        ("lamp", "city", (-9.5, 9.5, -10.25), (-8.5, 10.5, -9.25), F("lamp")),
        # police car (red sports)
        ("car_body", "car", (-4, 3, -8), (4, 5.5, -4), F("car", u="car", dn="tire")),
        ("car_cabin", "car", (-2, 5.5, -7.5), (2, 7.5, -4.5), F("glass")),
        ("w1", "car", (-3.5, 2, -8.3), (-1.5, 4, -7.7), F("tire")), ("w2", "car", (1.5, 2, -8.3), (3.5, 4, -7.7), F("tire")),
        ("w3", "car", (-3.5, 2, -4.3), (-1.5, 4, -3.7), F("tire")), ("w4", "car", (1.5, 2, -4.3), (3.5, 4, -3.7), F("tire")),
        ("siren_r", "siren", (-1, 7.5, -6.5), (0, 8.2, -5.5), F("siren_r")),
        ("siren_b", "siren", (0, 7.5, -6.5), (1, 8.2, -5.5), F("siren_b")),
        # taxi (other lane)
        ("taxi_body", "taxi", (-4, 3, -2.5), (4, 5.5, 1), F("taxi", dn="tire")),
        ("taxi_cabin", "taxi", (-2, 5.5, -2), (2, 7.2, 0.5), F("glass")),
        ("tw1", "taxi", (-3.5, 2, -2.8), (-1.5, 4, -2.2), F("tire")), ("tw2", "taxi", (1.5, 2, -2.8), (3.5, 4, -2.2), F("tire")),
        ("tw3", "taxi", (-3.5, 2, 0.7), (-1.5, 4, 1.3), F("tire")), ("tw4", "taxi", (1.5, 2, 0.7), (3.5, 4, 1.3), F("tire")),
        # city sign (front)
        ("sp_l", "sign", (-8, 2, -11), (-7, 12, -10), F("metal")),
        ("sp_r", "sign", (7, 2, -11), (8, 12, -10), F("metal")),
        ("sign_board", "sign", (-10, 11, -11.5), (10, 21, -10.5), F("sign", s="sign_back", e="trim", u="trim")),
    ]
    anim = {"root": {"position": [0, "math.sin(query.anim_time * 90) * 0.6", 0]},
            "car": {"position": ["math.sin(query.anim_time * 90) * 7", 0, 0]},
            "taxi": {"position": ["math.sin(query.anim_time * 90 + 180) * 7", 0, 0]},
            "siren": {"scale": ["1 + math.abs(math.sin(query.anim_time * 720)) * 0.3"] * 3},
            "sign": {"rotation": ["math.sin(query.anim_time * 90) * 2", 0, 0]}}
    return build("arvan_roleplay_city", "arvan:roleplay_city", A, bones, C, anim, 4, 1.3, [3.5, 4, 2])


models = [bedwars_duos(), roleplay_city()]

# ------------------------------------------------------------------ resource pack
RP = os.path.join(OUT, "ArvanLobby_RP")
shutil.rmtree(RP, ignore_errors=True)
for sub in ["models/entity", "animations", "textures/entity", "entity", "render_controllers", "texts"]:
    os.makedirs(os.path.join(RP, sub))
json.dump({"format_version": 2, "header": {
    "name": "§l§bArvan§fGaming §eLobby", "description": "§cBedWars Duos §7& §aRolePlay City §7portal models",
    "uuid": str(uuid.uuid5(NS, "header")), "version": [1, 0, 0], "min_engine_version": [1, 20, 0]},
    "modules": [{"type": "resources", "uuid": str(uuid.uuid5(NS, "module")), "version": [1, 0, 0]}]},
    open(os.path.join(RP, "manifest.json"), "w"), indent=2)
json.dump({"format_version": "1.8.0", "render_controllers": {"controller.render.arvan_lobby": {
    "geometry": "Geometry.default", "materials": [{"*": "Material.default"}], "textures": ["Texture.default"]}}},
    open(os.path.join(RP, "render_controllers/arvan_lobby.render_controllers.json"), "w"), indent=2)
lang = []
for m in models:
    n = m["name"]
    json.dump(m["geo"], open(os.path.join(RP, f"models/entity/{n}.geo.json"), "w"), indent=2)
    json.dump(m["anim"], open(os.path.join(RP, f"animations/{n}.animation.json"), "w"), indent=2)
    json.dump(m["ent"], open(os.path.join(RP, f"entity/{n}.entity.json"), "w"), indent=2)
    shutil.copy(m["tex"], os.path.join(RP, f"textures/entity/{n}.png"))
lang += ["entity.arvan:bedwars_duos.name=§l§cBed§9Wars §fDuos", "entity.arvan:roleplay_city.name=§l§aRolePlay City"]
open(os.path.join(RP, "texts/en_US.lang"), "w").write("\n".join(lang) + "\n")
json.dump(["en_US"], open(os.path.join(RP, "texts/languages.json"), "w"))

# pack icon: both signs
icon = Image.new("RGBA", (256, 256), (18, 12, 28, 255))
dd = ImageDraw.Draw(icon)
dd.ellipse([10, 10, 246, 246], fill=(35, 25, 55))
for i, m in enumerate(models):
    x, y, w, h = m["R"]["sign"]
    sg = m["img"].crop((x, y, x + w, y + h)).resize((w * 3, h * 3), Image.NEAREST)
    icon.paste(sg, (32, 30 + i * 104), sg)
icon.save(os.path.join(OUT, "preview_icon.png"))
icon.save(os.path.join(RP, "pack_icon.png"))


def zipdir(path, dest):
    with zipfile.ZipFile(dest, "w", zipfile.ZIP_DEFLATED) as z:
        for root, _, files in os.walk(path):
            for fn in sorted(files):
                full = os.path.join(root, fn)
                z.write(full, os.path.relpath(full, path))


zipdir(RP, os.path.join(OUT, "ArvanLobby_RP.zip"))
zipdir(RP, os.path.join(OUT, "ArvanLobby.mcpack"))
print("done")
