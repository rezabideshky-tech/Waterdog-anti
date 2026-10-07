#!/usr/bin/env python3
"""mock_holo.py — ماکت طرح‌های هولوگرام لیدربورد (برای انتخاب کاربر)
====================================================================
چهار تخته را کنار هم می‌کشد:
  ۱) وضعیت فعلی پلاگین BedWarsCore (رنگ‌ها قوس‌وقزح می‌چرخند، رتبه‌ها یک‌شکل)
  ۲) طرح A — «Medal Board»: سرتیتر طلایی، رتبهٔ ۱/۲/۳ با تاج و مدال، عدد سبز، نوار پیشرفت، فوتر
  ۳) طرح B — «Ranked RP»: از دادهٔ bw_profile (rp / peak_tier) — ردهٔ برنز تا گرندمستر
  ۴) طرح C — «هالووین»: تختهٔ Trick or Treat روی پک v1.1
خروجی: preview_holo_design.png
"""
from __future__ import annotations

from PIL import Image, ImageDraw

from bb_lib import center_text, crown_icon, glow, grad, medal, outline, pixel_text, rect, star_icon, text_w

BG = (12, 11, 20)
PANEL = (20, 18, 32)
GOLD = (252, 210, 74)
CYAN = (91, 224, 255)
GREEN = (76, 224, 122)
WHITE = (242, 242, 245)
GRAY = (138, 138, 150)
RED = (255, 90, 90)
PURPLE = (176, 108, 255)
ORANGE = (240, 138, 30)
SILVER = (206, 210, 220)
BRONZE = (196, 124, 72)

W, H = 290, 430
PAD = 10


def board(d, x, y, title, subtitle, accent, rows, footer, theme="plain"):
    """یک تختهٔ هولوگرام می‌کشد. rows = [(rank_text, icon, name, value, color)]"""
    rect(d, x, y, W, H, PANEL)
    outline(d, x, y, W, H, accent)
    outline(d, x + 2, y + 2, W - 4, H - 4, (accent[0] // 3, accent[1] // 3, accent[2] // 3))
    # سرتیتر
    grad(d, x + 3, y + 3, W - 6, 34, accent, (accent[0] // 2, accent[1] // 2, accent[2] // 2))
    rect(d, x + 3, y + 3, W - 6, 1, (255, 255, 255, 200))
    center_text(d, x + W / 2, y + 9, title, (26, 18, 8), None, 2)
    rect(d, x + 3, y + 37, W - 6, 14, (34, 30, 50))
    center_text(d, x + W / 2, y + 41, subtitle, accent, None, 1)
    # ردیف‌ها
    ry = y + 58
    for i, (rank_text, icon, name, value, color) in enumerate(rows):
        hl = i == 0
        if hl:
            rect(d, x + 4, ry - 3, W - 8, 30, (70, 56, 16))
            outline(d, x + 4, ry - 3, W - 8, 30, GOLD)
        elif i % 2 == 0:
            rect(d, x + 4, ry - 3, W - 8, 30, (28, 25, 42))
        # نشان رتبه
        if icon == "crown":
            crown_icon(d, x + 8, ry + 1, 18, 16)
        elif icon == "medal2":
            medal(d, x + 9, ry + 2, 15, 15, SILVER, (120, 124, 134), (245, 248, 252))
        elif icon == "medal3":
            medal(d, x + 9, ry + 2, 15, 15, BRONZE, (120, 70, 36), (235, 175, 130))
        elif icon == "pumpkin":
            rect(d, x + 9, ry + 2, 15, 14, ORANGE)
            rect(d, x + 13, ry + 12, 6, 3, (150, 70, 20))
            rect(d, x + 11, ry + 6, 3, 4, (30, 20, 10))
            rect(d, x + 19, ry + 6, 3, 4, (30, 20, 10))
            rect(d, x + 12, ry + 12, 9, 2, (30, 20, 10))
        elif icon == "skull":
            rect(d, x + 9, ry + 1, 15, 12, (232, 232, 224))
            rect(d, x + 12, ry + 4, 4, 4, (28, 26, 36))
            rect(d, x + 18, ry + 4, 4, 4, (28, 26, 36))
            rect(d, x + 12, ry + 13, 9, 3, (232, 232, 224))
        else:
            pixel_text(d, x + 12, ry + 5, rank_text, color, (0, 0, 0), 1)
        pixel_text(d, x + 32, ry + 5, name, WHITE, (0, 0, 0), 1)
        # عدد: همیشه سبز (و در ردیف اول طلایی)
        val_col = GOLD if hl else GREEN
        pixel_text(d, x + W - 12 - text_w(value, 1), ry + 5, value, val_col, (0, 0, 0), 1)
        if hl:
            star_icon(d, x + W - 22, ry + 3, 9, 9, GOLD)
        ry += 34
    # فوتر
    rect(d, x + 4, y + H - 46, W - 8, 42, (26, 23, 38))
    outline(d, x + 4, y + H - 46, W - 8, 42, (60, 56, 82))
    center_text(d, x + W / 2, y + H - 41, footer[0], GRAY, None, 1)
    center_text(d, x + W / 2, y + H - 29, footer[1], accent, None, 1)
    center_text(d, x + W / 2, y + H - 17, footer[2], (110, 106, 130), None, 1)


def main():
    img = Image.new("RGB", (4 * W + 60, H + 100), BG)
    d = ImageDraw.Draw(img, "RGBA")
    center_text(d, img.width / 2, 14, "ARVANGAMING  BEDWARS  HOLOGRAM  DESIGNS", GOLD, (0, 0, 0), 2)
    center_text(d, img.width / 2, 40, "CHOOSE  A  BOARD  -  EACH  ONE  PLUGS  INTO  BEDWARSCORE  V2", (150, 146, 170), None, 1)
    center_text(d, img.width / 2, 56, "PREVIEW ONLY  -  IN GAME RENDERED AS FLOATING TEXT + 3D MODEL BELOW", (110, 106, 130), None, 1)

    x = 20

    # ۱) وضعیت فعلی پلاگین
    rainbow = [RED, GREEN, GOLD, ORANGE, PURPLE, CYAN, (60, 200, 60), GOLD, (200, 60, 200), WHITE]
    rows = []
    for i, (n, v) in enumerate((("Ali", "241"), ("Sara", "198"), ("Reza", "155"), ("Mahan", "121"), ("Nika", "98"))):
        rows.append(("%d-" % (i + 1), "none", n, v, rainbow[i]))
    board(d, x, 76, "KILLS LEADERBOARD", "Live Rankings", RED, rows,
          ("1- Ali - 241", "rainbow per row", "no medals / no footer"), theme="current")
    x += W + 10

    # ۲) طرح A — Medal Board
    rows = [
        ("1", "crown", "Ali", "241", GOLD),
        ("2", "medal2", "Sara", "198", SILVER),
        ("3", "medal3", "Reza", "155", BRONZE),
        ("4", "none", "Mahan", "121", GRAY),
        ("5", "none", "Nika", "98", GRAY),
    ]
    board(d, x, 76, "BEDWARS TOP KILLS", "SEASON 1 - LIVE", GOLD, rows,
          ("updated 5s ago", "your rank: /rank", "42 players tracked"))
    x += W + 10

    # ۳) طرح B — Ranked RP
    rows = [
        ("1", "crown", "Ali", "GRANDMASTER", GOLD),
        ("2", "medal2", "Sara", "MASTER 2", SILVER),
        ("3", "medal3", "Reza", "DIAMOND 1", BRONZE),
        ("4", "none", "Mahan", "PLATINUM 3", GRAY),
        ("5", "none", "Nika", "GOLD 2", GRAY),
    ]
    board(d, x, 76, "RANKED TOP RP", "SEASON 1 - 12 DAYS LEFT", CYAN, rows,
          ("rp: win 25 / loss -12", "mvp bonus +5", "reset in 12d 04h"))
    x += W + 10

    # ۴) طرح C — هالووین
    rows = [
        ("1", "pumpkin", "Ali", "32 CANDY", GOLD),
        ("2", "medal2", "Sara", "27 CANDY", SILVER),
        ("3", "medal3", "Reza", "21 CANDY", BRONZE),
        ("4", "skull", "Mahan", "14 SOULS", GRAY),
        ("5", "skull", "Nika", "9 SOULS", GRAY),
    ]
    board(d, x, 76, "TRICK OR TREAT", "SPOOKY SEASON - LIVE", ORANGE, rows,
          ("souls released: 17", "pumpkins smashed: 91", "ends in 3 days"))
    glow(d, 0, 0, 0, 0)

    img.save("preview_holo_design.png")
    print("ok preview_holo_design.png", img.size)


if __name__ == "__main__":
    main()
