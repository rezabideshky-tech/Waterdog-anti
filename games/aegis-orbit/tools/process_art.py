"""Turn the raw AI-generated art into the exact files the game ships.

Run:  python3 tools/process_art.py   (needs Pillow; see tools/README.md)

- icon_source.png  -> icon.png (512, Godot project icon) and store_icon_1024.png (plain square, no radius)
- bg_space.png     -> bg_space.png (1080x1920 portrait background)
- planet_texture   -> planet.png (512 tileable-ish surface)
- rock_source.png  -> rock.png (magenta key-out, cropped, 256 px, RGBA)
"""
from pathlib import Path
from PIL import Image

ART = Path(__file__).resolve().parent.parent / "assets" / "art"


def key_out_magenta(img: Image.Image, tol: int = 90) -> Image.Image:
    img = img.convert("RGBA")
    px = img.load()
    w, h = img.size
    for y in range(h):
        for x in range(w):
            r, g, b, _ = px[x, y]
            # distance from pure magenta (255, 0, 255); soft edge for anti-aliasing
            d = ((255 - r) ** 2 + g ** 2 + (255 - b) ** 2) ** 0.5
            if d < tol:
                a = 0
            elif d < tol * 1.6:
                a = int(255 * (d - tol) / (tol * 0.6))
            else:
                a = 255
            # kill magenta fringe on partially transparent pixels
            if a < 255 and g < 200:
                r = min(r, g + 60)
                b = min(b, g + 60)
            px[x, y] = (r, g, b, a)
    return img


def main() -> None:
    icon = Image.open(ART / "icon_source.png").convert("RGB")
    icon.resize((1024, 1024), Image.LANCZOS).save(ART / "store_icon_1024.png", optimize=True)
    icon.resize((512, 512), Image.LANCZOS).save(ART / "icon.png", optimize=True)

    bg = Image.open(ART / "bg_space.png").convert("RGB")
    bg.resize((1080, 1920), Image.LANCZOS).save(ART / "bg_space.png", optimize=True)

    planet = Image.open(ART / "planet_texture.png").convert("RGB")
    planet.resize((512, 512), Image.LANCZOS).save(ART / "planet.png", optimize=True)

    rock = key_out_magenta(Image.open(ART / "rock_source.png"))
    bbox = rock.getbbox()
    rock = rock.crop(bbox)
    side = max(rock.size)
    square = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    square.paste(rock, ((side - rock.size[0]) // 2, (side - rock.size[1]) // 2))
    square.resize((256, 256), Image.LANCZOS).save(ART / "rock.png", optimize=True)

    for src in ("icon_source.png", "rock_source.png", "planet_texture.png"):
        p = ART / src
        if p.exists():
            p.unlink()  # the generated sources are reproducible from the prompts in README.md
    print("art processed")


if __name__ == "__main__":
    main()
