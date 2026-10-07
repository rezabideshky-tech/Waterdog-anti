#!/usr/bin/env python3
"""preview_hero.py — یک تصویر جمع‌بندی از هر چهار مدل (نمای داخل بازی) می‌سازد."""
from PIL import Image, ImageDraw
import os
OUT = os.path.dirname(os.path.abspath(__file__))
names = [("arvan_leaderboard", "LEADERBOARD  §6TOP 10"),
         ("arvan_podium", "PODIUM  §6TOP 3"),
         ("arvan_hologram", "HOLOGRAM  §6PROJECTOR"),
         ("arvan_bedwars_bed", "BEDWARS BED  §6SWORD")]
PW, PH = 470, 560
canvas = Image.new("RGB", (PW * 2, PH * 2), (12, 18, 30))
for i, (stem, label) in enumerate(names):
    img = Image.open(os.path.join(OUT, "preview_%s.png" % stem))
    panel = img.crop((PW, 0, PW * 2, PH))          # پنل دوم = نمای داخل بازی
    canvas.paste(panel, ((i % 2) * PW, (i // 2) * PH))
d = ImageDraw.Draw(canvas)
for i in range(1, 2):
    d.line([(PW * i, 0), (PW * i, PH * 2)], fill=(255, 200, 90), width=2)
d.line([(0, PH), (PW * 2, PH)], fill=(255, 200, 90), width=2)
canvas.save(os.path.join(OUT, "preview_hero.png"))
print("ok preview_hero.png", canvas.size)
