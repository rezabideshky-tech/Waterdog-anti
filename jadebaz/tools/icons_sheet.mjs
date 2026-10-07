import { createCanvas, loadImage } from '@napi-rs/canvas';
import { readdirSync, writeFileSync, mkdirSync } from 'node:fs';
const dir = '../assets/ui/';
const files = readdirSync(dir).filter(f => f.endsWith('.png')).sort();
const cols = 8, cell = 150, pad = 12;
const rows = Math.ceil(files.length / cols);
const cv = createCanvas(cols * cell + pad, rows * cell + pad + 30);
const g = cv.getContext('2d');
g.fillStyle = '#101a2e'; g.fillRect(0, 0, cv.width, cv.height);
g.font = 'bold 20px sans-serif'; g.fillStyle = '#ffcf4a';
g.fillText(`assets/ui — ${files.length} فایل`, pad, 26);
for (let i = 0; i < files.length; i++) {
  const img = await loadImage(dir + files[i]);
  const c = i % cols, r = Math.floor(i / cols);
  const x = pad + c * cell, y = 40 + r * cell;
  g.fillStyle = '#1b2740'; g.fillRect(x, y, cell - 8, cell - 8);
  const s = Math.min(cell - 40, img.width, img.height);
  g.drawImage(img, x + (cell - 8 - s) / 2, y + 12, s, s);
  g.fillStyle = '#bcc6dd'; g.font = '13px sans-serif'; g.textAlign = 'center';
  g.fillText(files[i].replace('.png', ''), x + (cell - 8) / 2, y + cell - 16);
  g.textAlign = 'left';
}
mkdirSync('../docs', { recursive: true });
writeFileSync('../docs/ui-sheet.png', cv.toBuffer('image/png'));
console.log('ok', files.length, cv.width, cv.height);
