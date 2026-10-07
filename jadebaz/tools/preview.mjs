import { createCanvas, loadImage } from '@napi-rs/canvas';
import { writeFileSync } from 'node:fs';
const R = new URL('..', import.meta.url).pathname;
const ids = ['peykan','pride','samand','van','nissan','benz','khavar'];
const colors = ['#f6f8fa','#e84a3a','#2f8fe0','#38b32a','#ffcf4a','#7a5bd6','#3a3f46'];
const CW = 600, CH = 280;
const cv = createCanvas(CW*2, CH*4);
const g = cv.getContext('2d');
// پس‌زمینهٔ آسمان+چمن برای پیش‌نمایش
const sky = g.createLinearGradient(0,0,0,CH*4);
sky.addColorStop(0,'#8fd4ff'); sky.addColorStop(0.6,'#cfeaff'); sky.addColorStop(1,'#8ecb6a');
g.fillStyle = sky; g.fillRect(0,0,CW*2,CH*4);
for (let i=0;i<7;i++){
  const id = ids[i];
  const paint = await loadImage(`${R}assets/cars/${id}_paint.png`);
  const det = await loadImage(`${R}assets/cars/${id}_detail.png`);
  const x = (i%2)*CW, y = Math.floor(i/2)*CH;
  // لایهٔ رنگ‌شده: بدنه × رنگ
  const tmp = createCanvas(560,240);
  const tg = tmp.getContext('2d');
  tg.drawImage(paint,0,0,560,240);
  tg.globalCompositeOperation = 'source-in';
  tg.fillStyle = colors[i]; tg.fillRect(0,0,560,240);
  tg.globalCompositeOperation = 'source-over';
  tg.drawImage(det,0,0,560,240);
  g.drawImage(tmp, x+20, y+14);
  g.fillStyle='#22304a'; g.font='bold 22px sans-serif'; g.textAlign='right';
  g.fillText(id, x+CW-24, y+38);
}
writeFileSync(R+'docs/cars-preview.png', cv.toBuffer('image/png'));
console.log('preview ok');
