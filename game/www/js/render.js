/* render.js — موتور گرافیکی: آسمان، پارالاکس، کاشی‌ها، تزئینات ایرانی، موجودات و HUD */

import { TILE, THEMES, DIFFICULTY_LABEL } from './config.js';
import { T } from './levels.js';
import { Sprites } from './sprites.js';
const Sprite = Sprites;
import { clamp, fa, faTime, shade, rand, makeRng } from './utils.js';
import { POWER } from './config.js';
import { Boss } from './entities.js';

export const VIEW_H = 240;

export class Renderer {
  constructor(canvas) {
    this.canvas = canvas;
    this.g = canvas.getContext('2d');
    this.scale = 1;
    this.viewW = 400;
    this.viewH = VIEW_H;
    this.quality = 1;
    this.lowQuality = false;      // تنظیم «کیفیت» بازی: کمتر کردن جلوه‌های گران
    this.shadows = true;          // سایهٔ زیر شخصیت‌ها و دشمنان
    this.atlas = {};
    this.decorCache = {};
    this.time = 0;
    this.clouds = [];
    this.stars = [];
    this.fade = 0;
    this.titleT = 0;
    this._initSky();
  }

  _initSky() {
    const rng = makeRng(4242);
    for (let i = 0; i < 14; i++) {
      this.clouds.push({ x: rng.range(0, 4000), y: rng.range(10, 90), s: rng.range(0.6, 1.5), v: rng.range(3, 9) });
    }
    for (let i = 0; i < 70; i++) {
      this.stars.push({ x: rng.range(0, 1), y: rng.range(0, 0.7), r: rng.range(0.5, 1.4), tw: rng.range(0, 6.28) });
    }
  }

  resize(cssW, cssH, dpr = 1) {
    this.canvas.width = Math.floor(cssW * dpr);
    this.canvas.height = Math.floor(cssH * dpr);
    const rawScale = this.canvas.height / VIEW_H;
    this.scale = rawScale;
    this.viewW = Math.round(this.canvas.width / rawScale);
    this.viewH = VIEW_H;
    this.dpr = dpr;
    this.g.imageSmoothingEnabled = false;
    return { w: this.viewW, h: this.viewH };
  }

  /* ------------------------------- کاشی‌ها ------------------------------- */
  buildAtlas(theme) {
    if (this.atlas[theme.id]) return this.atlas[theme.id];
    const mk = (fn) => { const cv = document.createElement('canvas'); cv.width = TILE; cv.height = TILE; fn(cv.getContext('2d')); return cv; };
    const th = theme;
    const atlas = {};
    atlas[T.GROUND] = mk((g) => {
      g.fillStyle = th.ground; g.fillRect(0, 0, 16, 16);
      g.fillStyle = th.groundTop; g.fillRect(0, 0, 16, 5);
      g.fillStyle = shade(th.groundTop, -0.12); g.fillRect(0, 5, 16, 2);
      g.fillStyle = shade(th.ground, -0.16); g.fillRect(0, 11, 16, 2);
    });
    atlas[T.GROUND_DARK] = mk((g) => {
      g.fillStyle = th.groundDark; g.fillRect(0, 0, 16, 16);
      g.fillStyle = shade(th.groundDark, -0.1); g.fillRect(0, 6, 16, 3);
      g.fillStyle = shade(th.groundDark, 0.06); g.fillRect(0, 0, 16, 2);
    });
    atlas[T.BRICK] = mk((g) => {
      g.fillStyle = th.brick; g.fillRect(0, 0, 16, 16);
      g.fillStyle = th.brickDark;
      g.fillRect(0, 0, 16, 1); g.fillRect(0, 7, 16, 1); g.fillRect(0, 15, 16, 1);
      g.fillRect(7, 0, 1, 7); g.fillRect(0, 8, 1, 7); g.fillRect(15, 8, 1, 7); g.fillRect(11, 8, 1, 7);
    });
    atlas[T.BRICK_COIN] = atlas[T.BRICK];
    atlas[T.QUESTION] = mk((g) => {
      g.fillStyle = th.block; g.fillRect(0, 0, 16, 16);
      g.fillStyle = th.blockDark;
      g.fillRect(0, 0, 16, 1); g.fillRect(0, 15, 16, 1); g.fillRect(0, 0, 1, 16); g.fillRect(15, 0, 1, 16);
      g.fillRect(2, 2, 12, 1); g.fillRect(2, 13, 12, 1);
      g.fillStyle = '#ffffff'; g.globalAlpha = 0.5; g.fillRect(2, 2, 12, 2); g.globalAlpha = 1;
      g.fillStyle = th.blockDark; g.font = 'bold 11px monospace'; g.textAlign = 'center';
      g.fillText('?', 8, 12);
      g.textAlign = 'left';
    });
    atlas[T.USED] = mk((g) => {
      g.fillStyle = shade(th.brickDark, -0.12); g.fillRect(0, 0, 16, 16);
      g.fillStyle = shade(th.brickDark, -0.25);
      g.fillRect(0, 0, 16, 1); g.fillRect(0, 15, 16, 1); g.fillRect(0, 0, 1, 16); g.fillRect(15, 0, 1, 16);
      g.fillRect(4, 4, 8, 8);
    });
    atlas[T.BLOCK] = mk((g) => {
      g.fillStyle = th.block; g.fillRect(0, 0, 16, 16);
      g.fillStyle = shade(th.block, -0.18);
      g.fillRect(0, 12, 16, 4); g.fillRect(12, 0, 4, 16);
      g.fillStyle = shade(th.block, 0.16); g.fillRect(0, 0, 16, 3); g.fillRect(0, 0, 3, 16);
    });
    atlas[T.SEMI] = mk((g) => {
      g.fillStyle = th.stone; g.fillRect(0, 0, 16, 6);
      g.fillStyle = shade(th.stone, 0.2); g.fillRect(0, 0, 16, 2);
      g.fillStyle = th.stoneDark; g.fillRect(0, 6, 16, 2);
      g.globalAlpha = 0.35; g.fillStyle = th.accent; g.fillRect(2, 2, 12, 2); g.globalAlpha = 1;
    });
    atlas[T.SPIKE] = mk((g) => {
      g.fillStyle = th.stoneDark; g.fillRect(0, 8, 16, 8);
      g.fillStyle = '#d8d8e0';
      for (let i = 0; i < 3; i++) {
        g.beginPath();
        const x = 2 + i * 5;
        g.moveTo(x, 14); g.lineTo(x + 2.5, 1); g.lineTo(x + 5, 14); g.closePath(); g.fill();
      }
      g.fillStyle = shade(th.stoneDark, -0.2); g.fillRect(0, 14, 16, 2);
    });
    atlas[T.WATER] = mk((g) => {
      g.fillStyle = th.water; g.fillRect(0, 0, 16, 16);
      g.fillStyle = shade(th.water, 0.2); g.fillRect(0, 0, 16, 3);
      g.fillStyle = th.waterDark; g.globalAlpha = 0.5; g.fillRect(0, 9, 16, 2); g.globalAlpha = 1;
    });
    atlas[T.CRUMBLE] = mk((g) => {
      g.fillStyle = th.stone; g.fillRect(0, 0, 16, 16);
      g.fillStyle = th.stoneDark;
      g.fillRect(0, 0, 16, 1); g.fillRect(0, 15, 16, 1); g.fillRect(0, 0, 1, 16); g.fillRect(15, 0, 1, 16);
      g.fillRect(6, 2, 1, 12); g.fillRect(11, 2, 1, 12);
      g.globalAlpha = 0.6; g.fillStyle = '#000'; g.fillRect(3, 7, 4, 1); g.fillRect(8, 10, 4, 1); g.globalAlpha = 1;
    });
    atlas[T.HIDDEN] = mk((g) => { g.fillStyle = '#00000000'; });
    atlas[T.PIPE_TL] = mk((g) => {
      g.fillStyle = th.pipe; g.fillRect(0, 2, 16, 14);
      g.fillStyle = shade(th.pipe, 0.2); g.fillRect(1, 2, 4, 14);
      g.fillStyle = th.pipeDark; g.fillRect(0, 2, 16, 2); g.fillRect(0, 13, 16, 3);
    });
    atlas[T.PIPE_TR] = mk((g) => {
      g.fillStyle = th.pipe; g.fillRect(0, 2, 16, 14);
      g.fillStyle = shade(th.pipe, -0.16); g.fillRect(11, 2, 5, 14);
      g.fillStyle = th.pipeDark; g.fillRect(0, 2, 16, 2); g.fillRect(0, 13, 16, 3);
    });
    atlas[T.PIPE_BL] = mk((g) => {
      g.fillStyle = th.pipe; g.fillRect(0, 0, 16, 16);
      g.fillStyle = shade(th.pipe, 0.2); g.fillRect(1, 0, 4, 16);
      g.fillStyle = th.pipeDark; g.fillRect(0, 0, 1, 16); g.fillRect(14, 0, 2, 16);
    });
    atlas[T.PIPE_BR] = mk((g) => {
      g.fillStyle = th.pipe; g.fillRect(0, 0, 16, 16);
      g.fillStyle = shade(th.pipe, -0.16); g.fillRect(11, 0, 5, 16);
      g.fillStyle = th.pipeDark; g.fillRect(15, 0, 1, 16);
    });
    atlas[T.COIN_TILE] = mk(() => {});
    this.atlas[theme.id] = atlas;
    return atlas;
  }

  /* ------------------------------- تزئینات ------------------------------ */
  decor(type, theme) {
    const key = type + ':' + theme.id;
    if (this.decorCache[key]) return this.decorCache[key];
    const cv = document.createElement('canvas');
    const g0 = cv.getContext('2d');
    const sizes = { tree: [34, 62], bush: [26, 16], house: [64, 54], lamp: [14, 54], kashiWall: [40, 30], stall: [56, 40], carpet: [26, 36], pot: [18, 20], arch: [58, 54], fountain: [46, 28], flowerBed: [34, 14], dune: [78, 30], palm: [40, 58], ruin: [46, 40], pillar: [22, 54], column: [26, 56], city: [70, 70], antenna: [18, 60], pine: [32, 54], snowRock: [40, 26], castle: [92, 74] };
    const [w, h] = sizes[type] || [32, 32];
    cv.width = w; cv.height = h;
    const g = g0;
    g.imageSmoothingEnabled = false;
    const th = theme;
    const dark = shade(th.mid, -0.25);
    const veg = th.veg || th.mid;      // رنگ گیاهان (سرو، درخت، بوته) مستقل از رنگ کوه‌ها
    switch (type) {
      case 'tree': { // سرو ایرانی: باریک، بلند و پله‌پله
        const cx = w / 2;
        g.fillStyle = '#6b4a26'; g.fillRect(cx - 2, h - 12, 4, 10);
        g.fillStyle = shade(dark, -0.1); g.fillRect(cx - 5, h - 3, 10, 3);
        const tiers = 6;
        for (let i = tiers - 1; i >= 0; i--) {
          const t = i / (tiers - 1);
          const yy = h - 9 - t * (h - 15);
          const rw = 2.5 + 10 * Math.sin(Math.PI * (0.12 + 0.88 * t)) * (1 - 0.45 * t);
          g.fillStyle = i % 2 ? shade(veg, -0.16) : veg;
          g.beginPath(); g.ellipse(cx, yy, rw, 4.5 + 2.5 * (1 - t), 0, 0, 6.3); g.fill();
        }
        g.fillStyle = shade(veg, 0.16);
        g.beginPath(); g.ellipse(cx - 2, h * 0.5, 2.5, h * 0.2, 0, 0, 6.3); g.fill();
        break;
      }
      case 'pine':
        g.fillStyle = '#5b4126'; g.fillRect(w / 2 - 2, h - 10, 4, 10);
        for (let i = 0; i < 3; i++) {
          const yy = h - 12 - i * 12, ww = w * (0.46 - i * 0.1);
          g.fillStyle = i % 2 ? shade(veg, -0.12) : veg;
          g.beginPath(); g.moveTo(w / 2, yy - 16); g.lineTo(w / 2 - ww, yy); g.lineTo(w / 2 + ww, yy); g.closePath(); g.fill();
        }
        g.fillStyle = '#f2fbff'; g.fillRect(w / 2 - 6, 6, 12, 3); g.fillRect(w / 2 - 4, 12, 8, 3);
        break;
      case 'bush': {
        const v = th.veg || th.mid;
        g.fillStyle = shade(v, -0.18); g.beginPath(); g.ellipse(w / 2, h - 5, w * 0.46, h * 0.46, 0, 0, 6.3); g.fill();
        g.fillStyle = v; g.beginPath(); g.ellipse(w / 2, h - 7, w * 0.36, h * 0.4, 0, 0, 6.3); g.fill();
        g.fillStyle = shade(v, 0.2); g.beginPath(); g.ellipse(w / 2 - 3, h - 9, w * 0.12, h * 0.2, 0, 0, 6.3); g.fill();
        break;
      }
      case 'house': // خانهٔ کاهگلی با گنبد
        g.fillStyle = th.near; g.fillRect(4, h - 34, w - 8, 34);
        g.fillStyle = shade(th.near, -0.18); g.fillRect(4, h - 34, w - 8, 3);
        g.fillStyle = th.accent; g.beginPath(); g.arc(w / 2, h - 34, 18, Math.PI, 0); g.fill();
        g.fillStyle = shade(th.accent, -0.2); g.beginPath(); g.arc(w / 2, h - 34, 18, Math.PI, Math.PI * 1.35); g.fill();
        g.fillStyle = '#3a2a1a'; g.fillRect(w / 2 - 6, h - 20, 12, 20); // در
        g.fillStyle = '#7fd0e0'; g.fillRect(12, h - 26, 8, 8); g.fillRect(w - 20, h - 26, 8, 8);
        break;
      case 'lamp': // فانوس
        g.fillStyle = '#3a3a44'; g.fillRect(w / 2 - 1, 6, 2, h - 6);
        g.fillStyle = '#f5d76e'; g.beginPath(); g.arc(w / 2, 10, 6, 0, 6.3); g.fill();
        g.fillStyle = '#c8a63c'; g.fillRect(w / 2 - 4, 4, 8, 2);
        break;
      case 'kashiWall': // دیوار کاشی
        g.fillStyle = shade(th.accent, -0.25); g.fillRect(0, 0, w, h);
        g.fillStyle = th.accent;
        for (let y = 0; y < h; y += 10) for (let x = 0; x < w; x += 10) {
          g.beginPath(); g.moveTo(x + 5, y + 1); g.lineTo(x + 9, y + 5); g.lineTo(x + 5, y + 9); g.lineTo(x + 1, y + 5); g.closePath(); g.fill();
        }
        g.fillStyle = '#ffffff'; g.globalAlpha = 0.35;
        for (let y = 0; y < h; y += 10) for (let x = 0; x < w; x += 10) g.fillRect(x + 4, y + 4, 2, 2);
        g.globalAlpha = 1;
        break;
      case 'stall': // بساط فروشگاه
        g.fillStyle = '#7a4a22'; g.fillRect(2, 14, w - 4, h - 14);
        g.fillStyle = th.accent; g.fillRect(-2, 6, w + 4, 10);
        g.fillStyle = '#ffffff';
        for (let i = 0; i < 5; i++) g.fillRect(i * 12, 6, 6, 10);
        g.fillStyle = '#e04a2a';
        for (let i = 0; i < 5; i++) g.fillRect(i * 12 + 6, 6, 6, 10);
        g.fillStyle = '#f5c542'; g.fillRect(6, h - 12, 6, 6); g.fillRect(w - 16, h - 12, 6, 6);
        break;
      case 'carpet': // فرش آویزان
        g.fillStyle = '#8a1f2b'; g.fillRect(2, 0, w - 4, h - 10);
        g.fillStyle = '#f5c542'; g.fillRect(5, 3, w - 10, h - 16);
        g.fillStyle = '#1f6b8a'; g.fillRect(8, 6, w - 16, h - 22);
        g.fillStyle = '#8a1f2b'; g.fillRect(10, 9, w - 20, h - 28);
        break;
      case 'pot':
        g.fillStyle = '#b0703a'; g.beginPath(); g.moveTo(3, 6); g.lineTo(w - 3, 6); g.lineTo(w - 6, h); g.lineTo(6, h); g.closePath(); g.fill();
        g.fillStyle = '#8a5326'; g.fillRect(2, 4, w - 4, 4);
        break;
      case 'arch': { // دروازهٔ سنگی با نقش میخی و سرتاق ایرانی
        const stone = th.near, stoneD = shade(th.near, -0.14);
        const inner = shade(th.near, -0.34);
        // پایهٔ دروازه تا رسیدن به خط زمین
        g.fillStyle = stoneD; g.fillRect(0, h - 8, w, 8);
        // ستون‌های دو طرف
        g.fillStyle = stone; g.fillRect(0, 12, 11, h - 12); g.fillRect(w - 11, 12, 11, h - 12);
        g.fillStyle = stoneD; g.fillRect(0, 12, 11, 3); g.fillRect(w - 11, 12, 11, 3);
        g.fillStyle = shade(stone, 0.16); g.fillRect(1, 15, 3, h - 15); g.fillRect(w - 4, 15, 3, h - 15);
        // داخل تاق (سایهٔ ملایم، نه سیاه)
        g.fillStyle = inner;
        g.beginPath();
        g.moveTo(11, h); g.lineTo(11, 30); g.quadraticCurveTo(w / 2, 4, w - 11, 30); g.lineTo(w - 11, h);
        g.closePath(); g.fill();
        // لبهٔ روشن تاق
        g.strokeStyle = shade(stone, 0.2); g.lineWidth = 2;
        g.beginPath(); g.moveTo(11, h); g.lineTo(11, 30); g.quadraticCurveTo(w / 2, 4, w - 11, 30); g.lineTo(w - 11, h); g.stroke();
        // نقش میخی هخامنشی روی ستون‌ها
        g.fillStyle = th.accent;
        for (let y = 20; y < h - 12; y += 9) { g.fillRect(3, y, 5, 2); g.fillRect(w - 8, y, 5, 2); }
        // سرتاق: نوار کنگره‌دار
        g.fillStyle = stone; g.fillRect(-2, 6, w + 4, 7);
        g.fillStyle = th.accent;
        for (let x = 2; x < w - 2; x += 8) g.fillRect(x, 8, 4, 3);
        g.fillStyle = stoneD; g.fillRect(-2, 6, w + 4, 2);
        break;
      }
      case 'fountain': // حوض آب
        g.fillStyle = th.stone; g.fillRect(0, h - 12, w, 12);
        g.fillStyle = th.stoneDark; g.fillRect(0, h - 12, w, 3);
        g.fillStyle = th.water; g.fillRect(4, h - 10, w - 8, 6);
        g.fillStyle = th.stone; g.fillRect(w / 2 - 2, 4, 4, h - 14);
        g.fillStyle = '#8fd8ff'; g.beginPath(); g.arc(w / 2, 4, 4, 0, 6.3); g.fill();
        break;
      case 'flowerBed':
        g.fillStyle = '#3f6b34'; g.fillRect(0, h - 8, w, 8);
        for (let i = 0; i < 6; i++) {
          const x = 4 + i * 5;
          g.fillStyle = ['#ef6ea0', '#f5c542', '#e04a2a', '#ffffff'][i % 4];
          g.beginPath(); g.arc(x, h - 10, 3, 0, 6.3); g.fill();
        }
        break;
      case 'dune':
        g.fillStyle = shade(th.near, -0.1); g.beginPath();
        g.moveTo(0, h); g.quadraticCurveTo(w * 0.3, 0, w * 0.62, h * 0.5); g.quadraticCurveTo(w * 0.85, h, w, h * 0.7); g.lineTo(w, h); g.fill();
        g.fillStyle = shade(th.near, 0.12); g.beginPath();
        g.moveTo(w * 0.2, h); g.quadraticCurveTo(w * 0.55, h * 0.3, w, h); g.fill();
        break;
      case 'palm':
        g.fillStyle = '#8a5a2a'; g.fillRect(w / 2 - 3, 18, 6, h - 18);
        for (let i = 0; i < 5; i++) {
          g.fillStyle = i % 2 ? '#2f7a3a' : '#3f9a48';
          g.save(); g.translate(w / 2, 18); g.rotate(-1.9 + i * 0.45);
          g.beginPath(); g.ellipse(16, 0, 18, 4, 0, 0, 6.3); g.fill(); g.restore();
        }
        g.fillStyle = '#c8901c'; g.beginPath(); g.arc(w / 2 - 6, 22, 3, 0, 6.3); g.fill(); g.beginPath(); g.arc(w / 2 + 6, 22, 3, 0, 6.3); g.fill();
        break;
      case 'ruin': // ویرانه
        g.fillStyle = th.near; g.fillRect(2, h - 26, w - 4, 26);
        g.fillStyle = shade(th.near, -0.2); g.fillRect(2, h - 26, w - 4, 4);
        g.fillStyle = shade(th.near, -0.35);
        g.fillRect(8, h - 34, 7, 10); g.fillRect(w - 16, h - 30, 7, 8);
        g.fillStyle = '#00000022'; g.fillRect(14, h - 14, 8, 14);
        break;
      case 'pillar': // ستون با سرستون
        g.fillStyle = th.stone; g.fillRect(4, 6, w - 8, h - 6);
        g.fillStyle = shade(th.stone, 0.18); g.fillRect(4, 6, 4, h - 6);
        g.fillStyle = shade(th.stone, -0.22); g.fillRect(w - 8, 6, 4, h - 6);
        g.fillStyle = shade(th.stone, 0.1); g.fillRect(0, 0, w, 8);
        g.fillStyle = shade(th.stone, -0.25); g.fillRect(0, 4, w, 2);
        break;
      case 'column':
        g.fillStyle = th.stone; g.fillRect(6, 8, w - 12, h - 8);
        g.fillStyle = shade(th.stone, 0.2); g.fillRect(6, 8, 4, h - 8);
        g.fillStyle = th.accent; g.fillRect(0, 0, w, 10);
        g.fillStyle = shade(th.accent, -0.3); g.fillRect(0, 8, w, 2);
        break;
      case 'city': // خط آسمان تهران + برج میلاد/آزادی
        g.fillStyle = shade(th.far, 0.06);
        for (let i = 0; i < 5; i++) {
          const bh = 20 + ((i * 37) % 34);
          g.fillRect(i * 14, h - bh, 12, bh);
        }
        g.fillStyle = shade(th.far, -0.12); g.fillRect(38, h - 62, 6, 62);
        g.fillStyle = shade(th.far, -0.05); g.fillRect(34, h - 46, 14, 5);
        g.fillStyle = '#ffd166'; g.globalAlpha = 0.8;
        for (let i = 0; i < 22; i++) g.fillRect(2 + (i % 6) * 14, h - 18 - Math.floor(i / 6) * 9, 2, 3);
        g.globalAlpha = 1;
        break;
      case 'antenna':
        g.fillStyle = '#4a4a58'; g.fillRect(w / 2 - 1, 8, 2, h - 8);
        g.fillStyle = '#ff6b6b'; g.beginPath(); g.arc(w / 2, 6, 3, 0, 6.3); g.fill();
        g.fillStyle = '#4a4a58'; g.fillRect(w / 2 - 5, 20, 10, 2); g.fillRect(w / 2 - 3, 32, 6, 2);
        break;
      case 'snowRock':
        g.fillStyle = shade(th.stone, -0.2); g.beginPath(); g.ellipse(w / 2, h - 6, w * 0.4, h * 0.45, 0, 0, 6.3); g.fill();
        g.fillStyle = '#f2fbff'; g.beginPath(); g.ellipse(w / 2, h - 12, w * 0.28, h * 0.22, 0, 0, 6.3); g.fill();
        break;
      case 'castle': { // قلعهٔ پایان با گنبد ایرانی
        g.fillStyle = th.near; g.fillRect(6, h - 46, w - 12, 46);
        g.fillStyle = shade(th.near, -0.2); g.fillRect(6, h - 46, w - 12, 4);
        g.fillStyle = th.accent; g.beginPath(); g.arc(w / 2, h - 46, 22, Math.PI, 0); g.fill();
        g.fillStyle = shade(th.accent, -0.22); g.beginPath(); g.arc(w / 2, h - 46, 22, Math.PI, Math.PI * 1.4); g.fill();
        g.fillStyle = '#f5d76e'; g.fillRect(w / 2 - 1, h - 76, 3, 10);
        g.fillStyle = '#2a2030'; g.beginPath();
        g.moveTo(w / 2 - 12, h); g.lineTo(w / 2 - 12, h - 26); g.quadraticCurveTo(w / 2, h - 52, w / 2 + 12, h - 26); g.lineTo(w / 2 + 12, h); g.closePath(); g.fill();
        g.fillStyle = th.accent; g.fillRect(8, h - 58, 12, 14); g.fillRect(w - 20, h - 58, 12, 14);
        g.fillStyle = shade(th.accent, -0.3); g.fillRect(8, h - 60, 12, 3); g.fillRect(w - 20, h - 60, 12, 3);
        break;
      }
      default:
        g.fillStyle = th.mid; g.fillRect(0, 0, w, h);
    }
    const out = { cv, w, h };
    this.decorCache[key] = out;
    return out;
  }

  /* ------------------------------- صحنه -------------------------------- */
  render(world, dt) {
    const g = this.g;
    const cam = world.camera || { x: 0, y: 0 };
    const vw = this.viewW, vh = this.viewH;
    const level = world.level;
    const th = level.themeDef;
    this.time += dt;
    const s = this.scale;
    g.setTransform(1, 0, 0, 1, 0, 0);
    g.imageSmoothingEnabled = false;

    /* آسمان */
    const grad = g.createLinearGradient(0, 0, 0, this.canvas.height);
    grad.addColorStop(0, th.sky[0]);
    grad.addColorStop(0.55, th.sky[1]);
    grad.addColorStop(1, th.sky[2]);
    g.fillStyle = grad;
    g.fillRect(0, 0, this.canvas.width, this.canvas.height);

    const camSX = Number.isFinite(cam.x) ? cam.x + (cam.sx || 0) : 0;
    const camSY = Number.isFinite(cam.y) ? cam.y + (cam.sy || 0) : 0;
    const camX = Math.round(camSX);
    const camY = Math.round(camSY);

    /* ---------- لایهٔ آسمان و پارالاکس (مختصات صفحه) ---------- */
    g.save();
    g.scale(s, s);

    if (th.night) {
      for (const st of this.stars) {
        const x = ((st.x * vw * 3 - camX * 0.06) % (vw + 20) + vw + 20) % (vw + 20);
        const tw = 0.6 + 0.4 * Math.sin(this.time * 2 + st.tw);
        g.globalAlpha = tw;
        g.fillStyle = '#ffffff';
        g.fillRect(x - 10, st.y * vh * 0.7 - camY * 0.06, st.r, st.r);
      }
      g.globalAlpha = 1;
      g.fillStyle = '#f7f2d0';
      g.beginPath(); g.arc(vw * 0.78 - camX * 0.04, 34 - camY * 0.06, 13, 0, 6.3); g.fill();
      g.fillStyle = th.sky[0]; g.beginPath(); g.arc(vw * 0.78 - camX * 0.04 + 6, 30 - camY * 0.06, 12, 0, 6.3); g.fill();
    } else if (th.sun) {
      const sunX = vw * 0.16 - camX * 0.04;
      const sunY = 44 - camY * 0.06;
      g.fillStyle = th.sun;
      g.globalAlpha = 0.95;
      g.beginPath(); g.arc(sunX, sunY, 19, 0, 6.3); g.fill();
      g.globalAlpha = 0.16;
      g.beginPath(); g.arc(sunX, sunY, 34, 0, 6.3); g.fill();
      g.globalAlpha = 1;
    }

    /* ابرها */
    const cloudStep = this.lowQuality ? 2 : 1;
    for (let ci = 0; ci < this.clouds.length; ci += cloudStep) {
      const c = this.clouds[ci];
      const x = ((c.x - camX * 0.12 + this.time * c.v) % (vw + 260) + vw + 260) % (vw + 260) - 130;
      const y = c.y - camY * 0.08;
      g.fillStyle = th.night ? 'rgba(180,200,230,0.28)' : 'rgba(255,255,255,0.75)';
      g.beginPath();
      g.ellipse(x, y, 26 * c.s, 9 * c.s, 0, 0, 6.3);
      g.ellipse(x + 18 * c.s, y + 3, 18 * c.s, 7 * c.s, 0, 0, 6.3);
      g.ellipse(x - 18 * c.s, y + 3, 16 * c.s, 6 * c.s, 0, 0, 6.3);
      g.fill();
    }

    /* کوه‌های دور (پارالاکس) */
    this.drawMountains(g, th, camX, camY, vw, vh, 0.16, 0.52, th.far);
    this.drawMountains(g, th, camX, camY, vw, vh, 0.30, 0.44, th.mid);

    /* تزئینات لایه‌های دور */
    for (const d of level.decor) {
      if (d.z > 0.4) continue;
      const dec = this.decor(d.type, th);
      const x = d.x - camX * d.z;
      const y = (d.baseY ?? d.y) - dec.h - camY * d.z;
      if (x + dec.w < -40 || x > vw + 40) continue;
      g.drawImage(dec.cv, Math.round(x), Math.round(y));
    }
    g.restore();

    /* ---------- لایهٔ اصلی صحنه (مختصات دنیا؛ هر رسم خودش دوربین را کم می‌کند) ---------- */
    g.save();
    g.scale(s, s);

    /* تزئینات نزدیک */
    for (const d of level.decor) {
      if (d.z <= 0.4) continue;
      const dec = this.decor(d.type, th);
      const x = d.x - camX * (0.9 + (d.z - 0.6));
      const y = (d.baseY ?? d.y) - dec.h - camY * 0.94;
      if (x + dec.w < -40 || x > vw + 40) continue;
      g.globalAlpha = 0.9;
      g.drawImage(dec.cv, Math.round(x), Math.round(y));
      g.globalAlpha = 1;
    }

    /* کاشی‌ها */
    this.drawTiles(g, world, camX, camY, vw, vh);

    /* آیتم‌ها، دشمنان، بازیکن */
    this.drawEntities(g, world, camX, camY);

    /* سپر پرچم تا شکست رئیس */
    this.drawFlagBarrier(world, { x: camX, y: camY });

    /* افکت‌ها */
    world.fx.draw(g, { x: camX, y: camY });

    g.restore();

    /* HUD و لایه‌های روی صفحه */
    this.drawHUD(world, dt);
    world.fx.drawFlash(g, this.canvas.width, this.canvas.height);
    this.drawVignette();
  }

  drawMountains(g, th, camX, camY, vw, vh, z, horizon, color) {
    const baseY = vh * horizon + 30 - camY * z * 0.4;
    g.fillStyle = color;
    g.globalAlpha = 0.75;
    g.beginPath();
    g.moveTo(-50, vh + 50);
    const step = 90;
    const off = (camX * z) % (step * 2);
    for (let i = -1; i < vw / step + 3; i++) {
      const x = i * step - off;
      g.lineTo(x, baseY - (i % 2 ? 40 : 62));
      g.lineTo(x + step / 2, baseY - (i % 2 ? 20 : 34));
    }
    g.lineTo(vw + 60, vh + 50);
    g.closePath();
    g.fill();
    g.globalAlpha = 1;
  }

  drawTiles(g, world, camX, camY, vw, vh) {
    const level = world.level;
    const atlas = this.buildAtlas(level.themeDef);
    const th = level.themeDef;
    const x0 = Math.max(0, Math.floor(camX / TILE) - 1);
    const x1 = Math.min(level.w - 1, Math.ceil((camX + vw) / TILE) + 1);
    const y0 = Math.max(0, Math.floor(camY / TILE) - 1);
    const y1 = Math.min(level.h - 1, Math.ceil((camY + vh) / TILE) + 1);
    // موج آب پویا
    const wave = Math.sin(this.time * 3) * 1.5;
    for (let ty = y0; ty <= y1; ty++) {
      for (let tx = x0; tx <= x1; tx++) {
        const t = level.tiles[ty * level.w + tx];
        if (!t) continue;
        const dx = Math.round(tx * TILE - camX);
        const dy = Math.round(ty * TILE - camY);
        if (t === T.COIN_TILE) { this.drawCoinTile(g, dx, dy); continue; }
        if (t === T.WATER) {
          g.drawImage(atlas[T.WATER], dx, dy);
          g.fillStyle = '#ffffff55';
          g.fillRect(dx, dy + 2, TILE, 1.5 + wave * 0.6);
          continue;
        }
        const cv = atlas[t];
        if (cv) g.drawImage(cv, dx, dy);
      }
    }
  }

  drawCoinTile(g, x, y) {
    const cv = Sprite.item.coin;
    const ph = (this.time * 3.4 + x * 0.35) % (Math.PI * 2);
    const sq = Math.abs(Math.cos(ph));
    const w = 3 + (cv.width - 3) * sq;
    if (sq < 0.28) {
      // لبهٔ سکه هنگام چرخش
      g.fillStyle = '#c8901c';
      g.fillRect(Math.round(x + 8 - w / 2), y + 2, Math.max(2, Math.round(w)), cv.height - 4);
    } else {
      g.drawImage(cv, Math.round(x + 8 - w / 2), y, Math.max(2, Math.round(w)), cv.height);
    }
  }

  drawEntities(g, world, camX, camY) {
    const cam = { x: camX, y: camY };
    // آیتم‌ها
    for (const e of world.entities) {
      if (e.kind === 'item') this.drawItem(g, e, cam);
    }
    // پرچم‌ها و ایستگاه‌ها
    for (const e of world.entities) {
      if (e.type === 'checkpoint') this.drawCheckpoint(g, e, cam);
    }
    if (world.flag) this.drawFlag(g, world.flag, cam, world);
    // دشمنان
    for (const e of world.entities) {
      if (e.kind === 'enemy') { this.drawGroundShadow(g, e, cam, 1); this.drawEnemy(g, e, cam); }
      else if (e.kind === 'shell') e.draw(g, cam);
      else if (e.kind === 'boss') { this.drawGroundShadow(g, e, cam, e.type === 'dragon' ? 1.3 : 1.1); this.drawBoss(g, e, cam); }
      else if (e instanceof Object && e.tile !== undefined) this.drawDebris(g, e, cam);
    }
    // گلوله‌های آتش
    for (const f of world.fireballs) {
      const cv = Sprite.item.fireball;
      g.save();
      g.translate(Math.round(f.cx - cam.x), Math.round(f.cy - cam.y));
      g.rotate(this.time * 9 * (f.dir || 1));
      g.drawImage(cv, -cv.width / 2, -cv.height / 2);
      g.restore();
    }
    // بازیکن
    const p = world.player;
    if (p && !(p.dead && p.deathTimer > 0.9)) this.drawPlayer(g, p, cam, world);
  }

  /* سایهٔ بیضی زیر اجسام (قابل خاموش‌کردن در تنظیمات) */
  drawGroundShadow(g, e, cam, scale = 1) {
    if (!this.shadows || this.lowQuality) return;
    const x = Math.round(e.cx - cam.x);
    const y = Math.round(e.y + e.h - cam.y);
    g.save();
    g.globalAlpha = 0.22;
    g.fillStyle = '#000';
    g.beginPath();
    g.ellipse(x, y - 1, (e.w * 0.5) * scale, Math.max(1.5, e.w * 0.16) * scale, 0, 0, 6.3);
    g.fill();
    g.restore();
  }

  drawPlayer(g, p, cam, world) {
    this.drawGroundShadow(g, p, cam, 0.9);
    const key = p.spriteKey;
    const entry = Sprite.hero[p.big ? 'big' : 'small'][key.split('.')[1]];
    if (!entry) return;
    const img = p.facing >= 0 ? entry.r : entry.l;
    const artW = img.width, artH = img.height;
    const bob = p.crouching ? 3 : 0;
    const dx = Math.round(p.x - cam.x - (artW - p.w) / 2);
    const dy = Math.round(p.y + p.h - cam.y - artH + (p.sizeKind === 'small' ? 3 : 3) + bob);
    if (p.dead) {
      g.save();
      g.translate(dx + artW / 2, dy + artH / 2);
      g.rotate(Math.min(1, p.deathTimer * 3) * 0.4);
      g.drawImage(img, -artW / 2, -artH / 2);
      g.restore();
    } else {
      if (p.invuln > 0 && Math.floor(p.invuln * 14) % 2) g.globalAlpha = 0.45;
      g.drawImage(img, dx, dy);
      g.globalAlpha = 1;
      if (p.star > 0 && Math.random() < 0.5) {
        world.fx.add('starTrail', p.cx + rand(-6, 6), p.y + rand(0, p.h), { color: Math.random() < 0.5 ? '#fff2a8' : '#a8f0ff' });
      }
      if (p.feather > 0 && p.vy > 0) {
        g.globalAlpha = 0.6;
        g.drawImage(Sprite.item.feather, dx - 6, dy + 4 + Math.sin(this.time * 8) * 2);
        g.globalAlpha = 1;
      }
    }
  }

  drawEnemy(g, e, cam) {
    const list = e.spriteList;
    const frameCv = list[e.frame % list.length];
    const img = e.dir >= 0 ? frameCv.r : frameCv.l;
    let dx = Math.round(e.cx - cam.x - img.width / 2);
    let dy = Math.round(e.y + e.h - cam.y - img.height);
    if (e.squashed > 0) {
      g.save();
      g.translate(dx + img.width / 2, dy + img.height);
      g.scale(1.15, 0.35);
      g.drawImage(img, -img.width / 2, -img.height);
      g.restore();
      return;
    }
    if (e.flippedDeath) {
      g.save();
      g.translate(dx + img.width / 2, dy + img.height / 2);
      g.scale(1, -1);
      g.drawImage(img, -img.width / 2, -img.height / 2);
      g.restore();
      return;
    }
    if (e.hurtFlash > 0 && Math.floor(e.hurtFlash * 20) % 2) g.globalAlpha = 0.5;
    g.drawImage(img, dx, dy);
    g.globalAlpha = 1;
    if (e.def.armored) {
      // گرد و غبار غلتیدن
      if (Math.random() < 0.3) g.fillStyle = 'rgba(220,210,190,0.5)', g.fillRect(dx + (e.dir > 0 ? 0 : img.width - 3), dy + img.height - 4, 3, 3);
    }
  }

  drawItem(g, e, cam) {
    let cv;
    switch (e.type) {
      case 'mushroom1up': cv = Sprite.item.mushroom1up; break;
      case 'mushroomLife': cv = Sprite.item.mushroomLife; break;
      case 'flower': cv = Sprite.item.flower; break;
      case 'star': cv = Sprite.item.star; break;
      case 'feather': cv = Sprite.item.feather; break;
      case 'coin': cv = Sprite.item.coin; break;
      default: cv = Sprite.item.mushroom; break;
    }
    if (!cv) return;
    const dx = Math.round(e.cx - cam.x - cv.width / 2);
    let dy = Math.round(e.y + e.h - cam.y - cv.height);
    if (e.type === 'star' || e.type === 'feather') dy = Math.round(e.y - cam.y);
    if (e.type === 'flower' || (e.type === 'star')) dy = Math.round(e.y + e.h - cam.y - cv.height);
    if (e.type === 'coin') {
      const sq = Math.abs(Math.sin(this.time * 12));
      const w = Math.max(2, cv.width * sq);
      g.drawImage(cv, Math.round(e.cx - cam.x - w / 2), dy, Math.max(1, Math.round(w)), cv.height);
      return;
    }
    g.drawImage(cv, dx, dy);
    if (e.type === 'star' || e.type === 'feather') {
      g.globalAlpha = 0.35;
      g.fillStyle = '#fff2a8';
      g.beginPath(); g.arc(e.cx - cam.x, e.cy - cam.y, 12 + Math.sin(this.time * 6) * 2, 0, 6.3); g.fill();
      g.globalAlpha = 1;
    }
  }

  drawBoss(g, b, cam) {
    const set = Sprite.boss?.[b.type];
    if (!set) return;
    let name = 'idle';
    if (b.defeated) name = 'hurt';
    else if (b.type === 'div') {
      if (b.hurtFlash > 0) name = 'hurt';
      else if (b.state === 'roar') name = 'attack';
      else name = 'walk' + ((Math.floor(this.time * 5) % 2) + 1);
    } else {
      if (b.hurtFlash > 0) name = 'hurt';
      else if (b.state === 'breath' || b.state === 'windup') name = 'breath';
      else name = 'flap' + ((Math.floor(this.time * 5) % 2) + 1);
    }
    const cv = set[name] || set.idle;
    const dx = Math.round(b.cx - cam.x - cv.width / 2);
    const dy = Math.round(b.y + b.h - cam.y - cv.height + 4);
    if (b.hurtFlash > 0 && Math.floor(b.hurtFlash * 20) % 2) g.globalAlpha = 0.6;
    g.save();
    if (b.dir > 0) { g.translate(dx + cv.width, dy); g.scale(-1, 1); g.drawImage(cv, 0, 0); }
    else g.drawImage(cv, dx, dy);
    g.restore();
    g.globalAlpha = 1;
    // نشانهٔ محل فرود اژدها (هشدار قابل‌خواندن)
    if (b.type === 'dragon' && !b.defeated && Number.isFinite(b.diveX) && (b.state === 'windup' || b.state === 'dive')) {
      const mx = Math.round(b.diveX - cam.x);
      const my = Math.round(12 * 16 - cam.y);
      if (Number.isFinite(mx) && Number.isFinite(my)) {
        const pulse = 0.45 + 0.35 * Math.sin(this.time * 14);
        g.globalAlpha = pulse;
        g.strokeStyle = '#ff5a3c'; g.lineWidth = 2;
        g.beginPath(); g.ellipse(mx, my - 2, 22, 7, 0, 0, 6.3); g.stroke();
        g.globalAlpha = pulse * 0.5;
        g.beginPath(); g.moveTo(mx, my - 120); g.lineTo(mx, my - 8); g.stroke();
        g.globalAlpha = 1;
      }
    }
    // نشانهٔ آسیب‌پذیری
    if (b.vulnStomp && !b.defeated) {
      g.globalAlpha = 0.5 + 0.3 * Math.sin(this.time * 12);
      g.strokeStyle = '#ffd166'; g.lineWidth = 2;
      g.strokeRect(b.x - cam.x - 2, b.y - cam.y - 2, b.w + 4, b.h + 4);
      g.globalAlpha = 1;
    }
  }

  drawDebris(g, e, cam) {
    const t = e.tile;
    const th = this.atlas[Object.keys(this.atlas)[0]];
    g.save();
    g.translate(Math.round(e.x - cam.x + 4), Math.round(e.y - cam.y + 4));
    g.rotate(e.rot || 0);
    g.fillStyle = e.tile === T.BRICK ? '#d9a066' : '#b9b3a6';
    g.fillRect(-4, -4, 8, 8);
    g.fillStyle = '#00000033';
    g.fillRect(-4, 2, 8, 2);
    g.restore();
  }

  drawCheckpoint(g, cp, cam) {
    const x = Math.round(cp.x - cam.x);
    const y = Math.round(cp.y - cam.y);
    g.fillStyle = '#5a4a3a';
    g.fillRect(x + 3, y, 3, 60);
    const wave = Math.sin(this.time * 4) * 2;
    if (cp.taken) {
      g.fillStyle = '#2f9e4f';
      g.fillRect(x + 6, y + 4, 16, 10);
      g.fillStyle = '#ffffff';
      g.fillRect(x + 6, y + 4, 16, 3);
      g.fillStyle = '#d42a34';
      g.fillRect(x + 6, y + 14, 16, 3);
    } else {
      g.fillStyle = '#9a9a9a';
      g.fillRect(x + 6 + wave * 0.3, y + 4, 14, 9);
    }
    g.fillStyle = '#e8dcc0';
    g.beginPath(); g.arc(x + 4.5, y - 2, 3, 0, 6.3); g.fill();
  }

  drawFlag(g, flag, cam) {
    const x = Math.round(flag.x - cam.x);
    const y = Math.round(flag.y - cam.y);
    const h = 9 * TILE;
    // میله
    g.fillStyle = '#7a7a86';
    g.fillRect(x, y, 3, h);
    g.fillStyle = '#e0e0e8';
    g.fillRect(x, y, 1, h);
    g.fillStyle = '#f5d76e';
    g.beginPath(); g.arc(x + 1.5, y - 3, 4, 0, 6.3); g.fill();
    // پرچم سه‌رنگ
    const fy = flag.taken || (this._flagY ?? 0);
    const wave = Math.sin(this.time * 5) * 2;
    const topY = y + 6;
    g.fillStyle = '#2f9e4f'; g.fillRect(x + 3, topY + wave * 0.2, 22, 8);
    g.fillStyle = '#f4f4f4'; g.fillRect(x + 3, topY + 8 + wave * 0.2, 22, 8);
    g.fillStyle = '#d42a34'; g.fillRect(x + 3, topY + 16 + wave * 0.2, 22, 8);
    g.fillStyle = '#c8901c';
    g.beginPath(); g.arc(x + 14, topY + 12 + wave * 0.2, 3, 0, 6.3); g.fill();
    // پایه
    g.fillStyle = '#8a8a96'; g.fillRect(x - 5, y + h - 4, 13, 6);
  }

  /* --------------------------------- HUD -------------------------------- */
  drawHUD(world, dt) {
    const g = this.g;
    const W = this.canvas.width, H = this.canvas.height;
    const s = clamp(this.canvas.height / 480, 0.75, 2.4);
    const pad = 10 * s;
    const p = world.player;
    g.setTransform(1, 0, 0, 1, 0, 0);
    g.imageSmoothingEnabled = false;
    g.textAlign = 'right';
    g.font = `bold ${Math.round(15 * s)}px Vazirmatn, system-ui, sans-serif`;

    // پلاک سکه‌ها
    const coinCv = Sprite.item.coin;
    const drawPill = (x, y, w, h2, alpha = 0.42) => {
      g.fillStyle = `rgba(12,10,22,${alpha})`;
      g.beginPath();
      if (g.roundRect) g.roundRect(x, y, w, h2, h2 / 2); else g.rect(x, y, w, h2);
      g.fill();
    };
    // سکه
    drawPill(W - pad - 86 * s, pad, 86 * s, 26 * s);
    g.drawImage(coinCv, W - pad - 80 * s, pad + 5 * s, 18 * s, 18 * s);
    g.fillStyle = '#ffe37a';
    g.fillText(fa(world.coins), W - pad - 24 * s, pad + 21 * s);
    // امتیاز
    g.fillStyle = '#ffffff';
    g.font = `bold ${Math.round(13 * s)}px Vazirmatn, system-ui, sans-serif`;
    drawPill(W - pad - 86 * s, pad + 30 * s, 86 * s, 22 * s);
    g.fillStyle = '#ffffffcc';
    g.fillText(fa(world.score), W - pad - 12 * s, pad + 46 * s);

    // چپ: جان و زمان
    g.textAlign = 'left';
    const lifeCv = Sprite.hero.small.normal_idle.r;
    for (let i = 0; i < Math.min(world.lives, 5); i++) {
      g.drawImage(lifeCv, pad + i * 16 * s, pad, 16 * s, 18 * s);
    }
    if (world.lives > 5) {
      g.fillStyle = '#fff';
      g.fillText('×' + fa(world.lives), pad + 5 * 16 * s + 2 * s, pad + 15 * s);
    }
    // زمان (نماد ساعت رسم می‌شود تا به فونت ایموجی وابسته نباشیم)
    drawPill(pad, pad + 24 * s, 84 * s, 24 * s);
    const clockX = pad + 13 * s, clockY = pad + 36 * s;
    g.strokeStyle = world.time < 45 ? '#ff8a80' : '#ffffff';
    g.lineWidth = Math.max(1, 1.5 * s);
    g.beginPath(); g.arc(clockX, clockY, 6 * s, 0, 6.3); g.stroke();
    g.beginPath();
    g.moveTo(clockX, clockY); g.lineTo(clockX, clockY - 3.4 * s);
    g.moveTo(clockX, clockY); g.lineTo(clockX + 3 * s, clockY + 1.2 * s);
    g.stroke();
    g.fillStyle = world.time < 45 ? '#ff8a80' : '#ffffff';
    g.font = `bold ${Math.round(14 * s)}px Vazirmatn, system-ui, sans-serif`;
    g.fillText(faTime(world.time), pad + 24 * s, pad + 41 * s);

    // نوار پیشرفت مرحله
    const barW = 120 * s, barX = W / 2 - barW / 2, barY = pad + 4 * s;
    g.fillStyle = 'rgba(0,0,0,0.35)';
    g.fillRect(barX, barY, barW, 7 * s);
    const prog = clamp((world.player.cx) / Math.max(1, (world.level.goal?.x || world.level.pixelW)), 0, 1);
    g.fillStyle = '#ffd166';
    g.fillRect(barX, barY, barW * prog, 7 * s);
    g.fillStyle = '#ffffffcc';
    g.font = `bold ${Math.round(11 * s)}px Vazirmatn, sans-serif`;
    g.textAlign = 'center';
    const bossFight = !!(world.boss && world.boss.active && !world.boss.defeated);
    if (!bossFight) {
      g.fillText(world.endless ? 'بی‌پایان' : `مرحله ${fa(world.level.n)} — ${world.level.name}`, W / 2, barY + 22 * s);
    }

    // وضعیت قدرت (نماد ستاره رسم می‌شود)
    const powY = barY + 40 * s;
    if (p && p.star > 0) {
      g.fillStyle = '#fff2a8';
      const sx = W / 2 - 2 * s, sy = powY - 4 * s, r0 = 6 * s, r1 = 2.6 * s;
      g.beginPath();
      for (let i = 0; i < 8; i++) {
        const a = (Math.PI / 4) * i - Math.PI / 2;
        const r = i % 2 ? r1 : r0;
        const px = sx + Math.cos(a) * r, py = sy + Math.sin(a) * r;
        if (i) g.lineTo(px, py); else g.moveTo(px, py);
      }
      g.closePath(); g.fill();
      g.textAlign = 'left';
      g.fillText('ستاره: ' + fa(Math.ceil(p.star)) + ' ثانیه', W / 2 + 12 * s, powY);
      g.textAlign = 'center';
    } else if (p && p.feather > 0) {
      g.fillStyle = '#8ce0ff';
      g.fillText('پرش دوم: ' + fa(Math.ceil(p.feather)) + ' ثانیه', W / 2, powY);
    }

    // نوار جان رئیس (همیشه روی صفحه، حتی وقتی رئیس بیرون کادر است)
    const boss = world.boss;
    if (boss && boss.active && !boss.defeated) {
      const bw = Math.min(180 * s, W - 200 * s);
      const bx = W / 2 - bw / 2;
      const by = pad + 26 * s;
      const frac = clamp(boss.hp / boss.maxHp, 0, 1);
      g.fillStyle = 'rgba(12,10,22,0.55)';
      if (g.roundRect) { g.beginPath(); g.roundRect(bx - 3 * s, by - 3 * s, bw + 6 * s, 14 * s, 7 * s); g.fill(); }
      else g.fillRect(bx - 3 * s, by - 3 * s, bw + 6 * s, 14 * s);
      g.fillStyle = '#2a1626';
      g.fillRect(bx, by, bw, 8 * s);
      g.fillStyle = boss.type === 'div' ? '#e04a2a' : '#8a5cd0';
      g.fillRect(bx + (bw - bw * frac) * 1, by, bw * frac, 8 * s);
      if (boss.vulnStomp && !boss.defeated) {
        g.globalAlpha = 0.35 + 0.3 * Math.sin(this.time * 14);
        g.fillStyle = '#fff3c8';
        g.fillRect(bx, by, bw * frac, 8 * s);
        g.globalAlpha = 1;
      }
      g.fillStyle = '#fff';
      g.font = `bold ${Math.round(11 * s)}px Vazirmatn, sans-serif`;
      g.textAlign = 'center';
      g.fillText(boss.type === 'div' ? 'دیو کویر' : 'اژدهای دماوند', W / 2, by + 22 * s);
      g.textAlign = 'left';
    }

    // کارت معرفی مرحله و کارت نبرد رئیس
    this.drawBanners(world, s, W, H);

    g.textAlign = 'left';
  }

  /* کارت‌های بزرگ وسط صفحه: «مرحله ۳ — گلستان ارم» و «نبرد رئیس!» */
  drawBanners(world, s, W, H) {
    const g = this.g;
    const card = (t, total, title, sub, color) => {
      const p = 1 - t / total;                       // ۰ تا ۱
      const appear = Math.min(1, p / 0.12);
      const leave = t < 0.45 ? Math.max(0, t / 0.45) : 1;
      const a = Math.min(appear, leave);
      if (a <= 0.01) return;
      const w = Math.min(W * 0.66, 430 * s), h = 64 * s;
      const cx = W / 2, cy = H * 0.40;
      g.save();
      g.globalAlpha = a * 0.88;
      g.fillStyle = 'rgba(14,10,26,0.92)';
      if (g.roundRect) { g.beginPath(); g.roundRect(cx - w / 2, cy - h / 2, w, h, 12 * s); g.fill(); }
      else g.fillRect(cx - w / 2, cy - h / 2, w, h);
      g.globalAlpha = a;
      g.strokeStyle = color;
      g.lineWidth = 2 * s;
      if (g.roundRect) { g.beginPath(); g.roundRect(cx - w / 2, cy - h / 2, w, h, 12 * s); g.stroke(); }
      else g.strokeRect(cx - w / 2, cy - h / 2, w, h);
      g.textAlign = 'center';
      g.fillStyle = '#ffffff';
      g.font = `bold ${Math.round(19 * s)}px Vazirmatn, sans-serif`;
      g.fillText(title, cx, cy + 4 * s);
      if (sub) {
        g.fillStyle = color;
        g.font = `bold ${Math.round(12 * s)}px Vazirmatn, sans-serif`;
        g.fillText(sub, cx, cy + 23 * s);
      }
      g.restore();
      g.textAlign = 'left';
    };
    if (world.introT > 0 && !world.completed) {
      const lv = world.level;
      const hazards = (lv.hazard || []).map((h) => ({ water: 'آب', spike: 'نیزه', void: 'پرتگاه' }[h] || '')).filter(Boolean);
      const sub = hazards.length ? `خطرها: ${hazards.join('، ')}` : (lv.boss ? 'پایان مرحله: نبرد رئیس' : '');
      card(world.introT, 2.8, `مرحله ${fa(lv.n)} — ${lv.name}`, sub, '#ffd166');
    } else if (world.bossIntroT > 0 && world.boss && !world.boss.defeated) {
      card(world.bossIntroT, 2.8, 'نبرد رئیس!', world.boss.type === 'div' ? 'دیو کویر' : 'اژدهای دماوند', '#ff6a4d');
    }
  }

  /* سپر طلایی روی پرچم تا زمانی که رئیس شکست نخورده */
  drawFlagBarrier(world, cam) {
    const g = this.g;
    if (!world.flag || !world.boss || world.boss.defeated || !world.level.boss || world.completed) return;
    const fx = world.flag.x - cam.x;
    if (fx < -90 || fx > this.viewW + 90) return;
    const fy = world.flag.y - cam.y;
    const t = this.time;
    const pulse = 0.34 + 0.12 * Math.sin(t * 4);
    const top = fy - 26, hgt = 168, halfW = 20;
    g.save();
    // ستون‌های نور
    for (const sx of [-halfW, halfW]) {
      const grad = g.createLinearGradient(fx + sx - 5, 0, fx + sx + 5, 0);
      grad.addColorStop(0, 'rgba(255,209,102,0)');
      grad.addColorStop(0.5, 'rgba(255,214,120,0.85)');
      grad.addColorStop(1, 'rgba(255,209,102,0)');
      g.globalAlpha = pulse + 0.35;
      g.fillStyle = grad;
      g.fillRect(fx + sx - 5, top, 10, hgt);
    }
    // پردهٔ نازک بین ستون‌ها
    g.globalAlpha = pulse * 0.5;
    g.fillStyle = '#ffe9a8';
    g.fillRect(fx - halfW, top, halfW * 2, hgt);
    // نقش قفل
    const ly = fy + 46;
    g.globalAlpha = Math.min(1, pulse + 0.5);
    g.strokeStyle = '#fff3c8';
    g.lineWidth = 2.4;
    g.strokeRect(fx - 8, ly, 16, 13);
    g.beginPath(); g.arc(fx, ly, 6, Math.PI, 0); g.stroke();
    g.globalAlpha = Math.min(0.9, pulse + 0.45);
    g.fillStyle = '#fff3c8';
    g.beginPath(); g.arc(fx, ly + 6, 2.4, 0, 6.3); g.fill();
    // درخشش پایین
    g.globalAlpha = pulse * 0.7;
    g.beginPath();
    g.ellipse(fx, top + hgt, 24, 6, 0, 0, 6.3);
    g.fill();
    g.restore();
  }

  drawVignette() {
    if (this.lowQuality) return;
    const g = this.g;
    const W = this.canvas.width, H = this.canvas.height;
    const grad = g.createRadialGradient(W / 2, H / 2, Math.min(W, H) * 0.35, W / 2, H / 2, Math.max(W, H) * 0.75);
    grad.addColorStop(0, 'rgba(0,0,0,0)');
    grad.addColorStop(1, 'rgba(0,0,0,0.35)');
    g.fillStyle = grad;
    g.fillRect(0, 0, W, H);
  }

  /* صحنهٔ عنوان: نمایش قهرمان و پس‌زمینه */
  renderTitle(world, dt) {
    this.time += dt;
    this.render(world, dt * 0.6);
  }
}

let world_hideBar = false;
