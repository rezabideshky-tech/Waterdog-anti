/* preview_car.mjs — پیش‌نمایش سوارکردن چرخ‌ها روی بدنه با هندسهٔ اندازه‌گیری‌شده */
import { createCanvas, loadImage } from '@napi-rs/canvas';
import { readFileSync, writeFileSync } from 'node:fs';

const ROOT = new URL('../', import.meta.url).pathname;
const gd = readFileSync(ROOT + 'src/data/CarGeometry.gd', 'utf8');
const SPECS = {};
for (const m of gd.matchAll(/"(\w+)": \{ ([^}]+) \}/g)) {
  const o = {};
  for (const kv of m[2].matchAll(/"(\w+)": (-?\d+)/g)) o[kv[1]] = +kv[2];
  SPECS[m[1]] = o;
}
const ids = Object.keys(SPECS);
const VS = 0.55;
const cw = 1280, ch = 300;
const cv = createCanvas(cw, ch * ids.length);
const g = cv.getContext('2d');
const tire = await loadImage(ROOT + 'assets/wheels/tire.png');
const rim = await loadImage(ROOT + 'assets/wheels/rim_2.png');
const cars = {};
for (const id of ids) {
  cars[id] = {
    paint: await loadImage(ROOT + `assets/cars/${id}_paint.png`),
    detail: await loadImage(ROOT + `assets/cars/${id}_detail.png`),
  };
}
ids.forEach((id, i) => {
  const s = SPECS[id];
  const sc = VS;
  const groundY = i * ch + 250;
  g.fillStyle = i % 2 ? '#3f7a3a' : '#c08a4e';
  g.fillRect(0, i * ch, cw, ch);
  g.fillStyle = 'rgba(0,0,0,.2)'; g.fillRect(0, groundY, cw, ch);
  g.strokeStyle = '#fff'; g.beginPath(); g.moveTo(0, groundY); g.lineTo(cw, groundY); g.stroke();
  const cx = 420, wy = s.wy * sc, wr = s.wr * sc;
  const bodyY = groundY - (wy + wr);
  // بدنه
  g.save();
  g.translate(cx, bodyY);
  g.scale(sc, sc);
  g.drawImage(cars[id].paint, -280, -120);
  g.globalCompositeOperation = 'source-atop';
  g.fillStyle = '#e8402a';
  g.fillRect(-280, -120, 560, 240);
  g.globalCompositeOperation = 'source-over';
  g.drawImage(cars[id].detail, -280, -120);
  g.restore();
  // چرخ‌ها با هندسهٔ اندازه‌گیری‌شده
  const ws = (wr / 92) * (92 / s.wr) * s.wr / 92; // = wr/92
  const wheelScale = wr / 92;
  for (const sign of [-1, 1]) {
    const wx = cx + sign * (s.wb / 2) * sc;
    const wyy = groundY - wr;
    g.drawImage(tire, wx - 92 * wheelScale, wyy - 92 * wheelScale, 184 * wheelScale, 184 * wheelScale);
    g.drawImage(rim, wx - 92 * wheelScale, wyy - 92 * wheelScale, 184 * wheelScale, 184 * wheelScale);
  }
  g.fillStyle = '#fff'; g.font = 'bold 22px sans-serif';
  g.fillText(`${id}  wb=${s.wb} wr=${s.wr} wy=${s.wy}  scale=${sc}`, 16, i * ch + 34);
  // خط مرکز چرخ
  g.strokeStyle = 'rgba(255,255,255,.5)';
  g.beginPath(); g.moveTo(cx - 200, groundY - wr); g.lineTo(cx + 200, groundY - wr); g.stroke();
});
writeFileSync(ROOT + 'docs/cars-rig.png', cv.toBuffer('image/png'));
console.log('ok', ids.join(','));
