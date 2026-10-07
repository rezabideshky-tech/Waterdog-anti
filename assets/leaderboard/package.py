#!/usr/bin/env python3
"""package.py — بسته‌بندی خروجی‌ها برای دانلود از گیت‌هاب
=================================================================
می‌سازد:
  • ArvanLeaderboards_plugin.zip   → پوشهٔ پلاگین (برای ریختن در plugins/)
  • ArvanLeaderboard_models.zip    → مدل‌ها/بافت/پیش‌نمایش + ابزار پایتون
  • ArvanLeaderboard.mcpack        ← ساختهٔ generate.py (ریسورس‌پک آمادهٔ بازی)
  • ArvanLeaderboard_RP.zip        ← همان پک به‌صورت پوشهٔ باز
"""
from __future__ import annotations

import os
import shutil
import zipfile

OUT = os.path.dirname(os.path.abspath(__file__))
shutil.rmtree(os.path.join(OUT, "__pycache__"), ignore_errors=True)


def zip_paths(dest: str, entries: list[tuple[str, str]]):
    with zipfile.ZipFile(dest, "w", zipfile.ZIP_DEFLATED) as z:
        for src, arc in entries:
            if os.path.isdir(src):
                for root, _, files in os.walk(src):
                    if "__pycache__" in root:
                        continue
                    for fn in files:
                        full = os.path.join(root, fn)
                        z.write(full, os.path.join(arc, os.path.relpath(full, src)))
            else:
                z.write(src, arc)
    print("📦", os.path.basename(dest), "→", round(os.path.getsize(dest) / 1024), "KB")


# ۱) پلاگین
zip_paths(os.path.join(OUT, "ArvanLeaderboards_plugin.zip"),
          [(os.path.join(OUT, "plugin/ArvanLeaderboards"), "ArvanLeaderboards")])

# ۲) مدل‌ها و ابزارها
model_files = [
    "README.md", "generate.py", "bb_lib.py", "hw_models.py", "crown_models.py",
    "package.py", "preview_crowns.py", "preview_hero.py",
    "preview_crowns.png", "preview_halloween.png", "preview_hero.png",
    "arvan_leaderboard_atlas.png", "preview_atlas.png", "preview_pack_icon.png",
    "docs/PATCH_bedwars_v7.md",
]
CLASSIC = ["arvan_leaderboard", "arvan_podium", "arvan_hologram", "arvan_bedwars_bed"]
HW = ["arvan_hw_top", "arvan_hw_podium", "arvan_hw_projector", "arvan_hw_bed", "arvan_hw_sign"]
CROWNS = ["arvan_giant_crown_kills", "arvan_giant_crown_wins", "arvan_giant_crown_beds_broken",
          "arvan_giant_crown_final_kills", "arvan_giant_crown_level", "arvan_giant_crown_coins"]

for stem in CLASSIC + HW:
    model_files += [stem + ".bbmodel", stem + ".geo.json", stem + ".animation.json",
                    "preview_%s.png" % stem]

# 👑 تاج‌ها: هندسه/انیمیشن هر شش نسخه + یک پروژهٔ .bbmodel پایه (بقیه همانند‌اند، فقط جواهر عوض است)
for stem in CROWNS:
    model_files += [stem + ".geo.json", stem + ".animation.json", "preview_%s.png" % stem]
model_files += ["arvan_giant_crown_kills.bbmodel", "arvan_giant_crown_kills.png"]

zip_paths(os.path.join(OUT, "ArvanLeaderboard_models.zip"),
          [(os.path.join(OUT, f), f) for f in model_files if os.path.exists(os.path.join(OUT, f))]
          + [(os.path.join(OUT, "ArvanLeaderboard.mcpack"), "ArvanLeaderboard.mcpack")])

print("✅ بسته‌بندی تمام شد.")
