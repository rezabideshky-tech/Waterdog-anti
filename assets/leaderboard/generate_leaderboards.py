#!/usr/bin/env python3
"""Build a cohesive animated Blockbench set for the seven BedWars leaderboards.

The shared model is a dark obsidian/glass hologram stand with gold detailing.
Each category changes only its badge, small accent light and title treatment.
Requires Pillow, like the other art generators in this repository.
"""
from __future__ import annotations

import base64
import io
import json
import math
import subprocess
import sys
import tempfile
import uuid
import zipfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "collection"
MODEL_RES = (128, 128)
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
    "badge": (96, 32, 16, 16),
    "glow": (112, 32, 16, 16),
    "stone": (64, 48, 16, 16),
    "bronze": (80, 48, 16, 16),
    "accent": (96, 48, 16, 16),
    "accent_side": (112, 48, 16, 16),
    "portal": (0, 64, 16, 16),
    "grass_top": (16, 64, 16, 16),
    "dirt": (32, 64, 16, 16),
    "crystal": (48, 64, 16, 16),
    "rune": (64, 64, 16, 16),
    "grass_side": (80, 64, 16, 16),
}
NAMESPACE = uuid.UUID("92d21e37-bb44-4bf1-9a3e-4d7d70c76f59")

# Tiny pixel font: category labels stay legible while leaving room for data.
FONT3 = {
    "A": ["010", "101", "111", "101", "101"], "B": ["110", "101", "110", "101", "110"],
    "C": ["011", "100", "100", "100", "011"], "D": ["110", "101", "101", "101", "110"],
    "E": ["111", "100", "110", "100", "111"], "F": ["111", "100", "110", "100", "100"],
    "G": ["011", "100", "101", "101", "011"], "H": ["101", "101", "111", "101", "101"],
    "I": ["111", "010", "010", "010", "111"], "J": ["001", "001", "001", "101", "010"],
    "K": ["101", "101", "110", "101", "101"], "L": ["100", "100", "100", "100", "111"],
    "M": ["101", "111", "111", "101", "101"], "N": ["101", "111", "111", "111", "101"],
    "O": ["010", "101", "101", "101", "010"], "P": ["110", "101", "110", "100", "100"],
    "Q": ["010", "101", "101", "111", "011"], "R": ["110", "101", "110", "101", "101"],
    "S": ["011", "100", "010", "001", "110"], "T": ["111", "010", "010", "010", "010"],
    "U": ["101", "101", "101", "101", "111"], "V": ["101", "101", "101", "101", "010"],
    "W": ["101", "101", "111", "111", "101"], "X": ["101", "101", "010", "101", "101"],
    "Y": ["101", "101", "010", "010", "010"], "Z": ["111", "001", "010", "100", "111"],
    "0": ["111", "101", "101", "101", "111"], "1": ["010", "110", "010", "010", "111"],
    "2": ["110", "001", "010", "100", "111"], "3": ["110", "001", "010", "001", "110"],
    "4": ["101", "101", "111", "001", "001"], "5": ["111", "100", "110", "001", "110"],
    "6": ["011", "100", "110", "101", "010"], "7": ["111", "001", "010", "010", "010"],
    "8": ["010", "101", "010", "101", "010"], "9": ["010", "101", "011", "001", "110"],
    " ": ["000", "000", "000", "000", "000"], "#": ["101", "111", "101", "111", "101"],
}

VARIANTS = [
    {"key": "top_kills", "title": "TOP KILLS", "label": "KILLS", "icon": "kills", "accent": (250, 84, 75)},
    {"key": "top_wins", "title": "TOP WINS", "label": "WINS", "icon": "wins", "accent": (255, 195, 66)},
    {"key": "top_win_streak", "title": "TOP WIN STREAK", "label": "STREAK", "icon": "streak", "accent": (255, 111, 48)},
    {"key": "top_beds", "title": "TOP BEDS", "label": "BEDS", "icon": "beds", "accent": (246, 76, 125)},
    {"key": "top_coins", "title": "TOP COINS", "label": "COINS", "icon": "coins", "accent": (42, 218, 137)},
    {"key": "top_level", "title": "TOP LEVEL", "label": "LEVEL", "icon": "level", "accent": (164, 112, 255)},
    {"key": "top_final_kills", "title": "TOP FINAL KILLS", "label": "FINALS", "icon": "finals", "accent": (218, 77, 255)},
]


def uid(key: str) -> str:
    return str(uuid.uuid5(NAMESPACE, key))


def rgba(color, alpha=255):
    return tuple(color) + (alpha,)


def gradient(draw, box, top, bottom):
    x0, y0, x1, y1 = box
    span = max(1, y1 - y0 - 1)
    for y in range(y0, y1):
        t = (y - y0) / span
        col = tuple(round(top[i] + (bottom[i] - top[i]) * t) for i in range(3))
        draw.line((x0, y, x1 - 1, y), fill=rgba(col))


def width3(text, scale=1):
    return sum((3 if c == " " else 4) * scale for c in text.upper()) - scale


def pixel_text(draw, text, center_x, y, color, scale=1):
    text = text.upper()
    x = center_x - width3(text, scale) // 2
    for char in text:
        glyph = FONT3.get(char, FONT3[" "])
        for gy, row in enumerate(glyph):
            for gx, bit in enumerate(row):
                if bit == "1":
                    px, py = x + gx * scale, y + gy * scale
                    draw.rectangle((px, py, px + scale - 1, py + scale - 1), fill=rgba(color))
        x += (3 if char == " " else 4) * scale


def draw_gem(draw, box, high, mid, low):
    x0, y0, x1, y1 = box
    cx, cy = (x0 + x1) // 2, (y0 + y1) // 2
    draw.polygon([(cx, y0), (x1, cy), (cx, y1), (x0, cy)], fill=rgba(mid))
    draw.polygon([(cx, y0), (cx, cy), (x0, cy)], fill=rgba(high))
    draw.polygon([(x0, cy), (cx, cy), (cx, y1)], fill=rgba(low))
    draw.line((cx, y0 + 1, x1 - 2, cy), fill=rgba(high))


def draw_icon(draw, icon, box, accent):
    x0, y0, x1, y1 = box
    dark = (17, 22, 39)
    accent_dark = tuple(max(0, round(c * 0.45)) for c in accent)
    draw.rectangle((x0, y0, x1 - 1, y1 - 1), fill=rgba(dark), outline=rgba(accent_dark))
    draw.line((x0 + 2, y0 + 1, x1 - 3, y0 + 1), fill=rgba(accent))
    white = (236, 249, 255)
    gold = (255, 216, 103)

    if icon == "kills":
        draw.line((x0 + 3, y1 - 4, x0 + 10, y0 + 3), fill=rgba(white), width=2)
        draw.line((x0 + 5, y0 + 3, x1 - 4, y1 - 4), fill=rgba(accent), width=2)
        draw.line((x0 + 2, y1 - 3, x0 + 5, y1 - 1), fill=rgba(gold), width=2)
        draw.line((x1 - 6, y1 - 3, x1 - 3, y1 - 1), fill=rgba(gold), width=2)
    elif icon == "wins":
        pts = [(x0 + 2, y0 + 5), (x0 + 5, y0 + 8), (x0 + 8, y0 + 3),
               (x0 + 11, y0 + 8), (x1 - 3, y0 + 5), (x1 - 4, y0 + 11), (x0 + 3, y0 + 11)]
        draw.polygon(pts, fill=rgba(gold), outline=rgba(accent))
        draw.rectangle((x0 + 4, y0 + 12, x1 - 4, y0 + 13), fill=rgba(accent))
        draw.point((x0 + 8, y0 + 1), fill=rgba(white))
    elif icon == "streak":
        flame = [(x0 + 8, y0 + 2), (x0 + 12, y0 + 7), (x0 + 11, y0 + 12),
                 (x0 + 8, y0 + 14), (x0 + 4, y0 + 12), (x0 + 4, y0 + 8), (x0 + 6, y0 + 6)]
        draw.polygon(flame, fill=rgba(accent), outline=rgba(gold))
        draw.polygon([(x0 + 8, y0 + 7), (x0 + 10, y0 + 10), (x0 + 8, y0 + 12),
                      (x0 + 6, y0 + 10)], fill=rgba(gold))
    elif icon == "beds":
        draw.rectangle((x0 + 2, y0 + 7, x0 + 13, y0 + 11), fill=rgba(accent))
        draw.rectangle((x0 + 2, y0 + 5, x0 + 6, y0 + 8), fill=rgba(white))
        draw.line((x0 + 2, y0 + 12, x0 + 3, y0 + 14), fill=rgba(gold), width=2)
        draw.line((x0 + 12, y0 + 12, x0 + 13, y0 + 14), fill=rgba(gold), width=2)
        draw.line((x0 + 8, y0 + 7, x0 + 8, y0 + 11), fill=rgba((255, 228, 181)))
    elif icon == "coins":
        draw.ellipse((x0 + 2, y0 + 4, x0 + 11, y0 + 13), fill=rgba(gold), outline=rgba(accent))
        draw.ellipse((x0 + 5, y0 + 2, x0 + 14, y0 + 11), fill=rgba(accent), outline=rgba(white))
        draw.ellipse((x0 + 8, y0 + 5, x0 + 11, y0 + 8), fill=rgba(gold))
    elif icon == "level":
        star = [(x0 + 8, y0 + 2), (x0 + 10, y0 + 6), (x0 + 14, y0 + 7),
                (x0 + 11, y0 + 10), (x0 + 12, y0 + 14), (x0 + 8, y0 + 12),
                (x0 + 4, y0 + 14), (x0 + 5, y0 + 10), (x0 + 2, y0 + 7), (x0 + 6, y0 + 6)]
        draw.polygon(star, fill=rgba(accent), outline=rgba(white))
        draw.point((x0 + 8, y0 + 8), fill=rgba(gold))
    elif icon == "finals":
        draw.ellipse((x0 + 2, y0 + 2, x0 + 14, y0 + 14), outline=rgba(accent), width=2)
        draw.ellipse((x0 + 5, y0 + 5, x0 + 11, y0 + 11), outline=rgba((62, 42, 91)), width=1)
        draw.line((x0 + 4, y0 + 12, x0 + 12, y0 + 4), fill=rgba(white), width=2)
        draw.line((x0 + 3, y0 + 13, x0 + 6, y0 + 12), fill=rgba(gold), width=2)


def draw_block_tile(draw, rect, name, accent):
    x, y, w, h = rect
    palettes = {
        "obsidian": ((62, 56, 83), (17, 20, 38)),
        "gold": ((255, 229, 137), (171, 98, 22)),
        "cyan": ((134, 246, 255), (17, 107, 143)),
        "emerald": ((157, 255, 190), (5, 106, 61)),
        "diamond": ((207, 255, 255), (21, 119, 169)),
        "red_wool": ((255, 131, 120), (128, 22, 40)),
        "blue_wool": ((139, 205, 255), (25, 58, 150)),
        "silver": ((247, 253, 255), (101, 120, 145)),
        "dark": ((42, 34, 57), (9, 11, 22)),
        "gold_side": ((218, 155, 50), (84, 44, 20)),
        "glow": ((130, 252, 255), (12, 84, 119)),
        "stone": ((115, 122, 141), (43, 47, 62)),
        "bronze": ((239, 164, 93), (102, 50, 28)),
        "accent": (tuple(min(255, round(v * 1.15 + 28)) for v in accent),
                   tuple(round(v * 0.42) for v in accent)),
        "accent_side": (tuple(round(v * 0.85 + 20) for v in accent),
                        tuple(round(v * 0.30) for v in accent)),
        "portal": ((105, 104, 177), (18, 20, 47)),
        "grass_top": ((119, 205, 82), (63, 142, 59)),
        "dirt": ((157, 111, 71), (84, 56, 49)),
        "crystal": ((157, 255, 255), (21, 105, 166)),
        "rune": ((126, 245, 255), (19, 77, 114)),
        "grass_side": ((125, 187, 82), (90, 62, 43)),
    }
    top, bottom = palettes[name]
    gradient(draw, (x, y, x + w, y + h), top, bottom)
    draw.line((x, y, x + w - 1, y), fill=rgba(tuple(min(255, c + 24) for c in top)))
    draw.line((x, y, x, y + h - 1), fill=rgba(tuple(min(255, c + 12) for c in top)))
    draw.rectangle((x, y, x + w - 1, y + h - 1), outline=rgba(tuple(max(0, c - 30) for c in bottom)))
    for px, py in [(3, 4), (8, 3), (12, 7), (5, 11), (10, 13)]:
        factor = 1.20 if (px + py) % 2 else 0.68
        bright = tuple(min(255, int(c * factor)) for c in top)
        draw.point((x + px, y + py), fill=rgba(bright))
    if name == "portal":
        draw.polygon([(x + 7, y + 2), (x + 12, y + 7), (x + 7, y + 13), (x + 3, y + 8)],
                     outline=(115, 100, 203, 255))
        draw.line((x + 5, y + 8, x + 10, y + 8), fill=(99, 232, 255, 255))
        draw.point((x + 7, y + 6), fill=(228, 244, 255, 255))
    elif name == "grass_top":
        draw.line((x + 2, y + 4, x + 5, y + 2), fill=(188, 239, 125, 255))
        draw.line((x + 8, y + 5, x + 10, y + 2), fill=(188, 239, 125, 255))
        draw.point((x + 13, y + 6), fill=(62, 132, 54, 255))
    elif name == "dirt":
        for px, py, color in [(3, 4, (199, 145, 89, 255)), (10, 7, (74, 50, 43, 255)),
                              (6, 12, (198, 142, 91, 255)), (13, 13, (111, 73, 54, 255))]:
            draw.rectangle((x + px, y + py, x + px + 1, y + py + 1), fill=color)
    elif name == "grass_side":
        draw.rectangle((x, y, x + 15, y + 3), fill=(119, 196, 77, 255))
        draw.line((x, y + 3, x + 15, y + 3), fill=(72, 137, 56, 255))
        for px, py in [(2, 5), (8, 8), (12, 12), (5, 14)]:
            draw.point((x + px, y + py), fill=(193, 133, 78, 255))
    elif name == "crystal":
        draw_gem(draw, (x + 2, y + 1, x + 14, y + 15),
                 (214, 255, 255), (75, 211, 249), (14, 74, 146))
    elif name == "rune":
        draw.line((x + 4, y + 3, x + 4, y + 12), fill=(126, 245, 255, 255))
        draw.line((x + 4, y + 3, x + 10, y + 3), fill=(126, 245, 255, 255))
        draw.line((x + 10, y + 3, x + 10, y + 9), fill=(91, 186, 255, 255))
        draw.line((x + 6, y + 9, x + 10, y + 9), fill=(91, 186, 255, 255))
        draw.point((x + 12, y + 12), fill=(255, 224, 112, 255))


def make_texture(variant):
    atlas = Image.new("RGBA", MODEL_RES, (0, 0, 0, 0))
    draw = ImageDraw.Draw(atlas)
    accent = variant["accent"]

    # A shared dark glass panel with ten clean, readable hologram lanes.
    gradient(draw, (0, 0, 64, 64), (10, 15, 31), (10, 27, 43))
    for x in range(3, 64, 8):
        draw.line((x, 0, x, 63), fill=(16, 27, 43, 255))
    for y in range(3, 64, 8):
        draw.line((0, y, 63, y), fill=(16, 27, 43, 255))
    draw.rectangle((1, 1, 62, 62), outline=(40, 87, 111, 255))
    draw.line((4, 2, 59, 2), fill=rgba(accent))
    pixel_text(draw, "BEDWARS", 32, 5, (127, 237, 255), scale=2)
    pixel_text(draw, variant["title"], 32, 18, accent, scale=1)
    draw.line((5, 25, 58, 25), fill=(89, 116, 142, 255))
    draw.line((17, 26, 46, 26), fill=rgba(accent))
    rank_colors = [(255, 211, 92), (220, 234, 246), (210, 145, 86)] + [accent] * 7
    for i in range(10):
        y = 28 + round(i * 3.45)
        color = rank_colors[i]
        draw.rectangle((4, y, 6, y + 2), fill=rgba(color))
        draw.point((5, y), fill=(255, 255, 245, 255))
        draw.line((10, y + 1, 39 + (i % 3) * 2, y + 1), fill=(87, 111, 135, 255))
        draw.line((46, y + 1, 58, y + 1), fill=(44, 80, 99, 255))
        draw.point((60, y + 1), fill=rgba(color))
        if i < 9:
            draw.line((4, y + 3, 60, y + 3), fill=(28, 48, 63, 255))
    draw.line((4, 63, 60, 63), fill=rgba(tuple(round(c * 0.6) for c in accent)))
    for x, y in [(4, 8), (59, 6), (7, 22), (57, 22), (5, 57), (58, 55)]:
        draw.point((x, y), fill=rgba(accent))

    for name, (x, y, w, h) in UV.items():
        if name in {"screen", "badge"}:
            continue
        draw_block_tile(draw, (x, y, w, h), name, accent)
    bx, by, bw, bh = UV["badge"]
    draw_icon(draw, variant["icon"], (bx, by, bx + bw, by + bh), accent)
    return atlas


def add_cube(cubes, name, f, t, front="obsidian", side=None, top=None, bottom=None, back=None, bone="root"):
    side = side or front
    cubes.append({
        "name": name, "bone": bone,
        "from": [float(v) for v in f], "to": [float(v) for v in t],
        "materials": {
            "north": front, "south": back or side,
            "east": side, "west": side,
            "up": top or side, "down": bottom or side,
        },
    })


def make_cubes():
    cubes = []
    # Floating BedWars island: grass cap, dirt rim and a tapered stone underside.
    # The island is deliberately stepped so it reads clearly at lobby distance.
    add_cube(cubes, "island_tip", [-4.5, -7, -2.5], [4.5, -4, 2.5], "stone", "stone", "stone", "dark")
    add_cube(cubes, "island_mid", [-8, -4, -5], [8, -1, 5], "stone", "stone", "dirt", "dark")
    add_cube(cubes, "island_grass_cap", [-14, -1.2, -8], [14, 0.6, 8], "grass_side", "grass_side", "grass_top", "stone")
    add_cube(cubes, "base_foot", [-12, 0, -6], [12, 2, 6], "obsidian", "dark", "gold_side", "dark")
    add_cube(cubes, "base_trim", [-10, 2, -5], [10, 3, 5], "gold_side", "gold_side", "gold")
    add_cube(cubes, "base_center", [-4, 3, -3], [4, 5, 3], "obsidian", "stone", "gold_side")
    add_cube(cubes, "stand_column", [-2.5, 4, -1], [2.5, 9, 2.5], "obsidian", "dark", "gold_side")
    add_cube(cubes, "stand_light", [-1.6, 5, -2.2], [1.6, 5.8, -1.8], "cyan", "cyan", "cyan")
    add_cube(cubes, "team_wool_red", [-9, 2.6, -6.5], [-6, 5.5, -3.5], "red_wool", "red_wool", "red_wool")
    add_cube(cubes, "team_wool_blue", [6, 2.6, -6.5], [9, 5.5, -3.5], "blue_wool", "blue_wool", "blue_wool")

    # Shared portal gate behind the sign. Voxel-stepped shoulders create an arch
    # without intruding into the open hologram panel.
    add_cube(cubes, "portal_pillar_left", [-24, 9, -0.8], [-20, 35, 3.8], "portal", "obsidian", "gold_side", "dark")
    add_cube(cubes, "portal_pillar_right", [20, 9, -0.8], [24, 35, 3.8], "portal", "obsidian", "gold_side", "dark")
    add_cube(cubes, "portal_shoulder_left", [-24, 34, -0.8], [-18, 39, 3.8], "portal", "obsidian", "gold_side")
    add_cube(cubes, "portal_shoulder_right", [18, 34, -0.8], [24, 39, 3.8], "portal", "obsidian", "gold_side")
    add_cube(cubes, "portal_step_left", [-21, 38, -0.8], [-14, 43, 3.8], "obsidian", "portal", "gold_side")
    add_cube(cubes, "portal_step_right", [14, 38, -0.8], [21, 43, 3.8], "obsidian", "portal", "gold_side")
    add_cube(cubes, "portal_cap_left", [-15, 41, -0.8], [-8, 45, 3.8], "portal", "obsidian", "gold_side")
    add_cube(cubes, "portal_cap_right", [8, 41, -0.8], [15, 45, 3.8], "portal", "obsidian", "gold_side")
    add_cube(cubes, "portal_rune_left", [-23.3, 13, -1.35], [-22.9, 31, -0.95], "rune", "accent_side", "accent")
    add_cube(cubes, "portal_rune_right", [22.9, 13, -1.35], [23.3, 31, -0.95], "rune", "accent_side", "accent")
    add_cube(cubes, "portal_glyph_left", [-23, 35, -1.4], [-21, 37, -0.9], "accent", "accent_side", "accent")
    add_cube(cubes, "portal_glyph_right", [21, 35, -1.4], [23, 37, -0.9], "accent", "accent_side", "accent")

    # Shared display chassis; category content stays in the broad glass inset.
    add_cube(cubes, "rear_body", [-18, 8, -0.5], [18, 42, 3.5], "dark", "obsidian", "obsidian", "dark")
    add_cube(cubes, "bezel_left", [-18, 8, -3.15], [-14, 38, -1], "obsidian", "gold_side", "obsidian")
    add_cube(cubes, "bezel_right", [14, 8, -3.15], [18, 38, -1], "obsidian", "gold_side", "obsidian")
    add_cube(cubes, "bezel_bottom", [-18, 8, -3.15], [18, 12, -1], "obsidian", "gold_side", "obsidian")
    add_cube(cubes, "bezel_top", [-18, 36, -3.15], [18, 42, -1], "obsidian", "gold_side", "obsidian")
    add_cube(cubes, "hologram_screen", [-14, 12, -3.42], [14, 36, -3.16], "screen", "cyan", "cyan", "dark", "obsidian")

    # Thin gold corner brackets and a clean cyan inner rail.
    add_cube(cubes, "corner_tl_h", [-18, 39.5, -3.8], [-14, 40.5, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "corner_tl_v", [-17.7, 36, -3.8], [-16.7, 40.5, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "corner_tr_h", [14, 39.5, -3.8], [18, 40.5, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "corner_tr_v", [16.7, 36, -3.8], [17.7, 40.5, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "corner_bl_h", [-18, 9.5, -3.8], [-14, 10.5, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "corner_bl_v", [-17.7, 9.5, -3.8], [-16.7, 14, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "corner_br_h", [14, 9.5, -3.8], [18, 10.5, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "corner_br_v", [16.7, 9.5, -3.8], [17.7, 14, -3.5], "gold", "gold_side", "gold")
    add_cube(cubes, "light_left", [-14.45, 12, -3.85], [-14.05, 36, -3.55], "cyan", "glow", "cyan")
    add_cube(cubes, "light_right", [14.05, 12, -3.85], [14.45, 36, -3.55], "cyan", "glow", "cyan")
    add_cube(cubes, "light_top", [-14, 36.05, -3.85], [14, 36.45, -3.55], "cyan", "glow", "cyan")

    # The animated, interchangeable category medal attaches to the portal crown.
    badge_bone = "floating_badge"
    add_cube(cubes, "badge_back", [-7.5, 40.5, -1.8], [7.5, 50, 2.2], "obsidian", "gold_side", "gold_side", bone=badge_bone)
    add_cube(cubes, "badge_top", [-8.5, 48.5, -4.2], [8.5, 50, -1.5], "gold", "gold_side", "gold", bone=badge_bone)
    add_cube(cubes, "badge_bottom", [-8.5, 40.5, -4.2], [8.5, 42, -1.5], "gold", "gold_side", "gold", bone=badge_bone)
    add_cube(cubes, "badge_side_left", [-8.5, 42, -4.2], [-7, 48.5, -1.5], "gold", "gold_side", "gold", bone=badge_bone)
    add_cube(cubes, "badge_side_right", [7, 42, -4.2], [8.5, 48.5, -1.5], "gold", "gold_side", "gold", bone=badge_bone)
    add_cube(cubes, "category_emblem", [-6.5, 42, -4.48], [6.5, 48.5, -4.2], "badge", "accent_side", "accent", "dark", bone=badge_bone)
    add_cube(cubes, "badge_gem_left", [-11.5, 44, -3.2], [-8.5, 47, -0.2], "accent", "accent_side", "accent", bone=badge_bone)
    add_cube(cubes, "badge_gem_right", [8.5, 44, -3.2], [11.5, 47, -0.2], "accent", "accent_side", "accent", bone=badge_bone)

    # BedWars red/blue markers on the gate frame.
    add_cube(cubes, "red_side_tab", [-26, 29, -1.5], [-23.5, 32, 1.5], "red_wool", "red_wool", "red_wool")
    add_cube(cubes, "blue_side_tab", [23.5, 29, -1.5], [26, 32, 1.5], "blue_wool", "blue_wool", "blue_wool")

    # Category-independent gems and portal core hover gently on a shared phase.
    for name, f, t, mat in [
        ("lower_emerald", [-22.5, 11, -3.5], [-19.5, 14, -0.5], "emerald"),
        ("lower_diamond", [19.5, 11, -3.5], [22.5, 14, -0.5], "diamond"),
        ("portal_core", [-1.5, -8, -1.5], [1.5, -5, 1.5], "crystal"),
        ("floating_rock_left", [-16, -2, -2], [-13, 1, 1], "stone"),
        ("floating_rock_right", [13, -2, -2], [16, 1, 1], "stone"),
    ]:
        add_cube(cubes, name, f, t, mat, mat, mat, bone="floating_gems")
    return cubes


def material_uv(material):
    x, y, w, h = UV[material]
    return {"uv": [x, y, x + w, y + h], "texture": 0}


def build_outliner(cubes, names):
    group_ids = {n: uid(names["key"] + ":bone:" + n) for n in ("root", "floating_badge", "floating_gems")}
    children = {n: [] for n in group_ids}
    elements = []
    for item in cubes:
        element_id = uid(names["key"] + ":cube:" + item["name"])
        children[item["bone"]].append(element_id)
        elements.append({
            "name": item["name"], "type": "cube", "uuid": element_id,
            "box_uv": False, "from": item["from"], "to": item["to"], "origin": [0, 0, 0],
            "faces": {face: material_uv(mat) for face, mat in item["materials"].items()},
        })
    groups = []
    for name, origin, child_bones in [
        ("root", [0, 0, 0], ["floating_badge", "floating_gems"]),
        ("floating_badge", [0, 45, 0], []),
        ("floating_gems", [0, 0, 0], []),
    ]:
        groups.append({
            "name": name, "origin": origin, "uuid": group_ids[name], "export": True,
            "isOpen": True, "visibility": True,
            "children": children[name] + [group_ids[kid] for kid in child_bones],
        })

    element_ids = {element["uuid"] for element in elements}

    def nested(group_name):
        node = next(g for g in groups if g["name"] == group_name)
        out = dict(node)
        out["children"] = [
            child if child in element_ids
            else nested(next(g["name"] for g in groups if g["uuid"] == child))
            for child in node["children"]
        ]
        return out
    return elements, [nested("root")], group_ids


def animation_data(key, group_ids):
    frames = [
        (0.0, (0, 0, 0), (0, 0, 0)),
        (1.5, (0, 0.35, 0), (2, 0, 1)),
        (3.0, (0, 0, 0), (0, 0, 0)),
        (4.5, (0, -0.25, 0), (-2, 0, -1)),
        (6.0, (0, 0, 0), (0, 0, 0)),
    ]
    gems = [(0.0, 0), (1.5, 0.25), (3.0, 0), (4.5, 0.25), (6.0, 0)]
    anim_uuid = uid(key + ":idle-animation")
    anim_name = "animation." + key + ".idle"
    animators = {}
    for bone_name in ("floating_badge", "floating_gems"):
        channels = []
        if bone_name == "floating_badge":
            for channel_index, channel_name in ((1, "position"), (2, "rotation")):
                for time, position, rotation in frames:
                    value = position if channel_index == 1 else rotation
                    channels.append({
                        "channel": channel_name,
                        "data_points": [dict(zip("xyz", [str(v) for v in value]))],
                        "uuid": uid(f"{key}:{bone_name}:{channel_name}:{time}"),
                        "time": time, "color": -1, "interpolation": "catmullrom",
                    })
        else:
            for time, amount in gems:
                channels.append({
                    "channel": "position",
                    "data_points": [{"x": "0", "y": str(amount), "z": "0"}],
                    "uuid": uid(f"{key}:{bone_name}:position:{time}"),
                    "time": time, "color": -1, "interpolation": "catmullrom",
                })
        animators[group_ids[bone_name]] = {"name": bone_name, "type": "bone", "keyframes": channels}
    bb_anim = {"uuid": anim_uuid, "name": anim_name, "loop": "loop", "override": False,
               "length": 6, "snapping": 24, "animators": animators}
    bedrock_anim = {
        "format_version": "1.8.0",
        "animations": {anim_name: {
            "loop": True, "animation_length": 6,
            "bones": {
                "floating_badge": {
                    "position": {str(time): list(position) for time, position, _ in frames},
                    "rotation": {str(time): list(rotation) for time, _, rotation in frames},
                },
                "floating_gems": {"position": {str(time): [0, amount, 0] for time, amount in gems}},
            },
        }},
    }
    return bb_anim, bedrock_anim


def build_geometry(key, cubes, group_ids, badge_origin=(0, 45, 0)):
    bones = []
    for name in ("root", "floating_badge", "floating_gems"):
        bone_cubes = []
        for item in cubes:
            if item["bone"] != name:
                continue
            f, t = item["from"], item["to"]
            face_uvs = {}
            for face, material in item["materials"].items():
                x, y, w, h = UV[material]
                face_uvs[face] = {"uv": [x, y], "uv_size": [w, h]}
            bone_cubes.append({
                "origin": [-t[0], f[1], f[2]],
                "size": [round(t[i] - f[i], 3) for i in range(3)],
                "uv": face_uvs,
            })
        pivot = [0, 0, 0] if name != "floating_badge" else [-badge_origin[0], badge_origin[1], badge_origin[2]]
        bone = {"name": name, "pivot": pivot, "cubes": bone_cubes}
        if name != "root":
            bone["parent"] = "root"
        bones.append(bone)
    geo = {
        "format_version": "1.12.0",
        "minecraft:geometry": [{
            "description": {
                "identifier": "geometry." + key, "texture_width": MODEL_RES[0], "texture_height": MODEL_RES[1],
                "visible_bounds_width": 5.2, "visible_bounds_height": 6.5,
                "visible_bounds_offset": [0, 2.3, 0],
            },
            "bones": bones,
        }],
    }
    return geo


def render_one(model_path, out_path):
    renderer_path = ROOT.parent / "lobby" / "render_preview.py"
    subprocess.run([sys.executable, str(renderer_path), str(model_path), str(out_path), "-17", "17"], check=True)


def make_model(variant):
    key = "bedwars_" + variant["key"]
    atlas = make_texture(variant)
    texture_path = OUT / (key + ".png")
    atlas.save(texture_path, optimize=True)
    cubes = make_cubes()
    elements, outliner, group_ids = build_outliner(cubes, variant)
    anim, bedrock_anim = animation_data(key, group_ids)

    buf = io.BytesIO()
    atlas.save(buf, format="PNG", optimize=True)
    b64 = base64.b64encode(buf.getvalue()).decode("ascii")
    bbmodel = {
        "meta": {"format_version": "4.10", "model_format": "bedrock", "box_uv": False},
        "name": key, "model_identifier": key,
        "visible_box": [5.4, 6.2, 3.0],
        "resolution": {"width": MODEL_RES[0], "height": MODEL_RES[1]},
        "elements": elements, "outliner": outliner,
        "textures": [{
            "path": "", "name": key + ".png", "id": "0", "width": MODEL_RES[0], "height": MODEL_RES[1],
            "uv_width": MODEL_RES[0], "uv_height": MODEL_RES[1], "render_mode": "default",
            "visible": True, "internal": True, "saved": True, "uuid": uid(key + ":texture"),
            "source": "data:image/png;base64," + b64,
        }],
        "animations": [anim],
    }
    bb_path = OUT / (key + ".bbmodel")
    bb_path.write_text(json.dumps(bbmodel, indent=2) + "\n", encoding="utf-8")

    geo = build_geometry(key, cubes, group_ids)
    (OUT / (key + ".geo.json")).write_text(json.dumps(geo, indent=2) + "\n", encoding="utf-8")
    (OUT / (key + ".animation.json")).write_text(json.dumps(bedrock_anim, indent=2) + "\n", encoding="utf-8")

    preview_path = OUT / (key + "_preview.png")
    render_one(bb_path, preview_path)
    return {"key": key, "title": variant["title"], "accent": variant["accent"], "preview": preview_path}


def make_overview(models):
    width, height = 1880, 1280
    canvas = Image.new("RGB", (width, height), (11, 13, 25))
    d = ImageDraw.Draw(canvas)
    # Subtle geometric ambience behind the seven model cards.
    for x in range(0, width, 64):
        d.line((x, 0, x, height), fill=(16, 20, 36))
    for y in range(0, height, 64):
        d.line((0, y, width, y), fill=(16, 20, 36))
    font_path = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
    try:
        title_font = ImageFont.truetype(font_path, 38)
        label_font = ImageFont.truetype(font_path, 19)
        sub_font = ImageFont.truetype(font_path, 15)
    except OSError:
        title_font = label_font = sub_font = ImageFont.load_default()
    d.text((width // 2, 28), "BEDWARS LEADERBOARD SET", font=title_font,
           fill=(231, 244, 255), anchor="ma")
    d.text((width // 2, 82), "PORTAL FRAME  /  FLOATING BEDWARS ISLAND  /  SWAPPABLE MEDALS",
           font=sub_font, fill=(107, 215, 232), anchor="ma")

    card_w, card_h = 430, 535
    gap_x, gap_y = 18, 24
    left = (width - (4 * card_w + 3 * gap_x)) // 2
    top_y = 130
    placements = []
    for i in range(4):
        placements.append((left + i * (card_w + gap_x), top_y))
    second_left = (width - (3 * card_w + 2 * gap_x)) // 2
    for i in range(3):
        placements.append((second_left + i * (card_w + gap_x), top_y + card_h + gap_y))

    for model, (x, y) in zip(models, placements):
        accent = model["accent"]
        d.rounded_rectangle((x, y, x + card_w, y + card_h), radius=18,
                            fill=(20, 23, 39), outline=tuple(accent), width=2)
        d.rounded_rectangle((x + 16, y + 15, x + card_w - 16, y + 62), radius=10,
                            fill=(15, 18, 32), outline=(47, 59, 82), width=1)
        d.text((x + card_w // 2, y + 38), model["title"], font=label_font,
               fill=(242, 247, 255), anchor="mm")
        with Image.open(model["preview"]) as im:
            im = im.convert("RGB").resize((card_w - 24, card_w - 24), Image.Resampling.LANCZOS)
            canvas.paste(im, (x + 12, y + 68))
        d.rounded_rectangle((x + 18, y + card_w + 50, x + card_w - 18, y + card_h - 14),
                            radius=10, fill=(14, 18, 32), outline=(46, 57, 79), width=1)
        d.text((x + card_w // 2, y + card_h - 31), "TOP 10  •  LIVE HOLOGRAM", font=sub_font,
               fill=tuple(accent), anchor="mm")

    overview = ROOT / "preview_bedwars_leaderboards.png"
    canvas.save(overview, optimize=True)
    return overview


def write_readme():
    text = """BEDWARS LEADERBOARD SET — Blockbench Bedrock models

Seven matching, editable models:
- bedwars_top_kills
- bedwars_top_wins
- bedwars_top_win_streak
- bedwars_top_beds
- bedwars_top_coins
- bedwars_top_level
- bedwars_top_final_kills

Every model shares the same obsidian-and-gold portal gate, a stepped floating
BedWars island base, a readable 10-row glass panel, and a swappable category
medal. Only the icon/accent colour changes. Each .bbmodel embeds its 128x128
texture and includes a subtle 6-second idle animation: badge bob/tilt and
slowly floating side gems and island crystals.

Each model also includes a PNG texture, Bedrock .geo.json geometry,
.animation.json export and rendered preview. Open a .bbmodel directly in
Blockbench. The model is an animated decorative backdrop; live names, ranks and
stats are intended to be supplied by a separate PocketMine hologram/plugin.

The project uses the Blockbench Bedrock model format (4.10). Preview renders
are visual mockups; adjust scale, entity configuration and hologram placement
for your server before deploying.
"""
    (OUT / "README.txt").write_text(text, encoding="utf-8")


def make_zip(models, overview):
    archive = ROOT / "BedWars_Leaderboard_Models.zip"
    files = [OUT / "README.txt", overview]
    for model in models:
        key = model["key"]
        files += [
            OUT / (key + ".bbmodel"), OUT / (key + ".png"),
            OUT / (key + ".geo.json"), OUT / (key + ".animation.json"),
            model["preview"],
        ]
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for path in files:
            zf.write(path, arcname=path.name)


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    models = [make_model(variant) for variant in VARIANTS]
    overview = make_overview(models)
    write_readme()
    make_zip(models, overview)
    print(f"Built {len(models)} Blockbench models: {ROOT / 'BedWars_Leaderboard_Models.zip'}")


if __name__ == "__main__":
    main()
