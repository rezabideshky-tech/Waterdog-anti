import { createCanvas, loadImage } from '@napi-rs/canvas';
import { writeFileSync } from 'node:fs';
const ids = ['peykan','pride','samand','van','nissan','khavar','benz'];
const cw = 560, ch = 240, pad = 6;
const cv = createCanvas(cw * 2 + pad * 3, (ch + 26) * ids.length + pad);
const g = cv.getContext('2d');
g.fillStyle = '#20304a'; g.fillRect(0,0,cv.width,cv.height);
g.font = 'bold 16px sans-serif'; g.fillStyle = '#ffcf4a';
for (let i = 0; i < ids.length; i++) {
  const y = i * (ch + 26) + 22;
  const a = await loadImage(`../assets/cars/${ids[i]}_paint.png`);
  const b = await loadImage(`../assets/cars/${ids[i]}_detail.png`);
  g.fillStyle = '#12192a'; g.fillRect(pad, y - 6, cw, ch);
  g.drawImage(a, pad, y - 6);
  g.fillStyle = '#12192a'; g.fillRect(cw + pad * 2, y - 6, cw, ch);
  g.drawImage(b, cw + pad * 2, y - 6);
  g.fillStyle = '#ffcf4a'; g.fillText(ids[i], pad, y - 10);
  // خط مرکز چرخ اندازه‌گیری‌شده
  g.strokeStyle = 'rgba(255,60,60,.8)'; g.lineWidth = 2;
  g.strokeRect(pad, y - 6 + ch / 2, cw, 1);
}
writeFileSync('../docs/cars-detail.png', cv.toBuffer('image/png'));
console.log('ok');
