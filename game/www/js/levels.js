/* levels.js — تولید مراحل با کیفیت دست‌ساز (قطعه‌های متنوع و وابسته به سختی)
 * هر مرحله با بذر مشخص ساخته می‌شود تا همیشه یکسان باشد و قابل اشتراک باشد.
 */

import { TILE, LEVELS, THEMES } from './config.js';
import { makeRng, clamp } from './utils.js';

export const T = {
  EMPTY: 0,
  GROUND: 1,
  BRICK: 2,
  BRICK_COIN: 3,
  QUESTION: 4,
  USED: 5,
  PIPE_TL: 6, PIPE_TR: 7, PIPE_BL: 8, PIPE_BR: 9,
  BLOCK: 10,
  SEMI: 11,
  COIN_TILE: 12,
  WATER: 13,
  SPIKE: 14,
  CRUMBLE: 15,
  HIDDEN: 16,
  GROUND_DARK: 17,
};

export const SOLID_TILES = new Set([T.GROUND, T.BRICK, T.BRICK_COIN, T.QUESTION, T.USED, T.PIPE_TL, T.PIPE_TR, T.PIPE_BL, T.PIPE_BR, T.BLOCK, T.CRUMBLE, T.HIDDEN, T.GROUND_DARK]);
export const BUMPABLE = new Set([T.BRICK, T.BRICK_COIN, T.QUESTION, T.CRUMBLE, T.HIDDEN]);

export const LEVEL_H = 15;                 // ارتفاع همهٔ مراحل (خانه)
const GROUND_ROW = 12;                     // سطر بالای زمین
const SPAWN_CLEAR = 14;                    // چند خانهٔ اول خالی بماند
const END_CLEAR = 12;                      // چند خانهٔ آخر برای پرچم/قلعه

/* ------------------------------ سازندهٔ مرحله ---------------------------- */
export function buildLevel(index, opts = {}) {
  const def = { ...LEVELS[index], index };
  const rng = makeRng(0x51ed + index * 7919);
  const w = def.length;
  const h = LEVEL_H;
  const tiles = new Uint8Array(w * h);
  const entities = [];
  const decor = [];
  const diff = def.diff;
  const hazard = def.hazard[0] || null;

  const set = (x, y, t) => { if (x >= 0 && x < w && y >= 0 && y < h) tiles[y * w + x] = t; };
  const get = (x, y) => (x >= 0 && x < w && y >= 0 && y < h ? tiles[y * w + x] : T.EMPTY);

  /* زمین پایه با گودال‌ها (با کمترین فاصله بین گودال‌ها تا مراحل منصفانه بماند) */
  const groundTop = GROUND_ROW;
  const minSpacing = (hazard === 'water' || hazard === 'void' || !hazard ? 9 : 7) + Math.floor(diff / 2);
  let lastGap = -99;
  for (let x = 0; x < w; x++) {
    const canGap = x > SPAWN_CLEAR && x < w - END_CLEAR && (x - lastGap) >= minSpacing;
    if (canGap && rng.chance(gapChance(diff, hazard))) {
      const gapW = gapWidth(rng, diff, hazard);
      // پاک‌کردن گودال
      for (let i = 0; i < gapW; i++) {
        for (let y = groundTop; y < h; y++) set(x + i, y, T.EMPTY);
      }
      // پرکردن گودال بر اساس نوع خطر
      if (hazard === 'spike') {
        // گودال کم‌عمق با نیزه: یک خانه پایین‌تر، قابل بیرون آمدن
        for (let y = groundTop + 1; y < h; y++) for (let i = 0; i < gapW; i++) set(x + i, y, T.GROUND_DARK);
        for (let i = 0; i < gapW; i++) set(x + i, groundTop, T.SPIKE);
      } else if (hazard === 'water') {
        for (let y = groundTop + 1; y < h; y++) for (let i = 0; i < gapW; i++) set(x + i, y, T.WATER);
      }
      lastGap = x + gapW;
      x += gapW - 1;
      continue;
    }
    set(x, groundTop, T.GROUND);
    set(x, groundTop + 1, T.GROUND_DARK);
    set(x, groundTop + 2, T.GROUND_DARK);
    if (diff >= 5 && rng.chance(0.05)) set(x, groundTop, T.GROUND_DARK);
  }

  /* قطعه‌های بازی */
  let x = SPAWN_CLEAR;
  const endX = w - END_CLEAR;
  const features = pickFeatures(diff, rng);
  let featureI = 0;
  while (x < endX) {
    const f = features[featureI % features.length];
    featureI++;
    const consumed = f({ x, endX, w, h, groundTop, rng, set, get, entities, decor, diff, def, hazard });
    x += Math.max(6, consumed || 12);
    x += rng.int(1, 3);
  }

  /* پله‌های پایانی و سکوی پرچم */
  const flagX = w - END_CLEAR + 2;
  const flagTop = groundTop - 1;
  for (let i = 0; i < 8; i++) set(flagX - 8 + i, groundTop - Math.min(i, 5) - 1, T.BLOCK);
  set(flagX, groundTop - 1, T.EMPTY);

  /* گذر اطمینان: بالای گودال‌ها مانع شناور نگذار تا پرش ممکن باشد */
  for (let x = SPAWN_CLEAR; x < w - 2; x++) {
    const isPit = (get(x, groundTop) === T.EMPTY || get(x, groundTop) === T.WATER || get(x, groundTop) === T.SPIKE);
    if (!isPit) continue;
    const isFirst = get(x - 1, groundTop) !== T.EMPTY && get(x - 1, groundTop) !== T.WATER && get(x - 1, groundTop) !== T.SPIKE;
    if (!isFirst) continue;
    // ستون‌های گودال را پیدا کن
    let xEnd = x;
    while (xEnd < w - 1 && (get(xEnd + 1, groundTop) === T.EMPTY || get(xEnd + 1, groundTop) === T.WATER || get(xEnd + 1, groundTop) === T.SPIKE)) xEnd++;
    // پاک‌کردن موانع شناور بالای گودال (۶ خانه بالاتر) و یک خانه دو طرف
    for (let cx = x - 1; cx <= xEnd + 1; cx++) {
      for (let cy = groundTop - 7; cy < groundTop; cy++) {
        const t = get(cx, cy);
        if (t === T.BRICK || t === T.BRICK_COIN || t === T.QUESTION || t === T.BLOCK || t === T.CRUMBLE || t === T.HIDDEN || t === T.USED || t === T.SEMI) set(cx, cy, T.EMPTY);
      }
    }
    x = xEnd;
  }

  /* گذر عرض: هیچ گودالی نباید پهن‌تر از ۴ خانه باشد */
  {
    const isHoleX = (x) => {
      const t = get(x, groundTop);
      return t === T.EMPTY || t === T.WATER || t === T.SPIKE;
    };
    for (let x = SPAWN_CLEAR; x < w - 1; x++) {
      if (!isHoleX(x)) continue;
      let xEnd = x;
      while (xEnd + 1 < w - 1 && isHoleX(xEnd + 1)) xEnd++;
      const width = xEnd - x + 1;
      if (width > 4) {
        // پرکردن انتهای گودال تا عرض ۴ بماند
        for (let cx = x + 4; cx <= xEnd; cx++) {
          set(cx, groundTop, T.GROUND);
          set(cx, groundTop + 1, T.GROUND_DARK);
          set(cx, groundTop + 2, T.GROUND_DARK);
          for (let y = groundTop + 3; y < h; y++) set(cx, y, T.GROUND_DARK);
        }
      }
      x = xEnd;
    }
  }

  /* گذر جزیره‌ها: اگر دو گودال نزدیک هم باشند، یکی پر می‌شود تا بازیکن جای فرود داشته باشد */
  {
    const isHoleX = (x) => {
      const t = get(x, groundTop);
      return t === T.EMPTY || t === T.WATER || t === T.SPIKE;
    };
    const fillGround = (x0, x1) => {
      for (let x = x0; x <= x1; x++) {
        set(x, groundTop, T.GROUND);
        set(x, groundTop + 1, T.GROUND_DARK);
        set(x, groundTop + 2, T.GROUND_DARK);
      }
    };
    let prevPit = null;
    for (let x = SPAWN_CLEAR; x < w - 1; x++) {
      if (!isHoleX(x)) continue;
      let xEnd = x;
      while (xEnd + 1 < w - 1 && isHoleX(xEnd + 1)) xEnd++;
      if (prevPit && (x - prevPit[1] - 1) < 4) fillGround(prevPit[0], prevPit[1]);
      prevPit = [x, xEnd];
      x = xEnd;
    }
  }

  /* گذر فاصله: چند خانه قبل و بعد از هر گودال خالی بماند تا پرش منصفانه باشد */
  {
    const isHole = (x) => {
      const t = get(x, groundTop);
      return t === T.EMPTY || t === T.WATER || t === T.SPIKE;
    };
    const CLEAR_ROWS_UP = 6, MARGIN = 3, MARGIN_BEFORE = 5;
    for (let x = SPAWN_CLEAR; x < w - 2; x++) {
      if (!isHole(x)) continue;
      if (isHole(x - 1) && x - 1 >= SPAWN_CLEAR) continue;   // فقط ابتدای گودال
      let xEnd = x;
      while (xEnd + 1 < w - 1 && isHole(xEnd + 1)) xEnd++;
      for (let cx = Math.max(1, x - MARGIN_BEFORE); cx <= Math.min(w - 2, xEnd + MARGIN); cx++) {
        for (let cy = Math.max(1, groundTop - CLEAR_ROWS_UP); cy < groundTop; cy++) {
          const t = get(cx, cy);
          if (t === T.BRICK || t === T.BRICK_COIN || t === T.QUESTION || t === T.BLOCK || t === T.CRUMBLE || t === T.HIDDEN || t === T.USED || t === T.SEMI || t === T.PIPE_TL || t === T.PIPE_TR || t === T.PIPE_BL || t === T.PIPE_BR) set(cx, cy, T.EMPTY);
        }
      }
      x = xEnd;
    }
  }

  /* گذر پله‌ای: هیچ دیواری نباید بیش از ۳ خانه از پای قبلی بلندتر باشد (سقف پرش ≈ ۴ خانه) */
  {
    const colH = new Array(w).fill(0);
    for (let x = 0; x < w; x++) {
      let top = groundTop;
      for (let y = 1; y < groundTop; y++) { if (SOLID_TILES.has(get(x, y))) { top = y; break; } }
      colH[x] = groundTop - top;
    }
    for (let x = 1; x < w; x++) {
      let diffH = colH[x] - colH[x - 1];
      let guard = 0;
      while (diffH >= 4 && guard++ < 8) {
        const y = groundTop - colH[x];
        set(x, y, T.EMPTY);
        // هم‌ترازیِ ستون کنار (لوله‌ها و ستون‌های پهن)
        if (x + 1 < w && colH[x + 1] === colH[x] && get(x + 1, y) !== T.EMPTY && get(x + 1, y) !== T.GROUND && get(x + 1, y) !== T.GROUND_DARK) {
          set(x + 1, y, T.EMPTY);
          colH[x + 1]--;
        }
        colH[x]--;
        diffH = colH[x] - colH[x - 1];
      }
    }
  }

  /* گذر ارتفاع: روی هر سطح، دو خانهٔ بالای سر باید خالی باشد تا بازیکنِ بزرگ گیر نکند */
  const SOFT = new Set([T.BRICK, T.BRICK_COIN, T.QUESTION, T.CRUMBLE, T.SEMI, T.HIDDEN, T.USED]);
  for (let x = 1; x < w - 1; x++) {
    for (let y = h - 1; y >= 1; y--) {
      const t = get(x, y);
      if (!SOLID_TILES.has(t)) continue;
      const above1 = get(x, y - 1);
      if (SOLID_TILES.has(above1)) continue;      // سقفِ چسبیده، سطح اینجا نیست
      const above2 = get(x, y - 2);
      if (SOLID_TILES.has(above2)) {
        if (SOFT.has(above2)) set(x, y - 2, T.EMPTY);
        else if (t === T.BLOCK || t === T.SEMI || t === T.BRICK) {
          // مانع سخت بالای سر: خودِ سطح را بردار تا مسیر باز شود
          set(x, y, T.EMPTY);
        }
      }
    }
  }

  /* میدان رئیس: زمین صاف و بدون گودال و مانع (برای نبرد منصفانه) */
  if (def.boss) {
    const ax0 = Math.max(SPAWN_CLEAR + 4, endX - 26);
    const ax1 = Math.min(w - 2, endX - 1);
    for (let x = ax0; x <= ax1; x++) {
      set(x, groundTop, T.GROUND);
      set(x, groundTop + 1, T.GROUND_DARK);
      set(x, groundTop + 2, T.GROUND_DARK);
      for (let y = 1; y < groundTop; y++) {
        const t = get(x, y);
        if (t !== T.EMPTY) set(x, y, T.EMPTY);
      }
    }
  }

  /* ایست‌های ذخیره (پرچم میانی) — و یک ایستگاه در ورودی میدان رئیس */
  const cpSpots = def.boss ? [0.38, 0.7] : [0.38, 0.7];
  for (const f of cpSpots) {
    let cx = Math.floor(w * f);
    let guard = 0;
    while (guard++ < 12 && (get(cx, groundTop) !== T.GROUND && get(cx, groundTop) !== T.GROUND_DARK)) cx++;
    if (get(cx, groundTop) === T.GROUND || get(cx, groundTop) === T.GROUND_DARK) {
      // میلهٔ پرچم ایستگاه (بدون بلوک کنار تا بازیکن گیر نکند)
      entities.push({ type: 'checkpoint', x: cx * TILE + 6, y: (groundTop - 4) * TILE, taken: false });
    }
  }

  /* ایستگاه ذخیره در ورودی میدان رئیس (برای نبرد رئیس) */
  if (def.boss) {
    const bax = Math.max(2, endX - 28);
    let gx = bax;
    let guard2 = 0;
    while (guard2++ < 10 && (get(gx, groundTop) !== T.GROUND && get(gx, groundTop) !== T.GROUND_DARK)) gx++;
    entities.push({ type: 'checkpoint', x: gx * TILE + 6, y: (groundTop - 4) * TILE, taken: false });
    // آیتم گل آتش پیش از نبرد
    entities.push({ type: 'gift', item: 'fire', x: (gx + 3) * TILE, y: (groundTop - 4) * TILE, static: false });
  }

  /* آیتم‌های هدیه در مسیر */
  const gift = def.boss ? 'fire' : def.gift;
  const giftAt = Math.floor(w * (def.boss ? 0.62 : 0.45));
  entities.push({ type: 'gift', item: gift, x: giftAt * TILE + 3, y: (groundTop - 4) * TILE, vx: 0, vy: 0, static: false });

  /* رئیس */
  if (def.boss) {
    entities.push({
      type: 'boss', boss: def.boss, x: (endX - 16) * TILE, y: (groundTop - 2) * TILE,
      arena: { x0: (endX - 22) * TILE, x1: (endX - 2) * TILE },
    });
  }

  /* پرچم پایان و قلعه */
  entities.push({ type: 'flag', x: flagX * TILE + 4, y: (flagTop - 9) * TILE });
  decor.push({ type: 'castle', x: (w - 7) * TILE, baseY: groundTop * TILE + 3, z: 0.15 });

  /* تزئینات پس‌زمینه */
  const theme = THEMES[def.theme];
  for (let i = 0; i < Math.floor(w / 14) + 4; i++) {
    const dx = 14 + i * 14 + rng.int(-3, 3);
    const kinds = themeIdDecor(theme.id);
    decor.push({ type: rng.pick(kinds), x: dx * TILE, baseY: groundTop * TILE + 2, z: rng.chance(0.45) ? 0.3 : 0.6 });
  }

  return {
    index, n: def.n, name: def.name, theme: theme.id, themeDef: theme, diff,
    w, h, tiles, entities, decor, spawn: { x: 3 * TILE, y: (groundTop - 2) * TILE },
    goal: { x: flagX * TILE, y: (flagTop - 9) * TILE },
    pixelW: w * TILE, pixelH: h * TILE,
    time: 300, boss: def.boss,
  };
}

/* --------------------------- گودال‌ها ---------------------- */
function gapChance(diff, hazard) {
  if (hazard === 'void') return 0.5;
  return 0.32 + diff * 0.025;
}
function gapWidth(rng, diff, hazard) {
  const maxW = (hazard === 'water' || hazard === 'void' || !hazard) ? 3 : 4;
  return clamp(rng.int(2, 2 + Math.floor(diff / 3)), 2, maxW);
}

function themeIdDecor(id) {
  switch (id) {
    case 'alley': return ['tree', 'house', 'lamp', 'bush', 'kashiWall'];
    case 'bazaar': return ['stall', 'lamp', 'carpet', 'pot', 'arch'];
    case 'garden': return ['tree', 'flowerBed', 'arch', 'fountain', 'bush'];
    case 'desert': return ['dune', 'palm', 'ruin', 'pot', 'arch'];
    case 'qanat': return ['pillar', 'lamp', 'pot', 'ruin'];
    case 'persepolis': return ['column', 'ruin', 'arch', 'lamp'];
    case 'tehran': return ['city', 'lamp', 'antenna', 'city'];
    case 'damavand': return ['pine', 'ruin', 'snowRock', 'bush'];
    default: return ['tree', 'bush'];
  }
}

function pickFeatures(diff, rng) {
  const easy = [fCoinArc, fBrickRow, fPipe, fStairsUp, fPlatform, fEnemyPatrol, fPitPlatforms];
  const mid = [fCoinArc, fBrickRow, fQuestionRun, fPipe, fStairsUp, fStairsDown, fPlatform, fEnemyPair, fPitPlatforms, fTallTower, fSemiBridge];
  const hard = [fBrickRow, fQuestionRun, fPipe, fStairsUp, fStairsDown, fPlatform, fEnemyPair, fPitPlatforms, fTallTower, fSemiBridge, fSpikeRun, fCrumbleBridge, fCoinGauntlet, fEnemyTriple];
  if (diff <= 2) return easy;
  if (diff <= 5) return mid;
  return hard;
}

/* ============================== قطعه‌ها ============================== */
/* هر قطعه چند خانه مصرف می‌کند و می‌تواند دشمن/آیتم/سکه اضافه کند */

function addEnemy(entities, type, x, y, rng, dir = -1) {
  if (!type) return;
  entities.push({ type: 'enemy', enemy: type, x, y, dir, speedScale: 0.9 + rng.next() * 0.25 });
}

function chooseEnemy(def, rng) {
  const pool = def.enemies.filter((e) => {
    if (e === 'spikeball' && def.diff < 6) return false;
    if (e === 'ghost' && def.diff < 5) return false;
    if (e === 'plant' && def.diff < 3) return false;
    return true;
  });
  return pool.length ? rng.pick(pool) : null;
}

function placeCoins(set, get, x0, y0, count, rng, curve = 'line') {
  for (let i = 0; i < count; i++) {
    const x = x0 + i;
    const off = curve === 'arc' ? -Math.round(Math.sin((i / Math.max(1, count - 1)) * Math.PI) * 2) : 0;
    if (get(x, y0 + off) === T.EMPTY) set(x, y0 + off, T.COIN_TILE);
  }
}

function groundHere(get, x, groundTop) { return get(x, groundTop) === T.GROUND || get(x, groundTop) === T.GROUND_DARK; }

function fCoinArc({ x, h, groundTop, rng, set, get }) {
  const n = rng.int(5, 8);
  placeCoins(set, get, x + 2, groundTop - 3, n, rng, 'arc');
  if (rng.chance(0.35)) set(x + 3 + rng.int(0, n - 2), groundTop - 4, T.QUESTION);
  return n + 4;
}

function fBrickRow({ x, groundTop, rng, set, get, entities, def }) {
  const n = rng.int(5, 8);
  const y = groundTop - 4;
  for (let i = 0; i < n; i++) set(x + 2 + i, y, T.BRICK);
  const qs = rng.int(1, 3);
  for (let i = 0; i < qs; i++) {
    const qi = rng.int(0, n - 1);
    set(x + 2 + qi, y, rng.chance(0.25) ? T.BRICK_COIN : T.QUESTION);
  }
  if (rng.chance(0.5)) {
    const e = chooseEnemy(def, rng);
    entities.push({ type: 'enemy', enemy: e, x: (x + 3 + rng.int(0, n - 2)) * TILE, y: (groundTop - 1) * TILE, dir: -1, fall: true, speedScale: 1 });
  }
  return n + 5;
}

function fQuestionRun({ x, groundTop, rng, set, get, entities, def }) {
  const n = rng.int(3, 5);
  for (let i = 0; i < n; i++) {
    const y = groundTop - 4 - (i % 2);
    set(x + 2 + i * 2, y, rng.chance(0.3) ? T.BRICK_COIN : T.QUESTION);
    if (rng.chance(0.5)) set(x + 2 + i * 2, y - 4, T.QUESTION);
  }
  const e = chooseEnemy(def, rng);
  if (e && rng.chance(0.6)) addEnemy(entities, e, (x + 4) * TILE, (groundTop - 2) * TILE, rng);
  return n * 2 + 4;
}

function fPipe({ x, groundTop, rng, set, get, entities, def, diff }) {
  const ph = rng.int(2, 3);
  const px = x + 2;
  for (let i = 0; i < ph; i++) {
    set(px, groundTop - 1 - i, i === ph - 1 ? T.PIPE_TL : T.PIPE_BL);
    set(px + 1, groundTop - 1 - i, i === ph - 1 ? T.PIPE_TR : T.PIPE_BR);
  }
  if (rng.chance(diff >= 4 ? 0.7 : 0.3)) {
    entities.push({ type: 'enemy', enemy: 'plant', x: px * TILE, y: (groundTop - 2) * TILE, plantBase: groundTop - 2, dir: 1, speedScale: 1 });
  }
  return 6;
}

function fStairsUp({ x, groundTop, rng, set, get, entities, def }) {
  const n = rng.int(2, 4);
  for (let i = 0; i < n; i++) for (let j = 0; j <= i; j++) set(x + 2 + i, groundTop - 1 - j, T.BLOCK);
  if (rng.chance(0.5)) { const e = chooseEnemy(def, rng); addEnemy(entities, e, (x + 2 + n) * TILE, (groundTop - 1 - n) * TILE, rng, -1); }
  return n + 5;
}

function fStairsDown({ x, groundTop, rng, set, get }) {
  // تپهٔ پله‌ای: بالا رفتن آرام و پایین آمدن (همیشه قابل بالا رفتن)
  const heights = [1, 2, 3, 2, 1];
  const n = heights.length;
  for (let i = 0; i < n; i++) {
    for (let j = 0; j < heights[i]; j++) set(x + 2 + i, groundTop - 1 - j, T.BLOCK);
  }
  placeCoins(set, get, x + 2, groundTop - 5, 5, rng, 'arc');
  if (rng.chance(0.4)) set(x + 4, groundTop - 5, T.QUESTION);
  return n + 5;
}

function fPlatform({ x, groundTop, rng, set, get }) {
  const n = rng.int(4, 7);
  const y = groundTop - rng.int(3, 5);
  for (let i = 0; i < n; i++) set(x + 2 + i, y, T.SEMI);
  placeCoins(set, get, x + 3, y - 1, Math.max(3, n - 1), rng);
  return n + 5;
}

function fTallTower({ x, groundTop, rng, set, get }) {
  // پله‌ای که بالا و پایین می‌رود (بدون حفرهٔ گیرافتادن)
  const hgt = rng.int(2, 3);
  const bx = x + 3;
  for (let j = 0; j < hgt; j++) set(bx + 1, groundTop - 1 - j, T.BLOCK);
  for (let j = 0; j < hgt - 1; j++) set(bx, groundTop - 1 - j, T.BLOCK);
  set(bx + 2, groundTop - 1, T.BLOCK);
  placeCoins(set, get, bx, groundTop - hgt - 3, 3, rng, 'arc');
  if (rng.chance(0.5)) set(bx + 3, groundTop - 2, T.BRICK);
  return 9;
}

function fSemiBridge({ x, groundTop, rng, set, get, entities, def }) {
  const n = rng.int(5, 9);
  const y = groundTop - 2;
  for (let i = 0; i < n; i++) set(x + 2 + i, y, T.SEMI);
  placeCoins(set, get, x + 3, y - 1, n - 2, rng);
  const e = chooseEnemy(def, rng);
  if (e && rng.chance(0.7)) addEnemy(entities, e, (x + 2 + n) * TILE, (groundTop - 3) * TILE, rng);
  return n + 5;
}

function fEnemyPatrol({ x, groundTop, rng, entities, def }) {
  const n = rng.int(1, 2);
  for (let i = 0; i < n; i++) {
    const e = chooseEnemy(def, rng);
    addEnemy(entities, e, (x + 2 + i * 4) * TILE, (groundTop - 1) * TILE, rng, i % 2 ? 1 : -1);
  }
  return 10;
}

function fEnemyPair({ x, groundTop, rng, entities, def, set, get }) {
  const e1 = chooseEnemy(def, rng);
  const e2 = chooseEnemy(def, rng);
  addEnemy(entities, e1, (x + 2) * TILE, (groundTop - 1) * TILE, rng, -1);
  addEnemy(entities, e2, (x + 7) * TILE, (groundTop - 1) * TILE, rng, -1);
  placeCoins(set, get, x + 4, groundTop - 3, 4, rng, 'arc');
  return 12;
}

function fEnemyTriple({ x, groundTop, rng, entities, def, set, get }) {
  for (let i = 0; i < 3; i++) {
    const e = chooseEnemy(def, rng);
    addEnemy(entities, e, (x + 2 + i * 5) * TILE, (groundTop - 1) * TILE, rng, i % 2 ? 1 : -1);
  }
  set(x + 8, groundTop - 4, T.QUESTION);
  return 16;
}

function fPitPlatforms({ x, w, h, groundTop, rng, set, get, diff }) {
  const n = clamp(rng.int(2, 4) + Math.floor(diff / 3), 2, 6);
  const py = groundTop - rng.int(2, 4);
  for (let i = 0; i < n; i++) {
    const px = x + 2 + i * 3;
    set(px, py, T.SEMI); set(px + 1, py, T.SEMI);
    if (rng.chance(0.6)) set(px, py - 3, T.COIN_TILE);
    if (rng.chance(0.35)) set(px + 2, groundTop - 1, T.SPIKE);
  }
  return n * 3 + 3;
}

function fSpikeRun({ x, groundTop, rng, set, get }) {
  const n = rng.int(2, 4);
  for (let i = 0; i < n; i++) set(x + 3 + i * 2, groundTop - 1, T.SPIKE);
  placeCoins(set, get, x + 2, groundTop - 4, 5, rng, 'arc');
  return n * 2 + 6;
}

function fCrumbleBridge({ x, groundTop, w, h, rng, set, get, hazard }) {
  // پل تخریب‌شدنی روی گودال کم‌عرض (حداکثر ۴ خانه)
  const n = rng.int(3, 4);
  for (let i = 0; i < n; i++) set(x + 2 + i, groundTop - 2, T.CRUMBLE);
  for (let i = 0; i < n; i++) {
    // گودال زیر پل
    for (let y = groundTop; y < h; y++) set(x + 2 + i, y, T.EMPTY);
    if (hazard === 'water') {
      for (let y = groundTop + 1; y < h; y++) set(x + 2 + i, y, T.WATER);
    } else if (hazard === 'spike') {
      for (let y = groundTop + 2; y < h; y++) set(x + 2 + i, y, T.GROUND_DARK);
      set(x + 2 + i, groundTop + 1, T.SPIKE);
    }
  }
  placeCoins(set, get, x + 2, groundTop - 3, n, rng);
  return n + 5;
}

function fCoinGauntlet({ x, groundTop, rng, set, get, entities, def }) {
  const py = groundTop - rng.int(3, 5);
  for (let i = 0; i < 8; i++) set(x + 2 + i * 2, py, T.SEMI);
  for (let i = 0; i < 8; i++) set(x + 2 + i * 2, py - 1, T.COIN_TILE);
  const e = chooseEnemy(def, rng);
  addEnemy(entities, e, (x + 6) * TILE, (groundTop - 1) * TILE, rng);
  return 20;
}

/* ------------------------------ پرس‌وجوی خانه ---------------------------- */
export function tileAt(level, tx, ty) {
  if (tx < 0 || tx >= level.w) return T.EMPTY;
  if (ty < 0) return T.EMPTY;
  if (ty >= level.h) return T.EMPTY;
  return level.tiles[ty * level.w + tx];
}
export function setTile(level, tx, ty, t) {
  if (tx < 0 || tx >= level.w || ty < 0 || ty >= level.h) return;
  level.tiles[ty * level.w + tx] = t;
}
export function isSolid(level, tx, ty) { return SOLID_TILES.has(tileAt(level, tx, ty)); }
export function isSemi(level, tx, ty) { return tileAt(level, tx, ty) === T.SEMI; }
export function isHazard(level, tx, ty) {
  const t = tileAt(level, tx, ty);
  return t === T.SPIKE || t === T.WATER;
}
