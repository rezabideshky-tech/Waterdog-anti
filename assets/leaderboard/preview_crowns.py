#!/usr/bin/env python3
"""preview_crowns.py — تصویر جمع‌بندی «شش تاج غول‌پیکر» (نمای داخل بازی)."""
from PIL import Image, ImageDraw

STEMS = ["arvan_giant_crown_kills", "arvan_giant_crown_wins", "arvan_giant_crown_beds_broken",
         "arvan_giant_crown_final_kills", "arvan_giant_crown_level", "arvan_giant_crown_coins"]
PW, PH = 470, 560


def main() -> None:
    canvas = Image.new("RGB", (PW * 3, PH * 2), (12, 11, 20))
    draw = ImageDraw.Draw(canvas)
    for i, stem in enumerate(STEMS):
        img = Image.open("preview_%s.png" % stem)
        canvas.paste(img.crop((PW, 0, PW * 2, PH)), ((i % 3) * PW, (i // 3) * PH))
        draw.rectangle([(i % 3) * PW + 3, (i // 3) * PH + 3,
                        (i % 3) * PW + PW - 4, (i // 3) * PH + PH - 4], outline=(252, 210, 74), width=2)
    canvas.save("preview_crowns.png")
    print("ok preview_crowns.png", canvas.size)


if __name__ == "__main__":
    main()
