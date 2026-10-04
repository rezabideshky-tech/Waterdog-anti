/* tools/spritesheet.mjs — رندر همهٔ اسپرایت‌ها در یک تصویر برای بازبینی بصری */
import { installDom, saveCanvasPng, composite } from './harness.mjs';
installDom();

const { buildSprites, buildBossSprites, Sprites } = await import('../www/js/sprites.js');
buildSprites();
buildBossSprites();

const groups = [];
const heroSmall = Object.entries(Sprites.hero.small).filter(([k]) => k.startsWith('normal_')).map(([k, v]) => [k.replace('normal_', ''), v.r]);
const heroFire = Object.entries(Sprites.hero.small).filter(([k]) => k.startsWith('fire_')).map(([k, v]) => ['fire:' + k.replace('fire_', ''), v.r]);
const heroBig = Object.entries(Sprites.hero.big).filter(([k]) => k.startsWith('normal_')).map(([k, v]) => ['big:' + k.replace('normal_', ''), v.r]);
const heroBigFire = Object.entries(Sprites.hero.big).filter(([k]) => k.startsWith('fire_')).map(([k, v]) => ['bigfire:' + k.replace('fire_', ''), v.r]);
const enemies = [];
for (const name in Sprites.enemy) Sprites.enemy[name].forEach((v, i) => enemies.push([`${name}${i}`, v.r]));
const items = Object.entries(Sprites.item).map(([k, v]) => [k, v]);

groups.push(['hero', heroSmall.concat(heroFire)]);
groups.push(['big', heroBig.concat(heroBigFire)]);
groups.push(['enemies', enemies]);
groups.push(['items', items]);
const bosses = [];
for (const type in Sprites.boss) for (const name in Sprites.boss[type]) bosses.push([`${type}:${name}`, Sprites.boss[type][name]]);
groups.push(['bosses', bosses]);

const CELL = 140;   // یاخته‌های بزرگ تا اسپرایت غول‌ها هم جا شوند
const maxCols = Math.max(...groups.map(([, l]) => l.length));
const cv = composite(CELL * maxCols, CELL * groups.length, (g) => {
  g.fillStyle = '#2b2438';
  g.fillRect(0, 0, CELL * maxCols, CELL * groups.length);
  groups.forEach(([, list], row) => {
    list.forEach(([name, sp], col) => {
      const x = col * CELL + 4, y = row * CELL + 4;
      g.fillStyle = (col + row) % 2 ? '#3a3350' : '#4a4166';
      g.fillRect(x, y, CELL - 8, CELL - 8);
      const scale = Math.max(1, Math.min(3, (CELL - 16) / Math.max(sp.width, sp.height)));
      g.imageSmoothingEnabled = false;
      g.drawImage(sp, x + (CELL - 8 - sp.width * scale) / 2, y + (CELL - 8 - sp.height * scale) / 2, sp.width * scale, sp.height * scale);
      g.fillStyle = '#fff';
      g.font = '9px sans-serif';
      g.fillText(name, x + 2, y + CELL - 12);
    });
  });
});
saveCanvasPng(cv, new URL('../.arena/tmp/spritesheet.png', import.meta.url).pathname, 2);
console.log('OK spritesheet written');
