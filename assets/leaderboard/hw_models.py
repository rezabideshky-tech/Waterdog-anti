#!/usr/bin/env python3
"""hw_models.py — ست هالووینی ArvanGaming (جمع‌وجور برای لابی، هم‌خانواده با بقیه)
=================================================================================
قاعدهٔ مشترک همهٔ مدل‌ها (پنج عنصر امضای Arvan):
  ۱) سکوی ابسیدین با لبهٔ طلایی        ۲) چهار ستون طلایی با کلاهک فیروزه‌ای
  ۳) حلقهٔ چرخان فیروزه‌ای پایین        ۴) حلقهٔ چرخان طلایی بالا
  ۵) نگین شناور در ارتفاع ثابت (برای هالووین: «نگین روح» سبز/نارنجی)

هالووینی که اضافه می‌شود: کدو تنبل تراشیده، تخته‌سنگ (tombstone)، عنکبوت‌تنیده،
خفاش مداری، شمع، دیگ جادو با شعلهٔ روح، روح شناور، جمجمه و آب‌نبات.

پنج مدل:
  arvan_hw_top       تخته‌سنگ لیدربورد + تاجِ کدو تنبل (جمع‌وجور، تابلو پهن)
  arvan_hw_podium    سه کدو تنبل روی پله‌ها (قهرمانان) + خفاش‌های مداری
  arvan_hw_projector دیگ جادو با شعلهٔ روح و روح شناور (کوچک‌ترین جا)
  arvan_hw_bed       تخت خون‌آشام با بال خفاش و پتو، جمجمه روی پایه
  arvan_hw_sign      تابلوی آویزان از زنجیر زیر طاق چوبی (خیلی جمع‌وجور)
"""
from __future__ import annotations

import math

from bb_lib import (plate, F, CORE, CORE_LEVELS, Model, bed_icon, center_text, core_plaza,
                    core_signature, crown_icon, gem, glow, grad, holo_glass, noise,
                    outline, pixel_text, plank, plate, rect, star_icon, stone, sword_icon,
                    tex, wool)
from PIL import ImageDraw

# ============================================================== بافت‌های هالووین
HW_REG: list[str] = []


def _pumpkin_side(d, x, y, w, h, col=(240, 130, 30), light=(255, 185, 80), dark=(150, 66, 10)):
    """کدو تنبل: رنگ نارنجی + سایهٔ بشکه‌ای (کناره‌ها تیره) + شیارهای خمیده."""
    import math as _m
    # بدنه با سایهٔ کروی
    cx = w / 2.0
    for px in range(w):
        t = abs(px - cx) / max(1.0, cx)                 # ۰ وسط، ۱ کناره
        shade = 1.0 - 0.55 * (t ** 1.7)
        band = [int(col[0] * shade + light[0] * (1 - shade) * 0.7),
                int(col[1] * shade + light[1] * (1 - shade) * 0.7),
                int(col[2] * shade + light[2] * (1 - shade) * 0.7)]
        d.rectangle([x + px, y, x + px, y + h - 1], fill=(min(255, band[0]), min(255, band[1]), min(255, band[2]), 255))
    # شیارهای عمودی خمیده (حس قاچ‌های کدو)
    for i in range(5):
        gx = x + 2 + i * (w - 4) / 4.0
        for py in range(h):
            bend = _m.sin((py / max(1.0, h - 1)) * _m.pi) * 1.6
            d.point([(min(x + w - 1, max(x, int(gx + bend))), y + py)], fill=dark + (170,))
            d.point([(min(x + w - 1, max(x, int(gx + bend) + 1)), y + py)], fill=light + (110,))
    # سایه بالا/پایین برای حس گردی
    for py in range(3):
        d.rectangle([x + py, y + py, x + w - 1 - py, y + py], fill=dark + (60 + py * 30,))
        d.rectangle([x + py, y + h - 1 - py, x + w - 1 - py, y + h - 1 - py], fill=dark + (60 + py * 30,))
    noise(d, x, y, w, h, [light, dark], 7)


def _pumpkin_face(d, x, y, w, h, glow_col=(255, 236, 150)):
    _pumpkin_side(d, x, y, w, h)
    cx = x + w / 2.0
    def _tri(pts, fill):
        d.polygon(pts, fill=fill)
        d.line(list(pts) + [pts[0]], fill=(120, 50, 8), width=1)
    eye_dx = w * 0.22
    eye_y = y + h * 0.30
    for sx in (-1, 1):
        _tri([(cx + sx * eye_dx - w * 0.11, eye_y), (cx + sx * eye_dx + w * 0.11, eye_y),
              (cx + sx * eye_dx, eye_y + h * 0.20)], glow_col)
    # دهان دندانه‌دار (پهن و واضح)
    my = y + h * 0.62
    pts = [(cx - w * 0.30, my)]
    steps = 7
    for i in range(steps):
        px = cx - w * 0.30 + i * (w * 0.60 / steps)
        pts.append((px, my + (h * 0.09 if i % 2 else 0)))
    pts += [(cx + w * 0.30, my), (cx + w * 0.30, my + h * 0.16), (cx - w * 0.30, my + h * 0.16)]
    d.polygon(pts, fill=glow_col)
    d.line(pts + [pts[0]], fill=(120, 50, 8), width=1)
    glow(d, x, y, w, h, glow_col)


def _pumpkin_top(d, x, y, w, h):
    grad(d, x, y, w, h, (220, 120, 30), (170, 80, 16))
    noise(d, x, y, w, h, [(250, 170, 70), (150, 70, 10)], 4)
    # ساقه
    cx, cy = x + w // 2, y + h // 2
    rect(d, cx - 3, cy - 6, 6, 12, (90, 110, 50))
    rect(d, cx - 3, cy - 6, 6, 3, (130, 160, 70))
    for i in range(8):
        import math as _m
        a = _m.tau * i / 8
        rect(d, cx + _m.cos(a) * w * 0.3 - 1, cy + _m.sin(a) * h * 0.3 - 1, 2, 2, (180, 110, 40))


def _tombslab(d, x, y, w, h):
    """تخته‌سنگ: سنگ تیره با ترک، گوشهٔ گرد و حاشیهٔ طلایی فرسوده."""
    grad(d, x, y, w, h, (96, 98, 108), (58, 58, 70))
    noise(d, x, y, w, h, [(120, 122, 132), (44, 44, 54)], 3)
    rect(d, x, y, w, 3, (132, 134, 144))
    # قوس بالای سنگ: گوشه‌ها را پاک کن
    for i in range(4):
        rect(d, x, y + i, 4 - i, 1, (0, 0, 0), 0)
        rect(d, x + w - 4 + i, y + i, 4 - i, 1, (0, 0, 0), 0)
    # ترک‌ها
    for i, (cx, cy, ln) in enumerate(((w * 0.3, h * 0.5, 8), (w * 0.66, h * 0.72, 6))):
        for k in range(int(ln)):
            rect(d, x + cx + k * 1.4, y + cy + (k % 3), 2, 1, (30, 30, 38), 210)
    outline(d, x, y, w, h, (26, 24, 34))


def _tombslab_panel(d, x, y, w, h):
    """ناحیهٔ متن روی تخته‌سنگ (تیره، خط‌دار، آمادهٔ نوشتن نام‌ها)."""
    _tombslab(d, x, y, w, h)
    rect(d, x + 4, y + 4, w - 8, h - 8, (24, 22, 34), 235)
    for yy in range(y + 6, y + h - 6, 4):
        rect(d, x + 6, yy, w - 12, 1, (70, 130, 190), 90)
    glow(d, x + w // 4, y + h // 4, w // 2, h // 2, (60, 140, 220))
    outline(d, x + 4, y + 4, w - 8, h - 8, (140, 210, 255), 190)


def _cobweb(d, x, y, w, h):
    """شبکهٔ عنکبوت روی زمینهٔ شفاف."""
    import math as _m
    cx, cy = x, y
    col = (225, 228, 240, 210)
    for k in range(6):
        a = _m.pi * k / 10
        d.line([(cx, cy), (cx + _m.cos(a) * w, cy + _m.sin(a) * h)], fill=col, width=1)
    for r in (0.28, 0.52, 0.78, 1.0):
        pts = [(cx + _m.cos(_m.pi * k / 10) * w * r, cy + _m.sin(_m.pi * k / 10) * h * r)
               for k in range(6)]
        d.line(pts + [pts[0]], fill=col, width=1)


def _bat(d, x, y, w, h, body=(48, 34, 66), wing=(96, 60, 130), eye=(255, 90, 60)):
    """خفاش پیکسلی با بال‌های دندانه‌دار، هالهٔ قرمز و چشم نورانی."""
    cx, cy = x + w / 2.0, y + h / 2.0
    glow(d, x, y, w, h, (150, 40, 90))
    for sx in (-1, 1):
        pts = [(cx, cy - h * 0.22)]
        for i in range(4):
            pts.append((cx + sx * w * (0.16 + i * 0.22), cy - h * 0.42 + (i % 2) * h * 0.34))
        pts += [(cx + sx * w * 0.52, cy + h * 0.06), (cx, cy + h * 0.22)]
        d.polygon(pts, fill=wing)
        d.line(pts + [pts[0]], fill=(40, 26, 58), width=1)
    rect(d, cx - w * 0.09, cy - h * 0.26, w * 0.18, h * 0.48, body)
    for sx in (-1, 1):
        d.polygon([(cx + sx * 4, cy - h * 0.26), (cx + sx * 8, cy - h * 0.48),
                   (cx + sx * 2, cy - h * 0.42)], fill=body)
    for sx in (-1, 1):
        rect(d, cx + sx * 4 - 2.5, cy - h * 0.2, 4, 3, eye)
    rect(d, cx - 2, cy + h * 0.1, 1, 3, (240, 240, 250))
    rect(d, cx + 1, cy + h * 0.1, 1, 3, (240, 240, 250))


def _ghost(d, x, y, w, h):
    grad(d, x, y, w, h, (250, 250, 255), (190, 200, 225))
    for i in range(6):    # دامن دندانه‌دار
        rect(d, x + i * w / 6, y + h - 4 - (i % 2) * 3, w / 6 - 1, 6, (210, 216, 235))
    for sx in (-1, 1):
        rect(d, x + w / 2 + sx * 5 - 2, y + h * 0.3, 4, 5, (30, 28, 44))
    rect(d, x + w / 2 - 3, y + h * 0.55, 6, 3, (30, 28, 44))
    outline(d, x, y, w, h, (160, 170, 200))


def _skull(d, x, y, w, h):
    rect(d, x + w * 0.12, y + h * 0.08, w * 0.76, h * 0.6, (238, 238, 230))
    rect(d, x + w * 0.28, y + h * 0.62, w * 0.44, h * 0.3, (238, 238, 230))
    for sx in (-1, 1):
        rect(d, x + w / 2 + sx * w * 0.2 - w * 0.1, y + h * 0.24, w * 0.2, h * 0.2, (24, 22, 32))
    rect(d, x + w / 2 - w * 0.05, y + h * 0.5, w * 0.1, h * 0.12, (24, 22, 32))
    for i in range(3):
        rect(d, x + w * 0.3 + i * w * 0.16, y + h * 0.68, w * 0.05, h * 0.2, (120, 116, 110))
    outline(d, x, y, w, h, (150, 148, 140))


def _cauldron(d, x, y, w, h):
    grad(d, x, y, w, h, (58, 60, 74), (26, 28, 38))
    noise(d, x, y, w, h, [(84, 86, 100), (16, 18, 26)], 4)
    rect(d, x, y, w, 3, (110, 112, 128))
    for i in range(0, w, 6):
        rect(d, x + i, y + 3, 1, h - 3, (16, 18, 26), 150)
    rect(d, x + w * 0.2, y + h * 0.45, w * 0.6, 2, (140, 142, 160), 120)
    outline(d, x, y, w, h, (12, 14, 22))


def _soulfire(d, x, y, w, h):
    for i in range(h):
        t = i / max(1, h - 1)
        a = int(215 * (1 - t) ** 1.2) + 12
        rect(d, x, y + i, w, 1, (110, 255, 170) if t < 0.5 else (70, 200, 240), a)
    for i in range(0, h, 5):
        rect(d, x + 1, y + i, w - 2, 1, (220, 255, 230), 130)


def _candle(d, x, y, w, h):
    grad(d, x, y, w, h, (250, 245, 220), (200, 190, 160))
    rect(d, x, y, w, 2, (255, 250, 235))
    noise(d, x, y, w, h, [(255, 250, 235), (190, 180, 150)], 6)
    outline(d, x, y, w, h, (150, 140, 115))


def _candle_top(d, x, y, w, h):
    rect(d, x, y, w, h, (250, 245, 220))
    for i in range(3):
        rect(d, x + i * w // 3, y + h // 2 - 1, 2, 3, (255, 216, 120))
    glow(d, x, y, w, h, (255, 190, 90))


def _candy(d, x, y, w, h):
    bands = [(250, 240, 120), (250, 160, 50), (240, 240, 240)]
    for i in range(3):
        rect(d, x, y + i * h / 3, w, h / 3 + 1, bands[i])
    outline(d, x, y, w, h, (150, 100, 50))


def _bone(d, x, y, w, h):
    rect(d, x, y, w, h, (238, 236, 224))
    rect(d, x, y, w, 2, (252, 250, 240))
    noise(d, x, y, w, h, [(210, 206, 190), (250, 248, 238)], 5)
    outline(d, x, y, w, h, (150, 146, 130))


def _wood_dark(d, x, y, w, h):
    plank(d, x, y, w, h, (92, 62, 40), (54, 34, 22), (124, 86, 56))


def _board_dark(d, x, y, w, h):
    """تابلوی چوبی تیره با میخ‌های طلایی (برای تابلوی آویزان)."""
    _wood_dark(d, x, y, w, h)
    for i in range(0, w, 7):
        rect(d, x + i, y, 1, h, (44, 28, 18), 180)
    for cx in (x + 2, x + w - 4):
        rect(d, cx, y + 2, 2, 2, (252, 206, 60))
        rect(d, cx, y + h - 4, 2, 2, (252, 206, 60))
    outline(d, x, y, w, h, (30, 18, 10))


def _header_hw(label: str, col=(255, 220, 140)):
    def fn(d, x, y, w, h):
        grad(d, x, y, w, h, (120, 70, 20), (60, 30, 10))
        rect(d, x + 1, y + 1, w - 2, h - 2, (24, 14, 26), 235)
        rect(d, x, y, w, 1, (252, 206, 60))
        rect(d, x, y + h - 1, w, 1, (252, 206, 60))
        center_text(d, x + w / 2, y + (h - 5) // 2, label, col, (70, 30, 10), 1, (16, 8, 16))
    return fn


def define_textures() -> None:
    """همهٔ بافت‌های هالووین را در بوم مشترک ثبت می‌کند."""
    tex("hw_pumpkin", 32, 32, lambda d, x, y, w, h: _pumpkin_side(d, x, y, w, h))
    tex("hw_pumpkin_face", 32, 32, lambda d, x, y, w, h: _pumpkin_face(d, x, y, w, h))
    tex("hw_pumpkin_small", 24, 24, lambda d, x, y, w, h: _pumpkin_side(d, x, y, w, h, (230, 120, 40), (255, 170, 80), (140, 60, 10)))
    tex("hw_pumpkin_face_small", 24, 24, lambda d, x, y, w, h: _pumpkin_face(d, x, y, w, h))
    tex("hw_pumpkin_top", 24, 24, _pumpkin_top)
    tex("hw_tomb", 40, 40, _tombslab)
    tex("hw_tomb_panel", 44, 44, _tombslab_panel)
    tex("hw_cobweb", 32, 32, _cobweb)
    tex("hw_bat", 48, 28, lambda d, x, y, w, h: _bat(d, x, y, w, h))
    tex("hw_bat_body", 16, 16, lambda d, x, y, w, h: plate(d, x, y, w, h, (48, 34, 66), (78, 54, 104), (26, 16, 38)))
    tex("hw_ghost", 28, 28, _ghost)
    tex("hw_skull", 28, 28, _skull)
    tex("hw_cauldron", 32, 32, _cauldron)
    tex("hw_soulfire", 24, 64, _soulfire)
    tex("hw_candle", 20, 20, _candle)
    tex("hw_candle_top", 20, 20, _candle_top)
    tex("hw_candy", 16, 24, _candy)
    tex("hw_bone", 24, 12, _bone)
    tex("hw_wood_dark", 32, 32, _wood_dark)
    tex("hw_board_dark", 40, 24, _board_dark)
    tex("hw_gem_soul", 32, 32, lambda d, x, y, w, h: gem(d, x, y, w, h, (200, 255, 220), (60, 230, 140), (10, 80, 50)))
    tex("hw_gem_pumpkin", 32, 32, lambda d, x, y, w, h: gem(d, x, y, w, h, (255, 230, 170), (250, 150, 50), (140, 60, 10)))
    tex("hw_header", 60, 14, _header_hw("HALLOWEEN"))
    tex("hw_header_top", 60, 14, _header_hw("SPOOKY TOP 3", (255, 200, 120)))
    tex("hw_header_boo", 60, 14, _header_hw("BOO! STATS", (200, 255, 210)))


# ==================================================================== مدل‌ها
def pumpkin(m: Model, bone, name: str, cx: float, cy: float, cz: float, size: float = 16.0,
            face: bool = True):
    """کدو تنبل سه‌تکه: تاج و پایهٔ باریک‌تر تا از مکعبِ ساده گردتر دیده شود."""
    hw = size / 2.0
    mid_h = size * 0.62
    cap_h = size * 0.20
    front = "hw_pumpkin_face" if face else "hw_pumpkin"
    m.cube(bone, name + "_mid", (cx - hw, cy, cz - hw), (cx + hw, cy + mid_h, cz + hw),
           F(front=front, top="hw_pumpkin_top", all="hw_pumpkin"))
    m.cube(bone, name + "_top", (cx - hw * 0.72, cy + mid_h, cz - hw * 0.72),
           (cx + hw * 0.72, cy + mid_h + cap_h, cz + hw * 0.72),
           F(front=front, top="hw_pumpkin_top", all="hw_pumpkin"))
    m.cube(bone, name + "_bot", (cx - hw * 0.78, cy - cap_h, cz - hw * 0.78),
           (cx + hw * 0.78, cy, cz + hw * 0.78),
           F(front=front, all="hw_pumpkin"))
    m.cube(bone, name + "_stem", (cx - 1.6, cy + mid_h + cap_h, cz - 1.6),
           (cx + 1.6, cy + mid_h + cap_h + 3.2, cz + 1.6), F(all="hw_wood_dark"))
    return cy + mid_h + cap_h + 3.2


def _candles(m: Model, bone, positions, h: float = 6.0):
    """شمع‌های کوچک روی سکو — عنصر تزئینی مشترک ست هالووین."""
    for i, (cx, cz) in enumerate(positions):
        m.cube(bone, "candle%d" % i, (cx - 1.5, CORE_LEVELS["floor_top"], cz - 1.5),
               (cx + 1.5, CORE_LEVELS["floor_top"] + h, cz + 1.5),
               F(all="hw_candle", top="hw_candle_top"))
        m.cube(bone, "flame%d" % i, (cx - 0.8, CORE_LEVELS["floor_top"] + h, cz - 0.8),
               (cx + 0.8, CORE_LEVELS["floor_top"] + h + 2.4, cz + 0.8),
               F(all="hw_candle_top"))


def _bat_orbit(m: Model, y: float, radius: float, count: int = 3, name: str = "bats"):
    """خفاش‌های مداری — به‌جای نگین‌های مدارِ مدل‌های معمولی."""
    bone = m.bone(name, (0, y, 0))
    for i in range(count):
        a = math.tau * i / count
        px, pz = math.cos(a) * radius, math.sin(a) * radius
        m.cube(bone, "%s_%d" % (name, i), (px - 9, y, pz - 1.2), (px + 9, y + 6, pz + 1.2),
               F(front="hw_bat", back="hw_bat", all="hw_bat_body"))
    return {"%s" % name: {"rotation": ["0", "query.anim_time * %d" % (60 + count * 8), "0"],
                          "position": ["0", "math.sin(query.anim_time * 120) * 1.2", "0"]}}


def build_hw_top() -> Model:
    """تخته‌سنگ لیدربورد با تاجِ کدو تنبل — تابلو پهن و خوانا، سکو جمع‌وجور."""
    m = Model("arvan_hw_top", "arvan:hw_top")
    base = m.bone("base", (0, 0, 0))
    core_plaza(m, hw=20, hd=14)
    _candles(m, base, [(-16, 9), (16, 9), (-16, -9)])
    # تخته‌سنگ: دو پایه + اسلب پهن با پنل متن
    m.cube(base, "tomb_foot", (-16, CORE_LEVELS["floor_top"], -6), (16, 8, 6), F(all="hw_tomb"))
    m.cube(base, "tomb_slab", (-20, 8, -5), (20, 44, 5), F(all="hw_tomb", front="hw_tomb"))
    m.cube(base, "tomb_panel", (-17, 12, -5.4), (17, 38, -5.0), F(front="hw_tomb_panel"))
    m.cube(base, "tomb_header", (-17, 38, -5.4), (17, 44, -5.0), F(front="hw_header", all="gold_dark"))
    m.cube(base, "tomb_trim_l", (-20.6, 8, -5.6), (-19.4, 44, 5.6), F(all="gold_dark"))
    m.cube(base, "tomb_trim_r", (19.4, 8, -5.6), (20.6, 44, 5.6), F(all="gold_dark"))
    m.cube(base, "tomb_cap", (-21, 44, -6), (21, 47, 6), F(top="gold", all="gold_dark"))
    # شبکهٔ عنکبوت روی گوشه‌ها
    m.cube(base, "web_l", (-20.2, 34, -5.2), (-19.8, 44, -4.8), F(front="hw_cobweb", all="hw_cobweb"))
    m.cube(base, "web_r", (19.8, 34, -5.2), (20.2, 44, -4.8), F(front="hw_cobweb", all="hw_cobweb"))
    m.cube(base, "web_top", (-6, 47, -5.2), (6, 47.4, -4.8), F(front="hw_cobweb", all="hw_cobweb"))
    # تاجِ کدو تنبل روی تخته (عنصر شاخص + تاج طلایی مشترک)
    crown = m.bone("crown", (0, 56, 0))
    top_y = pumpkin(m, crown, "hw_pumpkin", 0, 47, 0, 18.0)
    m.cube(crown, "gold_crown_body", (-7, top_y, -3), (7, top_y + 3, 3), F(all="gold"))
    for i, cx in enumerate((-7, 0, 7)):
        m.cube(crown, "gold_crown_spike%d" % i, (cx - 2, top_y + 3, -1.5), (cx + 2, top_y + 7, 1.5), F(all="gold"))
    core = core_signature(m, hw=20, hd=14, gem_y=80, ring_r=26, gem="hw_gem_pumpkin")
    bats = _bat_orbit(m, 52, 30, 3)
    m.anim("animation.arvan_hw_top.idle", {
        **core, **bats,
        "crown": {"position": ["0", "math.sin(query.anim_time * 80) * 1.6", "0"],
                  "rotation": ["0", "query.anim_time * -18", "0"]},
    })
    return m


def build_hw_podium() -> Model:
    """سکوی قهرمانان هالووین: سه کدو تنبل روی پله‌ها + خفاش‌های مداری."""
    m = Model("arvan_hw_podium", "arvan:hw_podium")
    base = m.bone("base", (0, 0, 0))
    core_plaza(m, hw=22, hd=16)
    _candles(m, base, [(-18, 11), (18, 11), (0, 13), (-18, -11), (18, -11)], 7.0)
    # سه پله
    m.cube(base, "step1", (-9, CORE_LEVELS["floor_top"], -8), (9, 18, 8), F(all="hw_tomb"))
    m.cube(base, "step2", (-19, CORE_LEVELS["floor_top"], -7), (-10, 11, 7), F(all="hw_tomb"))
    m.cube(base, "step3", (10, CORE_LEVELS["floor_top"], -7), (19, 11, 7), F(all="hw_tomb"))
    # سه کدو تنبل
    p1 = m.bone("pumpkin1", (0, 18, 0))
    top1 = pumpkin(m, p1, "p1", 0, 18, 0, 17.0)
    m.cube(p1, "p1_crown_body", (-6, top1, -3), (6, top1 + 3, 3), F(all="gold"))
    for i, cx in enumerate((-6, 0, 6)):
        m.cube(p1, "p1_crown_spike%d" % i, (cx - 1.8, top1 + 3, -1.5), (cx + 1.8, top1 + 6.5, 1.5), F(all="gold"))
    m.cube(p1, "p1_gem", (-1.5, top1 + 3, -2), (1.5, top1 + 5.5, 2), F(all="gem_red"))
    p2 = m.bone("pumpkin2", (-14.5, 11, 0))
    pumpkin(m, p2, "p2", -14.5, 11, 0, 12.0)
    p3 = m.bone("pumpkin3", (14.5, 11, 0))
    pumpkin(m, p3, "p3", 14.5, 11, 0, 12.0)
    # جمجمه روی سکو
    m.cube(base, "skull", (-4, CORE_LEVELS["floor_top"], 9), (4, 12, 15), F(front="hw_skull", all="hw_skull"))
    core = core_signature(m, hw=22, hd=16, gem_y=58, ring_r=28, gem="hw_gem_soul")
    bats = _bat_orbit(m, 46, 32, 4)
    m.anim("animation.arvan_hw_podium.idle", {
        **core, **bats,
        "pumpkin1": {"position": ["0", "math.sin(query.anim_time * 70) * 1.4", "0"],
                     "rotation": ["0", "math.sin(query.anim_time * 35) * 5", "0"]},
        "pumpkin2": {"position": ["0", "math.sin(query.anim_time * 70 + 40) * 1.1", "0"]},
        "pumpkin3": {"position": ["0", "math.sin(query.anim_time * 70 + 80) * 1.1", "0"]},
    })
    return m


def build_hw_projector() -> Model:
    """دیگ جادو با شعلهٔ روح و روح شناور — کم‌جا‌ترین مدل ست."""
    m = Model("arvan_hw_projector", "arvan:hw_projector")
    base = m.bone("base", (0, 0, 0))
    core_plaza(m, hw=15, hd=13)
    _candles(m, base, [(-11, 8), (11, 8)])
    # سه‌پایهٔ دیگ
    for i, (cx, cz) in enumerate(((-6, -6), (6, -6), (0, 7))):
        m.cube(base, "leg%d" % i, (cx - 1.5, CORE_LEVELS["floor_top"], cz - 1.5),
               (cx + 1.5, 8, cz + 1.5), F(all="hw_wood_dark"))
    # دیگ
    m.cube(base, "cauldron", (-9, 8, -9), (9, 22, 9), F(all="hw_cauldron"))
    m.cube(base, "cauldron_rim", (-10, 22, -10), (10, 24.5, 10), F(all="hw_cauldron", top="stone"))
    m.cube(base, "cauldron_soup", (-8, 24.5, -8), (8, 25.5, 8), F(top="hw_soulfire", bottom="hw_soulfire", all="hw_soulfire"))
    # شعلهٔ روح و پرتو
    beam = m.bone("beam", (0, 25.5, 0))
    m.cube(beam, "soul_core", (-7, 25.5, -7), (7, 50, 7), F(all="hw_soulfire"))
    m.cube(beam, "soul_ring", (-8, 25.5, -8), (8, 27.5, 8), F(all="holo_cyan"))
    # روح شناور بالا (به‌جای نگین معمولی، ولی نگین هسته سر جای خودش می‌ماند)
    ghost = m.bone("ghost", (0, 56, 0))
    m.cube(ghost, "ghost_body", (-7, 50, -5), (7, 62, 5), F(front="hw_ghost", back="hw_ghost", all="hw_ghost"))
    m.cube(ghost, "ghost_tail", (-5, 46, -4), (5, 50, 4), F(all="hw_ghost"))
    core = core_signature(m, hw=15, hd=13, gem_y=70, ring_r=20, gem="hw_gem_soul")
    bats = _bat_orbit(m, 40, 24, 2, name="bats")
    m.anim("animation.arvan_hw_projector.idle", {
        **core, **bats,
        "beam": {"scale": ["1 + math.sin(query.anim_time * 110) * 0.1", "1", "1 + math.sin(query.anim_time * 110) * 0.1"]},
        "ghost": {"position": ["0", "math.sin(query.anim_time * 60) * 2.4", "0"],
                  "rotation": ["0", "math.sin(query.anim_time * 30) * 12", "0"]},
    })
    return m


def build_hw_bed() -> Model:
    """تخت خون‌آشام: بدنهٔ چوبی تیره، پتوی قرمز، بال خفاش روی سر تخت، جمجمه و شمع."""
    m = Model("arvan_hw_bed", "arvan:hw_bed")
    base = m.bone("base", (0, 0, 0))
    core_plaza(m, hw=22, hd=18)
    _candles(m, base, [(-18, 12), (18, 12), (-18, -12), (18, -12)], 7.5)
    # تخت
    m.cube(base, "bed_frame", (-18, CORE_LEVELS["floor_top"], -13), (18, 8, 13), F(all="hw_wood_dark"))
    m.cube(base, "bed_mattress", (-17, 8, -12), (17, 13, 12), F(all="wool_red", top="wool_red"))
    m.cube(base, "bed_quilt", (-2, 13, -11), (16, 14.5, 12), F(top="wool_red", all="wool_red"))
    m.cube(base, "bed_pillow", (-16, 13, -11), (-4, 14.5, 12), F(top="wool_white", all="wool_white"))
    # تختهٔ سرِ تخت با بال خفاش
    m.cube(base, "headboard", (-20, 8, -18), (20, 30, -13), F(front="hw_wood_dark", all="hw_wood_dark"))
    m.cube(base, "headboard_top", (-20, 30, -18.5), (20, 33, -12.5), F(top="gold", all="gold_dark"))
    m.cube(base, "headboard_sign", (-15, 20, -18.2), (15, 27, -17.8), F(front="hw_header_boo", all="gold_dark"))
    for sx in (-1, 1):
        m.cube(base, "wing%d" % (0 if sx < 0 else 1), (sx * 11, 33, -17), (sx * 27, 47, -14),
               F(front="hw_bat", back="hw_bat", all="hw_bat_body"))
    m.cube(base, "web_bed", (-20.2, 24, -13.2), (-19.8, 33, -12.8), F(front="hw_cobweb", all="hw_cobweb"))
    # جمجمه و استخوان‌ها روی تخت
    m.cube(base, "bed_skull", (8, 13, -6), (14, 19, 0), F(front="hw_skull", all="hw_skull"))
    m.cube(base, "bed_bone1", (-15, 13, 4), (-6, 14.5, 6), F(all="hw_bone"))
    m.cube(base, "bed_bone2", (-12, 14.5, 7), (-9, 16, 10), F(all="hw_bone"))
    # شمشیر شناور تیره (هم‌خانواده با ست معمولی)
    sw = m.bone("sword", (0, 44, 0))
    m.cube(sw, "sword_blade", (-2, 42, -2), (2, 60, 2), F(all="hw_gem_soul"))
    m.cube(sw, "sword_tip", (-1, 60, -1), (1, 64, 1), F(all="hw_gem_soul"))
    m.cube(sw, "sword_guard", (-8, 39, -2), (8, 42, 2), F(all="gold"))
    m.cube(sw, "sword_grip", (-1.5, 34, -1.5), (1.5, 39, 1.5), F(all="hw_wood_dark"))
    core = core_signature(m, hw=22, hd=18, gem_y=74, ring_r=30, gem="hw_gem_soul")
    bats = _bat_orbit(m, 52, 33, 3)
    m.anim("animation.arvan_hw_bed.idle", {
        **core, **bats,
        "sword": {"rotation": ["math.sin(query.anim_time * 45) * 4", "query.anim_time * 50", "0"],
                  "position": ["0", "math.sin(query.anim_time * 70) * 2", "0"]},
    })
    return m


def build_hw_sign() -> Model:
    """تابلوی آویزان از زنجیر زیر طاق چوبی — جمع‌وجورترین مدل (مناسب گوشهٔ لابی)."""
    m = Model("arvan_hw_sign", "arvan:hw_sign")
    base = m.bone("base", (0, 0, 0))
    core_plaza(m, hw=14, hd=10)
    _candles(m, base, [(-10, 7), (10, 7)])
    # دو پایهٔ طاق + تیر بالایی
    for sx in (-1, 1):
        m.cube(base, "arch%d" % (0 if sx < 0 else 1), (sx * 12 - 1.5, CORE_LEVELS["floor_top"], -1.5),
               (sx * 12 + 1.5, 40, 1.5), F(all="hw_wood_dark"))
    m.cube(base, "arch_top", (-14, 40, -2.5), (14, 44, 2.5), F(all="hw_wood_dark", top="hw_bone"))
    pumpkin(m, base, "arch_pumpkin", 0, 44, 0, 11.0)
    m.cube(base, "arch_hook", (-2, 39.5, -1), (2, 40.5, 1), F(all="gold"))
    # زنجیرها
    for sx in (-1, 1):
        m.cube(base, "chain%d" % (0 if sx < 0 else 1), (sx * 8 - 0.6, 28, -0.6),
               (sx * 8 + 0.6, 40, 0.6), F(all="iron"))
    # تخته‌ی آویزان
    m.cube(base, "sign_board", (-12, 12, -2), (12, 30, 2), F(front="hw_board_dark", back="hw_board_dark", all="hw_board_dark"))
    m.cube(base, "sign_header", (-11, 25, -2.3), (11, 30, -1.9), F(front="hw_header", all="gold_dark"))
    m.cube(base, "sign_panel", (-11, 12, -2.3), (11, 25, -1.9), F(front="hw_tomb_panel"))
    m.cube(base, "sign_web", (-12.2, 22, -2.2), (-11.8, 30, -1.8), F(front="hw_cobweb", all="hw_cobweb"))
    core = core_signature(m, hw=14, hd=10, gem_y=62, ring_r=18, gem="hw_gem_pumpkin", posts=False)
    bats = _bat_orbit(m, 46, 20, 2, name="bats")
    m.anim("animation.arvan_hw_sign.idle", {
        **core, **bats,
        "base": {"rotation": ["0", "0", "math.sin(query.anim_time * 55) * 2"],   # تاب خوردن ملایم
                 "position": ["0", "math.sin(query.anim_time * 55) * 0.6", "0"]},
    })
    return m


# ================================================================ فهرست نهایی
ENTRIES: list[tuple[Model, str, tuple[str, str]]] = [
    (build_hw_top(), "1.0", ("#F08A1E", "#2C2246")),
    (build_hw_podium(), "1.0", ("#F08A1E", "#C8282D")),
    (build_hw_projector(), "1.0", ("#78FF96", "#2C2246")),
    (build_hw_bed(), "1.05", ("#C8282D", "#2C2246")),
    (build_hw_sign(), "1.0", ("#8A5A2B", "#F08A1E")),
]
