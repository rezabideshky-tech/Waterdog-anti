#!/usr/bin/env python3
"""ArvanGaming lobby portal models: BedWars Duos island + RolePlay mini city.
Outputs Blockbench projects, Bedrock geo/animations and a resource pack (.zip + .mcpack)."""
import base64, json, math, os, shutil, uuid, zipfile
from PIL import Image, ImageDraw

OUT = os.path.dirname(os.path.abspath(__file__))
NS = uuid.UUID("c9990984-1cbb-49e6-8037-322bc695bae9")
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
                        "visible_bounds_width": bounds[0] * scale * 1.5, "visible_bounds_height": bounds[1] * scale * 1.5,
                        "visible_bounds_offset": [0, bounds[1] * scale / 2, 0]}, "bones": gb}]}
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

    # ---------------- Steve + detailed team armor (base skin + 3D armor overlay layers)
    SKIN, SKIN_L, SKIN_D = (199, 143, 108), (214, 160, 124), (163, 110, 82)
    HAIR, HAIR_D = (58, 38, 20), (40, 26, 12)
    EYE_W, EYE = (255, 255, 255), (73, 62, 160)
    MOUTH, BEARD = (110, 58, 48), (104, 68, 46)
    SHIRT, SHIRT_D, PANTS, PANTS_D = (0, 175, 175), (0, 130, 135), (62, 56, 158), (44, 40, 120)
    TRIM, TRIM_L, TRIM_D = (252, 206, 60), (255, 240, 150), (170, 110, 20)
    ARMOR = {  # light, base, shade, deep
        "r": [(255, 145, 135), (222, 50, 52), (150, 22, 32), (75, 8, 16)],
        "b": [(150, 205, 255), (48, 120, 240), (24, 60, 165), (10, 24, 80)],
    }

    def grid(box, rows, cmap, cell=2):
        for gy, row in enumerate(rows):
            for gx, ch in enumerate(row):
                if ch != ".":
                    x, y = box[0] + gx * cell, box[1] + gy * cell
                    A.d.rectangle([x, y, x + cell - 1, y + cell - 1], fill=cmap[ch])

    def plate(box, pal, rivets=True, ridge=False):
        x0, y0, x1, y1 = box
        A.grad(box, pal[1], pal[2])
        A.noise(box, [pal[0], pal[2]], 9, 23)
        A.d.line([(x0, y0), (x1 - 1, y0)], fill=pal[0]); A.d.line([(x0, y0), (x0, y1 - 1)], fill=pal[0])
        A.d.line([(x0, y1 - 1), (x1 - 1, y1 - 1)], fill=pal[3]); A.d.line([(x1 - 1, y0), (x1 - 1, y1 - 1)], fill=pal[3])
        if ridge:
            m = (x0 + x1) // 2
            A.d.line([(m - 1, y0 + 1), (m - 1, y1 - 2)], fill=pal[0]); A.d.line([(m, y0 + 1), (m, y1 - 2)], fill=pal[2])
        if rivets and x1 - x0 >= 6 and y1 - y0 >= 6:
            for rx, ry in [(x0 + 1, y0 + 1), (x1 - 2, y0 + 1), (x0 + 1, y1 - 2), (x1 - 2, y1 - 2)]:
                A.d.point([(rx, ry)], fill=TRIM_L)

    def trim_line(x0, y, x1):
        A.d.line([(x0, y), (x1, y)], fill=TRIM); A.d.line([(x0, y + 1), (x1, y + 1)], fill=TRIM_D)

    # --- base Steve skin (shared by both fighters)
    S = {"H": HAIR, "h": HAIR_D, "S": SKIN, "L": SKIN_L, "D": SKIN_D, "W": EYE_W, "E": EYE, "M": MOUTH, "B": BEARD}
    b = A.alloc("st_face", 16, 16)
    grid(b, ["HHHHHHHH", "HHHHHHHH", "HLLLLLLH", "SSSSSSSS", "SWESSEWS", "SSSDDSSS", "SSBMMBSS", "SSBBBBSS"], S)
    b = A.alloc("st_hside", 16, 16)
    grid(b, ["HHHHHHHH", "HHHHHHHH", "HHHHHHSS", "HHHHHSSS", "HHHHSSSS", "HHHSSSSS", "HHSSSSSS", "HSSSSSSS"], S)
    b = A.alloc("st_hback", 16, 16)
    grid(b, ["HHHHHHHH"] * 5 + ["hHHHHHHh", "hhHHHHhh", "hhhhhhhh"], S)
    b = A.alloc("st_htop", 16, 16); grid(b, ["HHHHHHHH", "HhHHHHhH"] * 4, S)
    b = A.alloc("st_hbot", 16, 16); grid(b, ["DDDDDDDD"] * 8, S)
    b = A.alloc("st_body", 16, 24); A.grad(b, SHIRT, SHIRT_D); A.d.rectangle([b[0] + 5, b[1], b[0] + 10, b[1] + 3], fill=SKIN)
    A.d.rectangle([b[0], b[1] + 18, b[2] - 1, b[3] - 1], fill=PANTS)
    b = A.alloc("st_bside", 8, 24); A.grad(b, SHIRT, SHIRT_D); A.d.rectangle([b[0], b[1] + 18, b[2] - 1, b[3] - 1], fill=PANTS)
    b = A.alloc("st_arm", 8, 24); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SKIN)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[1] + 8], fill=SHIRT); A.d.line([(b[0], b[1] + 8), (b[2] - 1, b[1] + 8)], fill=SHIRT_D)
    A.d.line([(b[2] - 1, b[1] + 9), (b[2] - 1, b[3] - 1)], fill=SKIN_D)
    b = A.alloc("st_armtop", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SHIRT)
    b = A.alloc("st_hand", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SKIN_D)
    b = A.alloc("st_leg", 8, 24); A.grad(b, PANTS, PANTS_D); A.d.rectangle([b[0], b[3] - 4, b[2] - 1, b[3] - 1], fill=(90, 90, 95))
    b = A.alloc("st_legend", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=PANTS_D)

    for t in ("r", "b"):
        P = ARMOR[t]
        # helmet overlay front: brow band + cheek guards + nose guard, face open (transparent)
        b = A.alloc(f"hm_front_{t}", 20, 20)
        x0, y0, x1, y1 = b
        plate((x0, y0, x1, y0 + 7), P, rivets=False)
        trim_line(x0, y0 + 6, x1 - 1)
        plate((x0, y0 + 7, x0 + 4, y1 - 3), P, rivets=False)
        plate((x1 - 4, y0 + 7, x1, y1 - 3), P, rivets=False)
        plate((x0 + 8, y0 + 7, x0 + 12, y0 + 13), P, rivets=False)  # nose guard
        A.d.rectangle([x0 + 9, y0 + 12, x0 + 10, y0 + 13], fill=TRIM)
        for rx in range(x0 + 2, x1 - 2, 4):
            A.d.point([(rx, y0 + 3)], fill=TRIM_L)
        A.d.rectangle([x0 + 1, y0 + 1, x1 - 2, y0 + 1], fill=P[0])
        b = A.alloc(f"hm_side_{t}", 20, 20)
        plate((b[0], b[1], b[2], b[3] - 2), P)
        trim_line(b[0], b[1] + 6, b[2] - 1)
        A.d.rectangle([b[0] + 2, b[1] + 9, b[0] + 5, b[1] + 12], fill=P[3])  # ear vent
        for vy in range(b[1] + 9, b[1] + 13, 2):
            A.d.line([(b[0] + 2, vy), (b[0] + 5, vy)], fill=P[2])
        b = A.alloc(f"hm_back_{t}", 20, 20); plate((b[0], b[1], b[2], b[3] - 1), P, ridge=True); trim_line(b[0], b[1] + 6, b[2] - 1)
        b = A.alloc(f"hm_top_{t}", 20, 20); plate(b, P, ridge=True)
        b = A.alloc(f"crest_{t}", 16, 8)
        A.grad(b, TRIM_L, TRIM_D)
        for x in range(b[0], b[2], 2):
            A.d.line([(x, b[1]), (x, b[1] + 2)], fill=P[1])
        # chestplate overlay
        b = A.alloc(f"cp_front_{t}", 20, 28)
        x0, y0, x1, y1 = b
        plate((x0, y0, x1, y0 + 19), P, ridge=True)
        A.d.polygon([(x0 + 6, y0), (x1 - 7, y0), (x0 + 10, y0 + 4)], fill=(0, 0, 0, 0))  # neck cut
        A.d.line([(x0 + 6, y0), (x0 + 10, y0 + 4)], fill=TRIM); A.d.line([(x1 - 7, y0), (x0 + 10, y0 + 4)], fill=TRIM)
        A.d.line([(x0 + 1, y0 + 10), (x1 - 2, y0 + 10)], fill=P[3])  # ab plates
        A.d.line([(x0 + 2, y0 + 14), (x1 - 3, y0 + 14)], fill=P[3])
        # emblem: small gold bed
        A.d.rectangle([x0 + 6, y0 + 6, x0 + 13, y0 + 9], fill=TRIM_D)
        A.d.rectangle([x0 + 7, y0 + 6, x0 + 13, y0 + 8], fill=TRIM)
        A.d.rectangle([x0 + 7, y0 + 6, x0 + 8, y0 + 7], fill=WHITE)
        # belt + tassets
        A.d.rectangle([x0, y0 + 19, x1 - 1, y0 + 21], fill=(80, 52, 28)); A.d.line([(x0, y0 + 19), (x1 - 1, y0 + 19)], fill=(120, 80, 45))
        A.d.rectangle([x0 + 8, y0 + 19, x0 + 11, y0 + 21], fill=TRIM); A.d.point([(x0 + 9, y0 + 20)], fill=TRIM_D)
        plate((x0, y0 + 22, x0 + 9, y1), P, rivets=False); plate((x0 + 11, y0 + 22, x1, y1), P, rivets=False)
        b = A.alloc(f"cp_back_{t}", 20, 28)
        plate((b[0], b[1], b[2], b[1] + 19), P, ridge=True)
        A.d.rectangle([b[0], b[1] + 19, b[2] - 1, b[1] + 21], fill=(80, 52, 28))
        plate((b[0], b[1] + 22, b[2], b[3]), P, rivets=False)
        b = A.alloc(f"cp_side_{t}", 12, 28)
        plate((b[0], b[1], b[2], b[1] + 19), P)
        A.d.rectangle([b[0], b[1] + 19, b[2] - 1, b[1] + 21], fill=(80, 52, 28))
        plate((b[0], b[1] + 22, b[2], b[3]), P, rivets=False)
        b = A.alloc(f"cp_top_{t}", 20, 12)
        plate(b, P, rivets=False); A.d.rectangle([b[0] + 6, b[1] + 3, b[0] + 13, b[1] + 8], fill=(0, 0, 0, 0))
        # pauldron (shoulder) + bracer
        b = A.alloc(f"pd_side_{t}", 12, 12)
        plate(b, P); trim_line(b[0], b[3] - 3, b[2] - 1)
        A.d.line([(b[0] + 1, b[1] + 4), (b[2] - 2, b[1] + 4)], fill=P[3])
        b = A.alloc(f"pd_top_{t}", 12, 12); plate(b, P, ridge=True)
        b = A.alloc(f"br_side_{t}", 12, 12); plate(b, P, rivets=False); trim_line(b[0], b[1], b[2] - 1)
        # leggings + boots
        b = A.alloc(f"lg_side_{t}", 12, 24)
        plate(b, P, rivets=False)
        plate((b[0] + 2, b[1] + 9, b[2] - 2, b[1] + 15), P)  # knee plate
        A.d.point([(b[0] + 5, b[1] + 11), (b[0] + 6, b[1] + 11)], fill=TRIM_L)
        b = A.alloc(f"lg_top_{t}", 12, 12); plate(b, P, rivets=False)
        b = A.alloc(f"bt_side_{t}", 12, 12)
        A.grad(b, P[2], P[3]); trim_line(b[0], b[1], b[2] - 1)
        A.d.rectangle([b[0], b[3] - 3, b[2] - 1, b[3] - 1], fill=(35, 24, 16))
        A.d.line([(b[0], b[3] - 3), (b[2] - 1, b[3] - 3)], fill=(70, 50, 32))
        b = A.alloc(f"bt_bot_{t}", 12, 12); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(35, 24, 16))
    # diamond sword parts
    b = A.alloc("blade", 8, 32)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(90, 230, 230))
    A.d.rectangle([b[0] + 1, b[1], b[0] + 3, b[3] - 1], fill=(200, 255, 255))
    A.d.rectangle([b[0] + 6, b[1], b[2] - 1, b[3] - 1], fill=(30, 140, 160))
    A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=(10, 40, 50))
    b = A.alloc("hilt", 8, 8); A.grad(b, (130, 85, 40), (80, 50, 25))
    b = A.alloc("guard", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(30, 140, 160))
    A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=(200, 255, 255))

    # ---------------- sign: big gold BEDWARS logo
    b = A.alloc("sign", 64, 32)
    x0, y0, x1, y1 = b
    mid = (x0 + x1) // 2
    A.grad((x0, y0, mid, y1), (235, 60, 60), (110, 10, 20))
    A.grad((mid, y0, x1, y1), (70, 140, 255), (15, 35, 120))
    for r, c in [(13, (60, 20, 50)), (10, (35, 10, 30))]:  # dark plate behind text
        A.d.rounded_rectangle([x0 + 3, y0 + 16 - r // 1.6, x1 - 4, y0 + 16 + r // 1.6], radius=3, fill=c)
    # gold gradient text, scale 2, with dark outline + drop shadow
    word, sc, ty = "BEDWARS", 2, y0 + 11
    for ox, oy in [(-1, 0), (1, 0), (0, -1), (0, 1), (-1, -1), (1, -1), (-1, 1), (1, 1), (1, 2), (2, 2), (0, 2)]:
        A.text(word, mid + ox, ty + oy, (25, 8, 10), None, sc)
    tmp = Image.new("RGBA", (A.W, A.H), (0, 0, 0, 0))
    saved_img, saved_d = A.img, A.d
    A.img, A.d = tmp, ImageDraw.Draw(tmp)
    A.text(word, mid, ty, WHITE, None, sc)
    A.img, A.d = saved_img, saved_d
    for yy in range(ty, ty + 10):
        tcol = [GOLD[0], GOLD[0], GOLD[1], GOLD[1], GOLD[1], GOLD[2], GOLD[2], GOLD[1], GOLD[2], GOLD[3]][yy - ty]
        for xx in range(x0, x1):
            if tmp.getpixel((xx, yy))[3]:
                A.d.point([(xx, yy)], fill=tcol)
    for xx in range(x0, x1):  # top-left highlight pixel on each letter column
        if tmp.getpixel((xx, ty))[3] and xx % 2 == 0:
            A.d.point([(xx, ty)], fill=WHITE)
    # sparkles + crossed-sword dots
    for sx, sy in [(x0 + 6, y0 + 6), (x1 - 7, y0 + 6), (x0 + 6, y1 - 7), (x1 - 7, y1 - 7), (mid, y0 + 5), (mid, y1 - 6)]:
        A.d.point([(sx, sy)], fill=WHITE)
        A.d.point([(sx - 1, sy), (sx + 1, sy), (sx, sy - 1), (sx, sy + 1)], fill=GOLD[0])
    A.frame(b)
    for cx_, cy_ in [(x0 + 1, y0 + 1), (x1 - 3, y0 + 1), (x0 + 1, y1 - 3), (x1 - 3, y1 - 3)]:
        A.d.rectangle([cx_, cy_, cx_ + 1, cy_ + 1], fill=(90, 255, 140))
    b = A.alloc("sign_back", 64, 32); A.grad(b, (60, 40, 25), (35, 22, 12)); A.frame(b)
    A.text("ARVAN GAMING", (b[0] + b[2]) // 2, b[1] + 13, GOLD[1], GOLD[3])

    # ---------------- geometry
    def steve(t, cx, cz):
        """Half-scale Steve (16 units tall) on y=4, built facing north (-Z), with 3D armor overlays."""
        y = 4
        bones = [(f"fighter_{t}", [cx, y, cz], "root", [0, 60 if t == "r" else -60, 0]),
                 (f"body_{t}", [cx, y + 6, cz], f"fighter_{t}"),
                 (f"head_{t}", [cx, y + 12, cz], f"body_{t}"),
                 (f"rarm_{t}", [cx + 3, y + 11.5, cz], f"body_{t}"),
                 (f"larm_{t}", [cx - 3, y + 11.5, cz], f"body_{t}"),
                 (f"rleg_{t}", [cx + 1, y + 6, cz], f"fighter_{t}"),
                 (f"lleg_{t}", [cx - 1, y + 6, cz], f"fighter_{t}")]
        HEAD = F("st_face", s="st_hback", e="st_hside", u="st_htop", dn="st_hbot")
        HELM = F(f"hm_front_{t}", s=f"hm_back_{t}", e=f"hm_side_{t}", u=f"hm_top_{t}", dn="none")
        BODY = F("st_body", s="st_body", e="st_bside", u="st_bside")
        CHEST = F(f"cp_front_{t}", s=f"cp_back_{t}", e=f"cp_side_{t}", u=f"cp_top_{t}", dn="none")
        ARM = F("st_arm", u="st_armtop", dn="st_hand")
        PAUL = F(f"pd_side_{t}", u=f"pd_top_{t}", dn="none")
        BRAC = F(f"br_side_{t}", u="none", dn="none")
        LEG = F("st_leg", u="st_legend", dn="st_legend")
        LEGG = F(f"lg_side_{t}", u=f"lg_top_{t}", dn="none")
        BOOT = F(f"bt_side_{t}", u="none", dn=f"bt_bot_{t}")
        i = 0.3  # armor inflate
        cubes = [
            (f"head_{t}", f"head_{t}", (cx - 2, y + 12, cz - 2), (cx + 2, y + 16, cz + 2), HEAD),
            (f"helmet_{t}", f"head_{t}", (cx - 2 - i, y + 12.6, cz - 2 - i), (cx + 2 + i, y + 16 + i, cz + 2 + i), HELM),
            (f"crest_{t}", f"head_{t}", (cx - 0.35, y + 16.3, cz - 2), (cx + 0.35, y + 17.4, cz + 2.6), F(f"crest_{t}")),
            (f"body_{t}", f"body_{t}", (cx - 2, y + 6, cz - 1), (cx + 2, y + 12, cz + 1), BODY),
            (f"chest_{t}", f"body_{t}", (cx - 2 - i, y + 5.4, cz - 1 - i), (cx + 2 + i, y + 12 + i, cz + 1 + i), CHEST),
            (f"rarm_{t}", f"rarm_{t}", (cx + 2, y + 6, cz - 1), (cx + 4, y + 12, cz + 1), ARM),
            (f"rpaul_{t}", f"rarm_{t}", (cx + 2 - i, y + 9.6, cz - 1 - i), (cx + 4 + i + 0.2, y + 12 + i + 0.2, cz + 1 + i), PAUL),
            (f"rbrac_{t}", f"rarm_{t}", (cx + 2 - 0.2, y + 6.6, cz - 1 - 0.2), (cx + 4 + 0.2, y + 8.2, cz + 1 + 0.2), BRAC),
            (f"larm_{t}", f"larm_{t}", (cx - 4, y + 6, cz - 1), (cx - 2, y + 12, cz + 1), ARM),
            (f"lpaul_{t}", f"larm_{t}", (cx - 4 - i - 0.2, y + 9.6, cz - 1 - i), (cx - 2 + i, y + 12 + i + 0.2, cz + 1 + i), PAUL),
            (f"lbrac_{t}", f"larm_{t}", (cx - 4 - 0.2, y + 6.6, cz - 1 - 0.2), (cx - 2 + 0.2, y + 8.2, cz + 1 + 0.2), BRAC),
            (f"rleg_{t}", f"rleg_{t}", (cx, y, cz - 1), (cx + 2, y + 6, cz + 1), LEG),
            (f"rlegg_{t}", f"rleg_{t}", (cx - 0.2, y + 1.8, cz - 1 - 0.2), (cx + 2 + 0.2, y + 6, cz + 1 + 0.2), LEGG),
            (f"rboot_{t}", f"rleg_{t}", (cx - i, y - 0.01, cz - 1 - i - 0.2), (cx + 2 + i, y + 2, cz + 1 + i), BOOT),
            (f"lleg_{t}", f"lleg_{t}", (cx - 2, y, cz - 1), (cx, y + 6, cz + 1), LEG),
            (f"llegg_{t}", f"lleg_{t}", (cx - 2 - 0.2, y + 1.8, cz - 1 - 0.2), (cx + 0.2, y + 6, cz + 1 + 0.2), LEGG),
            (f"lboot_{t}", f"lleg_{t}", (cx - 2 - i, y - 0.01, cz - 1 - i - 0.2), (cx + i, y + 2, cz + 1 + i), BOOT),
            # diamond sword in right hand, blade forward (-Z) when arm hangs
            (f"hilt_{t}", f"rarm_{t}", (cx + 2.6, y + 6.4, cz - 1.6), (cx + 3.4, y + 7.2, cz + 1.2), F("hilt")),
            (f"guard_{t}", f"rarm_{t}", (cx + 2.2, y + 6.1, cz - 2.1), (cx + 3.8, y + 7.5, cz - 1.6), F("guard")),
            (f"blade_{t}", f"rarm_{t}", (cx + 2.75, y + 6.4, cz - 9.1), (cx + 3.25, y + 7.2, cz - 2.1),
             F("blade", e="blade", u="blade", dn="blade") | dict(north="guard", south="guard")),
        ]
        return bones, cubes

    bones = [("root", [0, 0, 0], None), ("island", [0, 0, 0], "root"), ("gen", [9, 7, -9], "root"),
             ("orbit", [0, 12, 0], "root"), ("sign", [0, 18, 13], "root")]
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
    return build("arvan_bedwars_duos", "arvan:bedwars_duos", A, bones, C, anim, 4, 3.2, [4, 4.5, 2])


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
    return build("arvan_roleplay_city", "arvan:roleplay_city", A, bones, C, anim, 4, 3.2, [3.5, 4, 2])


# ================================================================== CARGO TRUCK
def cargo_truck():
    A = Atlas(256, 256)
    CAB = [(255, 120, 90), (225, 45, 40), (150, 20, 25), (70, 8, 12)]
    CHROME = [(255, 255, 255), (215, 220, 230), (150, 158, 172), (80, 86, 100)]
    GLASS = [(200, 235, 255), (90, 160, 220), (30, 70, 130)]

    def body(box, pal=CAB):
        A.grad(box, pal[1], pal[2]); A.noise(box, [pal[0], pal[2]], 11, 31)

    def chrome_bar(x0, y0, x1, y1):
        A.grad((x0, y0, x1, y1), CHROME[0], CHROME[2])
        A.d.line([(x0, y1 - 1), (x1 - 1, y1 - 1)], fill=CHROME[3])

    # --- cab front 56x56 (14x14 units)
    b = A.alloc("cab_front", 56, 56); x0, y0, x1, y1 = b
    body(b)
    A.grad((x0 + 3, y0 + 3, x1 - 3, y0 + 24), GLASS[0], GLASS[2])           # windshield
    A.d.polygon([(x0 + 8, y0 + 23), (x0 + 20, y0 + 3), (x0 + 26, y0 + 3), (x0 + 14, y0 + 23)], fill=(220, 245, 255))
    A.d.polygon([(x0 + 18, y0 + 23), (x0 + 30, y0 + 3), (x0 + 32, y0 + 3), (x0 + 20, y0 + 23)], fill=(180, 225, 250))
    A.d.line([(x0 + 27, y0 + 3), (x0 + 27, y0 + 23)], fill=CAB[3])
    A.d.rectangle([x0 + 2, y0 + 2, x1 - 3, y0 + 24], outline=(25, 25, 30))
    A.d.rectangle([x0 + 8, y0 + 4, x1 - 9, y0 + 6], fill=(30, 30, 35))       # sun visor
    A.text("ARVAN", (x0 + x1) // 2, y0 + 4, (255, 210, 80))
    for yy in range(y0 + 29, y0 + 46, 3):                                    # grille
        chrome_bar(x0 + 14, yy, x1 - 14, yy + 2)
    A.d.rectangle([x0 + 13, y0 + 28, x1 - 14, y0 + 46], outline=CHROME[3])
    for lx in (x0 + 3, x1 - 12):                                             # headlights
        A.d.rectangle([lx, y0 + 30, lx + 8, y0 + 37], fill=CHROME[3])
        A.d.rectangle([lx + 1, y0 + 31, lx + 7, y0 + 36], fill=(255, 250, 200))
        A.d.rectangle([lx + 2, y0 + 32, lx + 4, y0 + 33], fill=WHITE)
        A.d.rectangle([lx, y0 + 39, lx + 8, y0 + 41], fill=(255, 170, 30))    # indicator
    A.d.rectangle([x0 + 20, y0 + 48, x1 - 21, y0 + 53], fill=WHITE)           # plate
    A.text("AG 1", (x0 + x1) // 2, y0 + 49, BLACK)
    # --- cab side 48x56 (12 long x 14 tall)
    b = A.alloc("cab_side", 48, 56); x0, y0, x1, y1 = b
    body(b)
    A.grad((x0 + 4, y0 + 4, x0 + 30, y0 + 24), GLASS[0], GLASS[2])           # door window
    A.d.line([(x0 + 8, y0 + 23), (x0 + 18, y0 + 5)], fill=(225, 245, 255), width=2)
    A.d.rectangle([x0 + 3, y0 + 3, x0 + 31, y0 + 25], outline=(25, 25, 30))
    A.d.rectangle([x0 + 2, y0 + 3, x0 + 33, y0 + 48], outline=CAB[3])        # door seam
    A.d.rectangle([x0 + 26, y0 + 29, x0 + 31, y0 + 30], fill=CHROME[1])       # handle
    A.d.ellipse([x0 + 8, y0 + 30, x0 + 24, y0 + 42], fill=GOLD[1], outline=GOLD[3])  # AG badge
    A.text("AG", x0 + 16, y0 + 34, CAB[3])
    A.grad((x0, y0 + 49, x1, y1), (60, 60, 68), (30, 30, 35))                # step / sill
    chrome_bar(x0 + 3, y0 + 51, x0 + 31, y0 + 53)
    A.d.line([(x0, y0 + 26), (x1 - 1, y0 + 26)], fill=GOLD[1])               # pin stripe
    b = A.alloc("cab_back", 56, 56); body(b)
    A.grad((b[0] + 18, b[1] + 6, b[2] - 18, b[1] + 16), GLASS[1], GLASS[2])
    b = A.alloc("cab_top", 56, 48); body(b)
    for yy in range(b[1] + 6, b[3] - 4, 8):
        A.d.line([(b[0] + 3, yy), (b[2] - 4, yy)], fill=CAB[2])
    # --- container 108x68 side (27 x 17 units)
    b = A.alloc("box_side", 108, 68); x0, y0, x1, y1 = b
    A.grad(b, (250, 250, 252), (205, 208, 215))
    for xx in range(x0, x1, 6):
        A.d.line([(xx, y0), (xx, y1 - 1)], fill=(190, 192, 200))
    A.d.rectangle([x0, y0, x1 - 1, y0 + 2], fill=CHROME[2]); A.d.rectangle([x0, y1 - 3, x1 - 1, y1 - 1], fill=CHROME[3])
    A.d.polygon([(x0, y0 + 46), (x1, y0 + 30), (x1, y0 + 40), (x0, y0 + 56)], fill=CAB[1])    # swoosh
    A.d.polygon([(x0, y0 + 56), (x1, y0 + 40), (x1, y0 + 43), (x0, y0 + 59)], fill=GOLD[1])
    A.outlined("ARVAN", (x0 + x1) // 2 - 6, y0 + 7, CAB[1], 4, out=CAB[3])
    A.text("GAMING CARGO", (x0 + x1) // 2 - 6, y0 + 31, (40, 40, 50), None, 1)
    A.d.ellipse([x1 - 22, y0 + 6, x1 - 4, y0 + 24], fill=GOLD[1], outline=GOLD[3])
    A.text("AG", x1 - 13, y0 + 12, CAB[3])
    b = A.alloc("box_back", 60, 68); x0, y0, x1, y1 = b
    A.grad(b, (240, 240, 245), (195, 198, 205))
    m = (x0 + x1) // 2
    A.d.line([(m, y0 + 2), (m, y1 - 3)], fill=(110, 110, 120))
    for bx in (m - 10, m - 4, m + 3, m + 9):                                  # lock bars
        chrome_bar(bx, y0 + 3, bx + 2, y1 - 4)
        A.d.rectangle([bx - 1, y0 + 30, bx + 3, y0 + 34], fill=CHROME[3])
    A.d.rectangle([x0, y0, x1 - 1, y1 - 1], outline=CHROME[3])
    for lx in (x0 + 2, x1 - 8):
        A.d.rectangle([lx, y1 - 10, lx + 5, y1 - 5], fill=(230, 30, 30))      # tail lights
        A.d.rectangle([lx + 1, y1 - 9, lx + 2, y1 - 8], fill=(255, 160, 160))
    for i in range(0, 10, 2):                                                # hazard stripes
        A.d.rectangle([x0 + 2 + i * 5, y1 - 3, x0 + 6 + i * 5, y1 - 2], fill=(255, 200, 0))
    b = A.alloc("box_front", 60, 68); A.grad(b, (235, 235, 240), (200, 203, 210)); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=CHROME[3])
    b = A.alloc("box_top", 30, 54); A.grad(b, (225, 228, 235), (200, 203, 210))
    for yy in range(b[1], b[3], 4): A.d.line([(b[0], yy), (b[2] - 1, yy)], fill=(180, 183, 190))
    # --- wheel (alpha circle) + parts
    b = A.alloc("wheel", 24, 24); x0, y0, x1, y1 = b
    A.d.ellipse([x0, y0, x1 - 1, y1 - 1], fill=(28, 28, 32))
    A.d.ellipse([x0 + 2, y0 + 2, x1 - 3, y1 - 3], outline=(55, 55, 62))
    A.d.ellipse([x0 + 6, y0 + 6, x1 - 7, y1 - 7], fill=CHROME[1], outline=CHROME[3])
    A.d.ellipse([x0 + 9, y0 + 9, x1 - 10, y1 - 10], fill=CHROME[3])
    for a in range(0, 360, 60):
        A.d.point([(x0 + 12 + 4 * math.cos(math.radians(a)), y0 + 12 + 4 * math.sin(math.radians(a)))], fill=GOLD[1])
    b = A.alloc("tread", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(30, 30, 34))
    for yy in range(b[1], b[3], 2): A.d.line([(b[0], yy), (b[2] - 1, yy)], fill=(50, 50, 56))
    b = A.alloc("chrome", 8, 8); A.grad(b, CHROME[0], CHROME[2])
    b = A.alloc("metal", 8, 8); A.grad(b, (70, 72, 82), (35, 36, 42))
    b = A.alloc("amber", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(255, 170, 20)); A.d.point([(b[0] + 2, b[1] + 2)], fill=WHITE)
    b = A.alloc("red", 8, 8); body(b)
    b = A.alloc("glass", 8, 8); A.grad(b, GLASS[0], GLASS[2])
    b = A.alloc("flap", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(25, 25, 28)); A.d.line([(b[0], b[1] + 2), (b[2] - 1, b[1] + 2)], fill=CHROME[2])
    b = A.alloc("smoke", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(170, 170, 178)); A.d.point([(b[0] + 2, b[1] + 2), (b[0] + 3, b[1] + 2)], fill=(210, 210, 215))
    b = A.alloc("none", 8, 8)

    W = lambda: F("tread") | dict(east="wheel", west="wheel")
    bones = [("root", [0, 0, 0], None), ("body", [0, 5, 0], "root"),
             ("axle_f", [0, 3.5, -14], "root"), ("axle_r1", [0, 3.5, 8], "root"), ("axle_r2", [0, 3.5, 15], "root"),
             ("smoke1", [-7.5, 22, -8], "body"), ("smoke2", [7.5, 22, -8], "body")]
    C = [
        # chassis
        ("chassis", "body", (-5.5, 3, -20), (5.5, 5, 21), F("metal")),
        ("bumper", "body", (-7.5, 2.5, -21.5), (7.5, 5.5, -20), F("chrome")),
        # cab (front = north / -Z)
        ("cab", "body", (-7, 5, -20), (7, 19, -8), F("cab_front", s="cab_back", e="cab_side", u="cab_top", dn="metal")),
        ("hood_trim", "body", (-7.2, 12.5, -20.3), (7.2, 13, -19.8), F("chrome")),
        ("spoiler", "body", (-6.5, 19, -15), (6.5, 22, -8.5), F("red")),
        ("visor", "body", (-7, 18.5, -21), (7, 19.2, -19.5), F("metal")),
        ("light1", "body", (-5, 19.2, -19.8), (-3.5, 20, -18.8), F("amber")),
        ("light2", "body", (-2, 19.2, -19.8), (-0.5, 20, -18.8), F("amber")),
        ("light3", "body", (0.5, 19.2, -19.8), (2, 20, -18.8), F("amber")),
        ("light4", "body", (3.5, 19.2, -19.8), (5, 20, -18.8), F("amber")),
        ("mirror_l", "body", (-9.5, 12, -18.5), (-8.5, 16, -17.5), F("metal") | dict(north="glass")),
        ("mirror_l_arm", "body", (-8.5, 14, -18.3), (-7, 14.5, -17.7), F("chrome")),
        ("mirror_r", "body", (8.5, 12, -18.5), (9.5, 16, -17.5), F("metal") | dict(north="glass")),
        ("mirror_r_arm", "body", (7, 14, -18.3), (8.5, 14.5, -17.7), F("chrome")),
        ("stack_l", "body", (-8.2, 8, -8.6), (-7, 22, -7.4), F("chrome")),
        ("stack_r", "body", (7, 8, -8.6), (8.2, 22, -7.4), F("chrome")),
        ("tank_l", "body", (-7.8, 4.5, -7), (-5.5, 7.5, -1), F("chrome")),
        ("tank_r", "body", (5.5, 4.5, -7), (7.8, 7.5, -1), F("chrome")),
        ("fender_f", "body", (-7.6, 7, -17), (7.6, 7.6, -11), F("red")),
        # cargo container
        ("box", "body", (-7.5, 5.5, -7), (7.5, 22.5, 21), F("box_front", s="box_back", e="box_side", u="box_top", dn="metal")),
        ("flap_l", "body", (-7, 1.5, 18.5), (-4, 5, 19), F("flap")),
        ("flap_r", "body", (4, 1.5, 18.5), (7, 5, 19), F("flap")),
        # smoke puffs
        ("puff1", "smoke1", (-8.1, 22.5, -8.6), (-6.9, 23.7, -7.4), F("smoke")),
        ("puff2", "smoke2", (6.9, 22.5, -8.6), (8.1, 23.7, -7.4), F("smoke")),
    ]
    for ax, z in (("axle_f", -14), ("axle_r1", 8), ("axle_r2", 15)):
        for side, (xa, xb) in (("l", (-8, -5)), ("r", (5, 8))):
            C.append((f"w_{ax}_{side}", ax, (xa, 0.5, z - 3), (xb, 6.5, z + 3), W()))
            C.append((f"w_{ax}_{side}45", ax, (xa + 0.05, 0.5, z - 3), (xb - 0.05, 6.5, z + 3),
                      F("none") | dict(north="tread", south="tread", up="tread", down="tread"), (45, 0, 0), (0, 3.5, z)))
    anim = {
        "body": {"position": [0, "math.abs(math.sin(query.anim_time * 720)) * 0.25", 0],
                 "rotation": ["math.sin(query.anim_time * 360) * 0.6", 0, 0]},
        "axle_f": {"rotation": ["query.anim_time * -360", 0, 0]},
        "axle_r1": {"rotation": ["query.anim_time * -360", 0, 0]},
        "axle_r2": {"rotation": ["query.anim_time * -360", 0, 0]},
        "smoke1": {"position": [0, "math.mod(query.anim_time * 6, 6)", "math.mod(query.anim_time * 6, 6) * 0.6"],
                   "scale": ["1 + math.mod(query.anim_time * 6, 6) * 0.35"] * 3},
        "smoke2": {"position": [0, "math.mod(query.anim_time * 6 + 3, 6)", "math.mod(query.anim_time * 6 + 3, 6) * 0.6"],
                   "scale": ["1 + math.mod(query.anim_time * 6 + 3, 6) * 0.35"] * 3},
    }
    return build("arvan_cargo_truck", "arvan:cargo_truck", A, bones, C, anim, 2, 2.2, [4, 2.5, 2])


# ================================================================== SKYBLOCK - COMING SOON
def skyblock_soon():
    A = Atlas(256, 256)
    YEL, BLK = (255, 205, 30), (25, 22, 25)
    # terrain
    b = A.alloc("grass", 16, 16); A.grad(b, (125, 200, 85), (95, 170, 65)); A.noise(b, [(80, 150, 55), (155, 220, 110)], 2)
    b = A.alloc("grass_side", 16, 16); A.grad(b, (140, 100, 66), (100, 72, 48)); A.noise(b, [(80, 56, 36), (165, 125, 85)], 3)
    for x in range(b[0], b[2]):
        A.d.line([(x, b[1]), (x, b[1] + 2 + (x * 7) % 3)], fill=(110, 185, 75))
    b = A.alloc("dirt", 16, 16); A.grad(b, (125, 90, 58), (88, 60, 40)); A.noise(b, [(72, 50, 32), (150, 112, 78)], 3, 2)
    b = A.alloc("stone", 16, 16); A.grad(b, (138, 138, 145), (98, 98, 108)); A.noise(b, [(82, 82, 92), (162, 162, 168)], 3, 3)
    b = A.alloc("log", 16, 16); A.grad(b, (110, 82, 50), (80, 58, 34))
    for x in range(b[0] + 2, b[2], 4): A.d.line([(x, b[1]), (x, b[3] - 1)], fill=(65, 46, 26))
    b = A.alloc("log_top", 16, 16); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(80, 58, 34))
    A.d.ellipse([b[0] + 2, b[1] + 2, b[2] - 3, b[3] - 3], fill=(170, 135, 85), outline=(120, 90, 55))
    b = A.alloc("leaves", 16, 16); A.grad(b, (90, 175, 70), (45, 120, 45)); A.noise(b, [(35, 100, 35), (130, 205, 95)], 2, 5)
    # ghost / blueprint block: transparent with dashed cyan outline
    b = A.alloc("ghost", 16, 16)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(120, 200, 255, 70))
    for i in range(0, 16, 4):
        for (x, y) in [(b[0] + i, b[1]), (b[0] + i, b[3] - 1), (b[0], b[1] + i), (b[2] - 1, b[1] + i)]:
            A.d.rectangle([x, y, x + 1, y], fill=(150, 230, 255, 255))
            A.d.rectangle([x, y, x, y + 1], fill=(150, 230, 255, 255))
    # chest + chains + padlock
    b = A.alloc("chest_front", 16, 16); A.grad(b, (175, 120, 55), (125, 82, 35))
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(70, 45, 20)); A.d.line([(b[0], b[1] + 5), (b[2] - 1, b[1] + 5)], fill=(70, 45, 20))
    b = A.alloc("chest_side", 16, 16); A.grad(b, (165, 112, 50), (120, 78, 32)); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(70, 45, 20))
    A.d.line([(b[0], b[1] + 5), (b[2] - 1, b[1] + 5)], fill=(70, 45, 20))
    b = A.alloc("chest_top", 16, 16); A.grad(b, (185, 130, 62), (150, 100, 45)); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(70, 45, 20))
    b = A.alloc("chain", 8, 16)
    for y in range(b[1], b[3], 4):
        A.d.rectangle([b[0] + 1, y, b[0] + 6, y + 2], outline=(110, 110, 120)); A.d.point([(b[0] + 2, y)], fill=(220, 220, 230))
    b = A.alloc("lock", 16, 16); A.grad(b, GOLD[0], GOLD[2]); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=GOLD[3])
    A.d.ellipse([b[0] + 6, b[1] + 4, b[0] + 9, b[1] + 7], fill=BLK); A.d.rectangle([b[0] + 7, b[1] + 7, b[0] + 8, b[1] + 11], fill=BLK)
    b = A.alloc("shackle", 8, 8); A.grad(b, (235, 235, 240), (150, 150, 160))
    # caution tape + posts, cone, toolbox
    b = A.alloc("tape", 32, 8)
    for x in range(b[0] - 8, b[2], 8):
        A.d.polygon([(x, b[3]), (x + 4, b[1]), (x + 8, b[1]), (x + 4, b[3])], fill=BLK)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=None)
    for x in range(b[0], b[2]):
        for y in range(b[1], b[3]):
            if A.img.getpixel((x, y))[3] == 0: A.d.point([(x, y)], fill=YEL)
    b = A.alloc("post", 8, 16)
    for y in range(b[1], b[3], 4): A.d.rectangle([b[0], y, b[2] - 1, y + 1], fill=YEL); A.d.rectangle([b[0], y + 2, b[2] - 1, y + 3], fill=BLK)
    b = A.alloc("cone", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(255, 120, 20)); A.d.line([(b[0], b[1] + 4), (b[2] - 1, b[1] + 4)], fill=WHITE)
    b = A.alloc("toolbox", 16, 8); A.grad(b, (220, 40, 40), (150, 20, 25)); A.d.line([(b[0], b[1] + 3), (b[2] - 1, b[1] + 3)], fill=(90, 10, 15))
    A.d.rectangle([b[0] + 6, b[1] + 3, b[0] + 9, b[1] + 4], fill=(200, 200, 210))
    # cloud
    b = A.alloc("cloud", 16, 16); A.grad(b, (255, 255, 255), (220, 228, 240)); A.noise(b, [(240, 244, 250)], 3, 8)
    # hourglass
    b = A.alloc("sand", 8, 8); A.grad(b, (250, 225, 140), (220, 185, 90))
    b = A.alloc("glass", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(200, 235, 255, 90), outline=(230, 250, 255, 200))
    b = A.alloc("gold", 16, 16); A.grad(b, GOLD[0], GOLD[2]); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=GOLD[3])
    b = A.alloc("planks", 16, 16); A.grad(b, (178, 132, 78), (140, 100, 56))
    for y in range(b[1] + 3, b[3], 4): A.d.line([(b[0], y), (b[2] - 1, y)], fill=(100, 70, 38))
    b = A.alloc("none", 8, 8)
    # builder Steve: skin, orange safety vest, hard hat, hammer
    SKIN, SKIN_D, HAIR = (199, 143, 108), (163, 110, 82), (58, 38, 20)
    VEST, VEST_D, REFL = (255, 130, 20), (215, 95, 10), (235, 240, 200)
    JEANS, JEANS_D, BOOT = (55, 75, 150), (40, 55, 115), (70, 45, 25)
    S = {"H": HAIR, "S": SKIN, "D": SKIN_D, "W": WHITE, "E": (73, 62, 160), "M": (110, 58, 48), "B": (104, 68, 46)}

    def grid(box, rows, cmap, cell=2):
        for gy, row in enumerate(rows):
            for gx, ch in enumerate(row):
                x, y = box[0] + gx * cell, box[1] + gy * cell
                A.d.rectangle([x, y, x + cell - 1, y + cell - 1], fill=cmap[ch])
    b = A.alloc("face", 16, 16); grid(b, ["HHHHHHHH", "HHHHHHHH", "HSSSSSSH", "SSSSSSSS", "SWESSEWS", "SSSDDSSS", "SSBMMBSS", "SSBBBBSS"], S)
    b = A.alloc("hside", 16, 16); grid(b, ["HHHHHHHH", "HHHHHHHH", "HHHHHHSS", "HHHHHSSS", "HHHHSSSS", "HHHSSSSS", "HHSSSSSS", "HSSSSSSS"], S)
    b = A.alloc("hback", 16, 16); grid(b, ["HHHHHHHH"] * 8, S)
    b = A.alloc("skin", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SKIN_D)
    b = A.alloc("vest", 16, 24); A.grad(b, VEST, VEST_D)
    A.d.rectangle([b[0] + 6, b[1], b[0] + 9, b[1] + 17], fill=(240, 240, 240))  # shirt visible in middle
    for yy in (b[1] + 8, b[1] + 13): A.d.rectangle([b[0], yy, b[2] - 1, yy + 1], fill=REFL)
    A.d.rectangle([b[0], b[1] + 18, b[2] - 1, b[3] - 1], fill=JEANS); A.d.rectangle([b[0], b[1] + 17, b[2] - 1, b[1] + 17], fill=(80, 50, 25))
    b = A.alloc("vest_side", 8, 24); A.grad(b, VEST, VEST_D)
    for yy in (b[1] + 8, b[1] + 13): A.d.rectangle([b[0], yy, b[2] - 1, yy + 1], fill=REFL)
    A.d.rectangle([b[0], b[1] + 18, b[2] - 1, b[3] - 1], fill=JEANS)
    b = A.alloc("arm", 8, 24); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SKIN)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[1] + 7], fill=(240, 240, 240)); A.d.rectangle([b[0], b[3] - 6, b[2] - 1, b[3] - 1], fill=(200, 160, 60))  # glove
    b = A.alloc("leg", 8, 24); A.grad(b, JEANS, JEANS_D); A.d.rectangle([b[0], b[3] - 5, b[2] - 1, b[3] - 1], fill=BOOT)
    b = A.alloc("hat", 16, 16); A.grad(b, (255, 225, 60), (230, 180, 20)); A.d.line([(b[0] + 7, b[1]), (b[0] + 7, b[3] - 1)], fill=(255, 245, 160))
    A.d.line([(b[0], b[3] - 1), (b[2] - 1, b[3] - 1)], fill=(170, 120, 10))
    b = A.alloc("handle", 8, 8); A.grad(b, (150, 100, 55), (100, 65, 35))
    b = A.alloc("hammer", 8, 8); A.grad(b, (170, 175, 185), (95, 100, 112))
    # sign 96x40
    b = A.alloc("sign", 96, 40); x0, y0, x1, y1 = b
    A.grad((x0, y0, x1, y0 + 20), (40, 150, 80), (20, 95, 50))
    A.outlined("SKYBLOCK", (x0 + x1) // 2, y0 + 5, GOLD[0], 2, out=(10, 50, 25))
    A.grad((x0, y0 + 20, x1, y1), (215, 40, 45), (140, 15, 25))
    A.outlined("COMING SOON", (x0 + x1) // 2, y0 + 25, WHITE, 2, out=(70, 5, 12))
    for x in range(x0 - 8, x1, 8):  # caution frame top & bottom
        for (yy0, yy1) in ((y0, y0 + 2), (y1 - 3, y1 - 1)):
            A.d.polygon([(x, yy1), (x + 3, yy0), (x + 7, yy0), (x + 4, yy1)], fill=BLK)
    for x in range(x0, x1):
        for y in list(range(y0, y0 + 3)) + list(range(y1 - 3, y1)):
            if A.img.getpixel((x, y))[:3] != BLK: A.d.point([(x, y)], fill=YEL)
    A.d.line([(x0, y0 + 20), (x1 - 1, y0 + 20)], fill=GOLD[1])
    b = A.alloc("sign_back", 96, 40); A.grad(b, (60, 40, 25), (35, 22, 12)); A.frame(b)
    A.text("ARVAN GAMING", (b[0] + b[2]) // 2, b[1] + 17, GOLD[1], GOLD[3], 1)
    b = A.alloc("trim", 16, 16)
    for y in range(b[1], b[3], 4): A.d.rectangle([b[0], y, b[2] - 1, y + 1], fill=YEL); A.d.rectangle([b[0], y + 2, b[2] - 1, y + 3], fill=BLK)

    # ---------------- geometry
    bones = [("root", [0, 0, 0], None), ("island", [0, 0, 0], "root"), ("clouds", [0, -4, 0], "root"),
             ("ghost", [-6, 4, -6], "root"), ("lock", [-7, 7, 4], "root"), ("qmark", [0, 33, 0], "root"),
             ("sand_top", [11, 14, 6], "root"), ("sand_bot", [11, 11, 6], "root"),
             ("builder", [5, 4, -4], "root", [0, -40, 0]), ("body_s", [5, 10, -4], "builder"),
             ("rarm_s", [8, 15.5, -4], "body_s"), ("head_s", [5, 16, -4], "body_s")]
    GH = F("ghost")
    C = [
        # L-shaped floating island
        ("top_a", "island", (-13, 0, -11), (13, 4, 3), F("grass_side", u="grass", dn="dirt")),
        ("top_b", "island", (-13, 0, 3), (1, 4, 12), F("grass_side", u="grass", dn="dirt")),
        ("under1", "island", (-11, -3, -9), (11, 0, 2), F("dirt")),
        ("under1b", "island", (-11, -3, 2), (-1, 0, 10), F("dirt")),
        ("under2", "island", (-8, -6, -6), (7, -3, 6), F("stone")),
        ("under3", "island", (-5, -9, -3), (3, -6, 3), F("stone")),
        ("tip", "island", (-2, -12, -1.5), (1, -9, 1.5), F("stone")),
        # half-built tree
        ("trunk", "island", (8, 4, -9), (10, 14, -7), F("log", u="log_top", dn="log_top")),
        ("leaf1", "island", (6, 12, -11), (12, 15, -5), F("leaves")),
        ("leaf_ghost", "ghost", (6, 15, -11), (12, 18, -5), GH),
        # locked chest with chains
        ("chest", "island", (-11, 4, 5), (-3, 10, 10), F("chest_front", s="chest_side", e="chest_side", u="chest_top", dn="chest_side")),
        ("chain_v", "island", (-7.5, 3.9, 4.6), (-6.5, 10.2, 10.4), F("chain", u="chain")),
        ("chain_h", "island", (-11.4, 6.5, 4.6), (-2.6, 7.5, 10.4), F("chain", u="chain")),
        ("padlock", "lock", (-8.5, 5.5, 3.4), (-5.5, 8.5, 4.6), F("lock")),
        ("shackle_l", "lock", (-8, 8.5, 3.8), (-7.4, 10, 4.2), F("shackle")),
        ("shackle_r", "lock", (-6.6, 8.5, 3.8), (-6, 10, 4.2), F("shackle")),
        ("shackle_t", "lock", (-8, 9.6, 3.8), (-6, 10.2, 4.2), F("shackle")),
        # blueprint (ghost) blocks being built
        ("g1", "ghost", (-12, 4, -10), (-8, 8, -6), GH), ("g2", "ghost", (-8, 4, -10), (-4, 8, -6), GH),
        ("g3", "ghost", (-12, 8, -10), (-8, 12, -6), GH), ("g4", "ghost", (-12, 4, -6), (-8, 8, -2), GH),
        ("built1", "island", (-4, 4, -10), (0, 8, -6), F("planks")),
        # caution tape + posts around front
        ("post1", "island", (-13, 4, -11.5), (-12, 10, -10.5), F("post")),
        ("post2", "island", (12, 4, -11.5), (13, 10, -10.5), F("post")),
        ("tape_front", "island", (-12, 8.2, -11.2), (12, 9.2, -10.8), F("tape")),
        ("tape_front2", "island", (-12, 6.2, -11.2), (12, 7.2, -10.8), F("tape")),
        ("cone", "island", (10, 4, -2), (12, 5, 0), F("cone")), ("cone2", "island", (10.4, 5, -1.6), (11.6, 7, -0.4), F("cone")),
        ("toolbox", "island", (-2, 4, 6), (2, 6, 8), F("toolbox")),
        # clouds hugging the underside
        ("c1", "clouds", (-16, -5, -6), (-8, -2, 2), F("cloud")), ("c2", "clouds", (8, -7, -4), (16, -4, 4), F("cloud")),
        ("c3", "clouds", (-4, -8, 6), (6, -5, 13), F("cloud")), ("c4", "clouds", (-6, -6, -14), (4, -3, -8), F("cloud")),
        # hourglass
        ("hg_top", "island", (9.5, 15, 4.5), (12.5, 15.6, 7.5), F("gold")), ("hg_bot", "island", (9.5, 4, 4.5), (12.5, 4.6, 7.5), F("gold")),
        ("hg_glass", "island", (10, 4.6, 5), (12, 15, 7), F("glass")),
        ("hg_sand_t", "sand_top", (10.4, 11, 5.4), (11.6, 14.5, 6.6), F("sand")),
        ("hg_sand_b", "sand_bot", (10.4, 4.6, 5.4), (11.6, 7, 6.6), F("sand")),
        # sign
        ("sp_l", "island", (-11, 4, 11), (-10, 20, 12), F("post")), ("sp_r", "island", (10, 4, 11), (11, 20, 12), F("post")),
        ("board", "island", (-13, 19, 10.5), (13, 30, 11.5), F("sign", s="sign_back", e="trim", u="trim")),
        # golden question mark
        ("q1", "qmark", (-2, 36, -0.5), (2, 37, 0.5), F("gold")), ("q2", "qmark", (1, 34, -0.5), (2, 36, 0.5), F("gold")),
        ("q3", "qmark", (-2, 35, -0.5), (-1, 36, 0.5), F("gold")), ("q4", "qmark", (-0.5, 33, -0.5), (1, 34, 0.5), F("gold")),
        ("q5", "qmark", (-0.5, 32, -0.5), (0.5, 33, 0.5), F("gold")), ("q6", "qmark", (-0.5, 30.5, -0.5), (0.5, 31.5, 0.5), F("gold")),
    ]
    cx, cz, y = 5, -4, 4
    C += [
        ("s_head", "head_s", (cx - 2, y + 12, cz - 2), (cx + 2, y + 16, cz + 2), F("face", s="hback", e="hside", u="hback", dn="skin")),
        ("s_hat", "head_s", (cx - 2.3, y + 15, cz - 2.3), (cx + 2.3, y + 16.8, cz + 2.3), F("hat")),
        ("s_brim", "head_s", (cx - 2.3, y + 15, cz - 3.4), (cx + 2.3, y + 15.4, cz - 2.3), F("hat")),
        ("s_body", "body_s", (cx - 2, y + 6, cz - 1), (cx + 2, y + 12, cz + 1), F("vest", e="vest_side", u="vest_side", dn="vest_side")),
        ("s_rarm", "rarm_s", (cx + 2, y + 6, cz - 1), (cx + 4, y + 12, cz + 1), F("arm", u="skin", dn="skin")),
        ("s_larm", "body_s", (cx - 4, y + 6, cz - 1), (cx - 2, y + 12, cz + 1), F("arm", u="skin", dn="skin")),
        ("s_rleg", "builder", (cx, y, cz - 1), (cx + 2, y + 6, cz + 1), F("leg", u="skin", dn="skin")),
        ("s_lleg", "builder", (cx - 2, y, cz - 1), (cx, y + 6, cz + 1), F("leg", u="skin", dn="skin")),
        ("hammer_h", "rarm_s", (cx + 2.6, y + 6.4, cz - 5), (cx + 3.4, y + 7.2, cz + 0.5), F("handle")),
        ("hammer_head", "rarm_s", (cx + 2.2, y + 5.6, cz - 6.2), (cx + 3.8, y + 8, cz - 4.6), F("hammer")),
    ]
    anim = {
        "root": {"position": [0, "math.sin(query.anim_time * 90) * 0.8", 0]},
        "clouds": {"rotation": [0, "query.anim_time * 20", 0]},
        "ghost": {"scale": ["0.92 + math.sin(query.anim_time * 180) * 0.06"] * 3},
        "lock": {"rotation": [0, 0, "math.sin(query.anim_time * 1440) * 6 * math.pow(math.sin(query.anim_time * 90), 8)"]},
        "qmark": {"rotation": [0, "query.anim_time * 120", 0], "position": [0, "math.sin(query.anim_time * 180) * 0.8", 0]},
        "sand_top": {"scale": [1, "1 - math.mod(query.anim_time, 4) / 4", 1]},
        "sand_bot": {"scale": [1, "0.2 + math.mod(query.anim_time, 4) / 4", 1]},
        "rarm_s": {"rotation": ["-70 + math.abs(math.sin(query.anim_time * 270)) * 60", 0, 0]},
        "body_s": {"rotation": ["math.abs(math.sin(query.anim_time * 270)) * 6", 0, 0]},
        "head_s": {"rotation": ["10 + math.abs(math.sin(query.anim_time * 270)) * 6", 0, 0]},
    }
    return build("arvan_skyblock_soon", "arvan:skyblock_soon", A, bones, C, anim, 4, 3.2, [4, 5, 2])


# ================================================================== BATTLE PASS CASTLE (replaces bedwars:battle_pass look)
def battlepass_castle():
    A = Atlas(256, 384)
    STONE = [(200, 200, 205), (165, 165, 172), (125, 125, 135), (85, 85, 95)]

    def bricks(box, pal, bw=8, bh=4):
        x0, y0, x1, y1 = box
        A.grad(box, pal[1], pal[2]); A.noise(box, [pal[0], pal[2]], 7, 41)
        for r, y in enumerate(range(y0, y1, bh)):
            A.d.line([(x0, y), (x1 - 1, y)], fill=pal[3])
            off = (bw // 2) * (r % 2)
            for x in range(x0 + off, x1, bw):
                A.d.line([(x, y), (x, min(y + bh - 1, y1 - 1))], fill=pal[3])

    def wool(box, pal):
        A.grad(box, pal[0], pal[2]); A.noise(box, [pal[1], pal[2], pal[0]], 2, 43)

    def slit(x, y, h=8):
        A.d.rectangle([x, y, x + 1, y + h], fill=(20, 15, 25)); A.d.rectangle([x - 1, y + h // 2 - 1, x + 2, y + h // 2], fill=(20, 15, 25))

    # terrain
    b = A.alloc("grass", 16, 16); A.grad(b, (120, 200, 75), (90, 165, 55)); A.noise(b, [(70, 140, 40), (150, 220, 95)], 2)
    b = A.alloc("grass_side", 16, 16); A.grad(b, (140, 98, 62), (100, 70, 45)); A.noise(b, [(80, 55, 35), (165, 122, 82)], 3)
    for x in range(b[0], b[2]): A.d.line([(x, b[1]), (x, b[1] + 2 + (x * 7) % 3)], fill=(105, 185, 65))
    b = A.alloc("dirt", 16, 16); A.grad(b, (125, 88, 55), (85, 58, 38)); A.noise(b, [(70, 48, 30), (150, 110, 75)], 3, 2)
    b = A.alloc("stone", 16, 16); bricks(b, STONE)
    b = A.alloc("path", 16, 16); A.grad(b, (160, 130, 90), (130, 100, 65)); A.noise(b, [(110, 85, 55), (180, 150, 110)], 3, 5)
    # towers (7 wide x 23 tall -> 28x92)
    for nm, pal in (("red", RED), ("blue", BLUE)):
        b = A.alloc(f"tower_{nm}", 28, 92); x0, y0, x1, y1 = b
        wool(b, pal)
        for yy in (y0 + 10, y0 + 50, y1 - 8):
            A.d.rectangle([x0, yy, x1 - 1, yy + 2], fill=GOLD[1]); A.d.line([(x0, yy + 3), (x1 - 1, yy + 3)], fill=GOLD[3])
        slit(x0 + 13, y0 + 22, 10); slit(x0 + 13, y0 + 60, 10)
        A.d.rectangle([x0, y0, x1 - 1, y1 - 1], outline=pal[3])
        b = A.alloc(f"tower_{nm}_top", 16, 16); wool(b, pal)
        b = A.alloc(f"merlon_{nm}", 8, 8); wool(b, pal); A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=GOLD[1])
        b = A.alloc(f"flag_{nm}", 16, 16); A.grad(b, pal[0], pal[2])
        A.d.polygon([(b[0] + 8, b[1] + 2), (b[0] + 10, b[1] + 6), (b[0] + 14, b[1] + 7), (b[0] + 11, b[1] + 10), (b[0] + 12, b[1] + 14),
                     (b[0] + 8, b[1] + 11), (b[0] + 4, b[1] + 14), (b[0] + 5, b[1] + 10), (b[0] + 2, b[1] + 7), (b[0] + 6, b[1] + 6)], fill=GOLD[1], outline=GOLD[3])
        b = A.alloc(f"roof_{nm}", 16, 16); A.grad(b, pal[1], pal[3])
        for y in range(b[1], b[3], 3): A.d.line([(b[0], y), (b[2] - 1, y)], fill=pal[3])
    # walls
    b = A.alloc("wall", 16, 60); bricks(b, STONE); A.d.rectangle([b[0], b[1], b[2] - 1, b[1] + 2], fill=GOLD[1])
    b = A.alloc("wall_long", 64, 48); bricks(b, STONE); A.d.rectangle([b[0], b[1], b[2] - 1, b[1] + 2], fill=GOLD[1])
    for x in range(b[0] + 8, b[2], 16): slit(x, b[1] + 16, 8)
    b = A.alloc("lintel", 24, 24); bricks(b, STONE)
    A.d.rectangle([b[0], b[3] - 4, b[2] - 1, b[3] - 1], fill=GOLD[2])
    A.d.polygon([(b[0] + 9, b[3] - 4), (b[0] + 12, b[3] - 9), (b[0] + 15, b[3] - 4)], fill=GOLD[1], outline=GOLD[3])  # keystone
    b = A.alloc("merlon", 8, 8); bricks(b, STONE, 4, 4)
    # keep (12 wide x 27 tall -> 48x108), side 32x108
    b = A.alloc("keep", 48, 108); x0, y0, x1, y1 = b; bricks(b, STONE)
    for yy in (y0 + 4, y1 - 6): A.d.rectangle([x0, yy, x1 - 1, yy + 2], fill=GOLD[1])
    b = A.alloc("keep_side", 32, 108); bricks(b, STONE); slit(b[0] + 15, b[1] + 20, 12); slit(b[0] + 15, b[1] + 60, 12)
    b = A.alloc("keep_top", 16, 16); bricks(b, STONE, 8, 8)
    # gate doors (3 wide x 9 tall -> 12x36)
    b = A.alloc("door", 12, 36); x0, y0, x1, y1 = b
    A.grad(b, (150, 100, 55), (105, 68, 35))
    for x in range(x0 + 3, x1, 3): A.d.line([(x, y0), (x, y1 - 1)], fill=(85, 55, 28))
    for yy in (y0 + 5, y0 + 17, y1 - 7):
        A.d.rectangle([x0, yy, x1 - 1, yy + 1], fill=(60, 60, 70)); A.d.point([(x0 + 2, yy), (x1 - 3, yy)], fill=(200, 200, 210))
    A.d.ellipse([x0 + 7, y0 + 18, x0 + 10, y0 + 21], outline=GOLD[1])
    b = A.alloc("wood", 8, 8); A.grad(b, (150, 100, 55), (105, 68, 35))
    # banner (12 wide x 13 tall -> 48x52)
    b = A.alloc("banner", 48, 52); x0, y0, x1, y1 = b
    A.grad(b, (120, 20, 40), (60, 5, 20))
    A.d.polygon([(x0, y1 - 8), (x0 + 24, y1 - 1), (x1, y1 - 8), (x1, y1), (x0, y1)], fill=(0, 0, 0, 0))
    A.d.line([(x0, y1 - 8), (x0 + 24, y1 - 1)], fill=GOLD[1]); A.d.line([(x1 - 1, y1 - 8), (x0 + 24, y1 - 1)], fill=GOLD[1])
    A.d.rectangle([x0, y0, x1 - 1, y0 + 2], fill=GOLD[1]); A.d.line([(x0 + 1, y0 + 3), (x0 + 1, y1 - 9)], fill=GOLD[2]); A.d.line([(x1 - 2, y0 + 3), (x1 - 2, y1 - 9)], fill=GOLD[2])
    A.outlined("BATTLE", (x0 + x1) // 2, y0 + 6, GOLD[0], 1)
    A.outlined("PASS", (x0 + x1) // 2, y0 + 14, GOLD[0], 2)
    for r, c in [(7, GOLD[3]), (6, GOLD[1])]:
        A.d.ellipse([x0 + 24 - r, y0 + 33 - r, x0 + 24 + r, y0 + 33 + r], fill=c)
    A.d.polygon([(x0 + 24, y0 + 28), (x0 + 25, y0 + 32), (x0 + 29, y0 + 33), (x0 + 25, y0 + 34), (x0 + 24, y0 + 38), (x0 + 23, y0 + 34),
                 (x0 + 19, y0 + 33), (x0 + 23, y0 + 32)], fill=WHITE)
    A.text("SEASON 1", (x0 + x1) // 2, y1 - 15, (120, 255, 160), (10, 60, 30))
    # treasure chest + card + coins
    b = A.alloc("tchest", 16, 16); A.grad(b, GOLD[0], GOLD[2]); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=GOLD[3])
    A.d.rectangle([b[0] + 2, b[1] + 2, b[2] - 3, b[3] - 3], fill=(150, 25, 40)); A.d.rectangle([b[0] + 6, b[1] + 1, b[0] + 9, b[1] + 5], fill=GOLD[0])
    b = A.alloc("tchest_top", 16, 16); A.grad(b, (190, 35, 50), (130, 18, 30)); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=GOLD[2])
    A.d.line([(b[0] + 7, b[1]), (b[0] + 7, b[3] - 1)], fill=GOLD[1]); A.d.line([(b[0] + 8, b[1]), (b[0] + 8, b[3] - 1)], fill=GOLD[1])
    b = A.alloc("glow", 16, 16); A.grad(b, (255, 250, 200), (255, 200, 60))
    b = A.alloc("card", 16, 24); x0, y0, x1, y1 = b
    A.grad(b, (150, 40, 220), (60, 10, 110)); A.d.rectangle([x0, y0, x1 - 1, y1 - 1], outline=GOLD[1]); A.d.rectangle([x0 + 1, y0 + 1, x1 - 2, y1 - 2], outline=GOLD[3])
    A.d.polygon([(x0 + 8, y0 + 6), (x0 + 9, y0 + 10), (x0 + 13, y0 + 11), (x0 + 9, y0 + 12), (x0 + 8, y0 + 16), (x0 + 7, y0 + 12), (x0 + 3, y0 + 11), (x0 + 7, y0 + 10)], fill=GOLD[0])
    A.text("BP", (x0 + x1) // 2, y1 - 7, WHITE)
    b = A.alloc("coin", 8, 8); A.d.ellipse([b[0], b[1], b[2] - 1, b[3] - 1], fill=GOLD[1], outline=GOLD[3]); A.d.point([(b[0] + 2, b[1] + 2)], fill=WHITE)
    b = A.alloc("diamond", 16, 16); A.grad(b, (220, 255, 255), (30, 130, 160))
    A.d.polygon([(b[0] + 8, b[1] + 2), (b[0] + 13, b[1] + 8), (b[0] + 8, b[1] + 13), (b[0] + 3, b[1] + 8)], fill=(90, 225, 235), outline=WHITE)
    b = A.alloc("emerald", 16, 16); A.grad(b, (170, 255, 190), (10, 100, 45))
    A.d.polygon([(b[0] + 8, b[1] + 2), (b[0] + 13, b[1] + 8), (b[0] + 8, b[1] + 13), (b[0] + 3, b[1] + 8)], fill=(40, 210, 100), outline=WHITE)
    b = A.alloc("gold", 16, 16); A.grad(b, GOLD[0], GOLD[2]); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=GOLD[3])
    b = A.alloc("flame", 8, 8); A.grad(b, (255, 245, 150), (255, 110, 20))
    b = A.alloc("metal", 8, 8); A.grad(b, (90, 92, 102), (45, 46, 54))
    b = A.alloc("none", 8, 8)
    # guards (face + armor per team)
    SKIN, SKIN_D, HAIR = (199, 143, 108), (163, 110, 82), (58, 38, 20)
    S = {"H": HAIR, "S": SKIN, "D": SKIN_D, "W": WHITE, "E": (73, 62, 160), "M": (110, 58, 48), "B": (104, 68, 46)}

    def grid(box, rows, cmap, cell=2):
        for gy, row in enumerate(rows):
            for gx, ch in enumerate(row):
                if ch != ".":
                    x, y = box[0] + gx * cell, box[1] + gy * cell
                    A.d.rectangle([x, y, x + cell - 1, y + cell - 1], fill=cmap[ch])
    b = A.alloc("g_face", 16, 16); grid(b, ["HHHHHHHH", "HHHHHHHH", "HSSSSSSH", "SSSSSSSS", "SWESSEWS", "SSSDDSSS", "SSBMMBSS", "SSBBBBSS"], S)
    b = A.alloc("g_hair", 16, 16); grid(b, ["HHHHHHHH"] * 8, S)
    b = A.alloc("g_skin", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=SKIN_D)
    for t, P in (("r", RED), ("b", BLUE)):
        b = A.alloc(f"g_helm_{t}", 20, 20); x0, y0, x1, y1 = b
        A.grad((x0, y0, x1, y0 + 7), P[0], P[1]); A.d.rectangle([x0, y0 + 6, x1 - 1, y0 + 7], fill=GOLD[1])
        A.grad((x0, y0 + 7, x0 + 4, y1 - 3), P[1], P[2]); A.grad((x1 - 4, y0 + 7, x1, y1 - 3), P[1], P[2])
        A.grad((x0 + 8, y0 + 7, x0 + 12, y0 + 13), P[1], P[2])
        b = A.alloc(f"g_helm_s_{t}", 20, 20); A.grad(b, P[0], P[2]); A.d.rectangle([b[0], b[1] + 6, b[2] - 1, b[1] + 7], fill=GOLD[1])
        b = A.alloc(f"g_chest_{t}", 16, 24); x0, y0, x1, y1 = b
        A.grad(b, P[0], P[2]); A.noise(b, [P[1], P[2]], 7, 45)
        A.d.line([(x0 + 7, y0 + 1), (x0 + 7, y0 + 15)], fill=P[0]); A.d.line([(x0 + 8, y0 + 1), (x0 + 8, y0 + 15)], fill=P[3])
        A.d.rectangle([x0 + 5, y0 + 5, x0 + 10, y0 + 9], fill=GOLD[1]); A.d.rectangle([x0 + 6, y0 + 6, x0 + 9, y0 + 8], fill=WHITE)
        A.d.rectangle([x0, y0 + 16, x1 - 1, y0 + 17], fill=(80, 52, 28)); A.d.rectangle([x0 + 6, y0 + 16, x0 + 9, y0 + 17], fill=GOLD[1])
        A.d.rectangle([x0, y0 + 18, x1 - 1, y1 - 1], fill=P[2])
        b = A.alloc(f"g_side_{t}", 8, 24); A.grad(b, P[1], P[2]); A.d.rectangle([b[0], b[1] + 16, b[2] - 1, b[1] + 17], fill=(80, 52, 28))
        b = A.alloc(f"g_arm_{t}", 8, 24); A.grad((b[0], b[1], b[2], b[1] + 10), P[0], P[2]); A.d.line([(b[0], b[1] + 9), (b[2] - 1, b[1] + 9)], fill=GOLD[1])
        A.d.rectangle([b[0], b[1] + 10, b[2] - 1, b[3] - 1], fill=SKIN); A.d.rectangle([b[0], b[3] - 8, b[2] - 1, b[3] - 1], fill=P[2])
        b = A.alloc(f"g_leg_{t}", 8, 24); A.grad(b, P[1], P[2]); A.d.rectangle([b[0], b[3] - 6, b[2] - 1, b[3] - 1], fill=P[3])
        A.d.line([(b[0], b[3] - 6), (b[2] - 1, b[3] - 6)], fill=GOLD[1])
        b = A.alloc(f"g_top_{t}", 8, 8); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=P[1])
    b = A.alloc("spear_tip", 8, 8); A.grad(b, (220, 255, 255), (60, 180, 200))
    b = A.alloc("trim", 16, 16); A.grad(b, GOLD[0], GOLD[2])

    # ---------------- geometry (front = -Z)
    bones = [("root", [0, 0, 0], None), ("castle", [0, 0, 0], "root"),
             ("door_l", [-3, 3, -9], "root"), ("door_r", [3, 3, -9], "root"),
             ("lid", [0, 7, 0.5], "root"), ("card", [0, 6, -2], "root"),
             ("flag_l", [-10.5, 30, -8.5], "root"), ("flag_r", [10.5, 30, -8.5], "root"), ("flag_k", [0, 36, 6], "root"),
             ("orbit", [0, 30, 6], "root"), ("torch_l", [-4.5, 10, -11.5], "root"), ("torch_r", [4.5, 10, -11.5], "root")]
    C = [
        # island
        ("ground", "castle", (-16, 0, -15), (16, 3, 13), F("grass_side", u="grass", dn="dirt")),
        ("under1", "castle", (-13, -3, -12), (13, 0, 10), F("dirt")),
        ("under2", "castle", (-9, -6, -8), (9, -3, 6), F("stone")),
        ("under3", "castle", (-4, -9, -4), (4, -6, 3), F("stone")),
        ("path", "castle", (-3, 3, -15), (3, 3.1, -9), F("path")),
        # towers
        ("tower_l", "castle", (-14, 3, -12), (-7, 26, -5), F("tower_red", u="tower_red_top", dn="tower_red_top")),
        ("tower_r", "castle", (7, 3, -12), (14, 26, -5), F("tower_blue", u="tower_blue_top", dn="tower_blue_top")),
        ("roof_l", "castle", (-13, 27, -11), (-8, 29, -6), F("roof_red")), ("roof_l2", "castle", (-12, 29, -10), (-9, 30, -7), F("roof_red")),
        ("roof_r", "castle", (8, 27, -11), (13, 29, -6), F("roof_blue")), ("roof_r2", "castle", (9, 29, -10), (12, 30, -7), F("roof_blue")),
        ("pole_l", "castle", (-10.75, 30, -8.75), (-10.25, 37, -8.25), F("wood")),
        ("pole_r", "castle", (10.25, 30, -8.75), (10.75, 37, -8.25), F("wood")),
        # front wall with gate opening
        ("wall_l", "castle", (-7, 3, -10), (-3, 18, -7), F("wall", u="stone", dn="stone")),
        ("wall_r", "castle", (3, 3, -10), (7, 18, -7), F("wall", u="stone", dn="stone")),
        ("lintel", "castle", (-3, 12, -10), (3, 18, -7), F("lintel", u="stone", dn="stone")),
        # side + back walls
        ("wall_sl", "castle", (-14, 3, -5), (-11, 16, 10), F("wall_long", u="stone")),
        ("wall_sr", "castle", (11, 3, -5), (14, 16, 10), F("wall_long", u="stone")),
        ("wall_b", "castle", (-14, 3, 10), (14, 16, 12), F("wall_long", u="stone")),
        # keep
        ("keep", "castle", (-6, 3, 2), (6, 30, 10), F("keep", s="keep_side", e="keep_side", u="keep_top", dn="keep_top")),
        ("banner", "castle", (-6, 15, 1.4), (6, 28, 1.9), F("none") | dict(north="banner", south="banner")),
        ("banner_rod", "castle", (-6.5, 28, 1.2), (6.5, 28.6, 1.9), F("trim")),
        ("pole_k", "castle", (-0.25, 30, 5.75), (0.25, 39, 6.25), F("wood")),
        # courtyard treasure
        ("tchest", "castle", (-2.5, 3, -3.5), (2.5, 7, 0.5), F("tchest", u="tchest_top", dn="tchest")),
        ("lid", "lid", (-2.6, 7, -3.6), (2.6, 8.5, 0.6), F("tchest_top")),
        ("glow", "card", (-1.5, 7.2, -2.6), (1.5, 7.4, -0.4), F("glow")),
        ("card", "card", (-1.6, 7.5, -1.6), (1.6, 12.3, -1.4), F("none") | dict(north="card", south="card")),
        # torches by the gate
        ("tstick_l", "castle", (-4.75, 8, -11), (-4.25, 10.5, -10.5), F("wood")),
        ("tstick_r", "castle", (4.25, 8, -11), (4.75, 10.5, -10.5), F("wood")),
        ("flame_l", "torch_l", (-5, 10.5, -11.25), (-4, 11.7, -10.25), F("flame")),
        ("flame_r", "torch_r", (4, 10.5, -11.25), (5, 11.7, -10.25), F("flame")),
        # doors (hinged at the gate sides)
        ("door_l", "door_l", (-3, 3, -9.2), (0, 12, -8.6), F("door", e="wood", u="wood", dn="wood")),
        ("door_r", "door_r", (0, 3, -9.2), (3, 12, -8.6), F("door", e="wood", u="wood", dn="wood")),
        # flags
        ("flag_l", "flag_l", (-10.25, 33.5, -8.6), (-6.25, 36.5, -8.4), F("none") | dict(north="flag_red", south="flag_red")),
        ("flag_r", "flag_r", (6.25, 33.5, -8.6), (10.25, 36.5, -8.4), F("none") | dict(north="flag_blue", south="flag_blue")),
        ("flag_k", "flag_k", (0.25, 35.5, 5.9), (5.25, 38.5, 6.1), F("none") | dict(north="flag_red", south="flag_blue")),
        # orbit resources around the keep
        ("o1", "orbit", (9, 29, 5), (11, 31, 7), F("diamond"), (45, 0, 45), (10, 30, 6)),
        ("o2", "orbit", (-11, 29, 5), (-9, 31, 7), F("emerald"), (45, 0, 45), (-10, 30, 6)),
        ("o3", "orbit", (-1, 29, 15), (1, 31, 17), F("gold"), (45, 0, 45), (0, 30, 16)),
    ]
    # merlons on towers, wall and keep
    for x0_, z0_, pal in ((-14, -12, "red"), (7, -12, "blue")):
        for dx, dz in ((0, 0), (5, 0), (0, 5), (5, 5), (2.5, 0), (2.5, 5), (0, 2.5), (5, 2.5)):
            C.append((f"m_{pal}_{dx}_{dz}", "castle", (x0_ + dx, 26, z0_ + dz), (x0_ + dx + 2, 27.5, z0_ + dz + 2), F(f"merlon_{pal}")))
    for i, x in enumerate((-7, -4, -1, 2, 5)):
        C.append((f"m_wall_{i}", "castle", (x, 18, -10), (x + 2, 19.5, -8), F("merlon")))
    for i, (x, z) in enumerate(((-6, 2), (4, 2), (-6, 8), (4, 8), (-1, 2), (-1, 8))):
        C.append((f"m_keep_{i}", "castle", (x, 30, z), (x + 2, 31.5, z + 2), F("merlon")))

    def guard(t, cx, cz):
        y = 3
        bn = f"guard_{t}"
        bones.append((bn, [cx, y, cz], "root", [0, 0, 0]))
        bones.append((f"garm_{t}", [cx + 3, y + 11.5, cz], bn))
        HE = F("g_face", s="g_hair", e="g_hair", u="g_hair", dn="g_skin")
        return [
            (f"g_head_{t}", bn, (cx - 2, y + 12, cz - 2), (cx + 2, y + 16, cz + 2), HE),
            (f"g_helm_{t}", bn, (cx - 2.3, y + 12.6, cz - 2.3), (cx + 2.3, y + 16.3, cz + 2.3),
             F(f"g_helm_{t}", s=f"g_helm_s_{t}", e=f"g_helm_s_{t}", u=f"g_helm_s_{t}", dn="none")),
            (f"g_plume_{t}", bn, (cx - 0.3, y + 16.3, cz - 1.8), (cx + 0.3, y + 17.6, cz + 2.4), F("trim")),
            (f"g_body_{t}", bn, (cx - 2, y + 6, cz - 1), (cx + 2, y + 12, cz + 1), F(f"g_chest_{t}", e=f"g_side_{t}", u=f"g_top_{t}")),
            (f"g_larm_{t}", bn, (cx - 4, y + 6, cz - 1), (cx - 2, y + 12, cz + 1), F(f"g_arm_{t}", u=f"g_top_{t}", dn="g_skin")),
            (f"g_rarm_{t}", f"garm_{t}", (cx + 2, y + 6, cz - 1), (cx + 4, y + 12, cz + 1), F(f"g_arm_{t}", u=f"g_top_{t}", dn="g_skin")),
            (f"g_spear_{t}", f"garm_{t}", (cx + 2.75, y + 3, cz - 2.25), (cx + 3.25, y + 20, cz - 1.75), F("wood")),
            (f"g_tip_{t}", f"garm_{t}", (cx + 2.5, y + 20, cz - 2.5), (cx + 3.5, y + 22, cz - 1.5), F("spear_tip"), (0, 45, 0), (cx + 3, y + 21, cz - 2)),
            (f"g_rleg_{t}", bn, (cx, y, cz - 1), (cx + 2, y + 6, cz + 1), F(f"g_leg_{t}", u=f"g_top_{t}", dn=f"g_top_{t}")),
            (f"g_lleg_{t}", bn, (cx - 2, y, cz - 1), (cx, y + 6, cz + 1), F(f"g_leg_{t}", u=f"g_top_{t}", dn=f"g_top_{t}")),
        ]
    C += guard("r", -5.5, -13) + guard("b", 6.5, -13)
    gate = "math.clamp(math.sin(query.anim_time * 60) * 2.2, 0, 1)"   # 6 s cycle: open, hold, close
    anim = {
        "door_l": {"rotation": [0, f"{gate} * 100", 0]},
        "door_r": {"rotation": [0, f"{gate} * -100", 0]},
        "lid": {"rotation": [f"{gate} * -75", 0, 0]},
        "card": {"position": [0, f"{gate} * 5 + math.sin(query.anim_time * 180) * 0.3 * {gate}", 0],
                 "rotation": [0, f"query.anim_time * 120 * {gate}", 0]},
        "flag_l": {"rotation": [0, "math.sin(query.anim_time * 300) * 15", 0]},
        "flag_r": {"rotation": [0, "math.sin(query.anim_time * 300 + 90) * 15", 0]},
        "flag_k": {"rotation": [0, "math.sin(query.anim_time * 300 + 45) * 15", 0]},
        "orbit": {"rotation": [0, "query.anim_time * -60", 0]},
        "torch_l": {"scale": ["0.85 + math.abs(math.sin(query.anim_time * 900)) * 0.3"] * 3},
        "torch_r": {"scale": ["0.85 + math.abs(math.sin(query.anim_time * 900 + 60)) * 0.3"] * 3},
        "garm_r": {"rotation": ["math.sin(query.anim_time * 90) * 3", 0, 0]},
        "garm_b": {"rotation": ["math.sin(query.anim_time * 90 + 90) * 3", 0, 0]},
    }
    return build("bedwars_battle_pass", "bedwars:battle_pass", A, bones, C, anim, 6, 2.6, [4, 5, 2])


models = [bedwars_duos(), roleplay_city(), cargo_truck(), skyblock_soon(), battlepass_castle()]

# ------------------------------------------------------------------ resource pack
RP = os.path.join(OUT, "ArvanLobby_RP")
shutil.rmtree(RP, ignore_errors=True)
for sub in ["models/entity", "animations", "textures/entity", "entity", "render_controllers", "texts"]:
    os.makedirs(os.path.join(RP, sub))
json.dump({"format_version": 2, "header": {
    "name": "§l§bArvan§fGaming §eLobby", "description": "§cBedWars Duos §7& §aRolePlay City §7portals + §6Battle Pass §7+ §fCargo Truck",
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
# drivable RolePlay truck: same model, wheels driven by real movement
json.dump({"format_version": "1.8.0", "animations": {"animation.arvan_rp_truck.drive": {"loop": True, "bones": {
    "axle_f": {"rotation": ["query.modified_distance_moved * -45", 0, 0]},
    "axle_r1": {"rotation": ["query.modified_distance_moved * -45", 0, 0]},
    "axle_r2": {"rotation": ["query.modified_distance_moved * -45", 0, 0]},
    "body": {"position": [0, "math.abs(math.sin(query.anim_time * 720)) * (0.1 + query.modified_move_speed * 0.4)", 0]},
    "smoke1": {"position": [0, "math.mod(query.anim_time * 6, 6)", "math.mod(query.anim_time * 6, 6) * 0.6"],
               "scale": ["1 + math.mod(query.anim_time * 6, 6) * 0.35"] * 3},
    "smoke2": {"position": [0, "math.mod(query.anim_time * 6 + 3, 6)", "math.mod(query.anim_time * 6 + 3, 6) * 0.6"],
               "scale": ["1 + math.mod(query.anim_time * 6 + 3, 6) * 0.35"] * 3}}}}},
    open(os.path.join(RP, "animations/arvan_rp_truck.animation.json"), "w"), indent=2)
json.dump({"format_version": "1.10.0", "minecraft:client_entity": {"description": {
    "identifier": "arvan:rp_truck", "materials": {"default": "entity_alphatest"},
    "textures": {"default": "textures/entity/arvan_cargo_truck"}, "geometry": {"default": "geometry.arvan_cargo_truck"},
    "animations": {"drive": "animation.arvan_rp_truck.drive"}, "scripts": {"animate": ["drive"], "scale": "2.2"},
    "render_controllers": ["controller.render.arvan_lobby"]}}},
    open(os.path.join(RP, "entity/arvan_rp_truck.entity.json"), "w"), indent=2)
lang += ["entity.arvan:rp_truck.name=§l§cArvan §fTruck"]
# merge Battle Pass pack
BP = os.path.join(OUT, "..", "battlepass", "BedWarsBattlePass_RP")
for sub in ["models/entity", "animations", "textures/entity", "entity", "render_controllers"]:
    for fn in os.listdir(os.path.join(BP, sub)):
        if not os.path.exists(os.path.join(RP, sub, fn)):  # castle model overrides the old battle pass card
            shutil.copy(os.path.join(BP, sub, fn), os.path.join(RP, sub, fn))
lang += [l for l in open(os.path.join(BP, "texts/en_US.lang")).read().splitlines() if l.strip()]
lang += ["entity.arvan:bedwars_duos.name=§l§cBed§9Wars §fDuos", "entity.arvan:roleplay_city.name=§l§aRolePlay City", "entity.arvan:cargo_truck.name=§l§cArvan §fCargo Truck", "entity.arvan:skyblock_soon.name=§l§aSkyBlock §7- §eComing Soon"]
open(os.path.join(RP, "texts/en_US.lang"), "w").write("\n".join(lang) + "\n")
json.dump(["en_US"], open(os.path.join(RP, "texts/languages.json"), "w"))

# pack icon: both signs
icon = Image.new("RGBA", (256, 256), (18, 12, 28, 255))
dd = ImageDraw.Draw(icon)
dd.ellipse([10, 10, 246, 246], fill=(35, 25, 55))
for i, m in enumerate(models[:2]):
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
