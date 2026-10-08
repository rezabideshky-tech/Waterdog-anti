#!/usr/bin/env python3
"""
ArvanGaming — Halloween BedWars leaderboard pedestal (compact lobby decor)

مدل مشترک برای همه‌ی leaderboard ها:
  * پایه‌ی سنگ‌قبری (زیر متن هولوگرام) + کدو فانوس + ثلاث شمع + جمجمه + تار عنکبوت + مه
  * تاج شناور (بالای متن هولوگرام): حلقه‌ی رونیک چرخان + فانوس کدویی + خفاش در حال گردش
  * ۸ واریانت (یکی برای هر stat) که فقط در رون/رنگ شعله/چهره‌ی کدو و فانوس فرق دارن

هندسه در واحده 1/16 بلاک ساخته می‌شه (16 unit = 1 block) و طوری چیده شده که:
      مدل y = 0   .. 24      -> پایه      (1.5 بلاک زیر متن)
      مدل y = 24  .. 81.6    -> ناحیه‌ی خالی مخصوص متن هولوگرام (13 خط × 0.3 بلاک)
      مدل y = 83  .. 106     -> تاج شناور (حلقه‌ی رونیک، فانوس، خفاش)
پس پلاگین باید انتیتی رو روی  pos.y - 1.5  اسپاون کنه (Y_OFFSET در کلاس پدستال).

خروجی‌ها: .bbmodel + geo/anim/entity + تکسچر ۸ واریانت + ریسورس‌پک (.zip/.mcpack) + رندرهای پیش‌نمایش
"""
import base64, json, math, os, shutil, uuid, zipfile
from PIL import Image, ImageDraw

OUT = os.path.dirname(os.path.abspath(__file__))
NS = uuid.UUID("f3a91d5c-6b24-4a51-9c07-2d8be14a77c3")
WHITE, BLACK = (255, 255, 255), (12, 8, 16)

TEX_W = TEX_H = 256
Y_OFFSET = 1.5          # پلاگین: entitiy روی pos.y - 1.5
BASE_TOP = 24           # مدل y محل شروع متن هولوگرام
LINES = 13              # title + subtitle + خالی + ۱۰ رنک
SPACING = 0.3 * 16      # 4.8 unit فاصله‌ی هر خط

FONT = {
    "A": ["010", "101", "111", "101", "101"], "B": ["110", "101", "110", "101", "110"],
    "C": ["011", "100", "100", "100", "011"], "D": ["110", "101", "101", "101", "110"],
    "E": ["111", "100", "110", "100", "111"], "F": ["111", "100", "110", "100", "100"],
    "G": ["011", "100", "101", "101", "011"], "H": ["101", "101", "111", "101", "101"],
    "I": ["111", "010", "010", "010", "111"], "J": ["001", "001", "001", "101", "010"],
    "K": ["101", "110", "100", "110", "101"], "L": ["100", "100", "100", "100", "111"],
    "M": ["101", "111", "111", "101", "101"], "N": ["110", "101", "101", "101", "101"],
    "O": ["010", "101", "101", "101", "010"], "P": ["110", "101", "110", "100", "100"],
    "Q": ["010", "101", "101", "110", "011"], "R": ["110", "101", "110", "101", "101"],
    "S": ["011", "100", "010", "001", "110"], "T": ["111", "010", "010", "010", "010"],
    "U": ["101", "101", "101", "101", "111"], "V": ["101", "101", "101", "101", "010"],
    "W": ["101", "101", "111", "111", "101"], "X": ["101", "101", "010", "101", "101"],
    "Y": ["101", "101", "010", "010", "010"], "Z": ["111", "001", "010", "100", "111"],
    "0": ["111", "101", "101", "101", "111"], "1": ["010", "110", "010", "010", "111"],
    "2": ["110", "001", "010", "100", "111"], "3": ["110", "001", "010", "001", "110"],
    "4": ["101", "101", "111", "001", "001"], "5": ["111", "100", "110", "001", "110"],
    "6": ["011", "100", "111", "101", "111"], "7": ["111", "001", "010", "010", "010"],
    "8": ["111", "101", "111", "101", "111"], "9": ["111", "101", "111", "001", "110"],
    "-": ["000", "000", "111", "000", "000"], ".": ["000", "000", "000", "000", "010"],
    ",": ["000", "000", "000", "010", "100"], "/": ["001", "001", "010", "100", "100"],
    " ": ["000"] * 5, "!": ["1", "1", "1", "0", "1"], ":": ["000", "010", "000", "010", "000"],
}


class Atlas:
    """Shelf packer + pixel-art helpers روی یک تکسچر."""

    def __init__(self, w=TEX_W, h=TEX_H):
        self.W, self.H = w, h
        self.img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        self.d = ImageDraw.Draw(self.img)
        self.x = self.y = self.row = 0
        self.R = {}

    def alloc(self, name, w, h):
        if self.x + w > self.W:
            self.x, self.y, self.row = 0, self.y + self.row, 0
        assert self.y + h <= self.H, f"atlas full at {name}"
        self.R[name] = (self.x, self.y, w, h)
        self.x += w
        self.row = max(self.row, h)
        return self.x - w, self.y, self.x, self.y + h

    # ---------- primitives ----------
    def grad(self, box, top, bot):
        x0, y0, x1, y1 = box
        for y in range(y0, y1):
            t = (y - y0) / max(1, y1 - y0 - 1)
            self.d.line([(x0, y), (x1 - 1, y)], fill=tuple(int(top[i] + (bot[i] - top[i]) * t) for i in range(3)))

    def noise(self, box, cols, step=3, seed=1):
        x0, y0, x1, y1 = box
        for y in range(y0, y1):
            for x in range(x0, x1):
                h = ((x + seed * 31) * 73856093 ^ (y + seed * 17) * 19349663) & 0xFFFF
                if h % step == 0:
                    self.d.point([(x, y)], fill=cols[(h >> 4) % len(cols)])

    def px(self, x, y, c):
        self.d.point([(x, y)], fill=c)

    def text(self, s, x, y, col, sh=None, sc=1):
        for ch in s:
            g = FONT.get(ch, FONT["?"] if "?" in FONT else FONT[" "])
            for ry, row in enumerate(g):
                for rx, v in enumerate(row):
                    if v == "1":
                        px, py = x + rx * sc, y + ry * sc
                        if sh:
                            self.d.rectangle([px + 1, py + 1, px + sc, py + sc], fill=sh)
                        self.d.rectangle([px, py, px + sc - 1, py + sc - 1], fill=col)
            x += (len(g[0]) + 1) * sc

    def ctext(self, s, cx, y, col, sh=None, sc=1):
        self.text(s, cx - (sum(len(FONT[c][0]) + 1 for c in s) * sc - sc) // 2, y, col, sh, sc)

    def outlined(self, s, cx, y, col, sc=1, out=BLACK):
        for ox, oy in [(-1, 0), (1, 0), (0, -1), (0, 1), (1, 1), (-1, -1), (1, -1), (-1, 1)]:
            self.ctext(s, cx + ox, y + oy, out, None, sc)
        self.ctext(s, cx, y, col, None, sc)


def mix(a, b, t):
    a = tuple(a) + ((255,) if len(a) == 3 else ())
    b = tuple(b) + ((255,) if len(b) == 3 else ())
    return tuple(int(round(a[i] + (b[i] - a[i]) * t)) for i in range(4))


def F(n, s=None, e=None, w=None, u=None, dn=None):
    s = s or n; e = e or n; w = w or e; u = u or n; dn = dn or u
    return dict(north=n, south=s, east=e, west=w, up=u, down=dn)


# ============================================================ art helpers (runes)
def draw_rune(A, box, kind, col, glow):
    """رون‌های واریانتی (روی پلاک پایه و تیغه‌های حلقه) — box مربع/مستطیل کوچک."""
    x0, y0, x1, y1 = box
    w, h = x1 - x0, y1 - y0
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    s = min(w, h)
    d = A.d
    dark = tuple(max(0, c - 70) for c in col)

    def r(px, py, pw, ph, c):
        px, py = int(round(px)), int(round(py))
        pw, ph = max(1, int(round(pw))), max(1, int(round(ph)))
        d.rectangle([px, py, px + pw - 1, py + ph - 1], fill=c)

    if kind == "skull":
        r(cx - s * 0.34, cy - s * 0.36, s * 0.68, s * 0.5, col)
        r(cx - s * 0.24, cy + s * 0.14, s * 0.48, s * 0.2, col)
        r(cx - s * 0.22, cy - s * 0.18, s * 0.16, s * 0.18, glow)
        r(cx + s * 0.06, cy - s * 0.18, s * 0.16, s * 0.18, glow)
        r(cx - s * 0.06, cy + s * 0.02, s * 0.12, s * 0.1, dark)
        for i in range(3):
            r(cx - s * 0.2 + i * s * 0.16, cy + s * 0.16, s * 0.06, s * 0.16, dark)
    elif kind == "trophy":  # wins
        r(cx - s * 0.3, cy - s * 0.4, s * 0.6, s * 0.36, col)
        r(cx - s * 0.22, cy - s * 0.04, s * 0.44, s * 0.12, col)
        r(cx - s * 0.08, cy + s * 0.08, s * 0.16, s * 0.22, col)
        r(cx - s * 0.26, cy + s * 0.3, s * 0.52, s * 0.1, col)
        r(cx - s * 0.14, cy - s * 0.34, s * 0.28, s * 0.3, glow)
        r(cx - s * 0.42, cy - s * 0.34, s * 0.1, s * 0.3, dark)
        r(cx + s * 0.32, cy - s * 0.34, s * 0.1, s * 0.3, dark)
    elif kind == "axe":  # final kills
        r(cx - s * 0.06, cy - s * 0.42, s * 0.12, s * 0.84, (120, 78, 42))
        d.polygon([(cx - s * 0.42, cy - s * 0.36), (cx - s * 0.06, cy - s * 0.46),
                   (cx - s * 0.06, cy + s * 0.02), (cx - s * 0.42, cy - s * 0.06)], fill=col)
        r(cx - s * 0.44, cy - s * 0.38, s * 0.08, s * 0.34, glow)
    elif kind == "bed":  # beds broken
        r(cx - s * 0.42, cy - s * 0.18, s * 0.84, s * 0.3, col)
        r(cx - s * 0.42, cy - s * 0.34, s * 0.3, s * 0.18, WHITE)
        r(cx - s * 0.44, cy + s * 0.12, s * 0.88, s * 0.12, (150, 104, 56))
        r(cx - s * 0.44, cy - s * 0.4, s * 0.06, s * 0.26, (150, 104, 56))
        r(cx + s * 0.38, cy - s * 0.4, s * 0.06, s * 0.26, (150, 104, 56))
        r(cx - s * 0.3, cy - s * 0.12, s * 0.2, s * 0.16, glow)
    elif kind == "star":  # level
        pts = []
        for i in range(10):
            a = math.radians(-90 + i * 36)
            rr = s * (0.46 if i % 2 == 0 else 0.2)
            pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
        d.polygon(pts, fill=col)
        d.polygon([(cx, cy - s * 0.12), (cx + s * 0.1, cy), (cx, cy + s * 0.12), (cx - s * 0.1, cy)], fill=glow)
    elif kind == "coin":  # coins
        d.ellipse([cx - s * 0.42, cy - s * 0.42, cx + s * 0.42, cy + s * 0.42], fill=col, outline=dark)
        d.ellipse([cx - s * 0.26, cy - s * 0.26, cx + s * 0.26, cy + s * 0.26], outline=glow)
        r(cx - s * 0.06, cy - s * 0.2, s * 0.12, s * 0.4, glow)
    elif kind == "ribs":  # deaths
        r(cx - s * 0.06, cy - s * 0.44, s * 0.12, s * 0.88, col)
        for i in range(4):
            yy = cy - s * 0.3 + i * s * 0.2
            r(cx - s * 0.34, yy, s * 0.28, s * 0.09, col)
            r(cx + s * 0.06, yy, s * 0.28, s * 0.09, col)
        r(cx - s * 0.34, cy - s * 0.46, s * 0.68, s * 0.1, glow)
    elif kind == "chain":  # win streak
        for i in range(3):
            yy = cy - s * 0.4 + i * s * 0.3
            d.ellipse([cx - s * 0.24, yy, cx + s * 0.24, yy + s * 0.24], outline=col, width=max(1, s // 12))
        r(cx - s * 0.04, cy - s * 0.44, s * 0.08, s * 0.2, glow)


# ============================================================ variants (per stat)
# stat (ستون دیتابیس پلاگین) -> شبکه، نام، رنگ رون، رنگ شعله، شکل چهره
VARIANTS = [
    dict(stat="kills",        net="arvan:lb_kills",        rune="skull",  col=(235, 236, 245), glow=(120, 255, 190),
         flame=(255, 176, 60),  flame2=(255, 92, 30),   face=0, accent=(255, 150, 40)),
    dict(stat="wins",         net="arvan:lb_wins",         rune="trophy", col=(255, 214, 92),  glow=(255, 248, 190),
         flame=(255, 214, 92),  flame2=(240, 150, 20),  face=1, accent=(255, 208, 84)),
    dict(stat="final_kills",  net="arvan:lb_final_kills",  rune="axe",    col=(240, 86, 78),   glow=(255, 170, 140),
         flame=(255, 96, 72),   flame2=(160, 18, 24),   face=2, accent=(232, 62, 60)),
    dict(stat="beds_broken",  net="arvan:lb_beds_broken",  rune="bed",    col=(96, 226, 130),  glow=(210, 255, 200),
         flame=(120, 235, 120), flame2=(30, 150, 60),   face=0, accent=(70, 210, 120)),
    dict(stat="deaths",       net="arvan:lb_deaths",       rune="ribs",   col=(196, 214, 236), glow=(150, 240, 255),
         flame=(150, 232, 255), flame2=(60, 120, 220),  face=3, accent=(130, 200, 245)),
    dict(stat="level",        net="arvan:lb_level",        rune="star",   col=(196, 132, 255), glow=(255, 230, 160),
         flame=(196, 130, 255), flame2=(110, 40, 200),  face=1, accent=(170, 110, 245)),
    dict(stat="coins",        net="arvan:lb_coins",        rune="coin",   col=(255, 206, 74),  glow=(255, 252, 210),
         flame=(255, 232, 120), flame2=(230, 160, 20),  face=2, accent=(255, 196, 60)),
    dict(stat="win_streak",   net="arvan:lb_win_streak",   rune="chain",  col=(255, 140, 205), glow=(255, 232, 250),
         flame=(255, 140, 205), flame2=(170, 40, 150),  face=3, accent=(240, 110, 190)),
]
VAR_BY_STAT = {v["stat"]: v for v in VARIANTS}


# ============================================================ texture atlas
def build_atlas(v):
    """آتلاس کامل رپ برای یک واریانت (رون/شعله/چهره واریانتی)."""
    A = Atlas()
    a_accent = v["accent"]
    flame, flame2 = v["flame"], v["flame2"]

    # ---------- پایه
    b = A.alloc("stone", 16, 16)
    A.grad(b, (120, 118, 128), (70, 68, 80)); A.noise(b, [(96, 94, 104), (58, 56, 68)], 3, 1)
    for (ax, ay, bx, by) in [(3, 4, 8, 9), (9, 2, 11, 6), (5, 11, 6, 15), (12, 9, 14, 12)]:
        A.d.line([(b[0] + ax, b[1] + ay), (b[0] + bx, b[1] + by)], fill=(48, 46, 56))
    b = A.alloc("stone_dark", 16, 16)
    A.grad(b, (86, 84, 96), (48, 46, 56)); A.noise(b, [(66, 64, 76), (34, 32, 42)], 3, 5)
    b = A.alloc("moss_brick", 16, 16)
    A.grad(b, (112, 118, 106), (74, 80, 70))
    for y in range(b[1], b[3], 4):
        A.d.line([(b[0], y), (b[2] - 1, y)], fill=(52, 56, 50))
    for i, y in enumerate(range(b[1], b[3], 4)):
        A.d.line([(b[0] + (4 + i * 6) % 16, y), (b[0] + (4 + i * 6) % 16, min(y + 3, b[3] - 1))], fill=(52, 56, 50))
    A.noise(b, [(88, 150, 74), (58, 108, 50), (150, 160, 140)], 4, 7)
    b = A.alloc("brick_top", 16, 16)
    A.grad(b, (128, 126, 136), (86, 84, 94)); A.noise(b, [(150, 148, 158), (70, 68, 78)], 4, 9)
    b = A.alloc("slate", 16, 16)
    A.grad(b, (58, 56, 72), (32, 30, 44)); A.noise(b, [(74, 70, 92), (24, 22, 34)], 3, 11)
    b = A.alloc("plaque", 32, 8)
    A.grad(b, (74, 74, 88), (44, 44, 56)); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(24, 22, 30))
    A.ctext("ARVAN", (b[0] + b[2]) // 2, b[1] + 2, a_accent, (18, 16, 22))
    b = A.alloc("cap_rune", 24, 10)
    A.grad(b, (52, 50, 66), (30, 28, 42)); A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(88, 86, 108))
    draw_rune(A, (b[0] + 7, b[1] + 1, b[2] - 7, b[3] - 1), v["rune"], v["col"], v["glow"])

    # ---------- کدو فانوس
    b = A.alloc("pumpkin_side", 16, 16)
    A.grad(b, (200, 118, 62), (146, 76, 38))
    for x in range(b[0], b[2], 4):
        A.d.line([(x, b[1]), (x, b[3] - 1)], fill=(150, 62, 16))
        A.d.line([(x + 1, b[1]), (x + 1, b[3] - 1)], fill=(248, 152, 62))
    A.noise(b, [(168, 92, 46), (120, 62, 30)], 3, 3)
    b = A.alloc("pumpkin_top", 16, 16)
    A.grad(b, (176, 96, 48), (124, 62, 30))
    for x in range(b[0], b[2], 5):
        A.d.line([(x, b[1]), (x, b[3] - 1)], fill=(150, 62, 16))
    A.d.ellipse([b[0] + 6, b[1] + 6, b[0] + 9, b[1] + 9], fill=(96, 74, 34))
    b = A.alloc("pumpkin_bot", 16, 16)
    A.grad(b, (150, 78, 38), (98, 48, 22)); A.noise(b, [(124, 60, 28), (170, 88, 44)], 4, 13)
    b = A.alloc("stem", 8, 8)
    A.grad(b, (120, 144, 70), (66, 92, 44)); A.noise(b, [(150, 172, 88), (48, 70, 34)], 3, 17)
    # چهره‌ی کدو (۴ شکل، ۸ واریانت روش پخش می‌شن)
    b = A.alloc("pumpkin_face", 16, 16)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(26, 12, 6))
    face = v["face"]
    if face == 0:      # عصبانی
        A.d.polygon([(1, 3), (6, 6), (1, 8)], fill=flame)
        A.d.polygon([(14, 3), (9, 6), (14, 8)], fill=flame)
        A.d.polygon([(3, 11), (6, 9), (9, 9), (12, 11), (9, 13), (6, 13)], fill=flame)
        A.d.line([(3, 11), (12, 11)], fill=flame2)
    elif face == 1:    # خندان با دندان
        A.d.polygon([(1, 2), (5, 5), (1, 7)], fill=flame)
        A.d.polygon([(14, 2), (10, 5), (14, 7)], fill=flame)
        A.d.rectangle([3, 9, 12, 12], fill=flame)
        for x in (4, 6, 8, 10):
            A.d.rectangle([x, 9, x, 10], fill=flame2)
    elif face == 2:    # ترسیده
        A.d.ellipse([1, 2, 5, 7], fill=flame)
        A.d.ellipse([10, 2, 14, 7], fill=flame)
        A.d.ellipse([6, 9, 9, 13], fill=flame)
        A.px(b[0] + 3, b[1] + 5, flame2); A.px(b[0] + 12, b[1] + 5, flame2)
    else:              # پهن و شیطانی
        A.d.polygon([(0, 3), (7, 6), (0, 9)], fill=flame)
        A.d.polygon([(15, 3), (8, 6), (15, 9)], fill=flame)
        A.d.polygon([(2, 10), (13, 10), (11, 13), (4, 13)], fill=flame)
        A.d.line([(4, 11), (11, 11)], fill=flame2)
    for (cx_, cy_), c in [((1, 2), WHITE), ((14, 2), WHITE), ((1, 13), WHITE), ((14, 13), WHITE)]:
        A.d.rectangle([b[0] + cx_, b[1] + cy_, b[0] + cx_ + 1, b[1] + cy_ + 1], fill=c)

    # ---------- شمع و شعله
    b = A.alloc("candle", 8, 8)
    A.grad(b, (238, 232, 210), (188, 180, 158)); A.noise(b, [(210, 204, 184), (166, 158, 138)], 4, 19)
    A.d.line([(b[0] + 2, b[1]), (b[0] + 5, b[1])], fill=(250, 246, 228))
    b = A.alloc("flame", 8, 8)
    A.grad(b, flame, flame2)
    A.d.polygon([(3, 0), (5, 3), (5, 7), (2, 7)], fill=flame)
    A.d.line([(3, 2), (3, 6)], fill=(255, 255, 220))
    A.noise(b, [(255, 236, 170)], 5, 23)

    # ---------- غبار جادویی (قفس هولوگرام)
    b = A.alloc("dust", 8, 8)
    A.d.line([(b[0] + 3, b[1] + 1), (b[0] + 4, b[1] + 1)], fill=(255, 255, 240))
    A.d.line([(b[0] + 2, b[1] + 2), (b[0] + 5, b[1] + 2)], fill=v["glow"])
    A.d.line([(b[0] + 2, b[1] + 3), (b[0] + 5, b[1] + 3)], fill=mix(v["glow"], a_accent, 0.6))
    A.d.line([(b[0] + 3, b[1] + 4), (b[0] + 4, b[1] + 4)], fill=a_accent)
    A.px(b[0] + 1, b[1] + 3, mix(v["glow"], v["col"], 0.4))
    A.px(b[0] + 6, b[1] + 2, mix(v["glow"], v["col"], 0.4))

    # ---------- جمجمه
    b = A.alloc("skull_f", 8, 8)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(228, 226, 214))
    A.d.rectangle([b[0] + 1, b[1] + 2, b[0] + 2, b[1] + 4], fill=(30, 26, 34))
    A.d.rectangle([b[0] + 5, b[1] + 2, b[0] + 6, b[1] + 4], fill=(30, 26, 34))
    A.d.rectangle([b[0] + 3, b[1] + 4, b[0] + 4, b[1] + 5], fill=(30, 26, 34))
    A.d.rectangle([b[0] + 2, b[1] + 6, b[0] + 5, b[1] + 7], fill=(206, 202, 190))
    A.d.line([(b[0] + 2, b[1] + 6), (b[0] + 2, b[1] + 7)], fill=(38, 32, 40))
    A.d.line([(b[0] + 5, b[1] + 6), (b[0] + 5, b[1] + 7)], fill=(38, 32, 40))
    b = A.alloc("skull_s", 8, 8)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(212, 210, 198))
    A.d.rectangle([b[0] + 5, b[1] + 2, b[0] + 7, b[1] + 5], fill=(190, 188, 176))
    A.noise(b, [(196, 194, 182)], 4, 29)

    # ---------- تار عنکبوت + مه
    # تار عنکبوت واقعی: رادیال + کمان‌ها، پس‌زمینه شفاف
    b = A.alloc("web", 16, 16)
    webc, webd = (232, 232, 244, 255), (196, 196, 214, 255)
    cx16 = cy16 = 0  # گوشه‌ی بالا-چپ به‌عنوان مرکز تار
    for i in range(0, 17, 4):
        A.d.line([(b[0] + cx16, b[1] + cy16), (b[0] + i, b[3] - 1)], fill=webc)
        A.d.line([(b[0] + cx16, b[1] + cy16), (b[2] - 1, b[1] + i)], fill=webc)
    for rr in (6, 11):
        A.d.arc([b[0] + cx16 - rr, b[1] + cy16 - rr, b[0] + cx16 + rr, b[1] + cy16 + rr], 0, 90, fill=webd)
    A.d.arc([b[0] - 13, b[1] - 13, b[0] + 13, b[1] + 13], 0, 90, fill=webc)   # رشته‌ی بلند
    A.d.line([(b[0] + 13, b[1] + 1), (b[0] + 13, b[1] + 6)], fill=webd)       # آویز پاره
    # قطره‌های شبنم
    for (dx, dy) in [(7, 4), (11, 8)]:
        A.px(b[0] + dx, b[1] + dy, (250, 250, 255, 255))

    # حلقه‌ی رونیک افقی (قاب بالای پایه / زیر تاج) — بدنه‌ی ابسیدین + رون‌های نورانی
    b = A.alloc("rune_ring", 32, 32)
    d = A.d
    for y in range(32):
        for x in range(32):
            dx, dy = x - 15.5, y - 15.5
            rr = math.hypot(dx, dy)
            if not (9.5 <= rr <= 15.5):
                continue
            h = ((x * 73856093) ^ (y * 19349663)) & 0xFFFF
            if rr < 10.6 or rr > 14.4:                     # لبه‌ها: رگه‌ی نور
                A.px(b[0] + x, b[1] + y, mix(v["glow"], a_accent, 0.5) if h % 3 else (255, 250, 235, 255))
            elif h % 5 == 0:                               # بافت ابسیدین
                A.px(b[0] + x, b[1] + y, (46, 40, 62, 255))
            else:
                A.px(b[0] + x, b[1] + y, (26, 22, 38, 255))
    for i in range(12):                                    # ۱۲ رون نورانی
        ang = math.radians(30 * i + 15)
        for t in range(3):
            px_, py_ = 15.5 + (11.0 + t) * math.cos(ang), 15.5 + (11.0 + t) * math.sin(ang)
            A.px(b[0] + int(round(px_)), b[1] + int(round(py_)), v["col"] if i % 2 else v["glow"])
    for i in range(24):                                    # تیک‌های ریز بین رون‌ها
        ang = math.radians(15 * i)
        px_, py_ = 15.5 + 12.5 * math.cos(ang), 15.5 + 12.5 * math.sin(ang)
        A.px(b[0] + int(round(px_)), b[1] + int(round(py_)), a_accent)

    b = A.alloc("mist", 32, 32)
    for y in range(32):
        for x in range(32):
            dx, dy = x - 15.5, y - 15.5
            rr = math.hypot(dx, dy)
            if rr > 15:
                continue
            h = ((x * 73856093) ^ (y * 19349663)) & 0xFFFF
            if rr < 5:
                c = (150, 210, 200, 150) if h % 3 else (190, 240, 220, 190)
            elif rr < 11:
                c = (140, 200, 195, 120) if h % 4 else (180, 235, 215, 165)
            else:
                c = (130, 190, 190, 90) if h % 6 else (170, 230, 210, 140)
            A.px(b[0] + x, b[1] + y, c)

    # ---------- تاج: رون، هاب، فانوس
    b = A.alloc("blade", 8, 16)
    A.grad(b, (60, 58, 78), (34, 32, 46))
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(20, 18, 28))
    draw_rune(A, (b[0] + 1, b[1] + 4, b[2] - 1, b[3] - 3), v["rune"], v["col"], v["glow"])
    A.d.line([(b[0] + 3, b[1] + 1), (b[0] + 4, b[1] + 3)], fill=a_accent)
    b = A.alloc("hub", 16, 12)
    A.grad(b, (70, 68, 92), (40, 38, 54))
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], outline=(22, 20, 30))
    draw_rune(A, (b[0] + 3, b[1] + 1, b[2] - 3, b[3] - 1), v["rune"], v["col"], v["glow"])
    b = A.alloc("hub_top", 16, 16)
    A.grad(b, (48, 46, 64), (28, 26, 38)); A.noise(b, [(64, 62, 84), (22, 20, 32)], 3, 31)
    b = A.alloc("lan_side", 16, 16)
    A.grad(b, (206, 104, 32), (150, 62, 18))
    for x in range(b[0], b[2], 5):
        A.d.line([(x, b[1]), (x, b[3] - 1)], fill=(140, 56, 16))
    A.noise(b, [(230, 126, 44), (166, 74, 22)], 5, 37)
    b = A.alloc("lan_face", 16, 16)
    A.grad(b, (34, 16, 8), (18, 8, 4))
    face = (v["face"] + 1) % 4
    if face % 2 == 0:
        A.d.polygon([(1, 3), (6, 6), (1, 8)], fill=flame)
        A.d.polygon([(14, 3), (9, 6), (14, 8)], fill=flame)
        A.d.polygon([(3, 11), (6, 9), (9, 9), (12, 11), (9, 13), (6, 13)], fill=flame2)
    else:
        A.d.ellipse([1, 2, 5, 7], fill=flame)
        A.d.ellipse([10, 2, 14, 7], fill=flame)
        A.d.rectangle([3, 9, 12, 12], fill=flame2)
        A.d.line([(3, 10), (12, 10)], fill=flame)
    b = A.alloc("lan_top", 16, 16)
    A.grad(b, (162, 86, 42), (112, 54, 26)); A.noise(b, [(188, 102, 50), (132, 66, 32)], 4, 41)
    b = A.alloc("glow", 8, 8)
    A.grad(b, tuple(min(255, c + 40) for c in flame), flame)
    A.d.rectangle([b[0] + 3, b[1] + 3, b[0] + 4, b[1] + 4], fill=(255, 255, 235))
    A.noise(b, [(255, 250, 210)], 3, 43)

    # ---------- خفاش + روح
    b = A.alloc("bat_body", 8, 8)
    A.grad(b, (58, 50, 78), (30, 24, 42)); A.noise(b, [(78, 68, 104), (22, 18, 32)], 3, 47)
    b = A.alloc("bat_eye", 8, 8)
    A.d.rectangle([b[0], b[1], b[2] - 1, b[3] - 1], fill=(40, 30, 46))
    A.d.rectangle([b[0] + 1, b[1] + 3, b[0] + 2, b[1] + 4], fill=(255, 90, 60))
    A.d.rectangle([b[0] + 5, b[1] + 3, b[0] + 6, b[1] + 4], fill=(255, 90, 60))
    b = A.alloc("bat_wing", 12, 8)
    A.grad(b, (70, 60, 96), (36, 30, 52))
    for i in range(3):
        A.d.line([(b[0] + 1, b[1] + 2 + i * 2), (b[2] - 2 - i * 2, b[1] + 1 + i)], fill=(24, 20, 34))
    A.d.line([(b[0], b[1] + 1), (b[2] - 1, b[1] + 1)], fill=(96, 84, 126))
    b = A.alloc("spirit_f", 8, 12)
    for y in range(12):
        wdt = 6 if y < 8 else 6 - (y - 8)
        for x in range(8):
            if 1 + (3 - wdt // 2) <= x < 1 + (3 - wdt // 2) + wdt:
                c = (196, 244, 238, 200) if (x + y) % 3 else (232, 255, 252, 230)
                A.px(b[0] + x, b[1] + y, c)
    A.d.rectangle([b[0] + 2, b[1] + 3, b[0] + 3, b[1] + 4], fill=(40, 70, 80, 255))
    A.d.rectangle([b[0] + 5, b[1] + 3, b[0] + 6, b[1] + 4], fill=(40, 70, 80, 255))
    b = A.alloc("spirit_s", 8, 12)
    for y in range(12):
        wdt = 5 if y < 8 else 5 - (y - 8)
        for x in range(1, 1 + wdt):
            A.px(b[0] + x, b[1] + y, (176, 236, 232, 180) if (x * y) % 4 else (216, 250, 248, 220))
    return A


# ============================================================ geometry
def build_model(v):
    A = build_atlas(v)
    a_accent = v["accent"]
    bones = [
        ("root", [0, 0, 0], None),
        ("base", [0, 0, 0], "root"),
        ("pumpkin", [-8, 20, 0], "root"),
        ("skull", [-7, 20, -7], "root"),
        ("candle_a", [8.5, 22, -9.5], "root"),
        ("candle_b", [8.5, 22, -0.5], "root"),
        ("candle_c", [8.5, 22, 8.5], "root"),
        ("flame_a", [8.5, 22, -9.5], "candle_a"),
        ("flame_b", [8.5, 22, -0.5], "candle_b"),
        ("flame_c", [8.5, 22, 8.5], "candle_c"),
        ("web_a", [12, 17, 12], "root"),
        ("web_b", [-12, 17, -12], "root"),
        ("mist", [0, 5, 0], "root"),
        ("spirit_orbit", [0, 15, 0], "root"),
        ("spirit_a", [28, 15, 0], "spirit_orbit"),
        ("spirit_b", [-28, 15, 0], "spirit_orbit"),
        ("rune_ring_low", [0, 20, 0], "root"),
        ("rune_ring_high", [0, 88, 0], "root"),
        ("top_ring", [0, 92, 0], "root"),
        ("lantern", [0, 98, 0], "root"),
        ("bat_orbit", [0, 108, 0], "root"),
        ("bat", [18, 108, 0], "bat_orbit"),
        ("bat_wing_l", [15, 110, 0], "bat"),
        ("bat_wing_r", [21, 110, 0], "bat"),
        ("embers", [0, 90, 0], "top_ring"),
        ("em_a", [13, 88, -2], "embers"),
        ("em_b", [-13, 86, 3], "embers"),
        ("em_c", [3, 90, 6], "embers"),
    ]
    # قفس غبار جادویی: ۳ مدار، ۴ غبار در هر مدار — ستون متن رو قاب می‌کنه
    dust_orbits = [(32, 21), (52, 24), (72, 21)]
    for oi, (oy, rad) in enumerate(dust_orbits):
        bones.append((f"dust_orbit_{oi}", [0, oy, 0], "root"))
        for di in range(4):
            a = math.radians(90 * di + 45 * oi)
            dx, dz = rad * math.cos(a), rad * math.sin(a)
            bones.append((f"dust_{oi}_{di}", [round(dx), oy, round(dz)], f"dust_orbit_{oi}"))

    ST, STD, MB, BT, SL = "stone", "stone_dark", "moss_brick", "brick_top", "slate"
    C = [
        # ---- پایه (0..17)
        ("b_foot", "base", (-15, 0, -15), (15, 3, 15), F(ST, u=STD, dn=STD)),
        ("b_foot2", "base", (-14, 3, -14), (14, 5, 14), F(STD, u=SL, dn=STD)),
        ("b_body", "base", (-12, 5, -12), (12, 14, 12), F(MB, BT)),
        ("b_top", "base", (-14, 14, -14), (14, 16, 14), F(BT, s=BT, u=BT, dn=MB)),
        ("b_cap", "base", (-12, 16, -12), (12, 17, 12), F(SL, u=SL, dn=BT)),
        ("plaque_f", "base", (-8, 8, 12), (8, 12, 12.6), F("plaque", s="plaque", e=SL, w=SL, u=SL, dn=SL)),
        ("plaque_b", "base", (-8, 8, -12.6), (8, 12, -12), F("plaque", s="plaque", e=SL, w=SL, u=SL, dn=SL)),
        ("cap_rune_f", "base", (-8, 17, 10), (8, 23, 11), F("cap_rune", s="cap_rune", e=SL, w=SL, u=SL, dn=SL)),
        ("cap_rune_b", "base", (-8, 17, -11), (8, 23, -10), F("cap_rune", s="cap_rune", e=SL, w=SL, u=SL, dn=SL)),
        # ---- کدو فانوس (روی پایه، سمت چپ-جلو)
        ("p_body", "pumpkin", (-13, 16, -5), (-3, 22, 5), F("pumpkin_side", e="pumpkin_side", w="pumpkin_side", u="pumpkin_top", dn="pumpkin_bot")),
        ("p_stem", "pumpkin", (-9, 22, 0), (-7, 24, 2), F("stem")),
        ("p_face_f", "pumpkin", (-11, 17.5, 5), (-5, 21.5, 5.6), F("pumpkin_face", s="pumpkin_face")),
        ("p_face_b", "pumpkin", (-11, 17.5, -5.6), (-5, 21.5, -5), F("pumpkin_face", s="pumpkin_face")),
        # ---- جمجمه (پشت-راست)
        ("sk_cranium", "skull", (-1, 16, -11), (5, 21, -5), F("skull_f", s="skull_f", e="skull_s", w="skull_s", u="skull_s")),
        ("sk_jaw", "skull", (0, 16, -7), (4, 18, -6), F("skull_f")),
        # ---- شمع‌ها
        ("c_a", "candle_a", (7, 16, -11), (10, 20, -8), F("candle")),
        ("c_b", "candle_b", (7, 16, -2), (10, 20, 1), F("candle")),
        ("c_c", "candle_c", (7, 16, 7), (10, 20, 10), F("candle")),
        ("f_a", "flame_a", (7, 20, -11), (10, 22, -8), F("flame")),
        ("f_b", "flame_b", (7, 20, -2), (10, 22, 1), F("flame")),
        ("f_c", "flame_c", (7, 20, 7), (10, 22, 10), F("flame")),
        # ---- تار عنکبوت (دو گوشه، دو صفحه‌ی آلفا)
        ("w_a_x", "web_a", (12.6, 17, 12), (13.0, 22, 16.4), F("web", e="web", w="web")),
        ("w_a_z", "web_a", (12, 17, 12.6), (16.4, 22, 13.0), F("web", s="web")),
        ("w_b_x", "web_b", (-16.4, 17, -13.0), (-12.6, 22, -12.6), F("web", e="web", w="web")),
        ("w_b_z", "web_b", (-13.0, 17, -16.4), (-12.6, 22, -12.6), F("web", s="web")),
        # ---- مه زمینی
        ("mist_plate", "mist", (-22, 5, -22), (22, 5.6, 22), F("mist", u="mist", dn="mist")),
        # ---- حلقه‌های رونیک افقی (قاب بالای پایه و زیر تاج — از هیچ زاویه‌ای متن رو نمی‌پوشونن)
        ("ring_low", "rune_ring_low", (-15, 20, -15), (15, 21, 15), F("rune_ring", u="rune_ring", dn="rune_ring")),
        ("ring_high", "rune_ring_high", (-12, 86, -12), (12, 87, 12), F("rune_ring", u="rune_ring", dn="rune_ring")),
        # ---- ارواح در حال گردش (دور پایه، زیر ستون متن)
        ("sp_a", "spirit_a", (24, 9, -4), (32, 21, 4), F("spirit_f", s="spirit_f", e="spirit_s", w="spirit_s", u="spirit_s", dn="spirit_s")),
        ("sp_b", "spirit_b", (-32, 9, -4), (-24, 21, 4), F("spirit_f", s="spirit_f", e="spirit_s", w="spirit_s", u="spirit_s", dn="spirit_s")),
        # ---- تاج: هاب رونی + ۸ تیغه (بالای آخرین خط متن که تا ~۸۶ unit می‌ره)
        ("ring_hub", "top_ring", (-6, 92, -6), (6, 99, 6), F("hub", s="hub", e="hub", u="hub_top", dn="hub_top")),
        # ---- فانوس کدویی آویز
        ("lan_body", "lantern", (-6, 98, -6), (6, 108, 6), F("lan_face", s="lan_face", e="lan_side", w="lan_side", u="lan_top", dn="lan_top")),
        ("lan_stem", "lantern", (-2, 108, -2), (2, 111, 2), F("stem")),
        ("lan_hook", "lantern", (-0.6, 111, -0.6), (0.6, 115, 0.6), F("slate")),
        ("lan_glow", "lantern", (-2, 101, -7), (2, 105, -6.5), F("glow")),
        # ---- خفاش در حال گردش
        ("bat_body", "bat", (15, 106, -3), (21, 112, 3), F("bat_body", u="bat_eye", dn="bat_eye")),
        ("bat_wing_l", "bat_wing_l", (8, 107, -2), (15, 111, 2), F("bat_wing", u="bat_wing", dn="bat_wing")),
        ("bat_wing_r", "bat_wing_r", (21, 107, -2), (28, 111, 2), F("bat_wing", u="bat_wing", dn="bat_wing")),
        # ---- اخگرها
        ("em_a", "embers", (12, 88, -3), (14, 90, -1), F("glow")),
        ("em_b", "embers", (-14, 86, 2), (-12, 88, 4), F("glow")),
        ("em_c", "embers", (2, 90, 5), (4, 92, 7), F("glow")),
    ]
    # ---- غبارها (۴×۴×۴ unit، شناور روی مدارها)
    for oi, (oy, rad) in enumerate(dust_orbits):
        for di in range(4):
            a = math.radians(90 * di + 45 * oi)
            dx, dz = rad * math.cos(a), rad * math.sin(a)
            C.append((f"dust_c_{oi}_{di}", f"dust_{oi}_{di}",
                      (round(dx - 2), oy - 2, round(dz - 2)), (round(dx + 2), oy + 2, round(dz + 2)), F("dust")))
    # تیغه‌های حلقه‌ی رونیک در دایره‌ای به شعاع 12
    for i in range(8):
        a = math.radians(45 * i)
        cx, cy = 12 * math.cos(a), 12 * math.sin(a)
        C.append((f"blade_{i}", "top_ring", (round(cx - 2), 88, round(cy - 1)),
                  (round(cx + 2), 95, round(cy + 1)), F("blade", s="blade", u="blade", dn="blade")))

    anim = {
        "base": {},  # زمین‌گیر، ثابت
        "pumpkin": {"rotation": [0, 0, "math.sin(query.anim_time * 90) * 1.5"]},
        "skull": {"rotation": ["math.sin(query.anim_time * 180) * 3", 0, 0]},
        "flame_a": {"scale": [f"1 + math.sin(query.anim_time * 1080) * 0.22"] * 3,
                    "position": [0, "math.sin(query.anim_time * 540) * 0.4", 0]},
        "flame_b": {"scale": [f"1 + math.sin(query.anim_time * 1080 + 140) * 0.22"] * 3,
                    "position": [0, "math.sin(query.anim_time * 540 + 140) * 0.4", 0]},
        "flame_c": {"scale": [f"1 + math.sin(query.anim_time * 1080 + 260) * 0.22"] * 3,
                    "position": [0, "math.sin(query.anim_time * 540 + 260) * 0.4", 0]},
        "web_a": {"rotation": [0, 0, "math.sin(query.anim_time * 90) * 3"]},
        "web_b": {"rotation": [0, 0, "math.sin(query.anim_time * 90 + 180) * 3"]},
        "mist": {"rotation": [0, "query.anim_time * 90", 0],
                 "scale": ["1 + math.sin(query.anim_time * 90) * 0.03", 1, "1 + math.sin(query.anim_time * 90) * 0.03"]},
        "dust_orbit_0": {"rotation": [0, "query.anim_time * -40", 0]},
        "dust_orbit_1": {"rotation": [0, "query.anim_time * 30", 0]},
        "dust_orbit_2": {"rotation": [0, "query.anim_time * -55", 0]},
        "rune_ring_low": {"rotation": [0, "query.anim_time * 30", 0],
                          "scale": ["1 + math.sin(query.anim_time * 180) * 0.02", 1, "1 + math.sin(query.anim_time * 180) * 0.02"]},
        "rune_ring_high": {"rotation": [0, "query.anim_time * -30", 0],
                           "scale": ["1 + math.sin(query.anim_time * 180 + 90) * 0.02", 1, "1 + math.sin(query.anim_time * 180 + 90) * 0.02"]},
        "spirit_orbit": {"rotation": [0, "query.anim_time * -60", 0]},
        "spirit_a": {"position": [0, "math.sin(query.anim_time * 180) * 1.4", 0],
                     "rotation": [0, 0, "math.sin(query.anim_time * 90) * 5"]},
        "spirit_b": {"position": [0, "math.sin(query.anim_time * 180 + 180) * 1.4", 0],
                     "rotation": [0, 0, "math.sin(query.anim_time * 90 + 90) * 5"]},
        "top_ring": {"rotation": [0, "query.anim_time * 90", 0]},
        "embers": {"rotation": [0, "query.anim_time * -180", 0]},
        "em_a": {"position": [0, "math.abs(math.sin(query.anim_time * 180)) * 5", 0]},
        "em_b": {"position": [0, "math.abs(math.sin(query.anim_time * 180 + 90)) * 6", 0]},
        "em_c": {"position": [0, "math.abs(math.sin(query.anim_time * 180 + 200)) * 4", 0]},
        "lantern": {"position": [0, "math.sin(query.anim_time * 180) * 2", 0],
                    "rotation": ["math.sin(query.anim_time * 90) * 3", "query.anim_time * 90", 0]},
        "bat_orbit": {"rotation": [0, "query.anim_time * -180", 0]},
        "bat": {"position": [0, "math.sin(query.anim_time * 270) * 1.4", 0]},
        "bat_wing_l": {"rotation": [0, 0, "20 + math.sin(query.anim_time * 1080) * 26"]},
        "bat_wing_r": {"rotation": [0, 0, "-20 - math.sin(query.anim_time * 1080) * 26"]},
    }
    # غبارها: بالا-پایین با فاز متفاوت + چرخش آروم
    for oi, (oy, rad) in enumerate(dust_orbits):
        for di in range(4):
            ph = 90 * di + 45 * oi
            anim[f"dust_{oi}_{di}"] = {
                "position": [0, f"math.sin(query.anim_time * 120 + {ph}) * 3", 0],
                "rotation": [0, f"query.anim_time * {20 + oi * 10}", 0],
                "scale": [f"{1.0 - 0.25 * oi * 0.1} + math.sin(query.anim_time * 240 + {ph}) * 0.15"] * 3,
            }
    return A, bones, C, anim


# ============================================================ exporters
def build(name, ident, atlas, bones, cubes, anim, length=4, scale=1.0, bounds=(2.6, 6.8, 2)):
    R = atlas.R
    tex_path = os.path.join(OUT, f"{name}.png")
    atlas.img.save(tex_path)

    els, kids = [], {b[0]: [] for b in bones}
    for n, b, f, t, fc, *rest in cubes:
        u = str(uuid.uuid4()); kids[b].append(u)
        e = {"name": n, "type": "cube", "uuid": u, "box_uv": False, "from": list(f), "to": list(t),
             "origin": list(rest[1]) if rest else [0, 0, 0],
             "faces": {k: {"uv": [R[v][0], R[v][1], R[v][0] + R[v][2], R[v][1] + R[v][3]], "texture": 0}
                       for k, v in fc.items()}}
        if rest:
            e["rotation"] = list(rest[0])
        els.append(e)
    buid = {b[0]: str(uuid.uuid4()) for b in bones}

    def outl(parent):
        res = []
        for bn, piv, par, *br in bones:
            if par == parent:
                o = {"name": bn, "origin": piv, "uuid": buid[bn], "export": True, "isOpen": True,
                     "visibility": True, "children": kids[bn] + outl(bn)}
                res.append(o)
        return res

    b64 = base64.b64encode(open(tex_path, "rb").read()).decode()
    aname = f"animation.{name}.idle"
    bb = {"meta": {"format_version": "4.10", "model_format": "bedrock", "box_uv": False},
          "name": name, "model_identifier": name, "visible_box": list(bounds),
          "resolution": {"width": atlas.W, "height": atlas.H}, "elements": els, "outliner": outl(None),
          "textures": [{"path": "", "name": f"{name}.png", "id": "0", "width": atlas.W, "height": atlas.H,
                        "uv_width": atlas.W, "uv_height": atlas.H, "render_mode": "default", "visible": True,
                        "internal": True, "saved": True, "uuid": str(uuid.uuid4()),
                        "source": "data:image/png;base64," + b64}],
          "animations": [{"uuid": str(uuid.uuid4()), "name": aname, "loop": "loop", "override": False,
                          "length": length, "snapping": 24,
                          "animators": {buid[bn]: {"name": bn, "type": "bone", "keyframes": [
                              {"channel": ch, "data_points": [dict(zip("xyz", [str(x) for x in v]))],
                               "uuid": str(uuid.uuid4()), "time": 0, "color": -1, "interpolation": "linear"}
                              for ch, v in chans.items() if chans]} for bn, chans in anim.items()}}]}
    with open(os.path.join(OUT, f"{name}.bbmodel"), "w") as fh:
        json.dump(bb, fh, indent=1)

    # ---- Bedrock geometry (X mirrored, مثل خروجی Blockbench)
    gb = []
    for bn, piv, par, *br in bones:
        cs = []
        for n, b, f, t, fc, *rest in cubes:
            if b != bn:
                continue
            c = {"origin": [-t[0], f[1], f[2]], "size": [round(t[i] - f[i], 3) for i in range(3)],
                 "uv": {k: {"uv": [R[v][0], R[v][1]], "uv_size": [R[v][2], R[v][3]]} for k, v in fc.items()}}
            if rest:
                rx, ry, rz = rest[0]
                c["rotation"] = [-rx, -ry, rz]
                c["pivot"] = [-rest[1][0], rest[1][1], rest[1][2]]
            cs.append(c)
        bone = {"name": bn, "pivot": [-piv[0], piv[1], piv[2]], "cubes": cs}
        if par:
            bone["parent"] = par
        gb.append(bone)
    geo = {"format_version": "1.12.0", "minecraft:geometry": [{
        "description": {"identifier": f"geometry.{name}", "texture_width": atlas.W, "texture_height": atlas.H,
                        "visible_bounds_width": round(bounds[0] * scale * 1.5, 2),
                        "visible_bounds_height": round(bounds[1] * scale * 1.5, 2),
                        "visible_bounds_offset": [0, round(bounds[1] * scale / 2, 2), 0]}, "bones": gb}]}
    an = {"format_version": "1.8.0", "animations": {aname: {"loop": True, "bones": {
        bn: {ch: v for ch, v in chans.items()} for bn, chans in anim.items() if chans}}}}
    ent = {"format_version": "1.10.0", "minecraft:client_entity": {"description": {
        "identifier": ident, "materials": {"default": "entity_alphatest"},
        "textures": {"default": f"textures/entity/{name}"}, "geometry": {"default": f"geometry.{name}"},
        "animations": {"idle": aname}, "scripts": {"animate": ["idle"], "scale": str(scale)},
        "render_controllers": ["controller.render.arvan_leaderboard"]}}}
    return dict(name=name, ident=ident, geo=geo, anim=an, ent=ent, tex=tex_path, R=R, img=atlas.img)


# ============================================================ resource pack
def write_pack(models):
    RP = os.path.join(OUT, "ArvanLeaderboard_RP")
    if os.path.isdir(RP):
        shutil.rmtree(RP)
    for sub in ("entity", "models/entity", "animations", "render_controllers", "textures/entity", "texts"):
        os.makedirs(os.path.join(RP, sub), exist_ok=True)

    first = models[0]
    with open(os.path.join(RP, "models/entity/arvan_leaderboard.geo.json"), "w") as fh:
        json.dump(first["geo"], fh, indent=1)
    with open(os.path.join(RP, "animations/arvan_leaderboard.animation.json"), "w") as fh:
        json.dump(first["anim"], fh, indent=1)
    with open(os.path.join(RP, "render_controllers/arvan_leaderboard.render_controllers.json"), "w") as fh:
        json.dump({"format_version": "1.8.0", "render_controllers": {
            "controller.render.arvan_leaderboard": {
                "geometry": "Geometry.default",
                "materials": [{"*": "Material.default"}],
                "textures": ["Texture.default"]}}}, fh, indent=1)

    lang = []
    for m in models:
        ent = m["ent"]
        desc = ent["minecraft:client_entity"]["description"]
        desc["geometry"] = {"default": "geometry.arvan_leaderboard"}
        desc["animations"] = {"idle": "animation.arvan_leaderboard.idle"}
        # هر واریانت تکسچر خودش رو داره (رون/رنگ شعله/چهره‌ی کدو فرق می‌کنه)
        desc["textures"] = {"default": f"textures/entity/arvan_leaderboard_{m['stat']}"}
        fname = m["ident"].replace(":", "_") + ".entity.json"
        with open(os.path.join(RP, "entity", fname), "w") as fh:
            json.dump(ent, fh, indent=1)
        shutil.copyfile(m["tex"], os.path.join(RP, "textures/entity", f"arvan_leaderboard_{m['stat']}.png"))
        lang.append(f"entity.{m['ident']}.name=Halloween {m['stat'].replace('_', ' ').title()} Pedestal")

    with open(os.path.join(RP, "texts/en_US.lang"), "w") as fh:
        fh.write("\n".join(lang) + "\n")
    with open(os.path.join(RP, "texts/languages.json"), "w") as fh:
        json.dump(["en_US"], fh)

    manifest = {
        "format_version": 2,
        "header": {"name": "§6§lArvan§fGaming §eHalloween Leaderboards",
                   "description": "§6BedWars §7leaderboard pedestals §8| §aJack-o'-lantern, bat, runes, spirits",
                   "uuid": str(uuid.uuid5(NS, "arvan-leaderboard-rp-header")), "version": [1, 0, 0],
                   "min_engine_version": [1, 20, 0]},
        "modules": [{"type": "resources", "uuid": str(uuid.uuid5(NS, "arvan-leaderboard-rp-module")),
                     "version": [1, 0, 0]}],
        "metadata": {"authors": ["ArvanGaming"], "license": "proprietary"},
    }
    with open(os.path.join(RP, "manifest.json"), "w") as fh:
        json.dump(manifest, fh, indent=2)

    # pack icon: کدو فانوس روی پس‌زمینه‌ی تیره
    icon = Image.new("RGBA", (128, 128), (26, 18, 34, 255))
    d = ImageDraw.Draw(icon)
    for i in range(28):
        d.line([(0, 96 + i), (128, 90 + i)], fill=(44 + i, 26 + i // 2, 52))
    icon.paste(models[0]["img"].crop((0, 0, 96, 96)).resize((96, 96), Image.NEAREST), (16, 18))
    d = ImageDraw.Draw(icon)
    d.rectangle([8, 8, 119, 119], outline=(255, 176, 60, 255))
    icon.save(os.path.join(RP, "pack_icon.png"))

    def zipdir(path, dest):
        with zipfile.ZipFile(dest, "w", zipfile.ZIP_DEFLATED) as z:
            for root, _, files in os.walk(path):
                for fn in sorted(files):
                    full = os.path.join(root, fn)
                    z.write(full, os.path.relpath(full, path))

    zipdir(RP, os.path.join(OUT, "ArvanLeaderboard_RP.zip"))
    zipdir(RP, os.path.join(OUT, "ArvanLeaderboard.mcpack"))
    return RP


# ============================================================ previews
def text_band(A_text):
    """۱۳ خط متن هولوگرام (mock) برای پیش‌نمایش هم‌ترازی — UV + تصویر.

    نکته: خط ۰ پایین‌ترین خط روی مدل است (هم‌جا با BASE_TOP)، پس تایتل باید در
    آخرین ناحیه‌ی آتلاس (پایین‌ترین) کشیده بشه، وگرنه در پیش‌نمایش برعکس دیده می‌شه.
    """
    regions = {}
    for i in range(LINES):
        regions[f"line{i}"] = A_text.alloc(f"line{i}", 64, 6)
    regions["blank"] = A_text.alloc("blank", 1, 1)
    palette = [(255, 92, 92), (120, 235, 120), (255, 236, 120), (255, 176, 60), (255, 140, 205),
               (120, 220, 250), (120, 200, 120), (255, 236, 120), (196, 132, 255), (240, 240, 240)]

    def draw_in(name, s, col):
        x0, y0, x1, _ = regions[name]          # هر خط در سلول خودش وسط‌چین می‌شه
        A_text.ctext(s, (x0 + x1) // 2, y0 + 1, col, (18, 14, 22))

    draw_in("line0", "KILLS LEADERBOARD", (255, 92, 92))
    draw_in("line1", "LIVE RANKINGS", (150, 150, 160))
    draw_in("line2", "-", (90, 90, 100))
    names = ["NOTCH", "DINNERBONE", "HEROBRINE", "STEVE", "ALEX", "JEB", "GRUMPY", "ARVAN", "PLAYER", "NOOB"]
    for i in range(10):
        draw_in(f"line{i + 3}", f"{i + 1}- {names[i]} - {12345 - i * 987}", palette[i % len(palette)])
    return regions


def scene_bbmodel(model, variant, out_path):
    """bbmodel پیش‌نمایش: مدل + ۱۳ صفحه‌ی متن هولوگرام در جای واقعی‌شون."""
    bb = json.load(open(os.path.join(OUT, f"{model['name']}.bbmodel")))
    atlas = build_atlas(variant)
    band = Atlas(TEX_W, 96)
    regions = text_band(band)
    canvas = Image.new("RGBA", (TEX_W, TEX_H + 96), (0, 0, 0, 0))
    canvas.paste(atlas.img, (0, 0))
    canvas.paste(band.img, (0, TEX_H))
    els = bb["elements"]
    line_uuids = []
    y = BASE_TOP
    for i in range(LINES):
        u = str(uuid.uuid4()); line_uuids.append(u)
        # خط 0 پلاگین = تایتل و بالاترین y رو داره، پس روی بالاترین پلاک می‌شینه
        r = regions[f"line{LINES - 1 - i}"]
        rb = regions["blank"]
        uv = [r[0], r[1] + TEX_H, r[2], r[3] + TEX_H]      # alloc() باکس (x0,y0,x1,y1) می‌ده
        uvb = [rb[0], rb[1] + TEX_H, rb[2], rb[3] + TEX_H]
        faces = {k: {"uv": uv if k in ("north", "south") else uvb, "texture": 0}
                 for k in ("north", "south", "east", "west", "up", "down")}
        els.append({"name": f"hologram_line_{i}", "type": "cube", "uuid": u, "box_uv": False,
                    "from": [-22, y, -0.2], "to": [22, y + 4.4, 0.2], "origin": [0, 0, 0],
                    "faces": faces})
        y += SPACING
    bb["elements"] = els
    bb["outliner"].append({"name": "hologram_text", "origin": [0, 0, 0], "uuid": str(uuid.uuid4()),
                           "export": False, "isOpen": True, "visibility": True, "children": line_uuids})
    bb["textures"][0]["source"] = "data:image/png;base64," + base64.b64encode(_png(canvas)).decode()
    bb["textures"][0]["height"] = TEX_H + 96
    with open(out_path, "w") as fh:
        json.dump(bb, fh, indent=1)


def _png(img):
    import io
    buf = io.BytesIO()
    img.save(buf, format="PNG")
    return buf.getvalue()


def _load_renderer():
    import importlib.util
    rp = os.path.join(os.path.dirname(OUT), "lobby", "render_preview.py")
    spec = importlib.util.spec_from_file_location("render_preview", rp)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


_renderer = _load_renderer()


def render(path, out, yaw=-28, pitch=18, size=900):
    _renderer.render(path, out, yaw, pitch, size)


# ============================================================ main
def main():
    models = []
    for v in VARIANTS:
        A, bones, C, anim = build_model(v)
        m = build("arvan_leaderboard", v["net"], A, bones, C, anim, 4, 1.0, (2.6, 7.2, 2))
        m["stat"] = v["stat"]
        models.append(m)
    write_pack(models)

    # پیش‌نمایش‌ها
    tmp = "/tmp/lb_preview"
    os.makedirs(tmp, exist_ok=True)
    scene = os.path.join(tmp, "scene.bbmodel")
    scene_bbmodel(models[0], VARIANTS[0], scene)
    render(scene, os.path.join(OUT, "preview_halloween_board.png"), -30, 16, 900)
    render(scene, os.path.join(OUT, "preview_halloween_front.png"), 0, 6, 900)
    render(os.path.join(OUT, "arvan_leaderboard.bbmodel"), os.path.join(OUT, "preview_halloween_decor.png"), -35, 20, 900)
    Image.open(models[0]["tex"]).resize((768, 768), Image.NEAREST).save(os.path.join(OUT, "preview_texture.png"))

    # ورق واریانت‌ها: ۸ تاج/کدو در کنار هم (فقط برای مرور سریع)
    sheet = Image.new("RGB", (8 * 128, 128), (24, 18, 32))
    for i, v in enumerate(VARIANTS):
        A = build_atlas(v)
        crop = A.img.crop((0, 0, 128, 128)).resize((128, 128), Image.NEAREST)
        sheet.paste(crop.convert("RGB"), (i * 128, 0))
    sheet.save(os.path.join(OUT, "preview_variants.png"))
    print("done:", ", ".join(m["ident"] for m in models))


if __name__ == "__main__":
    main()
