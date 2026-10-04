/* sprites.js — پیکسل‌آرت دستیِ قهرمان‌ها، دشمنان و آیتم‌ها
 * هر اسپرایت یک «نقشهٔ متنی» است؛ هر حرف یک رنگ از پالت.
 * تصاویر یک‌بار روی canvas رندر و کش می‌شوند (با نسخهٔ آینه‌ای).
 */

const P = {
  '.': null,
  K: '#141018', // خط دور / چشم
  k: '#3a3340',
  R: '#d42a34', // قرمز
  r: '#9c1620',
  H: '#1d1a24', // مو
  S: '#f4c49a', // پوست
  s: '#d99a68',
  V: '#2f8f4e', // جلیقه
  v: '#1d6b39',
  W: '#f6f1de', // پیراهن / سفید
  w: '#cfc7ad',
  B: '#33507a', // شلوار
  b: '#22395c',
  O: '#7a4a22', // کفش
  Y: '#f5c542', // زرد
  y: '#c8901c',
  G: '#7ec850', // سبز روشن
  g: '#3f8a34',
  T: '#37b0c8', // فیروزه‌ای
  t: '#1f7f96',
  C: '#8ce0ff', // آبی روشن (بال)
  c: '#63b8d8',
  P: '#e07ad0', // صورتی
  N: '#b0703a', // قهوه‌ای روشن
  n: '#6d431f',
  E: '#e8e4d8', // سفیدِ کدر (روح)
  e: '#b8b4a8',
  A: '#ff8a2b', // نارنجی آتش
  a: '#e04a12',
  D: '#8a2b2b', // قرمز تیره
  U: '#9a5cc8', // بنفش
  u: '#6a3a92',
  M: '#f2f2f2', // سفید خالص
};

/* ------------------------------- قهرمان ------------------------------- */
const HERO_BASE = [
  '.....RRRRRR.....',
  '....RRRRRRRR....',
  '...RRRRRRRRRR...',
  '...rrrrrrrrrr...',
  '..HHHHHHHHHH....',
  '..HHSSSSSSHH....',
  '..HSSWWSSWWS....',
  '..HSSWKSSWKH....',
  '..HSSSSssSSH....',
  '..HKKKssKKKH....',
  '...SSSKSSS......',
  '...WWSSSSWW.....',
  '..VVWWWWWWVV....',
  '..SVWWWWWWVS....',
  '..BBBBBBBBBB....',
  '...BBB..BBB.....',
  '...BBB..BBB.....',
  '...OOO..OOO.....',
];

// حالت‌های پا و دست برای انیمیشن دویدن
const LEGS = {
  stand: { 15: '...BBB..BBB.....', 16: '...BBB..BBB.....', 17: '...OOO..OOO.....' },
  run1:  { 15: '...BBBB.BBB.....', 16: '...BB....BBB....', 17: '..OOOO...OOO....' },
  run2:  { 15: '....BBBBBBB.....', 16: '....BB..BBB.....', 17: '...OOOOOOO......' },
  run3:  { 15: '..BB..BBB.......', 16: '.BBB....BB......', 17: '..OOO...OOO.....' },
  jump:  { 15: '..BB..BBB.......', 16: '.BBB....BBB.....', 17: '.OOO....OOO.....' },
  fall:  { 15: '...BBB.BB.......', 16: '...BB..BB.......', 17: '...OOO..OOOO....' },
};
const ARMS = {
  down: { 12: '..VVWWWWWWVV....', 13: '..SVWWWWWWVS....' },
  up:   { 12: '..SVWWWWWWVS....', 13: '..SVWWWWWWVS....' },
  back: { 12: '..VVWWWWWWVV....', 13: '...VWWWWWWV.....' },
  hurt: { 12: '..SVWWWWWWVS....', 13: '.S..WWWWWW..S...' },
};

function applyOverrides(map, overrides) {
  const out = map.slice();
  for (const key in overrides) out[+key] = overrides[key];
  return out;
}

/** بزرگ‌کردن قهرمان: درج سطرهای اضافی در تنه و پاها (بدون افت کیفیت افقی) */
function growMap(map, torsoExtra = 3, legExtra = 2) {
  const out = [];
  for (let i = 0; i < 12; i++) out.push(map[i]);       // سر و شانه‌ها
  out.push(map[12], map[13]);                          // جلیقه
  for (let i = 0; i < torsoExtra; i++) out.push(map[13]);
  out.push(map[14]);                                   // کمر
  for (let i = 0; i < legExtra + 1; i++) out.push(map[15], map[16]);
  out.push(map[17]);                                   // کفش
  return out;
}

/* ------------------------------- دشمنان ------------------------------- */
const LADYBUG_A = [
  '.....KKKK.......',
  '....KWWWWK......',
  '....KWKKWK......',
  '....KKKKKK......',
  '...KKRRRRKK.....',
  '..KKRRRRRRKK....',
  '..KRRKKRRRRK....',
  '..KRRKKRRRRK....',
  '..KRRRRRKKRK....',
  '..KRRRRRKKRK....',
  '..KKRRRRRRKK....',
  '...KKRRRRKK.....',
  '....KKKKKK......',
  '...K.K..K.K.....',
  '..K..K..K..K....',
  '................',
];
const LADYBUG_B = applyOverrides(LADYBUG_A, {
  13: '..K..K..K..K....', 14: '...K.K..K.K.....', 15: '................',
});

const TURTLE_A = [
  '................',
  '.....KKKK.......',
  '....KGGGGK......',
  '....KWKKWK......',
  '...KKKGGKKK.....',
  '..KKGGGGGGKK....',
  '.KKGggggggGKK...',
  '.KGgGGGGGGgGK...',
  '.KGgGGGGGGgGK...',
  '.KGGgGGGGgGGK...',
  '.KKGGggggGGKK...',
  '..KKKKKKKKKK....',
  '...WWW..WWW.....',
  '...KKK..KKK.....',
  '................',
  '................',
];
const TURTLE_B = applyOverrides(TURTLE_A, { 12: '...KKK..KKK.....', 13: '...WWW..WWW.....' });
const SHELL = [
  '................',
  '................',
  '....KKKKKK......',
  '..KKGgggggKK....',
  '.KGgGGGGGGgGK...',
  '.KGgGGGGGGgGK...',
  '.KGGgGGGGgGGK...',
  '.KKGGggggGGKK...',
  '..KKGGGGGGKK....',
  '...KKKKKKKK.....',
  '................',
  '................',
];

const BEE_A = [
  '................',
  '....KK..........',
  '...K..K..KKKK...',
  '..K.CCKKKYYYYK..',
  '..K.CCKKKYYYYK..',
  '...KKKKKKKKKKK..',
  '..KKYYYYKKYYYYK.',
  '..KYYKKKKKKKKKK.',
  '..KYYKKKKKKKKK..',
  '..KKYYYYKKYYYK..',
  '...KKKKKKKKKK...',
  '.....K....K.....',
  '....K......K....',
  '................',
  '................',
  '................',
];
const BEE_B = applyOverrides(BEE_A, {
  2: '...K..K..KKKK...', 4: '..KC.CCKKKYYYYK.', 12: '................',
});

const SPIKER_A = [
  '....K..K..K.....',
  '...KgKKgKKgK....',
  '..KKgGGGGGgKK...',
  '.KgGGGGGGGGGgK..',
  '.KKGGKKGGKKGGK..',
  '.KgGKKKKKKKGGgK.',
  '.KgGGKKKKGGGGgK.',
  '..KGGGGGGGGGK...',
  '..KgGGGGGGGgK...',
  '...KKgGGGgKK....',
  '.....KKKKK......',
  '....K.K.K.K.....',
  '...KgKKgKKgK....',
  '....K.K.K.K.....',
  '................',
  '................',
];
const SPIKER_B = applyOverrides(SPIKER_A, {
  11: '..K.K.K.K.K.K...',
  12: '.KgKKgKKgKKgK...',
  13: '..K.K.K.K.K.K...',
});

const GHOST_A = [
  '.....KKKK.......',
  '...KKEEEEKK.....',
  '..KEEEEEEEEK....',
  '..KEEKKEEKEEK...',
  '..KEKKKKKKEKK...',
  '..KEEKKKKEEEK...',
  '..KEEEEEEEEEK...',
  '..KEEEEEEEEEK...',
  '..KEEKKKKEEEK...',
  '..KEEEEEEEEEK...',
  '..KEEEEEEEEEK...',
  '..KEEEEEEEEEK...',
  '..KEEEEEEEEEK...',
  '..KEEEEEEEEEK...',
  '..KEEKEEKEKKK...',
  '..KEK.EEE.K.....',
  '..KK..KKK.K.....',
  '................',
  '................',
  '................',
];
const GHOST_B = applyOverrides(GHOST_A, { 15: '..KEEEEKKEEKK...', 16: '..KK.EEK.KK.....' });

const PLANT_A = [
  '..KKKRRKKK......',
  '.KMKKRRKKMK.....',
  '.KWKRRRRKWK.....',
  '.KWRRRRRRWK.....',
  '.KWKRRRRKWK.....',
  '.KKKMMMMKKK.....',
  '..KKMMMMKK......',
  '..KKRRRRKK......',
  '...KKRRKK.......',
  '...KgGGgK.......',
  '....KGGK........',
  '....KGGK........',
  '..KKKGGKKK......',
  '..KNNNNNNK......',
  '..KNnnnnNK......',
  '..KNNNNNNK......',
  '...KNnnNK.......',
  '...KNNNNK.......',
  '....KKKK........',
  '................',
];
const PLANT_B = applyOverrides(PLANT_A, {
  0: '..KKKKKKKK......', 1: '.KMWKRRKWK......', 2: '.KKWKRRKWKK.....',
  3: '.KWRRRRRRWK.....', 4: '.KKKWKKWKKK.....',
});

/* -------------------------------- آیتم‌ها ------------------------------ */
const MUSHROOM = [
  '....KKKKKK......',
  '..KKMMMMMMKK....',
  '.KMMKKMMKKMMK...',
  '.KMKKKMMKKKKK...',
  'KMMKKMMMMKKMMK..',
  'KMMMMMMMMMMMMK..',
  'KRRRRRRRRRRRRK..',
  'KKRRRRRRRRRRKK..',
  '.KKWWWWWWWWKK...',
  '..KWWKKKKWWK....',
  '..KWWKKKKWWK....',
  '..KWWWWWWWWK....',
  '..KWWWWWWWWK....',
  '..KKWWWWWWKK....',
  '...KKKKKKKK.....',
  '................',
];
const MUSHROOM_1UP = MUSHROOM.map((row) => row.replace(/R/g, 'G').replace(/M/g, 'W'));
const MUSHROOM_LIFE = MUSHROOM.map((row) => row.replace(/R/g, 'T').replace(/M/g, 'W'));

const FLOWER = [
  '....KKKK........',
  '..KKAAAAKK......',
  '.KAAKKKKAAK.....',
  '.KAKKWWKKAK.....',
  '.KAKKWWKKAK.....',
  '..KAAKKAAK......',
  '...KKAAKK.......',
  '....KGGK........',
  '...KGGGGK.......',
  '..KGGKKGGK......',
  '..KKGKKGKK......',
  '...KGGGGK.......',
  '....KGGK........',
  '....KGGK........',
  '...KKGGKK.......',
  '...KgGGgK.......',
  '....KGGK........',
  '....KGGK........',
  '...KgGGgK.......',
  '...KKKKKK.......',
];
const STAR = [
  '.......KK.......',
  '......KYYK......',
  '......KYYK......',
  '.....KKYYKK.....',
  'KKKKKKYYYYKKKKKK',
  '.KYYYYYYYYYYYYK.',
  '..KYYKYYYYKYYK..',
  '...KYKYYYYKYK...',
  '...KYKYYYYKYK...',
  '...KYKYYYYKYK...',
  '..KKYKYYYYKYKK..',
  '..KYYKYYYYKYYK..',
  '..KY.KYYYY.KYK..',
  '..KK..KYYK..KK..',
  '......KYYK......',
  '......KKKK......',
];
const FEATHER = [
  '.........KKK....',
  '.......KKCCK....',
  '.....KKCCCCK....',
  '....KCCCCCCK....',
  '...KCCTTTTCK....',
  '..KCCTTTTTTK....',
  '..KCTTTTTTK.....',
  '..KTTTTTTK......',
  '..KTTTTTK.......',
  '..KTTTTKK.......',
  '...KTTTK........',
  '...KTTK.........',
  '..KTTK..........',
  '..KTK...........',
  '...KK...........',
  '................',
];
const COIN = [
  '......KKKK......',
  '....KKYYYYKK....',
  '...KYYYYYYYYK...',
  '..KYYYyyyyYYYK..',
  '.KKYYWWyyyyYYKK.',
  '.KYYWyyyyyyyYYK.',
  '.KYYWyyyyyyyYYK.',
  '.KYyyyyyyyyyyYK.',
  '.KYYyyyyyyyyYYK.',
  '.KYYyyyyyyyyYYK.',
  '.KKYYyyyyyyYYKK.',
  '..KYYYyyyyYYYK..',
  '...KYYYYYYYYK...',
  '....KKYYYYKK....',
  '......KKKK......',
  '................',
];
const FIREBALL = [
  '.KKKK...',
  'KAAYYKK.',
  'KAYYYYAK',
  'KYYWWYYK',
  'KAYYYYAK',
  '.KAAYAK.',
  '..KKK...',
  '........',
];
const SPRING = [
  '..KKKKKKKKKK....',
  '..KgGGGGGGgK....',
  '..KKKKKKKKKK....',
  '...KggggggK.....',
  '..KKKKKKKKKK....',
  '..KgGGGGGGgK....',
  '..KKKKKKKKKK....',
  '................',
];

/* ------------------------------- ساخت کش ------------------------------- */
function mapSize(map) { return { w: map[0].length, h: map.length }; }

function drawMapToCanvas(map, palette, flip = false, tint = null) {
  const { w, h } = mapSize(map);
  const cv = document.createElement('canvas');
  cv.width = w; cv.height = h;
  const g = cv.getContext('2d');
  for (let y = 0; y < h; y++) {
    const row = map[y];
    for (let x = 0; x < w; x++) {
      const ch = row[x];
      if (!ch || ch === '.') continue;
      let col = palette[ch] || P[ch];
      if (tint) col = tint(col, ch);
      if (!col) continue;
      g.fillStyle = col;
      g.fillRect(flip ? w - 1 - x : x, y, 1, 1);
    }
  }
  return cv;
}

function flipped(cv) {
  const out = document.createElement('canvas');
  out.width = cv.width; out.height = cv.height;
  const g = out.getContext('2d');
  g.translate(cv.width, 0); g.scale(-1, 1);
  g.drawImage(cv, 0, 0);
  return out;
}

export const Sprites = {};

export function buildSprites() {
  const S = Sprites;

  /* --- قهرمان: کوچک / بزرگ / آتشین، در دو جهت، ۶ حالت --- */
  const heroFrames = {
    idle: applyOverrides(HERO_BASE, { ...LEGS.stand, ...ARMS.down }),
    run1: applyOverrides(HERO_BASE, { ...LEGS.run1, ...ARMS.down }),
    run2: applyOverrides(HERO_BASE, { ...LEGS.run2, ...ARMS.back }),
    run3: applyOverrides(HERO_BASE, { ...LEGS.run3, ...ARMS.up }),
    jump: applyOverrides(HERO_BASE, { ...LEGS.jump, ...ARMS.up }),
    fall: applyOverrides(HERO_BASE, { ...LEGS.fall, ...ARMS.hurt }),
    skid: applyOverrides(HERO_BASE, { ...LEGS.run2, ...ARMS.back }),
    crouch: applyOverrides(HERO_BASE, { ...LEGS.run2, ...ARMS.down, 5: '...HHSSSSHH.....', 6: '...HSSKSSKH.....', 9: '..SKKKSSKKS.....' }),
  };
  const palettes = {
    small: {},
    fire: { V: '#f4f1e6', v: '#c9c4b4', W: '#e04a2a', w: '#b03018' },
    star: {},
  };
  S.hero = {};
  for (const sizeKey of ['small', 'big']) {
    S.hero[sizeKey] = {};
    const grown = sizeKey === 'big';
    for (const state in heroFrames) {
      const map = grown ? growMap(heroFrames[state], 3, 3) : heroFrames[state];
      for (const kind of ['normal', 'fire', 'star1', 'star2']) {
        const pal = kind === 'fire' ? palettes.fire : {};
        const tint = kind === 'star1'
          ? (col) => (col === P.K ? '#141018' : '#fff2a0')
          : kind === 'star2' ? (col) => (col === P.K ? '#141018' : '#a8f0ff') : null;
        const right = drawMapToCanvas(map, pal, false, tint);
        S.hero[sizeKey][kind + '_' + state] = { r: right, l: flipped(right) };
      }
    }
  }

  /* --- دشمنان --- */
  const enemyDefs = {
    ladybug: [LADYBUG_A, LADYBUG_B],
    turtle: [TURTLE_A, TURTLE_B],
    shell: [SHELL],
    bee: [BEE_A, BEE_B],
    spikeball: [SPIKER_A, SPIKER_B],
    ghost: [GHOST_A, GHOST_B],
    plant: [PLANT_A, PLANT_B],
  };
  S.enemy = {};
  for (const name in enemyDefs) {
    S.enemy[name] = enemyDefs[name].map((m) => {
      const right = drawMapToCanvas(m, {});
      return { r: right, l: flipped(right) };
    });
  }

  /* --- آیتم‌ها و اجسام --- */
  S.item = {
    mushroom: drawMapToCanvas(MUSHROOM, {}),
    mushroom1up: drawMapToCanvas(MUSHROOM_1UP, {}),
    mushroomLife: drawMapToCanvas(MUSHROOM_LIFE, {}),
    flower: drawMapToCanvas(FLOWER, {}),
    star: drawMapToCanvas(STAR, {}),
    feather: drawMapToCanvas(FEATHER, {}),
    coin: drawMapToCanvas(COIN, {}),
    fireball: drawMapToCanvas(FIREBALL, {}),
    spring: drawMapToCanvas(SPRING, {}),
  };
  return S;
}

export function spriteSize(cv) { return { w: cv.width, h: cv.height }; }

/* =========================================================================
 *  رئیس‌ها (دیو و اژدها) — با شکل‌های پیکسلیِ کد ساخته می‌شوند
 *  هر رئیس چند «حالت» دارد که با شمارهٔ فریم انتخاب می‌شوند.
 * ========================================================================= */
function pixelCtx(w, h, scale = 2) {
  const cv = document.createElement('canvas');
  cv.width = w * scale; cv.height = h * scale;
  const g = cv.getContext('2d');
  g.imageSmoothingEnabled = false;
  g.save();
  g.scale(scale, scale);
  return { cv, g, scale };
}

function rect(g, x, y, w, h, c) { g.fillStyle = c; g.fillRect(x, y, w, h); }
function tri(g, x1, y1, x2, y2, x3, y3, c) {
  g.fillStyle = c; g.beginPath();
  g.moveTo(x1, y1); g.lineTo(x2, y2); g.lineTo(x3, y3); g.closePath(); g.fill();
}
function ell(g, cx, cy, rx, ry, c) {
  g.fillStyle = c; g.beginPath(); g.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2); g.fill();
}

const DIV_PAL = {
  skin: '#4f8a6a', skinD: '#2f5f47', horn: '#e8dcc0', hornD: '#b8a884',
  pant: '#b03a3a', pantD: '#7a2626', sash: '#e8c25a', eye: '#ffd24a', out: '#1a1418',
  club: '#8a5a2a', clubD: '#5f3c18', belt: '#3a3128', teeth: '#f4f0e0',
};

/** دیو کویر: هیکل درشت با شاخ، گرز و شلوار قرمز */
function drawDivFrame(pose) {
  const { cv, g } = pixelCtx(30, 30, 2);
  const P = DIV_PAL;
  const bob = pose.bob || 0;
  const y0 = 2 + bob;
  // پاها
  rect(g, 9, 22 - bob, 5, 6, P.pantD);
  rect(g, 17, 22 - bob, 5, 6, P.pantD);
  rect(g, 8, 27 - bob, 7, 3, P.out);
  rect(g, 16, 27 - bob, 7, 3, P.out);
  rect(g, 9, 22 - bob, 5, 2, P.pant);
  rect(g, 17, 22 - bob, 5, 2, P.pant);
  // تنه
  rect(g, 7, y0 + 7, 17, 12, P.out);
  rect(g, 8, y0 + 8, 15, 10, P.skin);
  rect(g, 8, y0 + 12, 15, 4, P.sash);
  rect(g, 7, y0 + 16, 17, 3, P.belt);
  // بازوی چپ (گرز)
  const armSwing = pose.swing || 0;
  rect(g, 3, y0 + 7 + armSwing, 4, 9, P.out);
  rect(g, 4, y0 + 8 + armSwing, 3, 8, P.skinD);
  // گرز
  rect(g, 1, y0 + 2 + armSwing, 2, 10, P.clubD);
  ell(g, 2, y0 + 1 + armSwing, 4, 3.5, P.out);
  ell(g, 2, y0 + 1 + armSwing, 3, 2.5, P.club);
  rect(g, 5, y0 + 2 + armSwing, 3, 1, P.hornD);
  // بازوی راست
  rect(g, 24, y0 + 8, 4, 9, P.out);
  rect(g, 24, y0 + 9, 3, 8, P.skin);
  // سر
  rect(g, 8, y0, 15, 12, P.out);
  rect(g, 9, y0 + 1, 13, 10, P.skin);
  // شاخ‌ها
  tri(g, 9, y0 + 1, 7, y0 - 4, 12, y0 + 1, P.horn);
  tri(g, 22, y0 + 1, 24, y0 - 4, 19, y0 + 1, P.horn);
  tri(g, 10, y0 + 1, 8, y0 - 2, 12, y0 + 1, P.hornD);
  tri(g, 21, y0 + 1, 23, y0 - 2, 19, y0 + 1, P.hornD);
  // ابرو و چشم‌های درخشان
  rect(g, 10, y0 + 3, 4, 2, P.out);
  rect(g, 18, y0 + 3, 4, 2, P.out);
  rect(g, 11, y0 + 4, 2, 2, P.eye);
  rect(g, 19, y0 + 4, 2, 2, P.eye);
  // بینی و دهان با دندان
  rect(g, 15, y0 + 5, 2, 3, P.skinD);
  rect(g, 11, y0 + 8, 9, 2, P.out);
  if (pose.roar) { rect(g, 11, y0 + 8, 9, 4, '#5a1010'); rect(g, 12, y0 + 8, 2, 2, P.teeth); rect(g, 16, y0 + 8, 2, 2, P.teeth); rect(g, 14, y0 + 10, 2, 2, P.teeth); }
  else { rect(g, 12, y0 + 9, 2, 2, P.teeth); rect(g, 17, y0 + 9, 2, 2, P.teeth); }
  // ریش‌بز
  rect(g, 13, y0 + 10, 5, 2, P.skinD);
  g.restore();
  return cv;
}

const DRG_PAL = {
  body: '#3f8f5f', bodyD: '#245c3c', belly: '#d8c27a', horn: '#f0e6c8',
  wing: '#c8452f', wingD: '#8a2a1c', eye: '#ffe14a', out: '#181420', teeth: '#f6f2e2', fire: '#ff9a2b',
};

/** اژدهای دماوند: بال‌دار با دم و آتش */
function drawDragonFrame(pose) {
  const { cv, g } = pixelCtx(38, 26, 2);
  const P = DRG_PAL;
  const flap = pose.flap || 0;
  const y0 = 3;
  // دم
  rect(g, 1, y0 + 12, 6, 3, P.bodyD);
  tri(g, 1, y0 + 12, 3, y0 + 8, 4, y0 + 15, P.bodyD);
  // بدن
  ell(g, 18, y0 + 12, 11, 6, P.out);
  ell(g, 18, y0 + 12, 10, 5, P.body);
  ell(g, 17, y0 + 15, 7, 3, P.belly);
  // بال‌ها
  tri(g, 12, y0 + 7, 2, y0 + 1 - flap, 20, y0 + 6, P.wing);
  tri(g, 12, y0 + 8, 5, y0 + 4 - flap, 18, y0 + 8, P.wingD);
  tri(g, 24, y0 + 7, 34, y0 + 1 - flap, 26, y0 + 6, P.wing);
  tri(g, 24, y0 + 8, 31, y0 + 4 - flap, 26, y0 + 8, P.wingD);
  // پاها
  rect(g, 16, y0 + 17, 4, 5, P.bodyD);
  rect(g, 24, y0 + 17, 4, 5, P.bodyD);
  rect(g, 15, y0 + 21, 6, 2, P.out);
  rect(g, 23, y0 + 21, 6, 2, P.out);
  // گردن و سر
  rect(g, 30, y0 + 5, 4, 8, P.out);
  rect(g, 31, y0 + 5, 3, 8, P.body);
  ell(g, 34, y0 + 4, 5, 4, P.out);
  ell(g, 34, y0 + 4, 4, 3, P.body);
  // پوزه و شاخ
  rect(g, 36, y0 + 4, 4, 3, P.body);
  rect(g, 36, y0 + 4, 4, 1, P.out);
  tri(g, 32, y0 + 1, 31, y0 - 3, 35, y0 + 1, P.horn);
  tri(g, 29, y0 + 2, 27, y0 - 2, 31, y0 + 2, P.horn);
  // چشم
  rect(g, 33, y0 + 3, 2, 2, P.eye);
  rect(g, 33, y0 + 3, 1, 1, P.out);
  // دهان / آتش
  rect(g, 36, y0 + 6, 4, 1, P.out);
  if (pose.breath) {
    rect(g, 39, y0 + 5, 6, 3, P.fire);
    rect(g, 44, y0 + 5, 4, 3, '#ffd24a');
    rect(g, 39, y0 + 4, 3, 1, '#ff5a2b');
  }
  if (pose.hurt) { rect(g, 33, y0 + 2, 3, 3, '#ffffff'); }
  g.restore();
  return cv;
}

/** ساخت همهٔ فریم‌های رئیس‌ها + نسخهٔ آینه‌ای */
export function buildBossSprites() {
  const divFrames = [
    ['idle', { bob: 0 }], ['idle2', { bob: 1 }],
    ['walk1', { bob: 0, swing: 3 }], ['walk2', { bob: 1, swing: -3 }],
    ['attack', { bob: 0, swing: -6, roar: true }], ['hurt', { bob: 2, roar: true }],
  ];
  const drgFrames = [
    ['idle', { flap: 0 }], ['flap1', { flap: 3 }], ['flap2', { flap: 5 }],
    ['breath', { flap: 2, breath: true }], ['hurt', { flap: 1, hurt: true }],
  ];
  const out = { div: {}, dragon: {} };
  for (const [name, pose] of divFrames) out.div[name] = drawDivFrame(pose);
  for (const [name, pose] of drgFrames) out.dragon[name] = drawDragonFrame(pose);
  // در دسترس رندرکننده قرار می‌گیرد (render.js از Sprite.boss استفاده می‌کند)
  Sprites.boss = out;
  return out;
}
