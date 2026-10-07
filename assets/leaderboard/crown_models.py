#!/usr/bin/env python3
"""crown_models.py — «غول‌پیکر تاج» (Giant Crowns) 👑
=====================================================
یک تاج غول‌پیکر که روی یک بالش مخملی آرام می‌چرخد؛ حلقه‌ای از پلاک‌های نام
دور تاج می‌گردد و **رنگ جواهر وسط تاج، دسته (stat) را مشخص می‌کند**.

شش نسخه (فقط جواهر عوض می‌شود؛ هندسه/انیمیشن کاملاً یکسان):
    arvan:giant_crown_kills        جواهر سرخ      → کیل‌ها
    arvan:giant_crown_wins         جواهر طلایی     → بردها
    arvan:giant_crown_beds_broken  جواهر الماسی    → تخت‌ها
    arvan:giant_crown_final_kills  جواهر بنفش      → فینال‌کیل‌ها
    arvan:giant_crown_level        جواهر زمردی     → سطح
    arvan:giant_crown_coins        جواهر کهربایی   → سکه‌ها

امضای مشترک خانواده (مثل بقیهٔ مدل‌ها): سکوی ابسیدین + لبهٔ طلایی + دو حلقهٔ
چرخان (فیروزه‌ای/طلایی) + نگین شناور — و اینجا نگینِ امضا **داخل خود تاج**
نشسته تا همان «جواهر وسط تاج» باشد.
"""
from __future__ import annotations

import math

from bb_lib import (F, CORE_LEVELS, Model, core_plaza, core_signature, gem, glow, grad,
                    noise, outline, plank, rect, wool)
from PIL import ImageDraw

# نگاشت stat → بافت جواهر + رنگ‌های تخم‌اسپاون
CROWN_VARIANTS: list[tuple[str, str, tuple[str, str]]] = [
    ("kills",       "jewel_red",    ("#C8282D", "#FCD24A")),
    ("wins",        "jewel_gold",   ("#FCD24A", "#7A4A10")),
    ("beds_broken", "jewel_cyan",   ("#2EC8F0", "#1B2740")),
    ("final_kills", "jewel_purple", ("#9654E6", "#FCD24A")),
    ("level",       "jewel_green",  ("#3CD26E", "#123018")),
    ("coins",       "jewel_orange", ("#F08A1E", "#3A2410")),
]

CROWN_TEX_SHARE = {("arvan_giant_crown_%s" % s): "arvan_giant_crown_kills" for s, _, _ in CROWN_VARIANTS}


# ------------------------------------------------------------------ بافت‌ها
def _velvet(d, x, y, w, h, base=(150, 22, 40), light=(212, 60, 78), dark=(96, 10, 24)):
    """مخمل قرمز با درخشش ملایم (بالش)."""
    grad(d, x, y, w, h, light, dark)
    for i in range(0, w, 6):
        rect(d, x + i, y, 1, h, dark, 70)
    noise(d, x, y, w, h, [light, dark], 9)
    rect(d, x, y, w, 2, light)
    rect(d, x, y + h - 2, w, 2, dark)


def _velvet_top(d, x, y, w, h):
    _velvet(d, x, y, w, h)
    # دکمهٔ طلایی وسط بالش + دوخت دور
    cx, cy = x + w // 2, y + h // 2
    rect(d, cx - 4, cy - 4, 9, 9, (252, 206, 60))
    rect(d, cx - 2, cy - 2, 5, 5, (255, 245, 190))
    for i in range(0, w, 4):
        rect(d, x + i, y + 3, 2, 1, (250, 220, 150), 150)
        rect(d, x + i, y + h - 4, 2, 1, (250, 220, 150), 150)


def _crown_band(d, x, y, w, h):
    """نوار تاج: طلای صیقلی با دو رخساره و ردیف نگین ریز فقط نزدیک لبهٔ بالا."""
    grad(d, x, y, w, h, (255, 232, 140), (206, 148, 26))
    rect(d, x, y, w, 2, (255, 248, 200))
    rect(d, x, y + h - 3, w, 3, (150, 100, 14))
    rect(d, x, y + h // 2 - 1, w, 2, (255, 240, 180), 150)
    for i in range(2, w - 2, 7):
        rect(d, x + i, y + 3, 3, 3, (150, 235, 255))
        rect(d, x + i + 1, y + 4, 1, 1, (255, 255, 255))
    outline(d, x, y, w, h, (128, 88, 12))


def _crown_inner(d, x, y, w, h):
    """داخل تاج: مخمل بنفش تیره."""
    grad(d, x, y, w, h, (74, 44, 96), (34, 20, 48))
    noise(d, x, y, w, h, [(96, 60, 122), (26, 14, 38)], 7)


def _crown_plate(d, x, y, w, h):
    """پلاک نام: قاب طلایی با صفحهٔ تیره (نام‌ها داخلش می‌نشینند)."""
    grad(d, x, y, w, h, (255, 226, 120), (176, 122, 20))
    rect(d, x + 2, y + 2, w - 4, h - 4, (22, 20, 34), 240)
    rect(d, x + 3, y + 3, w - 6, 1, (120, 110, 150), 120)
    outline(d, x, y, w, h, (120, 84, 12))
    for cx in (x + 1, x + w - 3):
        rect(d, cx, y + h // 2 - 1, 2, 2, (255, 245, 190))


def _jewel(d, x, y, w, h, mid, light, dark):
    """جواهر اشباع‌شده: بدنهٔ رنگی + رخ روشن + قاب تیره (رنگ دسته را می‌رساند)."""
    grad(d, x, y, w, h, light, dark)
    rect(d, x + 1, y + 1, w - 2, h - 2, mid)
    cx = x + w // 2
    rect(d, cx - 1, y + 2, 2, h - 4, light)
    rect(d, cx - 2, y + 2, 1, h - 6, light, 180)
    rect(d, x + 1, y + 1, w - 2, 1, (255, 255, 255), 220)
    rect(d, x + 1, y + h - 2, w - 2, 1, dark)
    outline(d, x, y, w, h, (24, 16, 26))


def _crown_fringe(d, x, y, w, h):
    """منگولهٔ طلایی لبهٔ بالش."""
    grad(d, x, y, w, h, (255, 232, 140), (188, 132, 20))
    for i in range(0, w, 3):
        rect(d, x + i, y + 2, 2, h - 2, (238, 196, 80))
        rect(d, x + i, y + h - 3, 2, 3, (150, 100, 14))


def define_textures() -> None:
    """بافت‌های تازهٔ تاج را در بوم مشترک ثبت می‌کند (بقیهٔ بافت‌ها از قبل هست)."""
    from bb_lib import tex
    tex("crown_velvet", 32, 32, lambda d, x, y, w, h: _velvet(d, x, y, w, h))
    tex("crown_velvet_top", 32, 32, _velvet_top)
    tex("crown_band", 32, 24, _crown_band)
    tex("crown_inner", 24, 24, _crown_inner)
    tex("crown_plate", 32, 20, _crown_plate)
    tex("crown_fringe", 32, 12, _crown_fringe)
    tex("crown_gem_frame", 24, 24, lambda d, x, y, w, h: _crown_band(d, x, y, w, h))
    for nm, mid, light, dark in (
            ("jewel_red", (222, 44, 52), (255, 140, 140), (120, 12, 20)),
            ("jewel_gold", (252, 206, 60), (255, 246, 190), (150, 96, 10)),
            ("jewel_cyan", (74, 214, 245), (206, 248, 255), (16, 96, 130)),
            ("jewel_purple", (150, 84, 230), (226, 196, 255), (60, 20, 110)),
            ("jewel_green", (60, 210, 110), (196, 255, 214), (10, 92, 46)),
            ("jewel_orange", (246, 138, 26), (255, 214, 150), (140, 60, 6))):
        tex(nm, 16, 16, lambda d, x, y, w, h, m=mid, l=light, k=dark: _jewel(d, x, y, w, h, m, l, k))


# ------------------------------------------------------------------- مدل
def build_giant_crown(stat: str, gem_tex: str, egg: tuple[str, str]) -> Model:
    """یک تاج غول‌پیکر روی بالش مخملی؛ تنها تفاوت نسخه‌ها، بافت جواهر است."""
    name = "arvan_giant_crown_%s" % stat
    m = Model(name, "arvan:giant_crown_%s" % stat)
    base = m.bone("base", (0, 0, 0))

    # ۱) امضای مشترک: سکوی ابسیدین + لبهٔ طلایی
    core_plaza(m, hw=24, hd=24)

    # ۲) بالش مخملی (سه لایه + منگولهٔ طلایی گوشه‌ها)
    m.cube(base, "cushion_1", (-22, CORE_LEVELS["floor_top"], -22), (22, 8, 22),
           F(top="crown_velvet_top", all="crown_velvet"))
    m.cube(base, "cushion_2", (-19, 8, -19), (19, 12, 19),
           F(top="crown_velvet_top", all="crown_velvet"))
    m.cube(base, "cushion_3", (-17, 12, -17), (17, 15, 17),
           F(top="crown_velvet_top", all="crown_velvet"))
    for i, (sx, sz) in enumerate(((-1, -1), (1, -1), (-1, 1), (1, 1))):
        m.cube(base, "tassel%d" % i, (sx * 22 - 2, 5, sz * 22 - 2), (sx * 22 + 2, 13, sz * 22 + 2),
               F(all="gold"))
        m.cube(base, "tassel%d_fringe" % i, (sx * 22 - 1.5, 1, sz * 22 - 1.5),
               (sx * 22 + 1.5, 5, sz * 22 + 1.5), F(all="crown_fringe"))

    # ۳) تاجِ غول‌پیکر — همهٔ اجزا داخل بون «crown» تا با هم و هم‌آهنگ بچرخند
    crown = m.bone("crown", (0, 15, 0))
    ring_r, seg = 15.0, 12
    for i in range(seg):
        a = math.tau * i / seg
        px, pz = math.cos(a) * ring_r, math.sin(a) * ring_r
        m.cube(crown, "band%d" % i, (px - 4.2, 14, pz - 4.2), (px + 4.2, 32, pz + 4.2),
               F(all="crown_band"))
    for i in range(seg):       # لبهٔ طلایی نازک روی نوار
        a = math.tau * i / seg
        px, pz = math.cos(a) * ring_r, math.sin(a) * ring_r
        m.cube(crown, "lip%d" % i, (px - 4.5, 32, pz - 4.5), (px + 4.5, 34, pz + 4.5),
               F(all="gold"))
    # جواهر دسته (stat) روی چهار جهت تاج → از هر زاویه‌ای رنگ دسته خوانا است
    for i in range(4):
        a = math.tau * i / 4
        px, pz = math.cos(a) * ring_r, math.sin(a) * ring_r
        m.cube(crown, "jewel_frame%d" % i, (px - 7, 16.5, pz - 7), (px + 7, 31.5, pz + 7),
               F(all="crown_gem_frame"))
        dx, dz = math.cos(a) * 0.9, math.sin(a) * 0.9
        m.cube(crown, "jewel%d" % i, (px - 6 * abs(dz) - 2 * abs(dx), 19, pz - 6 * abs(dx) - 2 * abs(dz)),
               (px + 6 * abs(dz) + 2 * abs(dx), 29, pz + 6 * abs(dx) + 2 * abs(dz)),
               F(front=gem_tex, back=gem_tex, left=gem_tex, right=gem_tex, all=gem_tex))
    # شش خار تاج (باریک، متناوب بلند/کوتاه) + گوی و مروارید روی نوک
    for i in range(6):
        a = math.tau * i / 6
        px, pz = math.cos(a) * (ring_r - 0.5), math.sin(a) * (ring_r - 0.5)
        hgt = 46 if i % 2 == 0 else 38
        m.cube(crown, "spike%d_a" % i, (px - 3.0, 34, pz - 3.0), (px + 3.0, 34 + (hgt - 34) * 0.55, pz + 3.0),
               F(all="crown_band"))
        m.cube(crown, "spike%d_b" % i, (px - 1.8, 34 + (hgt - 34) * 0.55, pz - 1.8), (px + 1.8, hgt, pz + 1.8),
               F(all="crown_band"))
        m.cube(crown, "spike%d_ball" % i, (px - 2.2, hgt, pz - 2.2), (px + 2.2, hgt + 4.4, pz + 2.2),
               F(all="gold"))
    # گویِ بالای تاج (نگین وسط، هم‌محور با تاج می‌چرخد)
    m.cube(crown, "orb_ring", (-7, 50.5, -7), (7, 54.5, 7), F(all="gold"))
    m.cube(crown, "orb_jewel", (-5, 54.5, -5), (5, 64.5, 5), F(all=gem_tex), rot=(0, 45, 0))
    m.cube(crown, "orb_pearl", (-2.5, 64.5, -2.5), (2.5, 67.5, 2.5), F(all=gem_tex))

    # ۴) حلقهٔ پلاک‌های نام (دور تاج می‌گردد؛ نام‌ها روی خودشان می‌نشینند)
    plates = m.bone("plates", (0, 40, 0))
    PR = 25.0
    for i in range(8):         # زنجیر ضخیم (۸ حلقه) که پلاک‌ها از آن آویزان‌اند
        a = math.tau * (i + 0.5) / 8
        px, pz = math.cos(a) * PR, math.sin(a) * PR
        m.cube(plates, "chain%d" % i, (px - 1.8, 30.0, pz - 1.8), (px + 1.8, 34.0, pz + 1.8),
               F(all="gold"))
    for i in range(8):         # ۸ پلاک نام دور تاج (جای ۸ نفر اول)
        a = math.tau * i / 8
        ox, oz = math.cos(a), math.sin(a)
        px, pz = ox * PR, oz * PR
        hx = 5.4 * abs(oz) + 1.5 * abs(ox)   # تقریب مکعبی چرخش → بدون چرخش بون، بدون خط اضافی
        hz = 5.4 * abs(ox) + 1.5 * abs(oz)
        m.cube(plates, "plate%d" % i, (px - hx, 21.5, pz - hz), (px + hx, 30.5, pz + hz),
               F(front="crown_plate", back="crown_plate", all="crown_gem_frame"))

    # ۵) امضای مشترک: حلقه‌ها + نگینِ امضا که همین‌جا «جواهر وسط تاج» است
    core = core_signature(m, hw=24, hd=24, gem_y=22, ring_r=30, gem=gem_tex, posts=False)

    m.anim("animation.%s.idle" % name, {
        **core,
        # کل تاج (نوار + جواهرها + خارها) با هم می‌چرخد → هیچ جزئی عقب نمی‌ماند
        "crown": {"rotation": ["0", "query.anim_time * 20", "0"],
                  "position": ["0", "math.sin(query.anim_time * 45) * 0.8", "0"],
                  "scale": ["1 + math.sin(query.anim_time * 90) * 0.02", "1",
                            "1 + math.sin(query.anim_time * 90) * 0.02"]},
        "plates": {"rotation": ["0", "query.anim_time * -14", "0"],
                   "position": ["0", "math.sin(query.anim_time * 55) * 1.2", "0"]},
        "base": {"position": ["0", "math.sin(query.anim_time * 30) * 0.35", "0"]},
    })
    return m


def entries() -> list[tuple[Model, str, tuple[str, str]]]:
    """همهٔ نسخه‌ها برای فهرست MODELS در generate.py."""
    out = []
    for stat, gem_tex, egg in CROWN_VARIANTS:
        out.append((build_giant_crown(stat, gem_tex, egg), "1.35", egg))
    return out
