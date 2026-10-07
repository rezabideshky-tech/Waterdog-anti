/* tools/gen_assets.mjs — ساخت همهٔ تکسچرها و آیکون‌های اختصاصی «جاده‌باز»
 * ماشین‌های ایرانی (پیکان، پراید، سمند، ون، نیسان، بنز ۶۰۸، خاور)، چرخ‌ها، زمین،
 * پس‌زمینه‌ها، تزئین‌ها، دکمه‌ها و آیکون‌های رابط — همه وکتوری و بدون تصویر بیرونی.
 * اجرا: cd jadebaz/tools && npm install && node gen_assets.mjs
 */
import { createCanvas, loadImage } from '@napi-rs/canvas';
import { writeFileSync, mkdirSync } from 'node:fs';

const ROOT = new URL('..', import.meta.url).pathname;
const A = (p) => { mkdirSync(ROOT + 'assets/' + p, { recursive: true }); return ROOT + 'assets/' + p + '/'; };
const OUT = {
  cars: A('cars'), wheels: A('wheels'), terrain: A('terrain'), deco: A('deco'),
  bg: A('bg'), ui: A('ui'), fx: A('fx'), root: ROOT,
};

const save = (dir, name, cv) => { writeFileSync(dir + name + '.png', cv.toBuffer('image/png')); console.log('  •', name); };
const cvOf = (w, h) => createCanvas(w, h);
function rr(g, x, y, w, h, r) {
  const rad = Math.min(r, Math.abs(w) / 2, Math.abs(h) / 2);
  g.beginPath();
  g.moveTo(x + rad, y);
  g.arcTo(x + w, y, x + w, y + h, rad);
  g.arcTo(x + w, y + h, x, y + h, rad);
  g.arcTo(x, y + h, x, y, rad);
  g.arcTo(x, y, x + w, y, rad);
  g.closePath();
}
function poly(g, pts, fill) {
  g.beginPath();
  g.moveTo(pts[0][0], pts[0][1]);
  for (let i = 1; i < pts.length; i++) g.lineTo(pts[i][0], pts[i][1]);
  g.closePath();
  if (fill) { g.fillStyle = fill; g.fill(); }
}
const stroke = (g, w, c) => { g.lineWidth = w; g.strokeStyle = c; g.stroke(); };

/* ============================ ماشین‌های ایرانی ============================ */
/* هر ماشین دو لایه دارد: بدنهٔ رنگی (paint، سفید کشیده می‌شود و در بازی رنگ می‌گیرد)
 * و جزئیات (details: شیشه، چراغ، سپر، خط و نشان). دید از نمای کنار. */

const W = 560, H = 240;               // بوم هر ماشین
const GROUND = 196;                   // خط زمین (مرکز چرخ‌ها)

/** ورودی: طرح بدنه به‌صورت مجموعه‌ای از مسیرها + جای چرخ‌ها */
function paintLayer(draw) {
  const cv = cvOf(W, H);
  const g = cv.getContext('2d');
  draw(g, { mode: 'paint' });
  return cv;
}
function detailLayer(draw) {
  const cv = cvOf(W, H);
  const g = cv.getContext('2d');
  draw(g, { mode: 'detail' });
  return cv;
}

/** شیشهٔ دودی با بازتاب */
function glass(g, pts, tint = 0.72) {
  poly(g, pts, `rgba(28,44,62,${tint})`);
  g.save();
  g.beginPath();
  g.moveTo(pts[0][0], pts[0][1]);
  for (const p of pts.slice(1)) g.lineTo(p[0], p[1]);
  g.closePath(); g.clip();
  const grad = g.createLinearGradient(0, 60, 0, 150);
  grad.addColorStop(0, 'rgba(255,255,255,.34)');
  grad.addColorStop(0.55, 'rgba(255,255,255,.06)');
  grad.addColorStop(1, 'rgba(0,0,0,.18)');
  g.fillStyle = grad; g.fillRect(0, 0, W, H);
  g.restore();
}

const CARS = {
  /* پیکان — سدان چهارگوش نوستالژیک */
  peykan: {
    name: 'پیکان', wheelbase: [-108, 104], wheelR: 30, bodyY: GROUND,
    draw(g, o) {
      const paint = o.mode === 'paint';
      if (paint) {
        poly(g, [[62, 104], [104, 74], [286, 74], [330, 104], [470, 108], [488, 128], [488, 156], [60, 156]], '#e9edf2');
        rr(g, 60, 112, 430, 46, 8); g.fillStyle = '#e9edf2'; g.fill();
      } else {
        g.strokeStyle = 'rgba(0,0,0,.22)'; g.lineWidth = 3; g.stroke();
      }
      return { back: [60, 112, 430, 46] };
    },
  },
};

/* نگارخانهٔ ماشین‌ها */
const CAR_DEFS = [
  { id: 'peykan', name: 'پیکان', wheelbase: [-104, 100], wheelR: 29, bodyTop: 74, detail() { } },
];

/* --- برای خوانایی، هر ماشین را جداگانه می‌کشیم --- */
function carPeykan(g, o) {
  const p = o.mode === 'paint';
  const body = '#eef2f6';
  // بدنهٔ اصلی
  poly(g, [[54, 118], [96, 82], [206, 74], [286, 78], [330, 112], [470, 118], [494, 140], [494, 158], [54, 158]], p ? body : 'rgba(0,0,0,0)');
  if (!p) { stroke(g, 3, 'rgba(20,28,40,.55)'); }
  if (p) {
    g.save(); g.clip();
    const grad = g.createLinearGradient(0, 74, 0, 170);
    grad.addColorStop(0, 'rgba(255,255,255,.35)');
    grad.addColorStop(0.5, 'rgba(255,255,255,0)');
    grad.addColorStop(1, 'rgba(0,0,0,.22)');
    g.fillStyle = grad; g.fillRect(0, 0, W, H);
    g.restore();
  }
  if (!p) {
    // شیشه‌ها
    glass(g, [[104, 84], [198, 78], [200, 116], [100, 116]]);
    glass(g, [[208, 78], [282, 82], [322, 114], [208, 114]]);
    // درز در
    stroke(g, 2.5, 'rgba(20,28,40,.45)');
    g.beginPath(); g.moveTo(202, 80); g.lineTo(204, 152); g.stroke();
    // دستگیره
    g.fillStyle = '#c9ced6'; rr(g, 216, 122, 34, 7, 3); g.fill();
    g.fillStyle = '#b6bcc4'; rr(g, 150, 122, 30, 7, 3); g.fill();
    // چراغ جلو و عقب
    g.fillStyle = '#ffe9a8'; rr(g, 476, 122, 20, 16, 5); g.fill();
    g.fillStyle = '#ff8a6a'; rr(g, 54, 122, 18, 16, 5); g.fill();
    poly(g, [[54, 112], [72, 104], [72, 118], [54, 122]], '#d8dee6');
    // سپرها
    g.fillStyle = '#dfe4ea'; rr(g, 46, 140, 24, 16, 4); g.fill(); rr(g, 480, 140, 26, 16, 4); g.fill();
    // خط تزئینی کنار
    g.fillStyle = 'rgba(30,40,56,.35)'; g.fillRect(64, 134, 424, 4);
    // چرخ‌خانه‌ها
    g.fillStyle = 'rgba(15,20,30,.85)';
    g.beginPath(); g.arc(120, GROUND, 36, Math.PI, 0); g.fill();
    g.beginPath(); g.arc(420, GROUND, 36, Math.PI, 0); g.fill();
  }
}
function carPride(g, o) {
  const p = o.mode === 'paint';
  poly(g, [[66, 122], [110, 84], [236, 76], [312, 84], [346, 116], [468, 122], [488, 142], [488, 158], [66, 158]], p ? '#eef2f6' : 'rgba(0,0,0,0)');
  if (p) {
    g.save(); g.clip();
    const grad = g.createLinearGradient(0, 76, 0, 170);
    grad.addColorStop(0, 'rgba(255,255,255,.38)'); grad.addColorStop(0.55, 'rgba(255,255,255,0)');
    grad.addColorStop(1, 'rgba(0,0,0,.22)');
    g.fillStyle = grad; g.fillRect(0, 0, W, H); g.restore();
  } else {
    glass(g, [[120, 86], [230, 80], [232, 120], [114, 120]]);
    glass(g, [[240, 80], [308, 88], [338, 118], [240, 118]]);
    g.fillStyle = '#ffe9a8'; rr(g, 470, 126, 20, 14, 5); g.fill();
    g.fillStyle = '#ff8a6a'; rr(g, 66, 126, 16, 14, 5); g.fill();
    g.fillStyle = 'rgba(30,40,56,.32)'; g.fillRect(80, 138, 400, 4);
    g.fillStyle = 'rgba(15,20,30,.85)';
    g.beginPath(); g.arc(140, GROUND, 34, Math.PI, 0); g.fill();
    g.beginPath(); g.arc(416, GROUND, 34, Math.PI, 0); g.fill();
    stroke(g, 3, 'rgba(20,28,40,.5)');
  }
}
function carSamand(g, o) {
  const p = o.mode === 'paint';
  poly(g, [[58, 120], [104, 78], [250, 70], [330, 80], [372, 114], [486, 120], [500, 142], [500, 158], [58, 158]], p ? '#eef2f6' : 'rgba(0,0,0,0)');
  if (p) {
    g.save(); g.clip();
    const grad = g.createLinearGradient(0, 70, 0, 172);
    grad.addColorStop(0, 'rgba(255,255,255,.4)'); grad.addColorStop(0.5, 'rgba(255,255,255,.02)');
    grad.addColorStop(1, 'rgba(0,0,0,.24)');
    g.fillStyle = grad; g.fillRect(0, 0, W, H); g.restore();
  } else {
    glass(g, [[114, 82], [244, 74], [246, 116], [108, 116]]);
    glass(g, [[256, 76], [326, 86], [364, 116], [256, 116]]);
    g.fillStyle = '#f6f8fa';
    // چراغ‌های باریک
    rr(g, 478, 124, 22, 12, 4); g.fillStyle = '#ffe9a8'; g.fill();
    rr(g, 58, 124, 18, 12, 4); g.fillStyle = '#ff8a6a'; g.fill();
    g.fillStyle = 'rgba(30,40,56,.3)'; g.fillRect(74, 138, 424, 4);
    g.fillStyle = 'rgba(15,20,30,.85)';
    g.beginPath(); g.arc(134, GROUND, 35, Math.PI, 0); g.fill();
    g.beginPath(); g.arc(430, GROUND, 35, Math.PI, 0); g.fill();
    stroke(g, 3, 'rgba(20,28,40,.5)');
  }
}
function carVan(g, o) {                          // ون دلیکا/هیوندای
  const p = o.mode === 'paint';
  poly(g, [[70, 60], [400, 56], [452, 74], [470, 106], [470, 156], [70, 156]], p ? '#eef2f6' : 'rgba(0,0,0,0)');
  if (p) {
    g.save(); g.clip();
    const grad = g.createLinearGradient(0, 56, 0, 172);
    grad.addColorStop(0, 'rgba(255,255,255,.35)'); grad.addColorStop(0.55, 'rgba(255,255,255,.02)');
    grad.addColorStop(1, 'rgba(0,0,0,.2)');
    g.fillStyle = grad; g.fillRect(0, 0, W, H); g.restore();
  } else {
    glass(g, [[92, 74], [200, 70], [200, 112], [92, 112]]);
    glass(g, [[210, 70], [330, 66], [330, 110], [210, 110]]);
    glass(g, [[420, 80], [452, 92], [456, 116], [420, 116]]);
    g.fillStyle = 'rgba(30,40,56,.32)'; g.fillRect(84, 132, 380, 6);
    g.fillStyle = '#ffe9a8'; rr(g, 456, 122, 16, 14, 4); g.fill();
    g.fillStyle = '#ff8a6a'; rr(g, 66, 120, 14, 16, 4); g.fill();
    g.fillStyle = 'rgba(15,20,30,.85)';
    g.beginPath(); g.arc(140, GROUND, 33, Math.PI, 0); g.fill();
    g.beginPath(); g.arc(400, GROUND, 33, Math.PI, 0); g.fill();
    stroke(g, 3, 'rgba(20,28,40,.5)');
  }
}
function carNissan(g, o) {                       // نیسان زامیاد (وانت با قفس)
  const p = o.mode === 'paint';
  // اتاقک + کفی
  poly(g, [[70, 78], [250, 74], [286, 96], [286, 156], [70, 156]], p ? '#eef2f6' : 'rgba(0,0,0,0)');
  if (p) {
    rr(g, 278, 104, 216, 52, 6); g.fillStyle = '#eef2f6'; g.fill();
    g.save(); g.clip();
    const grad = g.createLinearGradient(0, 74, 0, 172);
    grad.addColorStop(0, 'rgba(255,255,255,.34)'); grad.addColorStop(0.55, 'rgba(255,255,255,.02)');
    grad.addColorStop(1, 'rgba(0,0,0,.22)');
    g.fillStyle = grad; g.fillRect(0, 0, W, H); g.restore();
  } else {
    glass(g, [[92, 88], [230, 84], [240, 122], [92, 126]]);
    // قفس فلزی روی کفی
    g.strokeStyle = 'rgba(70,84,104,.85)'; g.lineWidth = 4;
    for (let i = 0; i <= 6; i++) { g.beginPath(); g.moveTo(292 + i * 32, 104); g.lineTo(292 + i * 32, 74); g.stroke(); }
    g.beginPath(); g.moveTo(288, 76); g.lineTo(492, 76); g.stroke();
    g.fillStyle = 'rgba(15,20,30,.85)';
    g.beginPath(); g.arc(140, GROUND, 32, Math.PI, 0); g.fill();
    g.beginPath(); g.arc(420, GROUND, 32, Math.PI, 0); g.fill();
    g.fillStyle = '#ffe9a8'; rr(g, 60, 108, 14, 14, 4); g.fill();
    g.fillStyle = '#ffb45a'; rr(g, 486, 138, 14, 12, 3); g.fill();
    stroke(g, 3, 'rgba(20,28,40,.5)');
  }
}
function carBenz(g, o) {                          // بنز ۶۰۸ کمپرسی (کامیون)
  const p = o.mode === 'paint';
  poly(g, [[64, 46], [250, 44], [286, 78], [286, 156], [64, 156]], p ? '#eef2f6' : 'rgba(0,0,0,0)');
  if (p) {
    // کمپرسی
    poly(g, [[292, 92], [520, 96], [520, 148], [292, 148]], '#eef2f6');
    poly(g, [[300, 96], [514, 100], [514, 132], [300, 132]], '#f6f8fa');
    g.save(); g.clip();
    const grad = g.createLinearGradient(0, 44, 0, 172);
    grad.addColorStop(0, 'rgba(255,255,255,.3)'); grad.addColorStop(0.55, 'rgba(255,255,255,.02)');
    grad.addColorStop(1, 'rgba(0,0,0,.25)');
    g.fillStyle = grad; g.fillRect(0, 0, W, H); g.restore();
  } else {
    glass(g, [[84, 58], [232, 56], [238, 104], [84, 108]]);
    // نردهٔ کمپرسی
    g.strokeStyle = 'rgba(150,120,80,.9)'; g.lineWidth = 5;
    for (let i = 0; i <= 5; i++) { g.beginPath(); g.moveTo(310 + i * 40, 96); g.lineTo(310 + i * 40, 62); g.stroke(); }
    g.beginPath(); g.moveTo(300, 64); g.lineTo(516, 68); g.stroke();
    g.fillStyle = '#40474f'; rr(g, 64, 140, 30, 18, 4); g.fill();
    g.fillStyle = 'rgba(15,20,30,.85)';
    g.beginPath(); g.arc(150, GROUND, 40, Math.PI, 0); g.fill();
    g.beginPath(); g.arc(430, GROUND, 40, Math.PI, 0); g.fill();
    g.fillStyle = '#ffe9a8'; rr(g, 62, 118, 12, 12, 3); g.fill();
    stroke(g, 3.5, 'rgba(20,28,40,.5)');
  }
}
function carKhavar(g, o) {                        // خاور (کامیون باری با اتاقک جلو)
  const p = o.mode === 'paint';
  poly(g, [[70, 54], [230, 52], [266, 92], [266, 156], [70, 156]], p ? '#eef2f6' : 'rgba(0,0,0,0)');
  if (p) {
    poly(g, [[272, 104], [524, 106], [524, 150], [272, 150]], '#eef2f6');
    g.save(); g.clip();
    const grad = g.createLinearGradient(0, 52, 0, 172);
    grad.addColorStop(0, 'rgba(255,255,255,.32)'); grad.addColorStop(0.55, 'rgba(255,255,255,.02)');
    grad.addColorStop(1, 'rgba(0,0,0,.22)');
    g.fillStyle = grad; g.fillRect(0, 0, W, H); g.restore();
  } else {
    glass(g, [[88, 66], [214, 64], [220, 104], [88, 108]]);
    g.fillStyle = 'rgba(40,50,64,.5)'; g.fillRect(280, 118, 236, 34);
    for (let i = 0; i < 5; i++) { g.fillStyle = 'rgba(0,0,0,.16)'; g.fillRect(284 + i * 46, 118, 2, 34); }
    g.fillStyle = 'rgba(15,20,30,.85)';
    g.beginPath(); g.arc(150, GROUND, 40, Math.PI, 0); g.fill();
    g.beginPath(); g.arc(440, GROUND, 40, Math.PI, 0); g.fill();
    g.fillStyle = '#ffe9a8'; rr(g, 68, 116, 12, 14, 3); g.fill();
    stroke(g, 3.5, 'rgba(20,28,40,.5)');
  }
}

const CAR_LIST = [
  ['peykan', carPeykan, [-108, 420 - 108 - 208]],
  ['pride', carPride, [0, 0]],
  ['samand', carSamand, [0, 0]],
  ['van', carVan, [0, 0]],
  ['nissan', carNissan, [0, 0]],
  ['benz', carBenz, [0, 0]],
  ['khavar', carKhavar, [0, 0]],
];
const CAR_RENDER = { peykan: carPeykan, pride: carPride, samand: carSamand, van: carVan, nissan: carNissan, benz: carBenz, khavar: carKhavar };

/* ------------------------------ ساخت ماشین‌ها ---------------------------- */
console.log('🚗 ماشین‌های ایرانی...');
for (const [id, fn] of Object.entries(CAR_RENDER)) {
  save(OUT.cars, id + '_paint', paintLayer(fn));
  save(OUT.cars, id + '_detail', detailLayer(fn));
}

/* --------------------------------- چرخ‌ها -------------------------------- */
console.log('🛞 چرخ و رینگ...');
{
  const R = 96;
  const tire = cvOf(R * 2, R * 2);
  const g = tire.getContext('2d');
  g.fillStyle = '#1c1f24';
  g.beginPath(); g.arc(R, R, R - 4, 0, Math.PI * 2); g.fill();
  g.strokeStyle = '#0e1114'; g.lineWidth = 6;
  g.beginPath(); g.arc(R, R, R - 8, 0, Math.PI * 2); g.stroke();
  // آج لاستیک
  g.strokeStyle = '#2c3138'; g.lineWidth = 5;
  for (let i = 0; i < 36; i++) {
    const a = i / 36 * Math.PI * 2;
    g.beginPath();
    g.moveTo(R + Math.cos(a) * (R - 16), R + Math.sin(a) * (R - 16));
    g.lineTo(R + Math.cos(a) * (R - 5), R + Math.sin(a) * (R - 5));
    g.stroke();
  }
  g.fillStyle = '#0b0d10';
  g.beginPath(); g.arc(R, R, R - 22, 0, Math.PI * 2); g.fill();
  save(OUT.wheels, 'tire', tire);

  // هفت رینگ مختلف
  const rims = [
    (g) => { g.beginPath(); g.arc(R, R, R - 30, 0, Math.PI * 2); g.fillStyle = '#c9ced6'; g.fill(); g.fillStyle = '#8f959e'; g.beginPath(); g.arc(R, R, R - 44, 0, Math.PI * 2); g.fill(); g.fillStyle = '#e6eaf0'; g.beginPath(); g.arc(R, R, 10, 0, Math.PI * 2); g.fill(); },
    (g) => { g.beginPath(); g.arc(R, R, R - 30, 0, Math.PI * 2); g.fillStyle = '#dfe4ea'; g.fill(); g.fillStyle = '#9aa1aa'; for (let i = 0; i < 6; i++) { const a = i / 6 * Math.PI * 2; poly(g, [[R + Math.cos(a) * 16, R + Math.sin(a) * 16], [R + Math.cos(a + 0.45) * (R - 34), R + Math.sin(a + 0.45) * (R - 34)], [R + Math.cos(a + 1.0) * (R - 34), R + Math.sin(a + 1.0) * (R - 34)]], null); g.fill(); } g.fillStyle = '#f2f5f9'; g.beginPath(); g.arc(R, R, 12, 0, Math.PI * 2); g.fill(); },
    (g) => { g.beginPath(); g.arc(R, R, R - 30, 0, Math.PI * 2); g.fillStyle = '#b9bfc8'; g.fill(); g.fillStyle = '#e8ecf1'; for (let i = 0; i < 10; i++) { const a = i / 10 * Math.PI * 2; g.save(); g.translate(R, R); g.rotate(a); rr(g, 12, -7, R - 44, 14, 6); g.fill(); g.restore(); } g.fillStyle = '#7d838c'; g.beginPath(); g.arc(R, R, 11, 0, Math.PI * 2); g.fill(); },
    (g) => { g.beginPath(); g.arc(R, R, R - 30, 0, Math.PI * 2); g.fillStyle = '#3a3f46'; g.fill(); g.strokeStyle = '#c9ced6'; g.lineWidth = 8; g.beginPath(); g.arc(R, R, R - 40, 0, Math.PI * 2); g.stroke(); g.fillStyle = '#c9ced6'; g.beginPath(); g.arc(R, R, 13, 0, Math.PI * 2); g.fill(); },
    (g) => { g.beginPath(); g.arc(R, R, R - 30, 0, Math.PI * 2); g.fillStyle = '#e6eaf0'; g.fill(); g.fillStyle = '#b0b6bf'; for (let i = 0; i < 5; i++) { const a = i / 5 * Math.PI * 2; g.beginPath(); g.arc(R + Math.cos(a) * 26, R + Math.sin(a) * 26, 13, 0, Math.PI * 2); g.fill(); } g.fillStyle = '#7d838c'; g.beginPath(); g.arc(R, R, 12, 0, Math.PI * 2); g.fill(); },
    (g) => { g.beginPath(); g.arc(R, R, R - 30, 0, Math.PI * 2); g.fillStyle = '#f4f1e6'; g.fill(); g.fillStyle = '#cfc9b4'; g.beginPath(); g.arc(R, R, R - 40, 0, Math.PI * 2); g.fill(); g.strokeStyle = '#ffffff'; g.lineWidth = 6; for (let i = 0; i < 4; i++) { g.beginPath(); g.arc(R, R, 20 + i * 8, 0, Math.PI * 2); g.stroke(); } },
    (g) => { g.beginPath(); g.arc(R, R, R - 30, 0, Math.PI * 2); g.fillStyle = '#ffcf4a'; g.fill(); g.fillStyle = '#e09a1e'; for (let i = 0; i < 8; i++) { const a = i / 8 * Math.PI * 2; poly(g, [[R, R], [R + Math.cos(a) * 10, R + Math.sin(a) * 10], [R + Math.cos(a + 0.4) * (R - 34), R + Math.sin(a + 0.4) * (R - 34)]], null); g.fill(); } g.fillStyle = '#fff3c4'; g.beginPath(); g.arc(R, R, 12, 0, Math.PI * 2); g.fill(); },
  ];
  rims.forEach((fn, i) => {
    const cv = cvOf(R * 2, R * 2);
    const g = cv.getContext('2d');
    g.save(); fn(g); g.restore();
    save(OUT.wheels, 'rim_' + (i + 1), cv);
  });
}

/* ------------------------------- زمین و تکسچر --------------------------- */
function noiseTile(name, { size = 192, base, dark, light, dots = 120, seed = 7 }) {
  const cv = cvOf(size, size);
  const g = cv.getContext('2d');
  g.fillStyle = base; g.fillRect(0, 0, size, size);
  let s = seed >>> 0 || 1;
  const rnd = () => { s ^= s << 13; s >>>= 0; s ^= s >> 17; s ^= s << 5; s >>>= 0; return s / 4294967296; };
  for (let i = 0; i < dots * 4; i++) {
    const x = rnd() * size, y = rnd() * size, r = 2 + rnd() * 9;
    g.fillStyle = rnd() > 0.5 ? dark : light;
    g.globalAlpha = 0.10 + rnd() * 0.25;
    g.beginPath(); g.ellipse(x, y, r, r * (0.5 + rnd() * 0.6), rnd() * 3, 0, Math.PI * 2); g.fill();
  }
  g.globalAlpha = 1;
  for (let i = 0; i < dots; i++) {
    const x = rnd() * size, y = rnd() * size;
    g.fillStyle = rnd() > 0.5 ? dark : light;
    g.globalAlpha = 0.25 + rnd() * 0.4;
    g.fillRect(x, y, 1 + rnd() * 3, 1 + rnd() * 3);
  }
  g.globalAlpha = 1;
  save(OUT.terrain, name, cv);
}
console.log('🏔 زمین و جاده...');
noiseTile('dirt', { base: '#6b4a2f', dark: '#4d3320', light: '#8a6644', seed: 11 });
noiseTile('grass', { base: '#4f8f3f', dark: '#356b2c', light: '#7ab556', seed: 23, dots: 160 });
noiseTile('sand', { base: '#d9b271', dark: '#b8874a', light: '#f0d7a4', seed: 31 });
noiseTile('snow', { base: '#e8f0f8', dark: '#b9cbdd', light: '#ffffff', seed: 47 });
noiseTile('rock', { base: '#8d8477', dark: '#6a6156', light: '#b0a89a', seed: 59 });
// آسفالت با خط‌کشی
{
  const size = 192;
  const cv = cvOf(size, size);
  const g = cv.getContext('2d');
  g.fillStyle = '#3a3d42'; g.fillRect(0, 0, size, size);
  for (let i = 0; i < 900; i++) {
    g.fillStyle = `rgba(${Math.random() > 0.5 ? '255,255,255' : '0,0,0'},${0.03 + Math.random() * 0.05})`;
    g.fillRect(Math.random() * size, Math.random() * size, 2, 2);
  }
  g.fillStyle = 'rgba(255,255,255,.75)';
  for (let x = 0; x < size; x += 48) g.fillRect(x, 8, 26, 5);
  save(OUT.terrain, 'asphalt', cv);
}

/* ------------------------------ پس‌زمینه‌ها ------------------------------ */
function skyGradient(name, top, mid, bottom, w = 720, h = 480) {
  const cv = cvOf(w, h);
  const g = cv.getContext('2d');
  const grad = g.createLinearGradient(0, 0, 0, h);
  grad.addColorStop(0, top); grad.addColorStop(0.55, mid); grad.addColorStop(1, bottom);
  g.fillStyle = grad; g.fillRect(0, 0, w, h);
  save(OUT.bg, name, cv);
}
function mountainLayer(name, { w = 1024, h = 300, color, color2, seed = 5, peeks = 9, jag = 0.6 }) {
  const cv = cvOf(w, h);
  const g = cv.getContext('2d');
  let s = seed;
  const rnd = () => { s = (s * 16807) % 2147483647; return s / 2147483647; };
  const draw = (col, baseY, amp) => {
    g.fillStyle = col;
    g.beginPath();
    g.moveTo(0, h);
    let x = 0;
    g.lineTo(0, baseY);
    while (x < w) {
      const step = 60 + rnd() * 90;
      const peak = baseY - amp * (0.4 + rnd() * jag);
      g.quadraticCurveTo(x + step * 0.5, peak, x + step, baseY + (rnd() - 0.5) * 22);
      x += step;
    }
    g.lineTo(w, h);
    g.closePath();
    g.fill();
  };
  draw(color2, h * 0.62, h * 0.5);
  draw(color, h * 0.78, h * 0.4);
  save(OUT.bg, name, cv);
}
function skylineLayer(name, { w = 1024, h = 320, color = '#2a3140', windows = '#ffd76a', seed = 13 }) {
  const cv = cvOf(w, h);
  const g = cv.getContext('2d');
  let s = seed;
  const rnd = () => { s = (s * 48271) % 2147483647; return s / 2147483647; };
  g.fillStyle = color;
  let x = 0;
  while (x < w) {
    const bw = 50 + rnd() * 90;
    const bh = 80 + rnd() * 170;
    g.fillRect(x, h - bh, bw, bh);
    // بالکن و پنجره
    for (let wy = h - bh + 14; wy < h - 14; wy += 24) {
      for (let wx = x + 8; wx < x + bw - 12; wx += 20) {
        if (rnd() < 0.55) { g.fillStyle = windows; g.globalAlpha = 0.5 + rnd() * 0.5; g.fillRect(wx, wy, 9, 12); g.fillStyle = color; g.globalAlpha = 1; }
      }
    }
    x += bw + (rnd() < 0.3 ? 24 : 6);
  }
  save(OUT.bg, name, cv);
}
function forestLayer(name, { w = 1024, h = 300, colors = ['#1f4d2b', '#2c6b36', '#3d8a44'], seed = 3 }) {
  const cv = cvOf(w, h);
  const g = cv.getContext('2d');
  let s = seed;
  const rnd = () => { s = (s * 16807) % 2147483647; return s / 2147483647; };
  colors.forEach((col, i) => {
    g.fillStyle = col;
    const baseY = h * (0.55 + i * 0.18);
    g.beginPath(); g.moveTo(0, h);
    let x = -20;
    while (x < w + 40) {
      const r = 34 + rnd() * 54;
      g.moveTo(x, h); g.arc(x + r, baseY, r, Math.PI, 0);
      x += r * 1.3;
    }
    g.fill();
    g.fillRect(0, baseY, w, h - baseY);
  });
  save(OUT.bg, name, cv);
}
console.log('🌅 پس‌زمینه‌ها...');
skyGradient('sky_day', '#6ec6ff', '#a8dcff', '#e8f6ff');
skyGradient('sky_dusk', '#f7a05a', '#f7c98b', '#ffe6c0');
skyGradient('sky_night', '#101a38', '#1e2b56', '#38507f');
skyGradient('sky_desert', '#8fd4ff', '#dfe9c0', '#f2e2b0');
skyGradient('sky_rain', '#5c6b7d', '#8a99ab', '#c2ccd8');
mountainLayer('mtn_day', { color: '#7f89a0', color2: '#9aa4b8', seed: 5 });
mountainLayer('mtn_dusk', { color: '#7a5560', color2: '#a06a66', seed: 9 });
mountainLayer('mtn_snow', { color: '#cfd9e6', color2: '#eef4fb', seed: 17 });
mountainLayer('mtn_desert', { color: '#c9a06a', color2: '#e0bd8c', seed: 21 });
skylineLayer('city_day', { color: '#3c4557', windows: '#cfe6ff' });
skylineLayer('city_dusk', { color: '#33304a', windows: '#ffd76a' });
skylineLayer('city_night', { color: '#1b2136', windows: '#ffdf8a' });
forestLayer('forest_green', { colors: ['#245531', '#2f6b3a', '#3f8b48'] });
forestLayer('forest_palm', { colors: ['#2c5a3a', '#3a7047', '#4b8a55'], seed: 8 });
forestLayer('forest_autumn', { colors: ['#6b4a25', '#8a5c2b', '#a9713a'], seed: 12 });

/* ------------------------------- تزئین‌ها -------------------------------- */
function deco(name, w, h, fn) {
  const cv = cvOf(w, h);
  const g = cv.getContext('2d');
  fn(g, w, h);
  save(OUT.deco, name, cv);
}
console.log('🌳 تزئین‌های محیط...');
deco('tree', 160, 220, (g, w, h) => {
  g.fillStyle = '#6b4a2a'; rr(g, w / 2 - 11, h - 96, 22, 96, 5); g.fill();
  const layers = [['#2e6b34', 0], ['#3d8a42', 26], ['#54a84f', 50]];
  for (const [col, off] of layers) {
    g.fillStyle = col;
    g.beginPath(); g.arc(w / 2 - 30, h - 100 - off * 0.6, 42 - off * 0.25, 0, Math.PI * 2); g.fill();
    g.beginPath(); g.arc(w / 2 + 30, h - 108 - off * 0.6, 46 - off * 0.25, 0, Math.PI * 2); g.fill();
    g.beginPath(); g.arc(w / 2, h - 140 - off, 52 - off * 0.3, 0, Math.PI * 2); g.fill();
  }
});
deco('pine', 130, 240, (g, w, h) => {
  g.fillStyle = '#5b3f22'; g.fillRect(w / 2 - 9, h - 60, 18, 60);
  for (let i = 0; i < 4; i++) {
    g.fillStyle = ['#1f5a2c', '#266b33', '#2f7d3c', '#3a9046'][i];
    const y = h - 60 - i * 44, sp = 34 + i * 16;
    poly(g, [[w / 2, y - 76], [w / 2 + sp, y], [w / 2 - sp, y]], null); g.fill();
  }
});
deco('palm', 170, 230, (g, w, h) => {
  g.strokeStyle = '#8a6a3a'; g.lineWidth = 16;
  g.beginPath(); g.moveTo(w / 2, h); g.quadraticCurveTo(w / 2 + 16, h - 90, w / 2 - 6, h - 150); g.stroke();
  g.fillStyle = '#2f7d3c';
  for (let i = 0; i < 7; i++) {
    const a = -Math.PI * 0.95 + i * (Math.PI * 0.95 / 6);
    g.save(); g.translate(w / 2 - 6, h - 150); g.rotate(a);
    g.beginPath(); g.moveTo(0, 0); g.quadraticCurveTo(52, -22, 86, 6); g.quadraticCurveTo(50, 8, 0, 12); g.fill();
    g.restore();
  }
  g.fillStyle = '#c98b2a'; g.beginPath(); g.arc(w / 2 - 6, h - 146, 9, 0, Math.PI * 2); g.fill();
});
deco('bush', 120, 90, (g, w, h) => {
  for (const [col, dx, r] of [['#255c2c', -26, 30], ['#317337', 24, 34], ['#3f8c44', 0, 38]]) {
    g.fillStyle = col; g.beginPath(); g.arc(w / 2 + dx, h - r * 0.7, r, 0, Math.PI * 2); g.fill();
  }
});
deco('rock', 130, 100, (g, w, h) => {
  poly(g, [[10, h], [30, h - 52], [62, h - 70], [96, h - 44], [118, h]], '#7d766a');
  poly(g, [[30, h - 52], [62, h - 70], [72, h - 40], [40, h - 30]], '#968e80');
});
deco('cactus', 110, 200, (g, w, h) => {
  g.fillStyle = '#3f8b52';
  rr(g, w / 2 - 16, h - 150, 32, 150, 16); g.fill();
  rr(g, w / 2 - 44, h - 120, 22, 60, 11); g.fill();
  rr(g, w / 2 - 44, h - 82, 46, 20, 10); g.fill();
  rr(g, w / 2 + 22, h - 140, 22, 62, 11); g.fill();
  rr(g, w / 2 + 4, h - 98, 40, 18, 9); g.fill();
});
deco('streetlight', 120, 300, (g, w, h) => {
  g.fillStyle = '#4a515c'; g.fillRect(w / 2 - 8, 40, 16, h - 40);
  g.beginPath(); g.moveTo(w / 2, 44); g.quadraticCurveTo(w / 2 + 40, 30, w / 2 + 46, 60); g.lineTo(w / 2 + 34, 60); g.quadraticCurveTo(w / 2 + 30, 44, w / 2, 58); g.fill();
  g.fillStyle = '#ffdf8a'; rr(g, w / 2 + 26, 58, 34, 14, 6); g.fill();
  g.fillStyle = '#3c434d'; rr(g, w / 2 - 22, h - 12, 44, 12, 4); g.fill();
});
deco('house_city', 260, 300, (g, w, h) => {
  g.fillStyle = '#d9c9a8'; g.fillRect(10, 60, w - 20, h - 60);
  g.fillStyle = '#c2b092'; g.fillRect(10, 60, w - 20, 16);
  for (let y = 92; y < h - 60; y += 62) {
    for (let x = 26; x < w - 46; x += 62) {
      g.fillStyle = '#8fb8d8'; g.fillRect(x, y, 40, 40);
      g.strokeStyle = '#f2f4f7'; g.lineWidth = 3; g.strokeRect(x, y, 40, 40);
      g.fillStyle = 'rgba(255,255,255,.35)'; g.fillRect(x + 4, y + 4, 12, 32);
    }
  }
  g.fillStyle = '#9c8b6e'; g.fillRect(w / 2 - 26, h - 90, 52, 90);
});
deco('house_village', 280, 240, (g, w, h) => {
  g.fillStyle = '#f2e3c8'; g.fillRect(20, 100, w - 40, h - 100);
  g.fillStyle = '#b8563c'; poly(g, [[10, 104], [w / 2, 24], [w - 10, 104]], null); g.fill();
  g.fillStyle = '#7a5a3a'; g.fillRect(w / 2 - 28, h - 74, 56, 74);
  g.fillStyle = '#8fb8d8'; g.fillRect(46, 128, 46, 42); g.fillRect(w - 92, 128, 46, 42);
  g.strokeStyle = '#f6f1e2'; g.lineWidth = 4; g.strokeRect(46, 128, 46, 42); g.strokeRect(w - 92, 128, 46, 42);
});
deco('sign_wood', 240, 150, (g, w, h) => {
  g.fillStyle = '#6b4a28'; rr(g, 20, 40, w - 40, 70, 12); g.fill();
  g.fillStyle = '#8a6234'; rr(g, 26, 46, w - 52, 58, 10); g.fill();
  g.strokeStyle = '#5a3c20'; g.lineWidth = 3;
  for (let i = 0; i < 4; i++) { g.beginPath(); g.moveTo(34, 56 + i * 14); g.lineTo(w - 34, 58 + i * 14); g.stroke(); }
  g.fillStyle = '#7a5630'; g.fillRect(w / 2 - 10, 106, 20, 40);
});
deco('rice_field', 300, 90, (g, w, h) => {
  g.fillStyle = 'rgba(140,190,90,.9)';
  for (let i = 0; i < 40; i++) {
    const x = 6 + i * 7.4;
    g.save(); g.translate(x, h); g.rotate(-0.2 + (i % 5) * 0.1);
    rr(g, -3, -34 - (i % 4) * 8, 6, 34 + (i % 4) * 8, 3); g.fill();
    g.restore();
  }
});
deco('tent', 200, 140, (g, w, h) => {
  poly(g, [[10, h], [w / 2, 16], [w - 10, h]], '#c9a15a');
  poly(g, [[w / 2, 16], [w - 10, h], [w / 2, h]], '#a9853f');
  g.fillStyle = '#4a3a24'; poly(g, [[w / 2 - 26, h], [w / 2, 54], [w / 2 + 26, h]], null); g.fill();
});
deco('barrier', 120, 70, (g, w, h) => {
  for (let i = 0; i < 6; i++) { g.fillStyle = i % 2 ? '#f6f8fa' : '#e84a3a'; g.fillRect(i * 20, 10, 20, 34); }
  g.fillStyle = '#7d838c'; g.fillRect(16, 44, 12, 26); g.fillRect(w - 28, 44, 12, 26);
});

/* ------------------------------ آیکون و رابط ----------------------------- */
function icon(name, size, fn) {
  const cv = cvOf(size, size);
  const g = cv.getContext('2d');
  fn(g, size);
  save(OUT.ui, name, cv);
}
console.log('🎛 آیکون‌های رابط...');
icon('icon_coin', 96, (g, s) => {
  g.fillStyle = '#e09a1e'; g.beginPath(); g.ellipse(s / 2, s / 2 + 3, 34, 34, 0, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#ffcf4a'; g.beginPath(); g.arc(s / 2, s / 2 - 2, 33, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#ffe9a8'; g.beginPath(); g.arc(s / 2 - 8, s / 2 - 12, 12, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#c98b2a'; g.font = 'bold 40px sans-serif'; g.textAlign = 'center'; g.textBaseline = 'middle';
  g.fillText('$', s / 2, s / 2 + 3);
});
icon('icon_gem', 96, (g, s) => {
  poly(g, [[s / 2, 8], [s - 10, 34], [s / 2, s - 8], [10, 34]], '#e8467c');
  poly(g, [[s / 2, 8], [s - 10, 34], [s / 2, 40]], '#ff7aa8');
  poly(g, [[s / 2, 8], [s / 2, 40], [10, 34]], '#c22a5c');
  stroke(g, 3, 'rgba(255,255,255,.6)');
});
icon('icon_key', 96, (g, s) => {
  g.fillStyle = '#ffcf4a';
  g.beginPath(); g.arc(30, 30, 18, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#e09a1e'; g.beginPath(); g.arc(30, 30, 8, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#ffcf4a'; rr(g, 34, 40, 12, 44, 4); g.fill();
  rr(g, 46, 66, 16, 9, 3); g.fill(); rr(g, 46, 78, 12, 8, 3); g.fill();
});
icon('icon_fuel', 96, (g, s) => {
  g.fillStyle = '#e84a3a'; rr(g, 22, 18, 40, 62, 8); g.fill();
  g.fillStyle = '#c22f22'; rr(g, 22, 18, 40, 14, 6); g.fill();
  g.fillStyle = '#f6f8fa'; rr(g, 30, 36, 24, 20, 4); g.fill();
  g.strokeStyle = '#c22f22'; g.lineWidth = 6;
  g.beginPath(); g.moveTo(62, 40); g.quadraticCurveTo(76, 46, 74, 66); g.stroke();
});
icon('icon_nitro', 96, (g, s) => {
  g.fillStyle = '#4a7ac0'; rr(g, 28, 16, 40, 66, 12); g.fill();
  g.fillStyle = '#6fa8ff'; rr(g, 34, 22, 28, 54, 10); g.fill();
  g.fillStyle = '#2f4f80'; g.fillRect(38, 40, 20, 6);
  g.fillStyle = '#c9ced6'; rr(g, 40, 8, 16, 12, 4); g.fill();
  g.fillStyle = '#5ff5c8'; g.font = 'bold 24px sans-serif'; g.textAlign = 'center'; g.fillText('N²', s / 2, 62);
});
icon('icon_star', 96, (g, s) => {
  g.fillStyle = '#ffcf4a';
  g.beginPath();
  for (let i = 0; i < 10; i++) {
    const a = -Math.PI / 2 + i * Math.PI / 5, r = i % 2 ? 18 : 42;
    const x = s / 2 + Math.cos(a) * r, y = s / 2 + Math.sin(a) * r;
    i ? g.lineTo(x, y) : g.moveTo(x, y);
  }
  g.closePath(); g.fill();
  stroke(g, 4, '#e09a1e');
});
icon('icon_trophy', 96, (g, s) => {
  g.fillStyle = '#ffcf4a';
  rr(g, 26, 16, 44, 34, 8); g.fill();
  g.beginPath(); g.moveTo(26, 22); g.quadraticCurveTo(6, 26, 22, 46); g.lineTo(30, 44); g.quadraticCurveTo(16, 32, 26, 30); g.fill();
  g.beginPath(); g.moveTo(70, 22); g.quadraticCurveTo(90, 26, 74, 46); g.lineTo(66, 44); g.quadraticCurveTo(80, 32, 70, 30); g.fill();
  g.fillStyle = '#e09a1e'; rr(g, 42, 48, 12, 18, 3); g.fill();
  rr(g, 30, 64, 36, 12, 4); g.fill();
});
icon('icon_wrench', 96, (g, s) => {
  g.save(); g.translate(s / 2, s / 2); g.rotate(-0.6);
  g.fillStyle = '#c9ced6'; rr(g, -8, -10, 16, 52, 6); g.fill();
  g.beginPath(); g.arc(0, -14, 18, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#8f959e'; g.beginPath(); g.arc(0, -14, 8, 0, Math.PI * 2); g.fill();
  g.restore();
  g.fillStyle = '#8f959e'; g.beginPath(); g.arc(s / 2 - 1, s / 2 - 15, 9, 0, Math.PI * 2); g.fill();
});
icon('icon_gear', 96, (g, s) => {
  g.fillStyle = '#c9ced6';
  for (let i = 0; i < 8; i++) {
    g.save(); g.translate(s / 2, s / 2); g.rotate(i * Math.PI / 4);
    rr(g, -8, -42, 16, 20, 4); g.fill(); g.restore();
  }
  g.beginPath(); g.arc(s / 2, s / 2, 28, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#5b626c'; g.beginPath(); g.arc(s / 2, s / 2, 12, 0, Math.PI * 2); g.fill();
});
icon('icon_wheel', 96, (g, s) => {
  g.fillStyle = '#1c1f24'; g.beginPath(); g.arc(s / 2, s / 2, 40, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#2b3038'; g.beginPath(); g.arc(s / 2, s / 2, 33, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#d7dde6'; g.beginPath(); g.arc(s / 2, s / 2, 26, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#8f959e';
  for (let i = 0; i < 5; i++) {
    const a = i / 5 * Math.PI * 2;
    g.beginPath(); g.arc(s / 2 + Math.cos(a) * 15, s / 2 + Math.sin(a) * 15, 8, 0, Math.PI * 2); g.fill();
  }
  g.fillStyle = '#5b626c'; g.beginPath(); g.arc(s / 2, s / 2, 8, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#ffcf4a'; g.beginPath(); g.arc(s / 2, s / 2, 4, 0, Math.PI * 2); g.fill();
});
icon('icon_mission', 96, (g, s) => {
  g.fillStyle = '#f6f8fa'; rr(g, 22, 12, 52, 72, 8); g.fill();
  g.fillStyle = '#c9ced6'; rr(g, 34, 6, 28, 12, 5); g.fill();
  g.strokeStyle = '#5b626c'; g.lineWidth = 5;
  for (let i = 0; i < 4; i++) { g.beginPath(); g.moveTo(32, 34 + i * 14); g.lineTo(64, 34 + i * 14); g.stroke(); }
  g.fillStyle = '#38c06a'; g.beginPath(); g.arc(30, 76, 12, 0, Math.PI * 2); g.fill();
  g.strokeStyle = '#fff'; g.lineWidth = 4; g.beginPath(); g.moveTo(24, 76); g.lineTo(29, 81); g.lineTo(37, 70); g.stroke();
});
icon('icon_lock', 96, (g, s) => {
  g.strokeStyle = '#c9ced6'; g.lineWidth = 10;
  g.beginPath(); g.arc(s / 2, 40, 18, Math.PI, 0); g.stroke();
  g.fillStyle = '#ffcf4a'; rr(g, 22, 40, 52, 44, 8); g.fill();
  g.fillStyle = '#e09a1e'; g.beginPath(); g.arc(s / 2, 60, 7, 0, Math.PI * 2); g.fill();
  rr(g, s / 2 - 3, 60, 6, 14, 3); g.fill();
});
icon('icon_stopwatch', 96, (g, s) => {
  g.fillStyle = '#f6f8fa'; g.beginPath(); g.arc(s / 2, 54, 30, 0, Math.PI * 2); g.fill();
  stroke(g, 6, '#5b626c');
  g.fillStyle = '#e84a3a'; rr(g, s / 2 - 10, 14, 20, 10, 4); g.fill();
  g.strokeStyle = '#e84a3a'; g.lineWidth = 5;
  g.beginPath(); g.moveTo(s / 2, 54); g.lineTo(s / 2 + 2, 34); g.stroke();
});
icon('icon_like', 96, (g, s) => {
  g.fillStyle = '#ff5a7a';
  g.beginPath();
  g.moveTo(s / 2, s - 14);
  g.bezierCurveTo(6, 58, 10, 22, 32, 22);
  g.bezierCurveTo(44, 22, s / 2, 34, s / 2, 34);
  g.bezierCurveTo(s / 2, 34, 52, 22, 64, 22);
  g.bezierCurveTo(86, 22, 90, 58, s / 2, s - 14);
  g.closePath(); g.fill();
  g.fillStyle = 'rgba(255,255,255,.45)';
  g.beginPath(); g.ellipse(34, 38, 8, 6, -0.5, 0, Math.PI * 2); g.fill();
});
icon('icon_flag', 96, (g, s) => {
  g.fillStyle = '#3c434d'; g.fillRect(28, 12, 8, 74);
  g.fillStyle = '#38c06a'; rr(g, 36, 16, 42, 18, 3); g.fill();
  g.fillStyle = '#f6f8fa'; rr(g, 36, 34, 42, 16, 3); g.fill();
  g.fillStyle = '#e84a3a'; rr(g, 36, 50, 42, 16, 3); g.fill();
});

/* --- آیکون‌های تکمیلی رابط (نوار بازی، منو، فروشگاه) --- */
icon('icon_pause', 96, (g, s) => {
  g.fillStyle = '#1c2a44'; g.beginPath(); g.arc(s / 2, s / 2, 38, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#f6f8fa'; rr(g, 32, 24, 12, 48, 5); g.fill(); rr(g, 52, 24, 12, 48, 5); g.fill();
});
icon('icon_play', 96, (g, s) => {
  g.fillStyle = '#38c06a'; g.beginPath(); g.arc(s / 2, s / 2, 38, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#f6f8fa'; poly(g, [[38, 26], [38, 70], [70, 48]], null); g.fill();
});
icon('icon_home', 96, (g, s) => {
  g.fillStyle = '#f6f8fa'; poly(g, [[s / 2, 12], [84, 46], [64, 46], [64, 84], [32, 84], [32, 46], [12, 46]], null); g.fill();
  g.fillStyle = '#8f5b32'; rr(g, 42, 56, 14, 28, 4); g.fill();
});
icon('icon_retry', 96, (g, s) => {
  g.strokeStyle = '#4a9cff'; g.lineWidth = 10; g.beginPath(); g.arc(s / 2, s / 2, 28, -1.1, 2.2); g.stroke();
  g.fillStyle = '#4a9cff'; poly(g, [[70, 14], [82, 40], [54, 34]], null); g.fill();
});
icon('icon_next', 96, (g, s) => {
  g.strokeStyle = '#f6f8fa'; g.lineWidth = 12; g.lineCap = 'round';
  g.beginPath(); g.moveTo(58, 22); g.lineTo(32, 48); g.lineTo(58, 74); g.stroke();
  g.strokeStyle = 'rgba(246,248,250,.5)';
  g.beginPath(); g.moveTo(78, 22); g.lineTo(52, 48); g.lineTo(78, 74); g.stroke();
});
icon('icon_arrow_left', 96, (g, s) => {
  g.strokeStyle = '#f6f8fa'; g.lineWidth = 12; g.lineCap = 'round';
  g.beginPath(); g.moveTo(62, 20); g.lineTo(34, 48); g.lineTo(62, 76); g.stroke();
});
icon('icon_close', 96, (g, s) => {
  g.strokeStyle = '#ff6b5a'; g.lineWidth = 13; g.lineCap = 'round';
  g.beginPath(); g.moveTo(28, 28); g.lineTo(68, 68); g.moveTo(68, 28); g.lineTo(28, 68); g.stroke();
});
icon('icon_check', 96, (g, s) => {
  g.strokeStyle = '#38c06a'; g.lineWidth = 14; g.lineCap = 'round';
  g.beginPath(); g.moveTo(20, 52); g.lineTo(40, 72); g.lineTo(78, 26); g.stroke();
});
icon('icon_spin', 96, (g, s) => {
  const cols = ['#ffcf4a', '#38c06a', '#4a9cff', '#e8467c', '#f08a1e', '#7a5bd6'];
  for (let i = 0; i < 6; i++) {
    g.fillStyle = cols[i];
    g.beginPath(); g.moveTo(s / 2, s / 2);
    g.arc(s / 2, s / 2, 36, i * Math.PI / 3, (i + 1) * Math.PI / 3); g.closePath(); g.fill();
  }
  g.fillStyle = '#f6f8fa'; g.beginPath(); g.arc(s / 2, s / 2, 12, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#1c2a44'; poly(g, [[s / 2 - 8, 6], [s / 2 + 8, 6], [s / 2, 22]], null); g.fill();
});
icon('icon_gift', 96, (g, s) => {
  g.fillStyle = '#e8467c'; rr(g, 14, 38, 68, 46, 8); g.fill();
  g.fillStyle = '#c22a5c'; rr(g, 14, 38, 68, 12, 4); g.fill();
  g.fillStyle = '#ffcf4a'; g.fillRect(s / 2 - 6, 38, 12, 46);
  g.beginPath(); g.arc(s / 2 - 14, 30, 10, 0, Math.PI * 2); g.fill();
  g.beginPath(); g.arc(s / 2 + 14, 30, 10, 0, Math.PI * 2); g.fill();
});
icon('icon_settings', 96, (g, s) => {
  g.strokeStyle = '#f6f8fa'; g.lineWidth = 8; g.lineCap = 'round';
  const ys = [26, 48, 70];
  ys.forEach((y, i) => {
    g.beginPath(); g.moveTo(18, y); g.lineTo(78, y); g.stroke();
    g.fillStyle = '#4a9cff'; const x = [34, 62, 42][i];
    g.beginPath(); g.arc(x, y, 11, 0, Math.PI * 2); g.fill();
  });
});
icon('icon_sound', 96, (g, s) => {
  g.fillStyle = '#ffcf4a'; poly(g, [[14, 38], [30, 38], [50, 20], [50, 76], [30, 58], [14, 58]], null); g.fill();
  g.strokeStyle = '#f6f8fa'; g.lineWidth = 7; g.lineCap = 'round';
  g.beginPath(); g.arc(50, 48, 18, -0.9, 0.9); g.stroke();
  g.beginPath(); g.arc(50, 48, 30, -0.9, 0.9); g.stroke();
});
icon('icon_music', 96, (g, s) => {
  g.fillStyle = '#5ff5c8';
  g.fillRect(56, 16, 9, 52);
  g.beginPath(); g.ellipse(48, 70, 18, 13, -0.3, 0, Math.PI * 2); g.fill();
  g.fillRect(28, 26, 9, 46);
  g.beginPath(); g.ellipse(22, 74, 15, 11, -0.3, 0, Math.PI * 2); g.fill();
  poly(g, [[28, 26], [65, 16], [65, 30], [28, 40]], null); g.fill();
});
icon('icon_shake', 96, (g, s) => {
  g.fillStyle = '#1c2a44'; rr(g, 30, 10, 36, 76, 8); g.fill();
  g.fillStyle = '#4a9cff'; rr(g, 34, 20, 28, 52, 4); g.fill();
  g.strokeStyle = '#ffcf4a'; g.lineWidth = 6; g.lineCap = 'round';
  g.beginPath(); g.moveTo(14, 34); g.lineTo(24, 40); g.moveTo(14, 60); g.lineTo(24, 56); g.stroke();
  g.beginPath(); g.moveTo(82, 34); g.lineTo(72, 40); g.moveTo(82, 60); g.lineTo(72, 56); g.stroke();
});
icon('icon_touch', 96, (g, s) => {
  g.fillStyle = '#f6d3a8'; g.beginPath(); g.arc(46, 22, 12, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#f6d3a8'; rr(g, 34, 30, 24, 50, 10); g.fill();
  g.strokeStyle = '#4a9cff'; g.lineWidth = 5;
  for (let i = 0; i < 3; i++) { g.beginPath(); g.arc(46, 56, 20 + i * 12, -2.6, -0.5); g.stroke(); }
  g.fillStyle = 'rgba(74,156,255,.35)'; g.beginPath(); g.arc(46, 56, 34, 0, Math.PI * 2); g.fill();
});
icon('icon_car_head', 96, (g, s) => {
  g.fillStyle = '#e8402a'; rr(g, 16, 34, 64, 34, 14); g.fill();
  g.fillStyle = '#9fd8ff'; rr(g, 24, 40, 22, 16, 6); g.fill(); rr(g, 50, 40, 22, 16, 6); g.fill();
  g.fillStyle = '#ffe9a8'; g.beginPath(); g.arc(22, 62, 7, 0, Math.PI * 2); g.fill();
  g.beginPath(); g.arc(74, 62, 7, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#1c1f24'; rr(g, 14, 62, 12, 14, 4); g.fill(); rr(g, 70, 62, 12, 14, 4); g.fill();
});
icon('icon_vip', 96, (g, s) => {
  g.fillStyle = '#ffcf4a'; poly(g, [[12, 66], [22, 22], [38, 48], [48, 16], [58, 48], [74, 22], [84, 66]], null); g.fill();
  g.fillStyle = '#e09a1e'; rr(g, 12, 66, 72, 14, 5); g.fill();
  g.fillStyle = '#ff6b5a'; g.beginPath(); g.arc(48, 30, 5, 0, Math.PI * 2); g.fill();
  g.beginPath(); g.arc(22, 34, 4, 0, Math.PI * 2); g.fill();
  g.beginPath(); g.arc(74, 34, 4, 0, Math.PI * 2); g.fill();
});
icon('icon_map', 96, (g, s) => {
  g.fillStyle = '#f0e2c0'; poly(g, [[10, 24], [36, 14], [62, 26], [88, 14], [88, 72], [62, 84], [36, 72], [10, 84]], null); g.fill();
  g.strokeStyle = '#c9b48a'; g.lineWidth = 3;
  g.beginPath(); g.moveTo(36, 14); g.lineTo(36, 72); g.moveTo(62, 26); g.lineTo(62, 84); g.stroke();
  g.fillStyle = '#e8402a'; g.beginPath(); g.arc(50, 42, 12, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#f6f8fa'; g.beginPath(); g.arc(50, 42, 5, 0, Math.PI * 2); g.fill();
});
icon('icon_paint', 96, (g, s) => {
  g.fillStyle = '#c9ced6'; rr(g, 22, 44, 40, 40, 8); g.fill();
  g.strokeStyle = '#8f959e'; g.lineWidth = 6; g.beginPath(); g.arc(42, 44, 16, Math.PI, 0); g.stroke();
  g.fillStyle = '#e8402a'; g.beginPath(); g.arc(34, 30, 12, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#4a9cff'; g.beginPath(); g.arc(58, 24, 10, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#38c06a'; g.beginPath(); g.arc(66, 40, 8, 0, Math.PI * 2); g.fill();
});
icon('icon_engine', 96, (g, s) => {
  g.fillStyle = '#c9ced6'; rr(g, 16, 30, 56, 38, 8); g.fill();
  g.fillStyle = '#8f959e'; rr(g, 24, 22, 16, 12, 4); g.fill(); rr(g, 50, 22, 16, 12, 4); g.fill();
  g.fillStyle = '#ffcf4a'; g.beginPath(); g.arc(66, 60, 10, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#e84a3a'; rr(g, 66, 34, 16, 10, 4); g.fill();
});

/* دکمه‌ها به‌صورت ۹-برشی */
function button(name, top, bottom, border, glow) {
  const w = 220, h = 96;
  const cv = cvOf(w, h);
  const g = cv.getContext('2d');
  g.fillStyle = border; rr(g, 0, 0, w, h, 26); g.fill();
  const grad = g.createLinearGradient(0, 8, 0, h - 8);
  grad.addColorStop(0, top); grad.addColorStop(0.55, bottom); grad.addColorStop(1, bottom);
  g.fillStyle = grad; rr(g, 6, 6, w - 12, h - 12, 20); g.fill();
  g.fillStyle = 'rgba(255,255,255,.35)'; rr(g, 14, 12, w - 28, 16, 10); g.fill();
  if (glow) { g.fillStyle = glow; rr(g, 14, h - 26, w - 28, 10, 6); g.fill(); }
  save(OUT.ui, name, cv);
}
console.log('🔘 دکمه‌ها...');
button('btn_green', '#7ee36a', '#38b32a', '#1f7a1a', 'rgba(255,255,255,.18)');
button('btn_orange', '#ffc46a', '#f08a1e', '#b3610f', 'rgba(255,255,255,.2)');
button('btn_blue', '#7ec8ff', '#2f8fe0', '#1c5f9c', 'rgba(255,255,255,.2)');
button('btn_red', '#ff9a86', '#e8402a', '#9c2318', 'rgba(255,255,255,.18)');
button('btn_gray', '#c9ced6', '#8f959e', '#5b626c', 'rgba(255,255,255,.15)');
// پنل‌ها
function panel(name, col1, col2, border, radius = 24, w = 320, h = 240) {
  const cv = cvOf(w, h);
  const g = cv.getContext('2d');
  g.fillStyle = border; rr(g, 0, 0, w, h, radius); g.fill();
  const grad = g.createLinearGradient(0, 6, 0, h - 6);
  grad.addColorStop(0, col1); grad.addColorStop(1, col2);
  g.fillStyle = grad; rr(g, 8, 8, w - 16, h - 16, radius - 6); g.fill();
  g.fillStyle = 'rgba(255,255,255,.10)'; rr(g, 16, 14, w - 32, 14, 8); g.fill();
  save(OUT.ui, name, cv);
}
panel('panel_blue', '#3f8fe0', '#1f4f8f', '#123a6b');
panel('panel_dark', '#3a4150', '#1e2431', '#12161f');
panel('panel_card', '#f7f9fc', '#dde4ee', '#9aa4b8', 20, 300, 200);
panel('panel_gold', '#ffdf8a', '#e09a1e', '#a5700f', 24, 300, 200);
// شکاف کارت
{
  const cv = cvOf(180, 240);
  const g = cv.getContext('2d');
  g.fillStyle = 'rgba(0,0,0,.35)'; rr(g, 0, 0, 180, 240, 18); g.fill();
  g.strokeStyle = 'rgba(255,255,255,.3)'; g.lineWidth = 3; rr(g, 6, 6, 168, 228, 14); g.stroke();
  save(OUT.ui, 'slot_card', cv);
}
// لوگو «جاده‌باز» روی تخته‌چوب
{
  const cv = cvOf(900, 340);
  const g = cv.getContext('2d');
  g.save();
  g.translate(450, 170);
  g.fillStyle = 'rgba(0,0,0,.25)'; rr(g, -330, -92, 700, 200, 26); g.fill();
  g.fillStyle = '#6b4a28'; rr(g, -336, -100, 700, 200, 26); g.fill();
  g.fillStyle = '#8a6234'; rr(g, -324, -88, 676, 176, 20); g.fill();
  g.strokeStyle = '#5a3c20'; g.lineWidth = 5;
  for (let i = 0; i < 7; i++) { g.beginPath(); g.moveTo(-310, -70 + i * 24); g.lineTo(340, -66 + i * 24); g.stroke(); }
  // چرخ دنده و لاستیک
  g.fillStyle = '#5b626c';
  for (let i = 0; i < 10; i++) { g.save(); g.rotate(i * Math.PI / 5); rr(g, -12, -140, 24, 26, 6); g.fill(); g.restore(); }
  g.beginPath(); g.arc(0, -112, 44, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#2c3138'; g.beginPath(); g.arc(268, 60, 62, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#c9ced6'; g.beginPath(); g.arc(268, 60, 34, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#5b626c'; g.beginPath(); g.arc(268, 60, 12, 0, Math.PI * 2); g.fill();
  g.restore();
  save(OUT.ui, 'logo_board', cv);
}
// نشان برنامه
{
  const s = 512;
  const cv = cvOf(s, s);
  const g = cv.getContext('2d');
  const grad = g.createLinearGradient(0, 0, 0, s);
  grad.addColorStop(0, '#6ec6ff'); grad.addColorStop(0.6, '#2f8fe0'); grad.addColorStop(1, '#1f4f8f');
  g.fillStyle = grad; rr(g, 0, 0, s, s, 96); g.fill();
  // جاده
  g.fillStyle = '#4a4f57'; poly(g, [[s * 0.2, s], [s * 0.44, s * 0.42], [s * 0.6, s * 0.42], [s * 0.92, s]], null); g.fill();
  g.fillStyle = '#ffdf8a';
  for (let i = 0; i < 4; i++) {
    const t = i / 4;
    const y = s * (0.5 + t * 0.45), w = 12 + t * 26;
    g.fillRect(s / 2 - w / 2, y, w, 26);
  }
  // تپه‌ها
  g.fillStyle = '#3f8b52'; g.beginPath(); g.arc(s * 0.22, s * 0.52, s * 0.22, Math.PI, 0); g.fill();
  g.fillStyle = '#2f6b3a'; g.beginPath(); g.arc(s * 0.8, s * 0.55, s * 0.26, Math.PI, 0); g.fill();
  // پیکان کوچک
  g.save(); g.translate(s * 0.5, s * 0.52); g.scale(1.15, 1.15);
  const bodyCv = loadImage(OUT.cars + 'peykan_paint.png');
  g.restore();
  // چرخ
  g.fillStyle = '#1c1f24'; g.beginPath(); g.arc(s * 0.38, s * 0.78, 34, 0, Math.PI * 2); g.fill();
  g.beginPath(); g.arc(s * 0.63, s * 0.78, 34, 0, Math.PI * 2); g.fill();
  g.fillStyle = '#c9ced6'; g.beginPath(); g.arc(s * 0.38, s * 0.78, 16, 0, Math.PI * 2); g.fill();
  g.beginPath(); g.arc(s * 0.63, s * 0.78, 16, 0, Math.PI * 2); g.fill();
  save(OUT.root, 'icon', cv);
}

/* --------------------------------- افکت‌ها ------------------------------- */
console.log('💨 افکت‌ها...');
function puff(name, size, color, alpha = 0.9) {
  const cv = cvOf(size, size);
  const g = cv.getContext('2d');
  const grad = g.createRadialGradient(size / 2, size / 2, 2, size / 2, size / 2, size / 2);
  grad.addColorStop(0, color.replace('ALPHA', alpha));
  grad.addColorStop(1, color.replace('ALPHA', '0'));
  g.fillStyle = grad; g.fillRect(0, 0, size, size);
  save(OUT.fx, name, cv);
}
puff('dust', 96, 'rgba(196,166,120,ALPHA)', 0.85);
puff('smoke', 96, 'rgba(120,124,132,ALPHA)', 0.7);
puff('spark', 64, 'rgba(255,207,74,ALPHA)', 0.95);
{
  const cv = cvOf(64, 64);
  const g = cv.getContext('2d');
  g.fillStyle = '#fff3c4';
  g.beginPath();
  for (let i = 0; i < 10; i++) {
    const a = -Math.PI / 2 + i * Math.PI / 5, r = i % 2 ? 8 : 30;
    const x = 32 + Math.cos(a) * r, y = 32 + Math.sin(a) * r;
    i ? g.lineTo(x, y) : g.moveTo(x, y);
  }
  g.closePath(); g.fill();
  save(OUT.fx, 'star', cv);
}

console.log('\n✅ همهٔ دارایی‌های «جاده‌باز» ساخته شد.');
