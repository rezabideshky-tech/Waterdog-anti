#!/usr/bin/env python3
"""Generate a Blockbench Bedrock model for a BedWars honor leaderboard.

Outputs the editable .bbmodel, its texture, Bedrock geometry JSON, and a
portable ZIP. The model is a decorative hologram backdrop; live player data
is intentionally left to a PocketMine hologram/plugin layer.
"""
from __future__ import annotations

import base64
import json
import os
import uuid
import zipfile
from pathlib import Path

from PIL import Image, ImageDraw

OUT = Path(__file__).resolve().parent
MODEL = "bedwars_honor_leaderboard"
IDENT = "geometry.bedwars_honor_leaderboard"
NS = uuid.UUID("efc4917b-32b7-4de3-96f1-572fbf64c19f")
SIZE = 128

# Texture atlas rectangles. A single embedded texture makes the project easy
# to open in Blockbench and the PNG is also shipped beside it for editing.
UV = {
    "screen": (0, 0, 64, 64),
    "obsidian": (64, 0, 16, 16),
    "gold": (80, 0, 16, 16),
    "cyan": (96, 0, 16, 16),
    "emerald": (112, 0, 16, 16),
    "diamond": (64, 16, 16, 16),
    "red_wool": (80, 16, 16, 16),
    "blue_wool": (96, 16, 16, 16),
    "silver": (112, 16, 16, 16),
    "dark": (64, 32, 16, 16),
    "gold_side": (80, 32, 16, 16),
    "trophy": (96, 32, 16, 16),
    "glow": (112, 32, 16, 16),
    "wood": (64, 48, 16, 16),
    "bronze": (80, 48, 16, 16),
    "panel_side": (96, 48, 16, 16),
}

FONT = {
    "A": ["01110", "10001", "10001", "11111", "10001", "10001", "10001"],
    "B": ["11110", "10001", "10001", "11110", "10001", "10001", "11110"],
    "C": ["01111", "10000", "10000", "10000", "10000", "10000", "01111"],
    "D": ["11110", "10001", "10001", "10001", "10001", "10001", "11110"],
    "E": ["11111", "10000", "10000", "11110", "10000", "10000", "11111"],
    "F": ["11111", "10000", "10000", "11110", "10000", "10000", "10000"],
    "G": ["01111", "10000", "10000", "10111", "10001", "10001", "01111"],
    "H": ["10001", "10001", "10001", "11111", "10001", "10001", "10001"],
    "I": ["11111", "00100", "00100", "00100", "00100", "00100", "11111"],
    "J": ["00111", "00010", "00010", "00010", "10010", "10010", "01100"],
    "K": ["10001", "10010", "10100", "11000", "10100", "10010", "10001"],
    "L": ["10000", "10000", "10000", "10000", "10000", "10000", "11111"],
    "M": ["10001", "11011", "10101", "10101", "10001", "10001", "10001"],
    "N": ["10001", "11001", "10101", "10011", "10001", "10001", "10001"],
    "O": ["01110", "10001", "10001", "10001", "10001", "10001", "01110"],
    "P": ["11110", "10001", "10001", "11110", "10000", "10000", "10000"],
    "Q": ["01110", "10001", "10001", "10001", "10101", "10010", "01101"],
    "R": ["11110", "10001", "10001", "11110", "10100", "10010", "10001"],
    "S": ["01111", "10000", "10000", "01110", "00001", "00001", "11110"],
    "T": ["11111", "00100", "00100", "00100", "00100", "00100", "00100"],
    "U": ["10001", "10001", "10001", "10001", "10001", "10001", "01110"],
    "V": ["10001", "10001", "10001", "10001", "10001", "01010", "00100"],
    "W": ["10001", "10001", "10001", "10101", "10101", "11011", "10001"],
    "X": ["10001", "10001", "01010", "00100", "01010", "10001", "10001"],
    "Y": ["10001", "10001", "01010", "00100", "00100", "00100", "00100"],
    "Z": ["11111", "00001", "00010", "00100", "01000", "10000", "11111"],
    "0": ["01110", "10001", "10011", "10101", "11001", "10001", "01110"],
    "1": ["00100", "01100", "00100", "00100", "00100", "00100", "01110"],
    "2": ["01110", "10001", "00001", "00010", "00100", "01000", "11111"],
    "3": ["11110", "00001", "00001", "01110", "00001", "00001", "11110"],
    "4": ["00010", "00110", "01010", "10010", "11111", "00010", "00010"],
    "5": ["11111", "10000", "10000", "11110", "00001", "00001", "11110"],
    "6": ["01110", "10000", "10000", "11110", "10001", "10001", "01110"],
    "7": ["11111", "00001", "00010", "00100", "01000", "01000", "01000"],
    "8": ["01110", "10001", "10001", "01110", "10001", "10001", "01110"],
    "9": ["01110", "10001", "10001", "01111", "00001", "00001", "01110"],
    " ": ["00000"] * 7,
}


def stable_uuid(name: str) -> str:
    return str(uuid.uuid5(NS, name))


def draw_gradient(draw: ImageDraw.ImageDraw, box, top, bottom):
    x0, y0, x1, y1 = box
    h = max(1, y1 - y0 - 1)
    for y in range(y0, y1):
        t = (y - y0) / h
        color = tuple(round(top[i] + (bottom[i] - top[i]) * t) for i in range(3))
        draw.line((x0, y, x1 - 1, y), fill=(*color, 255))


def text_width(text: str, scale=1) -> int:
    return max(0, sum((4 if c == " " else 6) * scale for c in text) - scale)


def pixel_text(draw: ImageDraw.ImageDraw, text: str, x: int, y: int, color, scale=1, centered=False, shadow=None):
    text = text.upper()
    if centered:
        x -= text_width(text, scale) // 2
    for ch in text:
        glyph = FONT.get(ch, FONT[" "])
        for yy, row in enumerate(glyph):
            for xx, bit in enumerate(row):
                if bit == "1":
                    px, py = x + xx * scale, y + yy * scale
                    if shadow:
                        draw.rectangle((px + scale, py + scale, px + scale * 2 - 1, py + scale * 2 - 1), fill=shadow)
                    draw.rectangle((px, py, px + scale - 1, py + scale - 1), fill=color)
        x += (4 if ch == " " else 6) * scale


def draw_gem(draw: ImageDraw.ImageDraw, box, light, mid, dark):
    x0, y0, x1, y1 = box
    cx, cy = (x0 + x1) // 2, (y0 + y1) // 2
    draw.polygon([(cx, y0 + 2), (x1 - 2, cy), (cx, y1 - 2), (x0 + 2, cy)], fill=mid)
    draw.polygon([(cx, y0 + 2), (cx, cy), (x0 + 2, cy)], fill=light)
    draw.polygon([(x0 + 2, cy), (cx, cy), (cx, y1 - 2)], fill=dark)
    draw.line((cx, y0 + 3, x1 - 3, cy), fill=light)
    draw.rectangle((x0, y0, x1 - 1, y1 - 1), outline=dark)


def make_texture() -> Image.Image:
    atlas = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    d = ImageDraw.Draw(atlas)

    # Large display face: dark enchanted obsidian with a readable title and
    # ten empty data lanes for the separate hologram text to sit over.
    screen = (0, 0, 64, 64)
    draw_gradient(d, screen, (13, 10, 28), (11, 31, 50))
    for x in range(2, 64, 8):
        d.line((x, 0, x, 63), fill=(19, 24, 43, 255))
    for y in range(2, 64, 8):
        d.line((0, y, 63, y), fill=(19, 24, 43, 255))
    # Tiny stars/pixels, intentionally restrained so future hologram text stays legible.
    for x, y in [(4, 5), (59, 8), (8, 21), (56, 19), (3, 47), (60, 51), (8, 61), (54, 62)]:
        d.point((x, y), fill=(57, 202, 218, 255))
    d.rectangle((1, 1, 62, 62), outline=(32, 169, 190, 255))
    d.line((4, 2, 59, 2), fill=(105, 242, 255, 255))
    pixel_text(d, "BEDWARS", 32, 5, (129, 238, 255, 255), centered=True)
    pixel_text(d, "TOP HONOR", 32, 14, (255, 211, 91, 255), centered=True)
    d.line((5, 23, 58, 23), fill=(105, 70, 38, 255))
    d.line((13, 24, 50, 24), fill=(40, 210, 227, 255))
    rank_colors = [
        (255, 212, 92, 255), (224, 238, 246, 255), (213, 145, 83, 255),
        (59, 198, 217, 255), (59, 198, 217, 255), (59, 198, 217, 255),
        (59, 198, 217, 255), (59, 198, 217, 255), (59, 198, 217, 255),
        (59, 198, 217, 255),
    ]
    for i, y in enumerate(range(26, 63, 4)):
        # A tiny rank gem, a muted empty player-name line, and a stat marker.
        col = rank_colors[i]
        d.rectangle((4, y, 6, y + 2), fill=col)
        d.point((5, y), fill=(255, 255, 240, 255))
        d.line((10, y + 1, 43 + (i % 3) * 2, y + 1), fill=(83, 111, 139, 255))
        d.line((48, y + 1, 59, y + 1), fill=(30, 75, 91, 255))
        if i < 3:
            d.point((60, y + 1), fill=col)
        if i < 9:
            d.line((4, y + 3, 60, y + 3), fill=(28, 51, 67, 255))
    d.line((4, 63, 60, 63), fill=(105, 70, 38, 255))

    # Reusable block textures for the sculpted 3D frame and accents.
    palettes = {
        "obsidian": ((43, 35, 65), (18, 18, 35)),
        "gold": ((255, 232, 142), (176, 105, 23)),
        "cyan": ((123, 247, 255), (15, 108, 146)),
        "emerald": ((135, 255, 184), (5, 104, 61)),
        "diamond": ((186, 255, 255), (24, 123, 176)),
        "red_wool": ((255, 122, 111), (123, 20, 36)),
        "blue_wool": ((127, 199, 255), (24, 54, 145)),
        "silver": ((245, 252, 255), (100, 119, 145)),
        "dark": ((34, 25, 48), (9, 10, 22)),
        "gold_side": ((207, 146, 46), (94, 50, 16)),
        "glow": ((94, 250, 255), (11, 86, 132)),
        "wood": ((180, 120, 67), (87, 46, 28)),
        "bronze": ((236, 160, 88), (100, 45, 24)),
        "panel_side": ((55, 50, 81), (17, 19, 38)),
    }
    for name, (top, bottom) in palettes.items():
        x, y, w, h = UV[name]
        draw_gradient(d, (x, y, x + w, y + h), top, bottom)
        # Block-like edging and a sparse pixel grain.
        d.line((x, y, x + w - 1, y), fill=tuple(min(255, v + 30) for v in top) + (255,))
        d.line((x, y, x, y + h - 1), fill=tuple(min(255, v + 12) for v in top) + (255,))
        d.rectangle((x, y, x + w - 1, y + h - 1), outline=tuple(max(0, v - 25) for v in bottom) + (255,))
        for px, py in [(3, 4), (9, 3), (12, 8), (5, 12), (10, 14)]:
            if (px + py) % 3 == 0:
                continue
            # Deterministic highlights and flecks in the block's own palette.
            factor = 1.22 if (px + py) % 2 else 0.68
            c = tuple(max(0, min(255, int(v * factor))) for v in top) + (255,)
            d.point((x + px, y + py), fill=c)

    # Gem faces get a readable diamond-cut highlight instead of noisy grain.
    draw_gem(d, (UV["emerald"][0] + 1, UV["emerald"][1] + 1, UV["emerald"][0] + 15, UV["emerald"][1] + 15),
             (181, 255, 205, 255), (30, 207, 112, 255), (3, 82, 53, 255))
    draw_gem(d, (UV["diamond"][0] + 1, UV["diamond"][1] + 1, UV["diamond"][0] + 15, UV["diamond"][1] + 15),
             (220, 255, 255, 255), (76, 210, 235, 255), (18, 93, 148, 255))

    # Trophy badge texture: a compact pixel-art cup with blocky handles.
    tx, ty, _, _ = UV["trophy"]
    d.rectangle((tx, ty, tx + 15, ty + 15), fill=(24, 19, 38, 255), outline=(111, 67, 22, 255))
    gold_hi, gold_mid, gold_lo = (255, 230, 128, 255), (239, 172, 44, 255), (156, 85, 23, 255)
    d.rectangle((tx + 5, ty + 3, tx + 10, ty + 7), fill=gold_mid)
    d.line((tx + 5, ty + 3, tx + 10, ty + 3), fill=gold_hi)
    d.line((tx + 6, ty + 8, tx + 9, ty + 8), fill=gold_lo)
    d.rectangle((tx + 3, ty + 4, tx + 4, ty + 6), fill=gold_mid)
    d.rectangle((tx + 11, ty + 4, tx + 12, ty + 6), fill=gold_mid)
    d.line((tx + 6, ty + 8, tx + 6, ty + 10), fill=gold_mid)
    d.line((tx + 9, ty + 8, tx + 9, ty + 10), fill=gold_mid)
    d.rectangle((tx + 5, ty + 10, tx + 10, ty + 11), fill=gold_mid)
    d.line((tx + 4, ty + 12, tx + 11, ty + 12), fill=gold_hi)
    d.point((tx + 7, ty + 2), fill=(255, 255, 226, 255))

    # Fill the rest of the small atlas with transparent pixels, not accidental
    # atlas bleed, and preserve crisp nearest-neighbour pixel art.
    return atlas


# Each cube stores a per-face material name. North is the visible front in the
# Bedrock/Blockbench coordinate convention used by the other models here.
CUBES = []


def cube(name, f, t, front="obsidian", side=None, top=None, bottom=None, back=None):
    side = side or front
    top = top or side
    bottom = bottom or side
    back = back or side
    CUBES.append({
        "name": name,
        "from": [float(v) for v in f],
        "to": [float(v) for v in t],
        "faces": {
            "north": front, "south": back,
            "east": side, "west": side,
            "up": top, "down": bottom,
        },
    })


def build_cubes():
    CUBES.clear()
    # Floating stand and stepped foundation.
    cube("pedestal_base", [-11, 0, -7], [11, 3, 7], "obsidian", "obsidian", "gold_side", "dark")
    cube("pedestal_trim", [-9, 3, -5], [9, 4, 5], "gold", "gold_side", "gold")
    cube("pedestal_core", [-3, 4, -2], [3, 9, 3], "obsidian", "panel_side", "gold_side")
    cube("pedestal_glow", [-2, 5, -2.2], [2, 6, -1.8], "cyan", "cyan")

    # Thick backing gives the hologram sign a proper silhouette from the side.
    cube("display_backplate", [-18, 7, -1.5], [18, 41, 3], "obsidian", "panel_side", "gold_side", "dark")
    # Screen is a very thin, front-facing panel; its ten muted lanes are clear
    # placeholders for live PocketMine hologram rows.
    cube("hologram_screen", [-14, 12, -3.25], [14, 36, -2.95], "screen", "cyan", "cyan", "dark", "obsidian")

    # Raised gold frame (all four pieces are separate Blockbench cubes).
    cube("frame_left", [-18, 7, -4.2], [-14, 37, -1.2], "gold", "gold_side", "gold")
    cube("frame_right", [14, 7, -4.2], [18, 37, -1.2], "gold", "gold_side", "gold")
    cube("frame_bottom", [-18, 7, -4.2], [18, 12, -1.2], "gold", "gold_side", "gold")
    cube("frame_top", [-18, 36, -4.2], [18, 41, -1.2], "gold", "gold_side", "gold")

    # Thin neon inner rails make the frame read as a BedWars hologram display.
    cube("neon_left", [-14, 12, -4.35], [-13.5, 36, -4.0], "cyan", "glow", "cyan")
    cube("neon_right", [13.5, 12, -4.35], [14, 36, -4.0], "cyan", "glow", "cyan")
    cube("neon_header", [-13.5, 35.5, -4.35], [13.5, 36, -4.0], "cyan", "glow", "cyan")

    # Crown/trophy crest floating above the display.
    cube("crest_back", [-7, 39, -2.4], [7, 48, 2.2], "obsidian", "gold_side", "gold")
    cube("crest_gold_top", [-8, 46.5, -4.4], [8, 48, -2.2], "gold", "gold_side", "gold")
    cube("crest_gold_bottom", [-8, 39, -4.4], [8, 40.5, -2.2], "gold", "gold_side", "gold")
    cube("crest_side_left", [-8, 40.5, -4.4], [-6.5, 46.5, -2.2], "gold", "gold_side", "gold")
    cube("crest_side_right", [6.5, 40.5, -4.4], [8, 46.5, -2.2], "gold", "gold_side", "gold")
    cube("trophy_emblem", [-6.5, 40.5, -4.7], [6.5, 46.5, -4.4], "trophy", "gold_side", "gold", "dark")
    # Gem accents on the crest are separate, slightly proud cubes.
    cube("crest_emerald", [-11, 43, -3.7], [-8, 46, -0.7], "emerald", "emerald", "emerald")
    cube("crest_diamond", [8, 43, -3.7], [11, 46, -0.7], "diamond", "diamond", "diamond")

    # Team-colour cues and small jewel blocks at the board's corners.
    cube("red_team_marker", [-20, 32, -1.8], [-18, 35, 1.2], "red_wool", "red_wool", "red_wool")
    cube("blue_team_marker", [18, 32, -1.8], [20, 35, 1.2], "blue_wool", "blue_wool", "blue_wool")
    cube("left_emerald", [-20, 11, -4.0], [-17, 14, -1.0], "emerald", "emerald", "emerald")
    cube("right_diamond", [17, 11, -4.0], [20, 14, -1.0], "diamond", "diamond", "diamond")

    # A small cyan strip over the pedestal and lower frame tie the composition together.
    cube("stand_light", [-2, 7, -2.1], [2, 8, -1.8], "cyan", "cyan", "cyan")
    cube("base_light_left", [-8, 2.5, -7.2], [-5, 3.0, -7.0], "cyan", "cyan", "cyan")
    cube("base_light_right", [5, 2.5, -7.2], [8, 3.0, -7.0], "cyan", "cyan", "cyan")


def uv_face(material: str):
    x, y, w, h = UV[material]
    return {"uv": [x, y, x + w, y + h], "texture": 0}


def build_blockbench(atlas: Image.Image):
    elements, child_uuids = [], []
    for item in CUBES:
        uid = stable_uuid("element:" + item["name"])
        child_uuids.append(uid)
        elements.append({
            "name": item["name"], "type": "cube", "uuid": uid,
            "box_uv": False, "from": item["from"], "to": item["to"],
            "origin": [0, 0, 0],
            "faces": {face: uv_face(mat) for face, mat in item["faces"].items()},
        })
    root_uuid = stable_uuid("group:root")
    image_bytes = __import__("io").BytesIO()
    atlas.save(image_bytes, format="PNG", optimize=True)
    encoded = base64.b64encode(image_bytes.getvalue()).decode("ascii")
    bbmodel = {
        "meta": {"format_version": "4.10", "model_format": "bedrock", "box_uv": False},
        "name": MODEL,
        "model_identifier": MODEL,
        "visible_box": [5.0, 5.5, 2.5],
        "resolution": {"width": SIZE, "height": SIZE},
        "elements": elements,
        "outliner": [{
            "name": "BedWars Honor Leaderboard", "origin": [0, 0, 0], "uuid": root_uuid,
            "export": True, "isOpen": True, "visibility": True, "children": child_uuids,
        }],
        "textures": [{
            "path": "", "name": MODEL + ".png", "id": "0",
            "width": SIZE, "height": SIZE, "uv_width": SIZE, "uv_height": SIZE,
            "particle": False, "use_as_default": True, "layers_enabled": True,
            "sync_to_project": True, "render_mode": "default", "visible": True,
            "internal": True, "saved": True, "uuid": stable_uuid("texture:atlas"),
            "source": "data:image/png;base64," + encoded,
        }],
        "animations": [],
    }
    (OUT / (MODEL + ".bbmodel")).write_text(json.dumps(bbmodel, indent=2) + "\n", encoding="utf-8")


def build_geometry():
    bedrock_cubes = []
    for item in CUBES:
        f, t = item["from"], item["to"]
        uv = {}
        for face, material in item["faces"].items():
            x, y, w, h = UV[material]
            uv[face] = {"uv": [x, y], "uv_size": [w, h]}
        bedrock_cubes.append({
            "origin": [-t[0], f[1], f[2]],
            "size": [round(t[i] - f[i], 3) for i in range(3)],
            "uv": uv,
        })
    geometry = {
        "format_version": "1.12.0",
        "minecraft:geometry": [{
            "description": {
                "identifier": IDENT, "texture_width": SIZE, "texture_height": SIZE,
                "visible_bounds_width": 4.8, "visible_bounds_height": 5.7,
                "visible_bounds_offset": [0, 2.8, 0],
            },
            "bones": [{"name": "root", "pivot": [0, 0, 0], "cubes": bedrock_cubes}],
        }],
    }
    (OUT / (MODEL + ".geo.json")).write_text(json.dumps(geometry, indent=2) + "\n", encoding="utf-8")


def build_bundle():
    archive = OUT / "BedWars_Honor_Leaderboard_Blockbench.zip"
    files = [
        OUT / (MODEL + ".bbmodel"),
        OUT / (MODEL + ".png"),
        OUT / (MODEL + ".geo.json"),
        OUT / "preview_bedwars_honor_leaderboard.png",
        OUT / "README.txt",
    ]
    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in files:
            zf.write(path, arcname=path.name)


def make_preview():
    """Render the Blockbench project using the repository's software renderer."""
    import subprocess
    import sys
    import tempfile

    renderer_path = OUT.parent / "lobby" / "render_preview.py"
    preview_path = OUT / "preview_bedwars_honor_leaderboard.png"
    with tempfile.NamedTemporaryFile(suffix=".png", dir=OUT, delete=False) as temp:
        raw_path = Path(temp.name)
    try:
        subprocess.run([
            sys.executable, str(renderer_path), str(OUT / (MODEL + ".bbmodel")),
            str(raw_path), "-25", "20",
        ], check=True)
        with Image.open(raw_path) as image:
            image.convert("RGB").save(preview_path, optimize=True)
    finally:
        raw_path.unlink(missing_ok=True)


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    atlas = make_texture()
    atlas.save(OUT / (MODEL + ".png"), optimize=True)
    build_cubes()
    build_blockbench(atlas)
    build_geometry()
    make_preview()
    build_bundle()


if __name__ == "__main__":
    main()
