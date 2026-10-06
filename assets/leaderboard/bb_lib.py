"""bb_lib.py — کتابخانهٔ ساخت دارایی‌های ArvanGaming
=====================================================
ابزارهای این فایل:
  • بافت‌ساز (Atlas) با جای‌گذاری خودکار + فونت پیکسلی ۳×۵ فارسی/انگلیسی
  • DSL مدل‌سازی: Bone / Cube / F() — همان چیزی که در بلوک‌بنج می‌بینی
  • خروجی‌ها: ‎.bbmodel‎ (پروژهٔ بلوک‌بنج) + ‎.geo.json‎ + ‎.animation.json‎ (بدراک)
  • پیش‌نمایش سه‌بعدی ایزومتریک با PIL برای بازبینی سریع مدل‌ها
  • ساختِ ریسورس‌پک، ‎.mcpack‎ و فایل zip

قاعدهٔ مهم: در بدراک محور X مدل آینه می‌شود؛ خروجی geo دقیقاً مثل مدل‌های
موجود پروژه (assets/lobby) آینه می‌کند تا مدل داخل بازی درست دیده شود.
"""
from __future__ import annotations

import base64
import json
import os
import shutil
import uuid
import zipfile

from PIL import Image, ImageDraw

# ----------------------------------------------------------------- فونت ۳×۵
FONT = {
    "A": "010|101|111|101|101", "B": "110|101|110|101|110", "C": "011|100|100|100|011",
    "D": "110|101|101|101|110", "E": "111|100|110|100|111", "F": "111|100|110|100|100",
    "G": "011|100|101|101|011", "H": "101|101|111|101|101", "I": "111|010|010|010|111",
    "J": "001|001|001|101|010", "K": "101|101|110|101|101", "L": "100|100|100|100|111",
    "M": "101|111|111|101|101", "N": "101|111|111|111|101", "O": "010|101|101|101|010",
    "P": "110|101|110|100|100", "Q": "010|101|101|110|011", "R": "110|101|110|101|101",
    "S": "011|100|010|001|110", "T": "111|010|010|010|010", "U": "101|101|101|101|111",
    "V": "101|101|101|101|010", "W": "101|101|111|111|101", "X": "101|101|010|101|101",
    "Y": "101|101|010|010|010", "Z": "111|001|010|100|111",
    "0": "111|101|101|101|111", "1": "010|110|010|010|111", "2": "110|001|010|100|111",
    "3": "111|001|011|001|111", "4": "101|101|111|001|001", "5": "111|100|110|001|110",
    "6": "011|100|111|101|111", "7": "111|001|010|010|010", "8": "111|101|111|101|111",
    "9": "111|101|111|001|110",
    "-": "000|000|111|000|000", "+": "000|010|111|010|000", ".": "000|000|000|000|010",
    ":": "000|010|000|010|000", "!": "010|010|010|000|010", "/": "001|001|010|100|100",
    "#": "101|111|101|111|101", "%": "101|001|010|100|101", "*": "101|010|111|010|101",
    " ": "000|000|000|000|000",
}


def _rows(ch: str):
    return FONT.get(ch.upper(), FONT[" "]).split("|")


def _lerp(a, b, t):
    if len(b) < len(a):                      # کوتاه‌تر را با آلفای کامل پر کن
        b = tuple(b) + (255,) * (len(a) - len(b))
    return tuple(int(round(a[i] + (b[i] - a[i]) * t)) for i in range(len(a)))


def _rgba(c, a=255):
    if isinstance(c, str):
        c = tuple(int(c[i:i + 2], 16) for i in (0, 2, 4))
    if len(c) == 3:
        return (c[0], c[1], c[2], a)
    return c


# -------------------------------------------------------------------- بافت
class Atlas:
    """بوم بافت با جای‌گذاری خودکار (چیدمان سطری)."""

    def __init__(self, size: int = 256):
        self.size = size
        self.img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
        self.d = ImageDraw.Draw(self.img)
        self.cx, self.cy, self.row = 0, 0, 0

    def alloc(self, w: int, h: int):
        if self.cx + w > self.size:
            self.cx = 0
            self.cy += self.row + 1
            self.row = 0
        x, y = self.cx, self.cy
        self.cx += w + 1
        self.row = max(self.row, h)
        if y + h > self.size:
            raise RuntimeError("بافت پر شد — اندازهٔ Atlas را بزرگ‌تر کن")
        return x, y


ATLAS = Atlas(512)
REG: dict[str, tuple[int, int, int, int]] = {}


def tex(name: str, w: int, h: int, fn):
    """یک ناحیهٔ بافت می‌سازد: ‎fn(d, x, y, w, h)‎."""
    x, y = ATLAS.alloc(w, h)
    REG[name] = (x, y, w, h)
    fn(ATLAS.d, x, y, w, h)
    return name


def rect(d, x, y, w, h, col, alpha=255):
    x, y = int(round(x)), int(round(y))
    w, h = max(1, int(round(w))), max(1, int(round(h)))
    d.rectangle([x, y, x + w - 1, y + h - 1], fill=_rgba(col, alpha))


def grad(d, x, y, w, h, top, bot, alpha=255):
    top, bot = _rgba(top, alpha), _rgba(bot, alpha)
    for i in range(h):
        d.rectangle([x, y + i, x + w - 1, y + i], fill=_lerp(top, bot, i / max(1, h - 1)))


def noise(d, x, y, w, h, cols, step=3, alpha=255):
    for yy in range(y, y + h):
        for xx in range(x, x + w):
            hsh = (xx * 73856093 ^ yy * 19349663) & 0xFFFF
            if hsh % step == 0:
                d.point([(xx, yy)], fill=_rgba(cols[(hsh // step) % len(cols)], alpha))


def outline(d, x, y, w, h, col, alpha=255):
    x, y = int(round(x)), int(round(y))
    w, h = max(1, int(round(w))), max(1, int(round(h)))
    d.rectangle([x, y, x + w - 1, y + h - 1], outline=_rgba(col, alpha))


def bevel(d, x, y, w, h, base, light, dark):
    """بلوک ماینکری: بدنه + لبهٔ روشن بالا/چپ + لبهٔ تیره پایین/راست."""
    rect(d, x, y, w, h, base)
    rect(d, x, y, w, 1, light)
    rect(d, x, y, 1, h, light)
    rect(d, x, y + h - 1, w, 1, dark)
    rect(d, x + w - 1, y, 1, h, dark)


def plate(d, x, y, w, h, base, light, dark, spots=None):
    bevel(d, x, y, w, h, base, light, dark)
    noise(d, x, y, w, h, [light, dark], 5)
    if spots:
        noise(d, x, y, w, h, spots, 7)


def wool(d, x, y, w, h, base, light, dark):
    rect(d, x, y, w, h, base)
    for yy in range(y, y + h, 4):
        for xx in range(x, x + w, 4):
            if ((xx // 4) + (yy // 4)) % 2:
                rect(d, xx, yy, 4, 4, _lerp(base, dark, 0.35))
    noise(d, x, y, w, h, [light, dark], 2)


def plank(d, x, y, w, h, base=(150, 104, 58), dark=(96, 64, 32), light=(190, 140, 84)):
    rect(d, x, y, w, h, base)
    for i in range(0, h, 6):
        rect(d, x, y + i, w, 1, dark)
        d.point([(x + (i * 7) % max(1, w - 2) + 1, y + i + 3)], fill=_rgba(light))
    noise(d, x, y, w, h, [light, dark], 4)


def stone(d, x, y, w, h, base=(196, 196, 190), light=(226, 226, 220), dark=(150, 150, 146)):
    plate(d, x, y, w, h, base, light, dark)
    for i in range(3):
        px = x + (i * 5 + 3) % max(1, w - 6)
        py = y + (i * 7 + 4) % max(1, h - 6)
        rect(d, px, py, 3, 2, dark)


def endstone(d, x, y, w, h):
    plate(d, x, y, w, h, (238, 236, 175), (252, 250, 205), (206, 202, 140),
          spots=[(216, 212, 150), (250, 250, 210)])


def goldblock(d, x, y, w, h):
    rect(d, x, y, w, h, (252, 206, 60))
    rect(d, x, y, w, 2, (255, 240, 150))
    rect(d, x, y + h - 2, w, 2, (196, 132, 20))
    for i in range(0, max(w, h), 8):
        rect(d, x + i % max(1, w - 3), y + 3, 3, 3, (255, 240, 150))
        rect(d, x + (i + 4) % max(1, w - 3), y + h - 7, 3, 3, (200, 140, 24))
    noise(d, x, y, w, h, [(255, 226, 120), (206, 150, 30)], 4)
    outline(d, x, y, w, h, (140, 92, 14))


def holo_glass(d, x, y, w, h, col=(90, 220, 255), alpha=118, grid=True):
    """شیشهٔ هولوگرامی نیمه‌شفاف با خطوط اسکن."""
    rect(d, x, y, w, h, col, alpha)
    if grid:
        for yy in range(y, y + h, 3):
            rect(d, x, yy, w, 1, _lerp(_rgba(col), (255, 255, 255), 0.35), min(255, alpha + 45))
    for i in range(4):
        rect(d, x + i * w // 4, y, 1, h, _lerp(_rgba(col), (255, 255, 255), 0.6), 165)
    outline(d, x, y, w, h, _lerp(_rgba(col), (255, 255, 255), 0.7), min(255, alpha + 80))


def holo_beam(d, x, y, w, h, col=(90, 220, 255)):
    """ستون نور: از پایین پررنگ به بالا محو."""
    for i in range(h):
        a = int(210 * (1 - i / max(1, h - 1)) ** 1.4) + 10
        d.rectangle([x, y + i, x + w - 1, y + i], fill=_rgba(col, a))
    for i in range(0, h, 4):
        rect(d, x, y + i, w, 1, _lerp(_rgba(col), (255, 255, 255), 0.5), 120)


def glow(d, x, y, w, h, col=(255, 220, 120)):
    cx, cy = x + w / 2, y + h / 2
    for i in range(min(w, h) // 2, 0, -1):
        t = i / (min(w, h) / 2)
        d.ellipse([cx - i, cy - i, cx + i, cy + i], fill=_rgba(col, int(40 + 120 * (1 - t))))


def pixel_text(d, x, y, s, col, shadow=None, sc=1, out_col=None):
    """متن پیکسلی؛ x,y گوشهٔ چپ-بالا."""
    x, y = int(round(x)), int(round(y))
    if out_col is not None:
        for ox, oy in ((-1, 0), (1, 0), (0, -1), (0, 1), (-1, -1), (1, 1), (-1, 1), (1, -1)):
            pixel_text(d, x + ox, y + oy, s, out_col, None, sc, None)
    cx = x
    for ch in s:
        for ry, row in enumerate(_rows(ch)):
            for rx, v in enumerate(row):
                if v == "1":
                    px, py = cx + rx * sc, y + ry * sc
                    if shadow:
                        rect(d, px + sc, py + sc, sc, sc, shadow)
                    rect(d, px, py, sc, sc, col)
        cx += 4 * sc
    return cx


def text_w(s, sc=1):
    return len(s) * 4 * sc - sc


def center_text(d, cx, y, s, col, shadow=None, sc=1, out_col=None):
    pixel_text(d, int(cx - text_w(s, sc) / 2), y, s, col, shadow, sc, out_col)


def gem(d, x, y, w, h, light, mid, dark):
    grad(d, x, y, w, h, light, dark)
    cx, cy = x + w // 2, y + h // 2
    d.polygon([(cx, y + 2), (x + w - 3, cy), (cx, y + h - 3), (x + 2, cy)],
              fill=_rgba(mid), outline=_rgba(light))
    rect(d, x + 3, y + 3, max(2, w // 4), 1, (255, 255, 255))
    outline(d, x, y, w, h, dark)


def crown_icon(d, x, y, w, h):
    base_y = y + h - 1
    pts = []
    n = max(3, w // 6)
    for i in range(n * 2 + 1):
        px = x + i * (w - 1) / (n * 2)
        py = y + (0 if i % 2 == 0 else h * 0.45)
        pts.append((px, py))
    pts += [(x + w - 1, base_y), (x, base_y)]
    d.polygon(pts, fill=(252, 206, 60))
    d.polygon(pts, outline=(150, 96, 16))
    rect(d, x, base_y - 2, w, 3, (255, 226, 120))
    for i in range(n):
        rect(d, int(x + (i + 0.5) * (w - 1) / n), y + 1, 1, 2, (255, 90, 90))


def medal(d, x, y, w, h, col, dark, light):
    cx, cy = x + w / 2, y + h * 0.62
    r = min(w, h) * 0.38
    d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=_rgba(col), outline=_rgba(dark))
    d.ellipse([cx - r * 0.6, cy - r * 0.6, cx + r * 0.6, cy + r * 0.6], fill=_rgba(light))
    rect(d, cx - r * 0.35, y, r * 0.7, h * 0.3, dark)
    rect(d, cx - r * 0.1, y, r * 0.2, h * 0.3, light)


def sword_icon(d, x, y, w, h, blade=(200, 240, 255), edge=(255, 255, 255), hilt=(150, 100, 50)):
    n = min(w, h)
    for i in range(n):
        px, py = x + int(i * (w - 1) / n), y + int((n - 1 - i) * (h - 1) / n)
        rect(d, px, py, max(1, w // 16), max(1, h // 16), blade if i < n * 0.7 else hilt)
        if i < n * 0.7:
            d.point([(px, py)], fill=_rgba(edge))


def bed_icon(d, x, y, w, h, wool=(220, 40, 45)):
    rect(d, x, y + h // 3, w, h // 3, wool)
    rect(d, x, y + h // 3, w, 1, (255, 110, 100))
    rect(d, x, y, w // 3, h // 3, (250, 250, 250))
    rect(d, x + w // 3, y + h // 3 + h // 3 - 1, w * 2 // 3, 1, (140, 15, 25))
    rect(d, x, y + h * 2 // 3, w, 1, (110, 70, 35))
    outline(d, x, y, w, h, (20, 20, 25))


def star_icon(d, x, y, w, h, col=(255, 220, 90)):
    cx, cy = x + w / 2, y + h / 2
    pts = []
    for i in range(10):
        import math as _m
        ang = -_m.pi / 2 + i * _m.pi / 5
        r = (min(w, h) / 2) * (0.45 if i % 2 else 1.0)
        pts.append((cx + _m.cos(ang) * r, cy + _m.sin(ang) * r))
    d.polygon(pts, fill=_rgba(col), outline=_rgba((180, 120, 20)))


# ------------------------------------------------------------------ DSL مدل
class Cube:
    def __init__(self, name, frm, to, faces, rot=None, pivot=None):
        self.name, self.frm, self.to, self.faces = name, list(frm), list(to), faces
        self.rot = list(rot) if rot else None
        self.pivot = list(pivot) if pivot else None


class Bone:
    def __init__(self, name, pivot=(0, 0, 0)):
        self.name = name
        self.pivot = list(pivot)
        self.cubes: list[Cube] = []


class Model:
    """مدل بدراک: چند Bone که هرکدام چند مکعب دارند (همان ساختار بلوک‌بنج)."""

    def __init__(self, name: str, ident: str):
        self.name = name
        self.ident = ident
        self.bones: list[Bone] = []
        self.anims: dict[str, dict] = {}      # {anim_name: {bone: {channel: [x,y,z]}}}

    def bone(self, name: str, pivot=(0, 0, 0)) -> Bone:
        b = Bone(name, pivot)
        self.bones.append(b)
        return b

    def cube(self, bone: Bone, name: str, frm, to, faces, rot=None, pivot=None) -> Cube:
        c = Cube(name, frm, to, faces, rot, pivot)
        bone.cubes.append(c)
        return c

    def anim(self, name: str, spec: dict):
        """spec: {bone_name: {"rotation": [x,y,z], "position": [...], "scale": [...]}}"""
        self.anims[name] = spec

    def bone_by_name(self, n: str) -> Bone:
        for b in self.bones:
            if b.name == n:
                return b
        raise KeyError(n)


def F(front=None, back=None, left=None, right=None, top=None, bottom=None, all=None):
    """نقشهٔ وجه‌ها: front=شمال (روبه بازیکن)، back=جنوب، right=شرق، left=غرب."""
    out = {}
    for key, val in (("north", front), ("south", back), ("west", left),
                     ("east", right), ("up", top), ("down", bottom)):
        if val:
            out[key] = val
        elif all:
            out[key] = all
    return out


# --------------------------------------------------------------- خروجی‌ها
def _uv(name: str):
    x, y, w, h = REG[name]
    return [x, y, x + w, y + h]


def _uvs(name: str):
    x, y, w, h = REG[name]
    return [x, y], [w, h]


def save_bbmodel(model: Model, path: str, texture_name: str, atlas_png: bytes, size: int):
    """پروژهٔ بلوک‌بنج (‎.bbmodel‎) با بافت جاسازی‌شده."""

    def new_uuid():
        return str(uuid.uuid4())

    elements, kids, buid = [], {}, {}
    for bone in model.bones:
        kids[bone.name] = []
        buid[bone.name] = new_uuid()
        for c in bone.cubes:
            u = new_uuid()
            kids[bone.name].append(u)
            el = {
                "name": c.name, "type": "cube", "uuid": u, "box_uv": False,
                "rescale": False, "locked": False, "light_emission": 0,
                "from": c.frm, "to": c.to,
                "autouv": 0, "color": 0, "origin": list(c.pivot or [0, 0, 0]),
                "faces": {k: {"uv": _uv(v), "texture": 0} for k, v in c.faces.items()},
            }
            if c.rot:
                el["rotation"] = c.rot
            elements.append(el)

    anims = []
    for aname, spec in model.anims.items():
        animators = {}
        for bname, channels in spec.items():
            kfs = []
            for ch, vals in channels.items():
                kfs.append({
                    "channel": ch,
                    "data_points": [dict(zip("xyz", vals))],
                    "uuid": new_uuid(), "time": 0, "color": -1, "interpolation": "linear",
                })
            animators[buid[bname]] = {"name": bname, "type": "bone", "keyframes": kfs}
        anims.append({
            "uuid": new_uuid(), "name": aname, "loop": "loop", "override": False,
            "length": 6, "snapping": 24, "animators": animators,
        })

    bb = {
        "meta": {"format_version": "4.10", "model_format": "bedrock", "box_uv": False},
        "name": model.name,
        "model_identifier": model.name,
        "visible_box": [3, 3, 1],
        "resolution": {"width": size, "height": size},
        "elements": elements,
        "outliner": [{
            "name": b.name, "origin": b.pivot, "uuid": buid[b.name],
            "export": True, "isOpen": True, "visibility": True, "children": kids[b.name],
        } for b in model.bones],
        "textures": [{
            "path": "", "name": texture_name, "id": "0", "width": size, "height": size,
            "uv_width": size, "uv_height": size, "render_mode": "default", "visible": True,
            "internal": True, "saved": True, "uuid": new_uuid(),
            "source": "data:image/png;base64," + base64.b64encode(atlas_png).decode(),
        }],
        "animations": anims,
    }
    with open(path, "w", encoding="utf-8") as f:
        json.dump(bb, f, indent=1)


def save_geo(model: Model, path: str, size: int):
    """هندسهٔ بدراک (‎.geo.json‎) — آینه‌کردن محور X مثل مدل‌های موجود پروژه."""
    bones = []
    for bone in model.bones:
        cubes = []
        for c in bone.cubes:
            f, t = c.frm, c.to
            cube = {
                "origin": [-t[0], f[1], f[2]],
                "size": [round(t[i] - f[i], 3) for i in range(3)],
                "uv": {k: {"uv": list(_uvs(v)[0]), "uv_size": list(_uvs(v)[1])}
                       for k, v in c.faces.items()},
            }
            if c.rot:
                cube["rotation"] = [-c.rot[0], -c.rot[1], c.rot[2]]
                piv = c.pivot or [0, 0, 0]
                cube["pivot"] = [-piv[0], piv[1], piv[2]]
            cubes.append(cube)
        bones.append({"name": bone.name, "pivot": bone.pivot, "cubes": cubes})
    geo = {"format_version": "1.12.0", "minecraft:geometry": [{
        "description": {
            "identifier": "geometry." + model.name, "texture_width": size,
            "texture_height": size, "visible_bounds_width": 12,
            "visible_bounds_height": 12, "visible_bounds_offset": [0, 4, 0],
        },
        "bones": bones}]}
    with open(path, "w", encoding="utf-8") as f:
        json.dump(geo, f, indent=2)


def save_anim(model: Model, path: str):
    """انیمیشن بدراک با Molang (شناوری، چرخش، پالس)."""
    anims = {}
    for aname, spec in model.anims.items():
        bones = {}
        for bname, channels in spec.items():
            bones[bname] = {}
            for ch, vals in channels.items():
                bones[bname][ch] = [v if isinstance(v, str) else v for v in vals]
        anims[aname] = {"loop": True, "bones": bones}
    with open(path, "w", encoding="utf-8") as f:
        json.dump({"format_version": "1.8.0", "animations": anims}, f, indent=2)


def save_entity(name: str, ident: str, texture: str, geo: str, anim: str,
                scale: str, controller: str, path: str, spawn_egg: tuple[str, str]):
    ent = {"format_version": "1.10.0", "minecraft:client_entity": {"description": {
        "identifier": ident,
        "materials": {"default": "entity_alphablend"},
        "textures": {"default": "textures/entity/" + texture},
        "geometry": {"default": geo},
        "animations": {"idle": anim},
        "scripts": {"animate": ["idle"], "scale": scale},
        "render_controllers": [controller],
        "spawn_egg": {"base_color": spawn_egg[0], "overlay_color": spawn_egg[1]},
    }}}
    with open(path, "w", encoding="utf-8") as f:
        json.dump(ent, f, indent=2)


def save_render_controller(name: str, path: str):
    rc = {"format_version": "1.8.0", "render_controllers": {name: {
        "geometry": "Geometry.default", "materials": [{"*": "Material.default"}],
        "textures": ["Texture.default"]}}}
    with open(path, "w", encoding="utf-8") as f:
        json.dump(rc, f, indent=2)


def save_manifest(path: str, name: str, desc: str, header_uuid: str, module_uuid: str):
    man = {"format_version": 2, "header": {
        "name": name, "description": desc, "uuid": header_uuid,
        "version": [1, 0, 0], "min_engine_version": [1, 20, 0]},
        "modules": [{"type": "resources", "uuid": module_uuid, "version": [1, 0, 0]}]}
    with open(path, "w", encoding="utf-8") as f:
        json.dump(man, f, indent=2)


def zip_dir(path: str, dest: str):
    with zipfile.ZipFile(dest, "w", zipfile.ZIP_DEFLATED) as z:
        for root, _, files in os.walk(path):
            for fn in files:
                full = os.path.join(root, fn)
                z.write(full, os.path.relpath(full, path))


# ------------------------------------------------------- پیش‌نمایش سه‌بعدی
def _reg_px(name: str):
    x, y, w, h = REG[name]
    return ATLAS.img.crop((x, y, x + w, y + h)), w, h


def _rot_point(p, pivot, rot):
    import math as _m
    x, y, z = p[0] - pivot[0], p[1] - pivot[1], p[2] - pivot[2]
    rx, ry, rz = [_m.radians(a) for a in rot]
    x, z = x * _m.cos(ry) + z * _m.sin(ry), -x * _m.sin(ry) + z * _m.cos(ry)
    y, z = y * _m.cos(rx) - z * _m.sin(rx), y * _m.sin(rx) + z * _m.cos(rx)
    x, y = x * _m.cos(rz) - y * _m.sin(rz), x * _m.sin(rz) + y * _m.cos(rz)
    return (x + pivot[0], y + pivot[1], z + pivot[2])


def _face_quads(frm, to):
    """نقاط هر وجه به‌ترتیب UV: (u0,v0) (u1,v0) (u1,v1) (u0,v1)."""
    x0, y0, z0 = frm
    x1, y1, z1 = to
    return {
        "north": [(x0, y1, z0), (x1, y1, z0), (x1, y0, z0), (x0, y0, z0)],
        "south": [(x1, y1, z1), (x0, y1, z1), (x0, y0, z1), (x1, y0, z1)],
        "east": [(x1, y1, z1), (x1, y1, z0), (x1, y0, z0), (x1, y0, z1)],
        "west": [(x0, y1, z0), (x0, y1, z1), (x0, y0, z1), (x0, y0, z0)],
        "up": [(x0, y1, z0), (x1, y1, z0), (x1, y1, z1), (x0, y1, z1)],
        "down": [(x0, y0, z1), (x1, y0, z1), (x1, y0, z0), (x0, y0, z0)],
    }


def _norm(v):
    import math as _m
    l = _m.sqrt(sum(c * c for c in v)) or 1.0
    return (v[0] / l, v[1] / l, v[2] / l)


def _cross(a, b):
    return (a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0])


def _dot(a, b):
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]


VIEWS = [
    ("BLOCKBENCH VIEW (as you build it)", (-0.85, -0.62, -0.85), (0, 1, 0), False),
    ("IN-GAME ISO (mirrored as in game)", (-0.85, -0.62, -0.85), (0, 1, 0), True),
    ("IN-GAME FRONT (facing player)", (0, 0, 1), (0, 1, 0), True),
    ("IN-GAME SIDE", (-1, 0, 0), (0, 1, 0), True),
]


def render_model(model: Model, path: str, scale: int = 4, bg=(14, 20, 34, 255),
                 panels: int = 4):
    """پیش‌نمایش واقعی: هر وجه با آفین از بافت روی صفحه نگاشت می‌شود."""
    faces = []                       # (depth_key, quad3d, texreg, face)
    for bone in model.bones:
        for c in bone.cubes:
            frm, to = list(c.frm), list(c.to)
            rot = c.rot
            for face, quad in _face_quads(frm, to).items():
                if face not in c.faces:
                    continue
                pts = quad
                if rot:
                    piv = c.pivot or [0, 0, 0]
                    pts = [_rot_point(p, piv, rot) for p in quad]
                faces.append((pts, c.faces[face], face))

    panel_w, panel_h = 470, 560
    canvas = Image.new("RGBA", (panel_w * panels, panel_h), bg)
    d = ImageDraw.Draw(canvas)

    for vi, (label, fwd, up, mirror) in enumerate(VIEWS[:panels]):
        ox = vi * panel_w
        d.rectangle([ox + 2, 2, ox + panel_w - 3, panel_h - 3], fill=(20, 28, 44, 255),
                    outline=(70, 92, 140))
        pixel_text(d, ox + 12, 10, label, (255, 220, 120), (0, 0, 0), 2)

        f = _norm(fwd)
        right = _norm(_cross(f, up))
        cam_up = _cross(right, f)

        def proj(p):
            q = (-p[0], p[1], p[2]) if mirror else p
            return (_dot(q, right), _dot(q, cam_up), _dot(q, f))

        items = []
        for pts, reg, face in faces:
            pr = [proj(p) for p in pts]
            depth = sum(q[2] for q in pr) / 4.0
            items.append((depth, pr, reg, face))
        items.sort(key=lambda it: it[0], reverse=True)   # دورترین اول

        xs = [p[0] for _, pr, _, _ in items for p in pr]
        ys = [p[1] for _, pr, _, _ in items for p in pr]
        if not xs:
            continue
        cx, cy = (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2
        spanx, spany = max(1e-3, max(xs) - min(xs)), max(1e-3, max(ys) - min(ys))
        sc = min((panel_w - 60) / spanx, (panel_h - 110) / spany, scale * 1.8)

        def place(p):
            return (ox + panel_w / 2 + (p[0] - cx) * sc,
                    panel_h / 2 + 26 - (p[1] - cy) * sc)

        for depth, pr, reg, face in items:
            src, tw, th = _reg_px(reg)
            s0, s1, s2, _s3 = [place(p) for p in pr]
            # آفین: (u,v) در [0,1] → صفحه
            M = ((s1[0] - s0[0]) / tw, (s2[0] - s0[0]) / th, s0[0],
                 (s1[1] - s0[1]) / tw, (s2[1] - s0[1]) / th, s0[1])
            a, b, cc, dd, e, ff = M
            det = a * e - b * dd
            if abs(det) < 1e-9:
                continue
            inv = (e / det, -b / det, (b * ff - e * cc) / det,
                   -dd / det, a / det, (dd * cc - a * ff) / det)
            bx0 = int(min(p[0] for p in (s0, s1, s2, _s3))) - 1
            by0 = int(min(p[1] for p in (s0, s1, s2, _s3))) - 1
            bx1 = int(max(p[0] for p in (s0, s1, s2, _s3))) + 2
            by1 = int(max(p[1] for p in (s0, s1, s2, _s3))) + 2
            bx0, by0 = max(bx0, ox + 3), max(by0, 26)
            if bx1 <= bx0 or by1 <= by0:
                continue
            w, h = bx1 - bx0, by1 - by0
            mul = (inv[0] * bx0 + inv[1] * by0 + inv[2],
                   inv[3] * bx0 + inv[4] * by0 + inv[5])
            coeffs = (inv[0], inv[1], mul[0], inv[3], inv[4], mul[1])
            try:
                warped = src.transform((w, h), Image.AFFINE, coeffs, resample=Image.NEAREST,
                                       fillcolor=(0, 0, 0, 0))
            except Exception:
                continue
            if face in ("up", "down"):
                sc_col = 1.0
            elif face in ("north", "south"):
                sc_col = 0.92
            else:
                sc_col = 0.78
            px = warped.load()
            for yy in range(h):
                for xx in range(w):
                    r, g, bl, al = px[xx, yy]
                    if al == 0:
                        continue
                    px[xx, yy] = (int(r * sc_col), int(g * sc_col), int(bl * sc_col), al)
            canvas.alpha_composite(warped, (bx0, by0))

    canvas.convert("RGB").save(path)
    return path


def texture_sheet(path: str, scale: int = 3):
    """پیش‌نمایش بافت با خطوط شبکه."""
    img = ATLAS.img.resize((ATLAS.size * scale, ATLAS.size * scale), Image.NEAREST)
    d = ImageDraw.Draw(img)
    for k in range(0, ATLAS.size + 1, 16):
        c = (255, 255, 255, 40) if k % 64 else (255, 220, 120, 90)
        d.line([(k * scale, 0), (k * scale, img.height)], fill=c)
        d.line([(0, k * scale), (img.width, k * scale)], fill=c)
    img.save(path)
    return path
