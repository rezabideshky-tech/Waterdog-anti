#!/usr/bin/env python3
"""Generate a compact, roomy Lucky-Cube style BedWars leaderboard set.

The seven models reuse the existing Blockbench/Bedrock export helpers but use
an entirely different silhouette: a floating Lucky Block above a clean holo
screen on a compact kiosk base (no portal arch or floating island).
"""
from __future__ import annotations

import importlib.util
import sys
import zipfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent
OUTPUT = ROOT / "luckycube"
LUCKY_UV = (96, 64, 16, 16)

# Avoid a .pyc beside the shared asset generator when importing it.
sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location("leaderboard_shared_generator", ROOT / "generate_leaderboards.py")
shared = importlib.util.module_from_spec(spec)
spec.loader.exec_module(shared)
shared.OUT = OUTPUT

_base_make_texture = shared.make_texture


def draw_lucky_face(draw, box):
    x, y, w, h = box
    # Iconic yellow lucky block with warm voxel bevels and a bold question mark.
    for py in range(h):
        t = py / max(1, h - 1)
        col = (round(255 - 39 * t), round(222 - 90 * t), round(74 - 34 * t), 255)
        draw.line((x, y + py, x + w - 1, y + py), fill=col)
    draw.rectangle((x, y, x + w - 1, y + h - 1), outline=(114, 58, 22, 255))
    draw.rectangle((x + 1, y + 1, x + w - 2, y + h - 2), outline=(255, 243, 157, 255))
    # Pixelated question-mark glyph, with a hard shadow for Bedrock readability.
    glyph = ["01110", "10001", "00001", "00010", "00100", "00100", "00000", "00100"]
    gx0, gy0 = x + 5, y + 4
    for gy, row in enumerate(glyph):
        for gx, bit in enumerate(row):
            if bit == "1":
                draw.point((gx0 + gx + 1, gy0 + gy + 1), fill=(107, 49, 18, 255))
                draw.point((gx0 + gx, gy0 + gy), fill=(49, 29, 39, 255))
    # Small corner glints sell the cubic, collectible item feel.
    for px, py in [(3, 3), (12, 3), (3, 12), (12, 12)]:
        draw.point((x + px, y + py), fill=(255, 255, 208, 255))


def make_lucky_texture(variant):
    # The shared generator paints the normal atlas first; reserve this extra
    # tile only afterwards so its regular material painter does not overwrite it.
    lucky = shared.UV.pop("lucky_block", None)
    atlas = _base_make_texture(variant)
    shared.UV["lucky_block"] = lucky or LUCKY_UV
    d = ImageDraw.Draw(atlas)
    draw_lucky_face(d, shared.UV["lucky_block"])
    return atlas


def make_lucky_cubes():
    cubes = []
    add = shared.add_cube

    # A compact stepped blackstone kiosk, not an island or portal frame.
    add(cubes, "kiosk_foot", [-12, 0, -7], [12, 3, 7], "dark", "stone", "gold_side", "dark")
    add(cubes, "gold_base_trim", [-10, 3, -5], [10, 4, 5], "gold_side", "gold_side", "gold")
    add(cubes, "center_pedestal", [-4, 4, -3], [4, 7, 3], "obsidian", "dark", "gold_side")
    add(cubes, "support_column", [-2.5, 6, -1], [2.5, 9, 2.5], "obsidian", "gold_side", "gold_side")
    add(cubes, "team_wool_red", [-9, 3, -6.5], [-6, 5.5, -3.5], "red_wool", "red_wool", "red_wool")
    add(cubes, "team_wool_blue", [6, 3, -6.5], [9, 5.5, -3.5], "blue_wool", "blue_wool", "blue_wool")

    # Roomy board: a broad dark-glass panel with ten unobstructed holo rows.
    add(cubes, "kiosk_back", [-18, 8, -0.5], [18, 39, 3.5], "dark", "obsidian", "obsidian", "dark")
    add(cubes, "frame_left", [-18, 8, -3.15], [-14, 36, -1], "obsidian", "gold_side", "obsidian")
    add(cubes, "frame_right", [14, 8, -3.15], [18, 36, -1], "obsidian", "gold_side", "obsidian")
    add(cubes, "frame_bottom", [-18, 8, -3.15], [18, 12, -1], "obsidian", "gold_side", "obsidian")
    add(cubes, "frame_header", [-18, 34, -3.15], [18, 39, -1], "obsidian", "gold_side", "obsidian")
    add(cubes, "screen", [-14, 12, -3.42], [14, 34, -3.16], "screen", "cyan", "cyan", "dark", "obsidian")

    # Small metal corner caps and two category-colour rails keep it premium,
    # while leaving the center completely open for the live leaderboard text.
    add(cubes, "corner_gold_left", [-18, 33, -3.8], [-14, 34, -3.5], "gold", "gold_side", "gold")
    add(cubes, "corner_gold_right", [14, 33, -3.8], [18, 34, -3.5], "gold", "gold_side", "gold")
    add(cubes, "corner_base_left", [-18, 9, -3.8], [-14, 10, -3.5], "gold", "gold_side", "gold")
    add(cubes, "corner_base_right", [14, 9, -3.8], [18, 10, -3.5], "gold", "gold_side", "gold")
    add(cubes, "light_left", [-14.4, 12, -3.8], [-14.05, 34, -3.55], "cyan", "glow", "cyan")
    add(cubes, "light_right", [14.05, 12, -3.8], [14.4, 34, -3.55], "cyan", "glow", "cyan")
    add(cubes, "light_header", [-14, 34.05, -3.8], [14, 34.4, -3.55], "accent", "accent_side", "accent")

    # The floating Lucky Block is the visual hook. It is animated as one module.
    badge_bone = "floating_badge"
    add(cubes, "lucky_block", [-7, 38, -6.5], [7, 52, 7.5],
        "lucky_block", "gold_side", "gold", "gold_side", bone=badge_bone)
    # A small stat badge is mounted below the cube, not over the data rows.
    add(cubes, "category_token", [-3.5, 35.5, -4.8], [3.5, 38, -4.4],
        "badge", "accent_side", "accent", "dark", bone=badge_bone)
    add(cubes, "lucky_shard_left", [-10, 42, -3], [-7.5, 44.5, -0.5],
        "accent", "accent_side", "accent", bone=badge_bone)
    add(cubes, "lucky_shard_right", [7.5, 42, -3], [10, 44.5, -0.5],
        "accent", "accent_side", "accent", bone=badge_bone)

    # Small BedWars item cubes flank the kiosk without crowding its silhouette.
    add(cubes, "side_emerald", [-21, 13, -2.8], [-18.5, 15.5, -0.3],
        "emerald", "emerald", "emerald", bone="floating_gems")
    add(cubes, "side_diamond", [18.5, 13, -2.8], [21, 15.5, -0.3],
        "diamond", "diamond", "diamond", bone="floating_gems")
    add(cubes, "floating_coin_left", [-13, 37, -2], [-11, 39, 0],
        "gold", "gold_side", "gold", bone="floating_gems")
    add(cubes, "floating_coin_right", [11, 37, -2], [13, 39, 0],
        "gold", "gold_side", "gold", bone="floating_gems")
    return cubes


shared.make_texture = make_lucky_texture
shared.make_cubes = make_lucky_cubes

LUCKY_VARIANTS = []
for source in shared.VARIANTS:
    item = dict(source)
    item["key"] = "luckycube_" + source["key"]
    LUCKY_VARIANTS.append(item)


def make_overview(models):
    width, height = 1880, 1280
    canvas = Image.new("RGB", (width, height), (12, 13, 24))
    d = ImageDraw.Draw(canvas)
    for x in range(0, width, 64):
        d.line((x, 0, x, height), fill=(18, 21, 35))
    for y in range(0, height, 64):
        d.line((0, y, width, y), fill=(18, 21, 35))
    font_path = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
    try:
        title_font = ImageFont.truetype(font_path, 38)
        label_font = ImageFont.truetype(font_path, 19)
        sub_font = ImageFont.truetype(font_path, 15)
    except OSError:
        title_font = label_font = sub_font = ImageFont.load_default()
    d.text((width // 2, 28), "LUCKY CUBE  •  BEDWARS TOP 10", font=title_font,
           fill=(255, 232, 156), anchor="ma")
    d.text((width // 2, 82), "COMPACT KIOSK  /  CLEAR HOLOGRAM PANEL  /  IDLE ANIMATION",
           font=sub_font, fill=(126, 229, 245), anchor="ma")
    card_w, card_h, gap_x, gap_y = 430, 535, 18, 24
    left, top_y = (width - (4 * card_w + 3 * gap_x)) // 2, 130
    positions = [(left + i * (card_w + gap_x), top_y) for i in range(4)]
    second_left = (width - (3 * card_w + 2 * gap_x)) // 2
    positions += [(second_left + i * (card_w + gap_x), top_y + card_h + gap_y) for i in range(3)]
    for model, (x, y) in zip(models, positions):
        accent = tuple(model["accent"])
        d.rounded_rectangle((x, y, x + card_w, y + card_h), radius=18,
                            fill=(22, 23, 37), outline=accent, width=2)
        d.rounded_rectangle((x + 16, y + 15, x + card_w - 16, y + 62), radius=10,
                            fill=(15, 18, 31), outline=(55, 61, 78), width=1)
        d.text((x + card_w // 2, y + 38), model["title"], font=label_font,
               fill=(242, 247, 255), anchor="mm")
        with Image.open(model["preview"]) as image:
            image = image.convert("RGB").resize((card_w - 24, card_w - 24), Image.Resampling.LANCZOS)
            canvas.paste(image, (x + 12, y + 68))
        d.rounded_rectangle((x + 18, y + card_w + 50, x + card_w - 18, y + card_h - 14), radius=10,
                            fill=(15, 18, 30), outline=(50, 57, 76), width=1)
        d.text((x + card_w // 2, y + card_h - 31), "TOP 10  •  LIVE HOLOGRAM", font=sub_font,
               fill=accent, anchor="mm")
    path = ROOT / "preview_bedwars_luckycube_set.png"
    canvas.save(path, optimize=True)
    return path


def write_readme():
    (OUTPUT / "README.txt").write_text("""BEDWARS LUCKY-CUBE LEADERBOARD SET — Blockbench Bedrock models

Seven editable models: Top Kills, Top Wins, Top Win Streak, Top Beds, Top Coins,
Top Level and Top Final Kills. This is an alternative visual style, separate
from the portal/island set: a compact blackstone leaderboard kiosk with a
floating, animated Lucky Block, a spacious ten-row hologram screen and small
BedWars gem accents. The category title, accent colour and token vary by model.

Each .bbmodel embeds its texture and includes a subtle looping idle animation
for the Lucky Block and floating items. PNG texture, Bedrock geometry JSON,
.animation.json and rendered previews are included. Open a .bbmodel directly
in Blockbench. Live player names and scores are supplied separately by a
PocketMine hologram/plugin.
""", encoding="utf-8")


def make_zip(models, overview):
    archive = ROOT / "BedWars_LuckyCube_Leaderboard_Set.zip"
    files = [OUTPUT / "README.txt", overview]
    for model in models:
        key = model["key"]
        files += [
            OUTPUT / (key + ".bbmodel"), OUTPUT / (key + ".png"),
            OUTPUT / (key + ".geo.json"), OUTPUT / (key + ".animation.json"),
            model["preview"],
        ]
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in files:
            zf.write(path, arcname=path.name)


def main():
    OUTPUT.mkdir(parents=True, exist_ok=True)
    models = [shared.make_model(variant) for variant in LUCKY_VARIANTS]
    overview = make_overview(models)
    write_readme()
    make_zip(models, overview)
    print(f"Built {len(models)} Lucky-Cube Blockbench models: {ROOT / 'BedWars_LuckyCube_Leaderboard_Set.zip'}")


if __name__ == "__main__":
    main()
