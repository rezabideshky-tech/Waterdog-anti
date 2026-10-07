/* utils.js — ریاضی، تصادفیِ با‌بذر، برخورد و ذخیره‌سازی */

export const clamp = (v, a, b) => (v < a ? a : v > b ? b : v);
export const lerp = (a, b, t) => a + (b - a) * t;
export const sign = (v) => (v > 0 ? 1 : v < 0 ? -1 : 0);
export const approach = (v, target, step) => (v < target ? Math.min(v + step, target) : Math.max(v - step, target));
export const rand = (a = 0, b = 1) => a + Math.random() * (b - a);
export const randInt = (a, b) => Math.floor(rand(a, b + 1));
export const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];

/** تصادفیِ قابل‌تکرار (xorshift32) برای ساخت مرحله و حالت بی‌پایان */
export function makeRng(seed) {
  let s = (seed >>> 0) || 0x9e3779b9;
  const next = () => {
    s ^= s << 13; s >>>= 0;
    s ^= s >>> 17;
    s ^= s << 5; s >>>= 0;
    return s / 4294967296;
  };
  return {
    next,
    range: (a, b) => a + next() * (b - a),
    int: (a, b) => Math.floor(a + next() * (b - a + 1)),
    pick: (arr) => arr[Math.floor(next() * arr.length)],
    chance: (p) => next() < p,
    seed: seed,
  };
}

export function aabb(a, b) {
  return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
}
export function pointInRect(px, py, r) {
  return px >= r.x && px <= r.x + r.w && py >= r.y && py <= r.y + r.h;
}
export function rectOverlapArea(a, b) {
  const w = Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x);
  const h = Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y);
  return w > 0 && h > 0 ? w * h : 0;
}
export function dist(x1, y1, x2, y2) { return Math.hypot(x2 - x1, y2 - y1); }

/* ------------------------------ ذخیره‌سازی ------------------------------ */
const KEY = 'gharchekhor.save.v1';

export const DEFAULT_SAVE = {
  bank: 0,                 // سکه‌های بانک (برای فروشگاه)
  savedCoins: 0,
  livesBonus: 0,
  best: {},                // بهترین امتیاز هر مرحله
  stars: {},               // ستاره‌های هر مرحله
  unlockedLevel: 1,
  character: 'koko',
  owned: [],               // آیتم‌های فروشگاه
  achievements: [],
  totalMushrooms: 0,
  totalCoins: 0,
  totalKills: 0,
  endlessBest: 0,
  settings: { music: true, sfx: true, vibrate: true, leftHanded: false, shadows: true, quality: 'auto' },
  seenIntro: false,
};

export function loadSave() {
  try {
    const raw = (typeof localStorage !== 'undefined') && localStorage.getItem(KEY);
    if (!raw) return structuredCloneish(DEFAULT_SAVE);
    const data = JSON.parse(raw);
    return { ...structuredCloneish(DEFAULT_SAVE), ...data,
      settings: { ...DEFAULT_SAVE.settings, ...(data.settings || {}) },
      best: data.best || {}, stars: data.stars || {}, owned: data.owned || [], achievements: data.achievements || [] };
  } catch (e) {
    return structuredCloneish(DEFAULT_SAVE);
  }
}

export function saveGame(save) {
  try {
    if (typeof localStorage !== 'undefined') localStorage.setItem(KEY, JSON.stringify(save));
  } catch (e) { /* حافظه در دسترس نیست */ }
}

export function resetSave() {
  try { if (typeof localStorage !== 'undefined') localStorage.removeItem(KEY); } catch (e) {}
}

function structuredCloneish(o) { return JSON.parse(JSON.stringify(o)); }

/* ------------------------------- اعداد فارسی ------------------------------ */
const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
export function fa(n) {
  return String(n).replace(/\d/g, (d) => FA_DIGITS[+d]);
}
export function faTime(sec) {
  const s = Math.max(0, Math.floor(sec));
  const m = Math.floor(s / 60);
  return fa(m) + ':' + fa(String(s % 60).padStart(2, '0'));
}

/* ------------------------------ کمکی‌های رنگ ----------------------------- */
export function shade(hex, amt) {
  const c = hex.replace('#', '');
  const n = parseInt(c.length === 3 ? c.split('').map((x) => x + x).join('') : c, 16);
  let r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
  r = clamp(Math.round(r + 255 * amt), 0, 255);
  g = clamp(Math.round(g + 255 * amt), 0, 255);
  b = clamp(Math.round(b + 255 * amt), 0, 255);
  return `rgb(${r},${g},${b})`;
}
