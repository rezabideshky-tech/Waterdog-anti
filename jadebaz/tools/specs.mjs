/* specs.mjs — هندسهٔ هر ماشین را از روی تصویرهای تولیدشده اندازه می‌گیرد و
 * فایل src/data/CarGeometry.gd را می‌سازد تا فیزیک و تصویر دقیقاً روی هم بنشینند.
 *
 * چرخ‌ها در لایهٔ جزئیات به شکل نیم‌دایرهٔ تیره کشیده شده‌اند؛ پس:
 *   شعاع چرخ = نصف پهنای افقی خوشهٔ تیره
 *   مرکز چرخ = پایین‌ترین پیکسل همان خوشه
 * و بدنهٔ رنگی از bounding-box لایهٔ رنگ به‌دست می‌آید.
 */
import { createCanvas, loadImage } from '@napi-rs/canvas';
import { writeFileSync, mkdirSync } from 'node:fs';

const ROOT = new URL('../', import.meta.url).pathname;
const IDS = ['peykan', 'pride', 'samand', 'van', 'nissan', 'khavar', 'benz'];

function pixels(img) {
  const cv = createCanvas(img.width, img.height);
  const g = cv.getContext('2d');
  g.drawImage(img, 0, 0);
  return { d: g.getImageData(0, 0, img.width, img.height).data, w: img.width, h: img.height };
}

function measureWheels(img) {
  const { d, w, h } = pixels(img);
  const dark = new Uint8Array(w * h);
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = (y * w + x) * 4;
      if (d[i + 3] < 100) continue;
      const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
      if (lum < 70) dark[y * w + x] = 1;
    }
  }
  // برچسب‌گذاری مؤلفه‌های همبند (BFS)
  const seen = new Uint8Array(w * h);
  const comps = [];
  const stack = [];
  for (let start = 0; start < w * h; start++) {
    if (!dark[start] || seen[start]) continue;
    stack.length = 0;
    stack.push(start);
    seen[start] = 1;
    let minX = 1e9, maxX = -1e9, minY = 1e9, maxY = -1e9, area = 0;
    while (stack.length) {
      const p = stack.pop();
      const x = p % w, y = (p - x) / w;
      area++;
      if (x < minX) minX = x; if (x > maxX) maxX = x;
      if (y < minY) minY = y; if (y > maxY) maxY = y;
      if (x > 0 && dark[p - 1] && !seen[p - 1]) { seen[p - 1] = 1; stack.push(p - 1); }
      if (x < w - 1 && dark[p + 1] && !seen[p + 1]) { seen[p + 1] = 1; stack.push(p + 1); }
      if (y > 0 && dark[p - w] && !seen[p - w]) { seen[p - w] = 1; stack.push(p - w); }
      if (y < h - 1 && dark[p + w] && !seen[p + w]) { seen[p + w] = 1; stack.push(p + w); }
    }
    comps.push({ area, minX, maxX, minY, maxY });
  }
  // چرخ‌ها: مؤلفه‌های بزرگی که تا پایین‌ترین ناحیه می‌رسند
  const maxBottom = comps.reduce((m, c) => Math.max(m, c.maxY), 0);
  const cand = comps.filter((c) => {
    const bw = c.maxX - c.minX + 1, bh = c.maxY - c.minY + 1;
    return c.maxY >= maxBottom - 4 && c.area > 250 &&
      bw > w * 0.04 && bw <= w * 0.2 && bh >= bw * 0.3;   // چرخ: پهنای معقول و ارتفاع کافی
  });
  if (cand.length < 2) return null;
  cand.sort((a, b) => b.area - a.area);
  const two = cand.slice(0, 2).sort((a, b) => a.minX - b.minX);
  const L = two[0], R = two[1];
  return {
    wb: Math.round(((R.minX + R.maxX) - (L.minX + L.maxX)) / 2),
    wr: Math.round(((L.maxX - L.minX) + (R.maxX - R.minX)) / 4),
    wy: Math.round(((L.maxY + R.maxY) / 2) - h / 2),
  };
}

function measureBody(img, h, w) {
  const { d } = pixels(img);
  let a = 1e9, b = -1e9, c = 1e9, e = -1e9;
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = (y * w + x) * 4;
      if (d[i + 3] < 60) continue;
      const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
      if (lum < 150) continue;                        // فقط بدنهٔ سفید/روشن
      if (x < a) a = x; if (x > b) b = x; if (y < c) c = y; if (y > e) e = y;
    }
  }
  if (a > b) return null;
  return { left: a - w / 2, right: b - w / 2, top: c - h / 2, bottom: e - h / 2 };
}

let out = `extends Node
class_name CarGeometry
## CarGeometry.gd — هندسهٔ اندازه‌گیری‌شدهٔ هر ماشین (خروجی خودکار tools/specs.mjs)
## همهٔ اعداد در «پیکسل تصویر» هستند و در بازی در مقیاس جهانی ضرب می‌شوند.
## wb فاصلهٔ محور چرخ‌ها، wr شعاع چرخ، wy مرکز چرخ نسبت به مرکز تصویر،
## left/right/top/bottom حدود بدنه نسبت به مرکز تصویر (برای برخورد بدنه).

const SPECS := {
`;
let ok = 0;
for (const id of IDS) {
  const detail = await loadImage(`${ROOT}assets/cars/${id}_detail.png`);
  const paint = await loadImage(`${ROOT}assets/cars/${id}_paint.png`);
  const wh = measureWheels(detail);
  const body = measureBody(paint, paint.height, paint.width);
  if (!wh || !body) { console.error('❌ اندازه‌گیری نشد:', id); continue; }
  console.log(id, wh, body);
  out += `\t"${id}": { "wb": ${wh.wb}, "wr": ${wh.wr}, "wy": ${wh.wy},` +
    ` "left": ${Math.round(body.left)}, "right": ${Math.round(body.right)},` +
    ` "top": ${Math.round(body.top)}, "bottom": ${Math.round(body.bottom)} },\n`;
  ok++;
}
out += `}

static func of(id: String) -> Dictionary:
	return SPECS.get(id, SPECS["peykan"])

## مقیاس تصویرِ چرخ نسبت به فایل چرخ‌ها (شعاع آن ۹۲ پیکسل است)
static func wheel_scale(id: String, world_scale: float) -> float:
	return world_scale * float(of(id)["wr"]) / 92.0
`;
mkdirSync(ROOT + 'src/data', { recursive: true });
writeFileSync(ROOT + 'src/data/CarGeometry.gd', out, 'utf8');
console.log(`✅ CarGeometry.gd نوشته شد (${ok} ماشین).`);
