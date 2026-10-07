#!/usr/bin/env python3
"""generate.py — ساخت مدل‌های لیدربورد و هولوگرام سریور BedWars «ArvanGaming»
================================================================================
چهار مدل ساخته می‌شود:
  1) arvan_leaderboard   دیوار لیدربورد طلایی با تاج شناور، حلقه‌های چرخان و نگین‌های مدار
  2) arvan_podium        سکوی سه‌پلهٔ قهرمانان با ستون‌های نور و کریستال بالای سکو
  3) arvan_hologram      پروژکتور هولوگرام (برای وضعیت آرنا/آمار) با دیسک و حلقه‌های چرخان
  4) arvan_bedwars_bed   تخت بزرگ BedWars با شمشیر شناور — اثر شاخص لابی

برای هر مدل: بافت اختصاصی، ‎.bbmodel‎ (بلوک‌بنج)، ‎.geo.json‎، ‎.animation.json‎
و در پایان ریسورس‌پک آماده (‎.mcpack‎ / zip) + پیش‌نمایش تصویری.

اجرا:  python3 generate.py       (نیازمند: pillow)
"""
from __future__ import annotations

import os
import shutil
import uuid

import crown_models
import hw_models
from bb_lib import (ATLAS, F, REG, Model, core_plaza, core_signature, model_top, bed_icon, center_text, crown_icon, endstone,
                    gem, glow, goldblock, grad, holo_beam, holo_glass, medal, noise,
                    outline, pixel_text, plank, plate, rect, render_model,
                    save_anim, save_bbmodel, save_entity, save_geo, save_manifest,
                    save_render_controller, star_icon, stone, sword_icon, tex,
                    texture_sheet, wool, zip_dir)
from PIL import Image, ImageDraw

OUT = os.path.dirname(os.path.abspath(__file__))
S = 768                      # اندازهٔ بافت
NS = uuid.UUID("8f2c1d44-7a91-4c3e-9c11-2ab24e6f9d10")   # برای uuid پایدار

GOLD = [(255, 240, 150), (252, 206, 60), (220, 150, 25), (140, 85, 15)]
RED = [(255, 110, 100), (220, 40, 45), (140, 15, 25), (60, 5, 12)]
BLUE = [(120, 190, 255), (40, 110, 230), (20, 50, 150), (8, 18, 60)]
CYAN = (90, 220, 255)
GREEN = [(170, 255, 190), (40, 210, 100), (5, 90, 40)]
PURPLE = [(210, 170, 255), (140, 70, 220), (60, 20, 120)]
WHITE = (248, 248, 252)

# =============================================================== بافت‌ها
print("🎨 ساخت بافت…")


def t_endstone(d, x, y, w, h):
    endstone(d, x, y, w, h)


def t_stone(d, x, y, w, h):
    stone(d, x, y, w, h)


def t_obsidian(d, x, y, w, h):
    grad(d, x, y, w, h, (44, 34, 70), (22, 16, 38))
    noise(d, x, y, w, h, [(70, 54, 110), (14, 10, 26)], 4)
    for i in range(0, w, 11):
        rect(d, x + i, y, 1, h, (86, 66, 132), 90)
    outline(d, x, y, w, h, (12, 8, 22))


def t_gold(d, x, y, w, h):
    goldblock(d, x, y, w, h)


def t_gold_dark(d, x, y, w, h):
    grad(d, x, y, w, h, (196, 140, 30), (140, 92, 14))
    noise(d, x, y, w, h, [(230, 180, 60), (110, 70, 10)], 4)
    for i in range(0, h, 8):
        rect(d, x, y + i, w, 1, (110, 70, 10), 160)
    outline(d, x, y, w, h, (90, 58, 8))


def t_bronze(d, x, y, w, h):
    plate(d, x, y, w, h, (176, 112, 62), (214, 150, 96), (124, 72, 34))


def t_iron(d, x, y, w, h):
    plate(d, x, y, w, h, (200, 202, 208), (232, 234, 240), (150, 152, 158))


def t_plank(d, x, y, w, h):
    plank(d, x, y, w, h)


def t_wool_red(d, x, y, w, h):
    wool(d, x, y, w, h, (190, 38, 44), (240, 90, 90), (120, 10, 20))


def t_wool_blue(d, x, y, w, h):
    wool(d, x, y, w, h, (40, 100, 200), (110, 170, 250), (16, 40, 120))


def t_wool_white(d, x, y, w, h):
    wool(d, x, y, w, h, (232, 232, 238), (252, 252, 255), (196, 196, 206))


def t_holo_panel(d, x, y, w, h):
    """پنل هولوگرامی تیره: پشتِ متن‌های لیدربورد."""
    rect(d, x, y, w, h, (18, 30, 58), 205)
    for yy in range(y, y + h, 4):
        rect(d, x, yy, w, 1, (60, 120, 200), 90)
    for i in range(5):
        rect(d, x, y + i * h // 5, w, 1, (90, 180, 255), 120)
    glow(d, x + w // 2 - w // 4, y + h // 2 - h // 4, w // 2, h // 2, (40, 120, 220))
    outline(d, x, y, w, h, (120, 200, 255), 170)


def t_holo_cyan(d, x, y, w, h):
    holo_glass(d, x, y, w, h, CYAN, 120)


def t_holo_gold(d, x, y, w, h):
    holo_glass(d, x, y, w, h, (255, 200, 80), 120)


def t_beam_cyan(d, x, y, w, h):
    holo_beam(d, x, y, w, h, CYAN)


def t_beam_gold(d, x, y, w, h):
    holo_beam(d, x, y, w, h, (255, 210, 90))


def t_beam_green(d, x, y, w, h):
    holo_beam(d, x, y, w, h, (110, 255, 170))


def t_holo_disc(d, x, y, w, h):
    """دیسک گرد هولوگرامی — گوشه‌ها شفاف تا در بازی دایره دیده شود."""
    cx, cy, r = x + w / 2, y + h / 2, min(w, h) / 2 - 1
    d.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(60, 170, 240, 120))
    d.ellipse([cx - r * 0.86, cy - r * 0.86, cx + r * 0.86, cy + r * 0.86], fill=(110, 220, 255, 90))
    for i in range(3):
        rr = r * (0.35 + i * 0.22)
        d.ellipse([cx - rr, cy - rr, cx + rr, cy + rr], outline=(200, 250, 255, 200), width=1)
    for i in range(16):
        import math as _m
        a = _m.tau * i / 16
        d.point([(cx + _m.cos(a) * r * 0.94, cy + _m.sin(a) * r * 0.94)], fill=(255, 255, 255, 230))


def t_gem_factory(light, mid, dark):
    def fn(d, x, y, w, h):
        gem(d, x, y, w, h, light, mid, dark)
    return fn


def t_header_bedwars(d, x, y, w, h):
    goldblock(d, x, y, w, h)
    rect(d, x + 2, y + 2, w - 4, h - 4, (30, 16, 40), 235)
    center_text(d, x + w / 2, y + 3, "BEDWARS", (255, 240, 150), (90, 40, 10), 1, (20, 10, 20))
    star_icon(d, x + 4, y + h // 2 - 3, 8, 8)
    star_icon(d, x + w - 12, y + h // 2 - 3, 8, 8)


def t_header_generic(label, col=(255, 240, 150)):
    def fn(d, x, y, w, h):
        goldblock(d, x, y, w, h)
        rect(d, x + 2, y + 2, w - 4, h - 4, (26, 14, 36), 235)
        center_text(d, x + w / 2, y + (h - 5) // 2, label, col, (90, 40, 10), 1, (18, 10, 18))
    return fn


def t_number(num, base, light, dark):
    def fn(d, x, y, w, h):
        plate(d, x, y, w, h, base, light, dark)
        rect(d, x + 2, y + 2, w - 4, h - 4, (26, 18, 40), 220)
        sc = max(2, h // 10)
        center_text(d, x + w / 2, y + (h - 5 * sc) // 2, num, (255, 240, 150), (70, 30, 10), sc)
        medal(d, x + 3, y + 2, 12, h - 4, base, dark, light)
    return fn


def t_bed_top(d, x, y, w, h):
    """رویهٔ تخت: پتو قرمز + بالش سفید در سر تخت + خط‌های لحاف."""
    wool(d, x, y, w, h, (190, 38, 44), (240, 90, 90), (120, 10, 20))
    pw = int(w * 0.28)
    wool(d, x, y, pw, h, (232, 232, 238), (252, 252, 255), (196, 196, 206))
    rect(d, x + pw, y, 1, h, (140, 20, 30))
    for i in range(pw + 6, w, 8):
        rect(d, x + i, y + 1, 1, h - 2, (150, 20, 30), 200)
    for j in range(2, h, 8):
        rect(d, x + 1, y + j, w - 2, 1, (150, 20, 30), 160)


def t_screen(icon_fn, col):
    def fn(d, x, y, w, h):
        plate(d, x, y, w, h, (60, 66, 84), (110, 118, 140), (34, 38, 50))
        rect(d, x + 2, y + 2, w - 4, h - 4, (16, 26, 46), 240)
        glow(d, x + 3, y + 3, w - 6, h - 6, col)
        icon_fn(d, x + 4, y + 4, w - 8, h - 8)
        for i in range(2, h - 2, 3):
            rect(d, x + 2, y + i, w - 4, 1, (90, 200, 255), 45)
        outline(d, x + 2, y + 2, w - 4, h - 4, (140, 220, 255), 200)
    return fn


def t_flag_canvas(cols, symbol=None):
    def fn(d, x, y, w, h):
        grad(d, x, y, w, h, cols[0], cols[2])
        for i in range(0, w, 6):
            rect(d, x + i, y, 3, h, cols[1], 120)
        outline(d, x, y, w, h, cols[3])
        if symbol == "crown":
            crown_icon(d, x + w // 4, y + h // 3, w // 2, h // 3)
        elif symbol == "sword":
            sword_icon(d, x + w // 4, y + h // 4, w // 2, h // 2)
        elif symbol == "bed":
            bed_icon(d, x + w // 5, y + h // 3, w * 3 // 5, h // 3)
    return fn


# --- ناحیه‌ها
tex("endstone", 32, 32, t_endstone)
tex("stone", 32, 32, t_stone)
tex("obsidian", 32, 32, t_obsidian)
tex("gold", 32, 32, t_gold)
tex("gold_dark", 32, 32, t_gold_dark)
tex("bronze", 32, 32, t_bronze)
tex("iron", 32, 32, t_iron)
tex("plank", 32, 32, t_plank)
tex("wool_red", 32, 32, t_wool_red)
tex("wool_blue", 32, 32, t_wool_blue)
tex("wool_white", 32, 32, t_wool_white)
tex("holo_panel", 48, 48, t_holo_panel)
tex("holo_cyan", 32, 32, t_holo_cyan)
tex("holo_gold", 32, 32, t_holo_gold)
tex("beam_cyan", 16, 64, t_beam_cyan)
tex("beam_gold", 16, 64, t_beam_gold)
tex("beam_green", 16, 64, t_beam_green)
tex("holo_disc", 64, 64, t_holo_disc)
tex("gem_diamond", 32, 32, t_gem_factory((220, 255, 255), (80, 220, 230), (20, 110, 140)))
tex("gem_emerald", 32, 32, t_gem_factory((190, 255, 210), (40, 210, 100), (5, 90, 40)))
tex("gem_gold", 32, 32, t_gem_factory((255, 245, 180), (252, 206, 60), (140, 85, 15)))
tex("gem_red", 32, 32, t_gem_factory((255, 170, 170), (220, 40, 45), (90, 5, 12)))
tex("gem_purple", 32, 32, t_gem_factory((220, 190, 255), (140, 70, 220), (50, 15, 100)))
tex("header_bedwars", 92, 12, t_header_bedwars)
tex("header_top3", 72, 12, t_header_generic("TOP 3"))
tex("header_stats", 72, 12, t_header_generic("ARVAN STATS", (190, 240, 255)))
tex("header_bedwars_stats", 92, 12, t_header_generic("BEDWARS STATS"))
tex("num1", 44, 20, t_number("1", GOLD[1], GOLD[0], GOLD[2]))
tex("num2", 44, 20, t_number("2", (208, 210, 216), (238, 240, 246), (150, 152, 158)))
tex("num3", 44, 20, t_number("3", (186, 116, 62), (220, 156, 96), (124, 72, 34)))
tex("bed_top", 96, 64, t_bed_top)
tex("screen_sword", 40, 28, t_screen(lambda d, x, y, w, h: sword_icon(d, x, y, w, h), (255, 90, 90)))
tex("screen_bed", 40, 28, t_screen(lambda d, x, y, w, h: bed_icon(d, x, y, w, h), (255, 160, 90)))
tex("screen_crown", 40, 28, t_screen(lambda d, x, y, w, h: crown_icon(d, x, y, w, h), (255, 214, 90)))
tex("screen_star", 40, 28, t_screen(lambda d, x, y, w, h: star_icon(d, x, y, w, h), (150, 220, 255)))
tex("flag_red", 24, 40, t_flag_canvas(RED, "sword"))
tex("flag_blue", 24, 40, t_flag_canvas(BLUE, "sword"))
tex("flag_bed", 24, 40, t_flag_canvas(GOLD, "bed"))
tex("banner_arvan", 32, 48, lambda d, x, y, w, h: (
    grad(d, x, y, w, h, (36, 20, 52), (16, 10, 26)),
    pixel_text(d, x + 3, y + 4, "A", GOLD[0], (60, 20, 10), 2),
    pixel_text(d, x + 3, y + 18, "R", GOLD[0], (60, 20, 10), 2),
    pixel_text(d, x + 3, y + 32, "V", GOLD[0], (60, 20, 10), 2),
    star_icon(d, x + 18, y + 8, 10, 10),
))


# ============================================================= مدل ۱: لیدربورد
def build_leaderboard() -> Model:
    m = Model("arvan_leaderboard", "arvan:leaderboard")
    base = m.bone("base", (0, 0, 0))
    # سکو
    m.cube(base, "platform", (-36, -2, -18), (36, 2, 18), F(top="endstone", all="stone"))
    m.cube(base, "rim_n", (-36, 2, -18), (36, 3.5, -14), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_s", (-36, 2, 14), (36, 3.5, 18), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_w", (-36, 2, -14), (-32, 3.5, 14), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_e", (32, 2, -14), (36, 3.5, 14), F(top="gold", all="gold_dark"))
    m.cube(base, "floor", (-32, 3.5, -14), (32, 4.5, 14), F(top="obsidian", all="obsidian"))
    # پایه‌ها
    m.cube(base, "pillar_l", (-33, 4.5, -3.5), (-27, 55, 3.5), F(top="gold", all="gold_dark"))
    m.cube(base, "pillar_r", (27, 4.5, -3.5), (33, 55, 3.5), F(top="gold", all="gold_dark"))
    m.cube(base, "pillar_l_cap", (-34.5, 53, -5), (-25.5, 62, 5), F(top="gold", all="gold"))
    m.cube(base, "pillar_r_cap", (25.5, 53, -5), (34.5, 62, 5), F(top="gold", all="gold"))
    # تخته و قاب
    board = m.bone("board", (0, 28, 0))
    m.cube(board, "top_bar", (-34, 58, -3), (34, 62, 3), F(top="gold", all="gold"))
    m.cube(board, "frame_bottom", (-28, 8, -1.5), (28, 12, 2), F(all="gold_dark", front="gold"))
    m.cube(board, "frame_left", (-28, 12, -1.5), (-25, 50, 2), F(all="gold_dark", front="gold"))
    m.cube(board, "frame_right", (25, 12, -1.5), (28, 50, 2), F(all="gold_dark", front="gold"))
    m.cube(board, "header", (-25, 50, -1.5), (25, 56, 2),
           F(front="header_bedwars", all="gold"))
    m.cube(board, "backing", (-25, 12, 0.6), (25, 50, 1.6), F(front="holo_panel", all="obsidian"))
    # ستون‌های تزئینی داخل تخته
    for i, x in enumerate((-24, -8, 8, 24)):
        m.cube(board, "stud%d" % i, (x - 1, 12, -0.2), (x + 1, 50, 0.6),
               F(front="gold", all="gold_dark"))
    # تاج شناور
    crown = m.bone("crown", (0, 66, 0))
    m.cube(crown, "crown_body", (-8, 62, -5), (8, 65, 5), F(all="gold"))
    for i, cx in enumerate((-8, 0, 8)):
        m.cube(crown, "crown_spike%d" % i, (cx - 2.5, 65, -1), (cx + 2.5, 70, 1),
               F(all="gold"))
    m.cube(crown, "crown_gem", (-2, 65, -1.2), (2, 68, 1.2), F(all="gem_red"))
    # پرچم‌های تیم
    bl = m.bone("banner_l", (-38, 58, 0))
    m.cube(bl, "banner_l_cloth", (-46, 34, 0.2), (-34, 58, 0.8), F(front="flag_red", all="wool_red"))
    m.cube(bl, "banner_l_rod", (-46.5, 58, 0), (-33.5, 59.5, 1), F(all="gold_dark"))
    br = m.bone("banner_r", (38, 58, 0))
    m.cube(br, "banner_r_cloth", (34, 34, 0.2), (46, 58, 0.8), F(front="flag_blue", all="wool_blue"))
    m.cube(br, "banner_r_rod", (33.5, 58, 0), (46.5, 59.5, 1), F(all="gold_dark"))
    core = core_signature(m, hw=36, hd=18, gem_y=model_top(m) + 9, ring_r=26, posts=False)
    m.anim("animation.arvan_leaderboard.idle", {
        **core,
        "board": {"position": ["0", "math.sin(query.anim_time * 60) * 0.8", "0"],
                  "rotation": ["math.sin(query.anim_time * 45) * 0.6", "0", "0"]},
        "crown": {"position": ["0", "math.sin(query.anim_time * 90) * 1.6", "0"],
                  "rotation": ["0", "query.anim_time * -35", "0"]},
        "banner_l": {"rotation": ["0", "0", "math.sin(query.anim_time * 100) * 3"]},
        "banner_r": {"rotation": ["0", "0", "math.sin(query.anim_time * 100 + 40) * -3"]},
    })
    return m


# ================================================================ مدل ۲: سکو
def build_podium() -> Model:
    m = Model("arvan_podium", "arvan:podium")
    base = m.bone("base", (0, 0, 0))
    m.cube(base, "platform", (-36, -2, -24), (36, 2, 24), F(top="endstone", all="stone"))
    m.cube(base, "rim_n", (-36, 2, -24), (36, 3.5, -20), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_s", (-36, 2, 20), (36, 3.5, 24), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_w", (-36, 2, -20), (-32, 3.5, 20), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_e", (32, 2, -20), (36, 3.5, 20), F(top="gold", all="gold_dark"))
    m.cube(base, "floor", (-32, 3.5, -20), (32, 4.5, 20), F(top="obsidian", all="obsidian"))
    # پله‌ها (۱ وسط بلند، ۲ چپ، ۳ راست)
    m.cube(base, "step1", (-10, 4.5, -12), (10, 18, 12), F(top="gold", all="gold_dark"))
    m.cube(base, "step2", (-28, 4.5, -10), (-11, 10, 10), F(top="bronze", all="bronze"))
    m.cube(base, "step3", (11, 4.5, -10), (28, 10, 10), F(top="iron", all="iron"))
    m.cube(base, "plate1", (-7, 12, -12.4), (7, 17, -12.0), F(front="num1", all="gold_dark"))
    m.cube(base, "plate2", (-25, 5.5, -10.4), (-14, 9.5, -10.0), F(front="num2", all="bronze"))
    m.cube(base, "plate3", (14, 5.5, -10.4), (25, 9.5, -10.0), F(front="num3", all="iron"))
    # تختهٔ عنوان پشت سکو
    m.cube(base, "backwall", (-22, 18, 12), (22, 22, 16), F(all="obsidian"))
    m.cube(base, "backpost_l", (-23, 18, 12), (-20, 46, 16), F(all="gold_dark"))
    m.cube(base, "backpost_r", (20, 18, 12), (23, 46, 16), F(all="gold_dark"))
    m.cube(base, "backsign", (-20, 30, 12.2), (20, 36, 15.8), F(front="header_top3", all="gold"))
    # ستون‌های نور
    for i, (bx, by, top) in enumerate(((0, 18, 46), (-19, 10, 36), (19, 10, 36))):
        b = m.bone("beam%d" % (i + 1), (bx, by, 0))
        m.cube(b, "beam%d_core" % (i + 1), (bx - 5, by, -5), (bx + 5, top, 5),
               F(all="beam_gold" if i == 0 else "beam_cyan"))
        m.cube(b, "beam%d_base" % (i + 1), (bx - 7, by - 0.5, -7), (bx + 7, by + 0.5, 7),
               F(all="holo_gold" if i == 0 else "holo_cyan"))
    # تاج/مدال‌های شناور
    c1 = m.bone("top1", (0, 60, 0))
    m.cube(c1, "top1_body", (-9, 56, -6), (9, 60, 6), F(all="gold"))
    for i, cx in enumerate((-9, 0, 9)):
        m.cube(c1, "top1_spike%d" % i, (cx - 3, 60, -1.5), (cx + 3, 66, 1.5), F(all="gold"))
    m.cube(c1, "top1_gem", (-2.5, 60, -1.5), (2.5, 64, 1.5), F(all="gem_red"))
    for i, (bx, by, txt) in enumerate(((-19, 48, "2"), (19, 48, "3"))):
        bid = m.bone("top%d" % (i + 2), (bx, by, 0))
        col = "iron" if i else "bronze"
        m.cube(bid, "top%d_medal" % (i + 2), (bx - 6, by, -1.5), (bx + 6, by + 12, 1.5),
               F(front="num%s" % txt, all=col))
        m.cube(bid, "top%d_ring" % (i + 2), (bx - 7.5, by - 1.5, -2.5), (bx + 7.5, by + 1, 2.5),
               F(all="holo_cyan"))
    # پرچم‌ها
    bl = m.bone("banner_l", (-30, 46, 16))
    m.cube(bl, "bl_cloth", (-37, 24, 16.2), (-25, 46, 16.9), F(front="flag_red", all="wool_red"))
    br = m.bone("banner_r", (30, 46, 16))
    m.cube(br, "br_cloth", (25, 24, 16.2), (37, 46, 16.9), F(front="flag_blue", all="wool_blue"))
    core = core_signature(m, hw=36, hd=24, gem_y=model_top(m) + 9, ring_r=28, gem="gem_purple", posts=False)
    m.anim("animation.arvan_podium.idle", {
        **core,
        "beam1": {"scale": ["1", "1 + math.sin(query.anim_time * 120) * 0.12", "1"]},
        "beam2": {"scale": ["1", "1 + math.sin(query.anim_time * 120 + 60) * 0.15", "1"]},
        "beam3": {"scale": ["1", "1 + math.sin(query.anim_time * 120 + 120) * 0.15", "1"]},
        "top1": {"position": ["0", "math.sin(query.anim_time * 90) * 1.8", "0"],
                 "rotation": ["0", "query.anim_time * -30", "0"]},
        "top2": {"position": ["0", "math.sin(query.anim_time * 90 + 45) * 1.4", "0"]},
        "top3": {"position": ["0", "math.sin(query.anim_time * 90 + 90) * 1.4", "0"]},
        "banner_l": {"rotation": ["0", "0", "math.sin(query.anim_time * 100) * 3"]},
        "banner_r": {"rotation": ["0", "0", "math.sin(query.anim_time * 100 + 40) * -3"]},
    })
    return m


# ============================================================ مدل ۳: پروژکتور
def build_hologram() -> Model:
    m = Model("arvan_hologram", "arvan:hologram")
    base = m.bone("base", (0, 0, 0))
    m.cube(base, "platform", (-24, -2, -24), (24, 2, 24), F(top="obsidian", all="stone"))
    m.cube(base, "rim_n", (-24, 2, -24), (24, 3.5, -20), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_s", (-24, 2, 20), (24, 3.5, 24), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_w", (-24, 2, -20), (-20, 3.5, 20), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_e", (20, 2, -20), (24, 3.5, 20), F(top="gold", all="gold_dark"))
    m.cube(base, "floor", (-20, 3.5, -20), (20, 4.5, 20), F(top="obsidian", all="obsidian"))
    # چهار ستون گوشه
    for i, (sx, sz) in enumerate(((-1, -1), (1, -1), (-1, 1), (1, 1))):
        px, pz = sx * 17, sz * 17
        m.cube(base, "pillar%d" % i, (px - 3, 4.5, pz - 3), (px + 3, 29, pz + 3),
               F(all="obsidian"))
        m.cube(base, "pillar%d_cap" % i, (px - 4, 26, pz - 4), (px + 4, 31, pz + 4), F(all="gold"))
    # صفحه‌نمایش‌های چهارطرف
    m.cube(base, "screen_n1", (-16, 12, -20.2), (-6, 20, -19.6), F(front="screen_sword", all="obsidian"))
    m.cube(base, "screen_n2", (6, 12, -20.2), (16, 20, -19.6), F(front="screen_bed", all="obsidian"))
    m.cube(base, "screen_s1", (-16, 12, 19.6), (-6, 20, 20.2), F(back="screen_crown", all="obsidian"))
    m.cube(base, "screen_s2", (6, 12, 19.6), (16, 20, 20.2), F(back="screen_star", all="obsidian"))
    # هستهٔ نورانی
    core = m.bone("core", (0, 4.5, 0))
    m.cube(core, "core_body", (-6, 4.5, -6), (6, 17, 6), F(all="gem_emerald"))
    m.cube(core, "core_glow", (-8, 6, -8), (8, 15, 8), F(all="holo_gold"))
    # پرتو
    beam = m.bone("beam", (0, 31, 0))
    m.cube(beam, "beam_core", (-13, 31, -13), (13, 52, 13), F(all="beam_cyan"))
    m.cube(beam, "beam_ring", (-14, 31, -14), (14, 33, 14), F(all="holo_cyan"))
    # دیسک و حلقه‌های چرخان
    disc = m.bone("disc", (0, 53, 0))
    m.cube(disc, "disc_plate", (-20, 52, -20), (20, 54.5, 20), F(top="holo_disc", bottom="holo_disc",
                                                             all="holo_cyan"))
    m.cube(disc, "disc_rim", (-21, 52, -21), (21, 55, -20.4), F(all="holo_cyan"))
    m.cube(disc, "disc_rim2", (-21, 52, 20.4), (21, 55, 21), F(all="holo_cyan"))
    core = core_signature(m, hw=24, hd=24, gem_y=78, ring_r=24, posts=False)
    m.anim("animation.arvan_hologram.idle", {
        **core,
        "core": {"scale": ["1 + math.sin(query.anim_time * 150) * 0.06", "1", "1 + math.sin(query.anim_time * 150) * 0.06"]},
        "beam": {"scale": ["1 + math.sin(query.anim_time * 90) * 0.08", "1", "1 + math.sin(query.anim_time * 90) * 0.08"]},
        "disc": {"rotation": ["0", "query.anim_time * 24", "0"],
                 "position": ["0", "math.sin(query.anim_time * 60) * 1.2", "0"]},
    })
    return m


# ============================================================== مدل ۴: تخت
def build_bed() -> Model:
    m = Model("arvan_bedwars_bed", "arvan:bedwars_bed")
    base = m.bone("base", (0, 0, 0))
    m.cube(base, "platform", (-38, -2, -26), (38, 2, 26), F(top="endstone", all="stone"))
    m.cube(base, "rim_n", (-38, 2, -26), (38, 3.5, -22), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_s", (-38, 2, 22), (38, 3.5, 26), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_w", (-38, 2, -22), (-34, 3.5, 22), F(top="gold", all="gold_dark"))
    m.cube(base, "rim_e", (34, 2, -22), (38, 3.5, 22), F(top="gold", all="gold_dark"))
    m.cube(base, "floor", (-34, 3.5, -22), (34, 4.5, 22), F(top="obsidian", all="obsidian"))
    # تخت
    m.cube(base, "bed_frame", (-28, 4.5, -18), (28, 8, 18), F(all="plank"))
    m.cube(base, "bed_mattress", (-26, 8, -16), (26, 13, 16),
           F(top="bed_top", all="wool_red"))
    m.cube(base, "bed_pillow", (-26, 13, -16), (-10, 14.5, 16), F(top="wool_white", all="wool_white"))
    # تاج تخت (سر تخت) + نوشته
    m.cube(base, "headboard", (-30, 8, -25), (30, 26, -19), F(front="plank", all="plank"))
    m.cube(base, "headboard_trim", (-30, 26, -25.5), (30, 29, -18.5), F(all="gold_dark"))
    m.cube(base, "headboard_sign", (-22, 14, -25.2), (22, 21, -24.8),
           F(front="header_bedwars_stats", all="gold_dark"))
    m.cube(base, "footboard", (-30, 8, 19), (30, 16, 25), F(back="plank", all="plank"))
    for i, lx in enumerate((-29, -19, 19, 29)):
        m.cube(base, "leg%d" % i, (lx - 1.5, 4.5, -21), (lx + 1.5, 6, -18),
               F(all="plank"))
    # شمشیر شناور
    sw = m.bone("sword", (0, 40, 0))
    m.cube(sw, "sword_blade", (-2, 42, -2), (2, 62, 2), F(all="gem_diamond"))
    m.cube(sw, "sword_tip", (-1, 62, -1), (1, 66, 1), F(all="gem_diamond"))
    m.cube(sw, "sword_guard", (-8, 39, -2), (8, 42, 2), F(all="gold"))
    m.cube(sw, "sword_grip", (-1.5, 34, -1.5), (1.5, 39, 1.5), F(all="plank"))
    m.cube(sw, "sword_pommel", (-3.5, 32, -3.5), (3.5, 34, 3.5), F(all="gem_red"))
    # تاج روی سر تخت
    cr = m.bone("crown", (0, 40, -22))
    m.cube(cr, "crown_body", (-9, 32, -25), (9, 35, -19), F(all="gold"))
    for i, cx in enumerate((-9, 0, 9)):
        m.cube(cr, "crown_spike%d" % i, (cx - 3, 35, -23.5), (cx + 3, 41, -20.5), F(all="gold"))
    m.cube(cr, "crown_gem", (-2.5, 35, -23), (2.5, 39, -21), F(all="gem_red"))
    # پرچم‌ها
    bl = m.bone("banner_l", (-34, 44, -8))
    m.cube(bl, "bl_cloth", (-40, 22, -8.2), (-28, 44, -7.5), F(front="flag_red", all="wool_red"))
    m.cube(bl, "bl_rod", (-41, 44, -9), (-27, 45.5, -6.5), F(all="gold_dark"))
    br = m.bone("banner_r", (34, 44, -8))
    m.cube(br, "br_cloth", (28, 22, -8.2), (40, 44, -7.5), F(front="flag_blue", all="wool_blue"))
    m.cube(br, "br_rod", (27, 44, -9), (41, 45.5, -6.5), F(all="gold_dark"))
    core = core_signature(m, hw=38, hd=26, gem_y=model_top(m) + 9, ring_r=30, gem="gem_emerald", posts=False)
    m.anim("animation.arvan_bedwars_bed.idle", {
        **core,
        "sword": {"rotation": ["math.sin(query.anim_time * 45) * 4", "query.anim_time * 45", "0"],
                  "position": ["0", "math.sin(query.anim_time * 70) * 2.2", "0"]},
        "crown": {"position": ["0", "math.sin(query.anim_time * 90 + 30) * 1.5", "0"],
                  "rotation": ["0", "query.anim_time * -25", "0"]},
        "banner_l": {"rotation": ["0", "0", "math.sin(query.anim_time * 100) * 3.5"]},
        "banner_r": {"rotation": ["0", "0", "math.sin(query.anim_time * 100 + 50) * -3.5"]},
    })
    return m


# ======================================================== بافت‌های هالووین
print("🎃 بافت‌های هالووین…")
hw_models.define_textures()

# ================================================================ ساخت همه
MODELS = [build_leaderboard(), build_podium(), build_hologram(), build_bed()]
SCALE = {"arvan_leaderboard": "1.0", "arvan_podium": "1.0",
         "arvan_hologram": "1.0", "arvan_bedwars_bed": "1.0"}
EGG = {"arvan_leaderboard": ("#FFD24A", "#1B2740"), "arvan_podium": ("#FFD24A", "#C8282D"),
       "arvan_hologram": ("#5BE0FF", "#1B2740"), "arvan_bedwars_bed": ("#DC282D", "#F0F0F5")}

# ست هالووین (هم‌خانواده با همان پنج عنصر مشترک)
for hw_model, hw_scale, hw_egg in hw_models.ENTRIES:
    MODELS.append(hw_model)
    SCALE[hw_model.name] = hw_scale
    EGG[hw_model.name] = hw_egg

# 👑 غول‌پیکر تاج‌ها (۶ نسخه؛ فقط رنگ جواهر وسط تاج عوض می‌شود)
crown_models.define_textures()
TEX_SHARE = dict(crown_models.CROWN_TEX_SHARE)
CROWN_NAMES = {
    "arvan_giant_crown_kills": "👑 GIANT CROWN · KILLS",
    "arvan_giant_crown_wins": "👑 GIANT CROWN · WINS",
    "arvan_giant_crown_beds_broken": "👑 GIANT CROWN · BEDS",
    "arvan_giant_crown_final_kills": "👑 GIANT CROWN · FINALS",
    "arvan_giant_crown_level": "👑 GIANT CROWN · LEVEL",
    "arvan_giant_crown_coins": "👑 GIANT CROWN · COINS",
}
for cr_model, cr_scale, cr_egg in crown_models.entries():
    MODELS.append(cr_model)
    SCALE[cr_model.name] = cr_scale
    EGG[cr_model.name] = cr_egg
    # فقط نسخهٔ پایه .bbmodel بگیرد (بقیه هندسهٔ یکسان با جواهر متفاوت دارند)
    if cr_model.name != "arvan_giant_crown_kills":
        cr_model.skip_bbmodel = True

TEX_PATH = os.path.join(OUT, "arvan_leaderboard_atlas.png")
ATLAS.img.save(TEX_PATH)

print("🧱 خروجی مدل‌ها…")
for m in MODELS:
    base = os.path.join(OUT, m.name)
    if not getattr(m, "skip_bbmodel", False):
        save_bbmodel(m, base + ".bbmodel", m.name + ".png", open(TEX_PATH, "rb").read(), S)
    save_geo(m, base + ".geo.json", S)
    save_anim(m, base + ".animation.json")
    render_model(m, os.path.join(OUT, "preview_%s.png" % m.name),
                 scale=3 if m.name.startswith("arvan_giant_crown") else 4)
    print("   ✔", m.name, "|", sum(len(b.cubes) for b in m.bones), "مکعب |",
          len(m.bones), "بون |", sum(len(c) for c in m.anims.values()), "کانال انیمیشن")

# ------------------------------------------------------------- ریسورس‌پک
print("📦 ساخت ریسورس‌پک…")
RP = os.path.join(OUT, "ArvanLeaderboard_RP")
shutil.rmtree(RP, ignore_errors=True)
for sub in ("models/entity", "animations", "textures/entity", "entity",
            "render_controllers", "texts"):
    os.makedirs(os.path.join(RP, sub))

save_manifest(os.path.join(RP, "manifest.json"),
              "§l§bArvan§fGaming §6Leaderboards",
              "§eAnimated BedWars leaderboards & holograms §7- TOP, Podium, Projector, Bed",
              str(uuid.uuid5(NS, "header")), str(uuid.uuid5(NS, "module")))
save_render_controller("controller.render.arvan_leaderboard",
                       os.path.join(RP, "render_controllers/arvan_leaderboard.render_controllers.json"))

NAMES = {
    "arvan_leaderboard": "LEADERBOARD", "arvan_podium": "PODIUM",
    "arvan_hologram": "HOLOGRAM", "arvan_bedwars_bed": "BEDWARS BED",
    "arvan_hw_top": "🎃 SPOOKY TOP", "arvan_hw_podium": "🎃 PUMPKIN PODIUM",
    "arvan_hw_projector": "👻 HAUNTED HOLO", "arvan_hw_bed": "🦇 VAMPIRE BED",
    "arvan_hw_sign": "TRICK OR TREAT",
}
lang = []
TEX_SHARE = globals().get("TEX_SHARE", {})
for m in MODELS:
    shutil.copy(TEX_PATH, os.path.join(RP, "textures/entity/%s.png" % TEX_SHARE.get(m.name, m.name)))
    shutil.copy(os.path.join(OUT, m.name + ".geo.json"),
                os.path.join(RP, "models/entity/%s.geo.json" % m.name))
    shutil.copy(os.path.join(OUT, m.name + ".animation.json"),
                os.path.join(RP, "animations/%s.animation.json" % m.name))
    save_entity(m.name, m.ident, TEX_SHARE.get(m.name, m.name), "geometry." + m.name,
                "animation.%s.idle" % m.name, SCALE[m.name],
                "controller.render.arvan_leaderboard",
                os.path.join(RP, "entity/%s.entity.json" % m.name), EGG[m.name])
    pretty = CROWN_NAMES.get(m.name) or NAMES.get(m.name, m.name.replace("arvan_", "").upper())
    lang.append("entity.%s.name=§l§6%s" % (m.ident, pretty))

with open(os.path.join(RP, "texts/en_US.lang"), "w", encoding="utf-8") as f:
    f.write("\n".join(lang) + "\n")
with open(os.path.join(RP, "texts/languages.json"), "w", encoding="utf-8") as f:
    f.write('["en_US"]')

# آیکون پک
icon = Image.new("RGBA", (256, 256), (14, 20, 34, 255))
di = ImageDraw.Draw(icon)
glow(di, 20, 40, 216, 176, (60, 140, 230))
glow(di, 50, 60, 156, 60, (255, 200, 90))
center_text(di, 128, 78, "ARVAN", (255, 240, 150), (60, 20, 10), 5, (20, 10, 20))
center_text(di, 128, 112, "LEADERBOARD", (150, 230, 255), (10, 30, 60), 2, (10, 20, 40))
star_icon(di, 40, 150, 40, 40)
crown_icon(di, 108, 146, 44, 34)
bed_icon(di, 172, 152, 44, 30)
di.rectangle([0, 0, 255, 255], outline=(255, 200, 90), width=3)
icon.save(os.path.join(RP, "pack_icon.png"))
icon.save(os.path.join(OUT, "preview_pack_icon.png"))

zip_dir(RP, os.path.join(OUT, "ArvanLeaderboard.mcpack"))
zip_dir(RP, os.path.join(OUT, "ArvanLeaderboard_RP.zip"))
texture_sheet(os.path.join(OUT, "preview_atlas.png"), scale=3)

print("✅ تمام شد — پک آماده است:", os.path.join(OUT, "ArvanLeaderboard.mcpack"))
