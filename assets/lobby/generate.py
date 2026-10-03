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
        for bn, piv, par in bones:
            if par == parent:
                res.append({"name": bn, "origin": piv, "uuid": buid[bn], "export": True, "isOpen": True,
                            "visibility": True, "children": kids[bn] + outl(bn)})
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
    for bn, piv, par in bones:
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
    A = Atlas(128, 128)
    # grass top
    b = A.alloc("grass", 16, 16); A.grad(b, (120, 200, 75), (90, 165, 55)); A.noise(b, [(70, 140, 40), (150, 220, 95)], 2)
    b = A.alloc("grass_side", 16, 16); A.grad(b, (140, 98, 62), (100, 70, 45)); A.noise(b, [(80, 55, 35), (165, 122, 82)], 3)
    for x in range(b[0], b[2]):
        A.d.line([(x, b[1]), (x, b[1] + 2 + (x * 7) % 3)], fill=(105, 185, 65))
    b = A.alloc("dirt", 16, 16); A.grad(b, (125, 88, 55), (85, 58, 38)); A.noise(b, [(70, 48, 30), (150, 110, 75)], 3, 2)
    b = A.alloc("stone", 16, 16); A.grad(b, (135, 135, 140), (95, 95, 105)); A.noise(b, [(80, 80, 90), (160, 160, 165)], 3, 3)
    b = A.alloc("wood", 16, 16); A.grad(b, (165, 115, 62), (115, 78, 42))
    for y in range(b[1], b[3], 4): A.d.line([(b[0], y), (b[2] - 1, y)], fill=(90, 60, 30))
    for nm, pal in [("bed_red", RED), ("bed_blue", BLUE)]:
        b = A.alloc(nm + "_top", 32, 16)
        A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=pal[1]); A.noise(b, [pal[0], pal[2]], 4, 5)
        A.d.rectangle([b[0], b[1], b[0] + 10, b[3] - 1], fill=WHITE)
        A.d.line([(b[0], b[1] + 1), (b[0] + 10, b[1] + 1)], fill=(225, 225, 235))
        A.d.line([(b[0] + 11, b[1]), (b[0] + 11, b[3] - 1)], fill=pal[0])
        b = A.alloc(nm, 16, 16); A.grad(b, pal[0], pal[2]); A.noise(b, [pal[1], pal[2]], 3, 7)
        A.d.line([(b[0], b[1]), (b[2] - 1, b[1])], fill=pal[0])
    # heart sprite with alpha
    b = A.alloc("heart", 16, 16)
    H = ["0011000001100", "0122100012210", "1222210122221", "1222221222221", "1222222222221",
         "0122222222210", "0012222222100", "0001222221000", "0000122210000", "0000012100000", "0000001000000"]
    for y, row in enumerate(H):
        for x, v in enumerate(row):
            if v != "0":
                A.d.point([(b[0] + 1 + x, b[1] + 2 + y)], fill=(90, 0, 20) if v == "1" else (255, 60, 90))
    A.d.point([(b[0] + 4, b[1] + 4), (b[0] + 4, b[1] + 5), (b[0] + 5, b[1] + 4)], fill=(255, 200, 210))
    b = A.alloc("none", 16, 16)
    b = A.alloc("diamond", 16, 16); A.grad(b, (220, 255, 255), (30, 130, 160))
    A.d.polygon([(b[0] + 8, b[1] + 2), (b[0] + 13, b[1] + 8), (b[0] + 8, b[1] + 13), (b[0] + 3, b[1] + 8)], fill=(90, 225, 235), outline=WHITE)
    b = A.alloc("emerald", 16, 16); A.grad(b, (170, 255, 190), (10, 100, 45))
    A.d.polygon([(b[0] + 8, b[1] + 2), (b[0] + 13, b[1] + 8), (b[0] + 8, b[1] + 13), (b[0] + 3, b[1] + 8)], fill=(40, 210, 100), outline=WHITE)
    b = A.alloc("gold", 16, 16); A.grad(b, GOLD[0], GOLD[2]); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=GOLD[3])
    b = A.alloc("obsidian", 16, 16); A.grad(b, (40, 25, 60), (15, 8, 25)); A.noise(b, [(90, 60, 140), (60, 35, 95)], 4, 9)
    # sign board front 64x32
    b = A.alloc("sign", 64, 32)
    x0, y0, x1, y1 = b
    for x in range(x0, x1):  # split red|blue
        t = (x - x0) / 63
        c = RED[1] if t < 0.5 else BLUE[1]
        A.d.line([(x, y0), (x, y1 - 1)], fill=c)
    A.grad((x0 + 3, y0 + 3, x1 - 3, y0 + 16), (25, 15, 35), (10, 5, 15))
    A.outlined("BEDWARS", (x0 + x1) // 2, y0 + 5, GOLD[0], 1)
    A.d.line([(x0 + 3, y0 + 16), (x1 - 4, y0 + 16)], fill=GOLD[1])
    A.outlined("DUOS", (x0 + x1) // 2, y0 + 19, WHITE, 2)
    A.frame(b)
    for cx in (x0 + 2, x1 - 3):
        A.d.rectangle([cx - 1, y0 + 1, cx, y0 + 2], fill=(90, 255, 140))
    b = A.alloc("sign_back", 64, 32); A.grad(b, (60, 40, 25), (35, 22, 12)); A.frame(b)
    A.text("ARVAN GAMING", (b[0] + b[2]) // 2, b[1] + 13, GOLD[1], GOLD[3])
    b = A.alloc("trim", 16, 16); A.grad(b, GOLD[0], GOLD[2])

    bones = [("root", [0, 0, 0], None), ("island", [0, 0, 0], "root"), ("heart", [0, 15, 0], "root"),
             ("orbit", [0, 10, 0], "root"), ("sign", [0, 14, 7], "root")]
    C = [
        # floating island (stepped underside)
        ("top", "island", (-11, 0, -9), (11, 4, 9), F("grass_side", u="grass", dn="dirt")),
        ("under1", "island", (-9, -3, -7), (9, 0, 7), F("dirt")),
        ("under2", "island", (-6, -6, -5), (6, -3, 5), F("stone")),
        ("under3", "island", (-3, -9, -2.5), (3, -6, 2.5), F("stone")),
        ("tip", "island", (-1, -11, -1), (1, -9, 1), F("stone")),
        # beds
        ("bed_red", "island", (-9, 5, -5), (-1.5, 7.5, 6), F("bed_red", u="bed_red_top", dn="wood")),
        ("bed_red_legs", "island", (-9, 4, -5), (-1.5, 5, 6), F("wood")),
        ("bed_blue", "island", (1.5, 5, -5), (9, 7.5, 6), F("bed_blue", u="bed_blue_top", dn="wood")),
        ("bed_blue_legs", "island", (1.5, 4, -5), (9, 5, 6), F("wood")),
        # defense blocks
        ("obs_l", "island", (-11, 4, -9), (-8, 7, -6), F("obsidian")),
        ("obs_r", "island", (8, 4, -9), (11, 7, -6), F("obsidian")),
        ("gen", "island", (-1.5, 4, -8.5), (1.5, 5, -5.5), F("gold")),
        ("gen_gem", "island", (-1, 5.5, -7.5), (1, 7.5, -5.5), F("diamond"), (0, 45, 0), (0, 6.5, -6.5)),
        # heart (flat sprite)
        ("heart", "heart", (-3.5, 12, -0.25), (3.5, 19, 0.25), F("none") | dict(north="heart", south="heart")),
        # orbit gems
        ("o_dia", "orbit", (12, 9, -1), (14, 11, 1), F("diamond"), (45, 0, 45), (13, 10, 0)),
        ("o_eme", "orbit", (-14, 9, -1), (-12, 11, 1), F("emerald"), (45, 0, 45), (-13, 10, 0)),
        ("o_gld", "orbit", (-1, 9, 12), (1, 11, 14), F("gold"), (45, 0, 45), (0, 10, 13)),
        # sign board behind beds
        ("post_l", "sign", (-9, 4, 7), (-8, 14, 8), F("wood")),
        ("post_r", "sign", (8, 4, 7), (9, 14, 8), F("wood")),
        ("board", "sign", (-10, 13, 6.5), (10, 23, 7.5), F("sign", s="sign_back", e="trim", u="trim")),
    ]
    anim = {"root": {"position": [0, "math.sin(query.anim_time * 90) * 1.0", 0]},
            "heart": {"rotation": [0, "query.anim_time * 90", 0],
                      "scale": ["1 + math.pow(math.sin(query.anim_time * 180), 8) * 0.25"] * 3,
                      "position": [0, "math.sin(query.anim_time * 180) * 0.5", 0]},
            "orbit": {"rotation": [0, "query.anim_time * -90", 0]},
            "sign": {"rotation": ["math.sin(query.anim_time * 90) * 2", 0, 0]}}
    return build("arvan_bedwars_duos", "arvan:bedwars_duos", A, bones, C, anim, 4, 1.4, [3, 3.5, 2])


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
