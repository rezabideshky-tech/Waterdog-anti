/* tools/gallery.mjs — ساخت تصویر گالری از صحنه‌های بازی (برای README و بازبینی بصری)
   اجرا: node tools/gallery.mjs  →  docs/gallery.png */
import { installDom, saveCanvasPng, registerFonts, composite } from './harness.mjs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdirSync } from 'node:fs';

const here = dirname(fileURLToPath(import.meta.url));
installDom();
registerFonts(join(here, '../www/assets/fonts'));

const config = await import('../www/js/config.js');
const { buildSprites, buildBossSprites } = await import('../www/js/sprites.js');
const { World } = await import('../www/js/game.js');
const { Renderer } = await import('../www/js/render.js');
const { Input } = await import('../www/js/input.js');
const { loadSave } = await import('../www/js/utils.js');
const { bossThink } = await import('./bossai.mjs');

buildSprites();
buildBossSprites();

const CELL_W = 640, CELL_H = 360, COLS = 2;
const cells = [];

/** یک صحنه را شبیه‌سازی می‌کند و کادر پایانی را برمی‌گرداند */
function scene({ levelIndex, seconds, atPx = 0, boss = false, power = null }) {
  const canvas = document.createElement('canvas');
  const renderer = new Renderer(canvas);
  const view = renderer.resize(CELL_W, CELL_H, 1);
  renderer.buildAtlas(config.THEMES[config.LEVELS[levelIndex].theme]);
  const save = loadSave();
  save.owned = ['permFeather'];
  const world = new World({ save, character: config.CHARACTERS[0], levelIndex, view, onEvent: () => {} });
  const input = new Input(null);
  const pl = world.player;
  pl.setPower(power || config.POWER.FIRE);
  if (atPx) { pl.x = atPx; pl.y = (12 - 2) * config.TILE; world.updateCamera(1, true); }
  if (boss && world.boss) {
    world.boss.active = true;
    world.boss.intro = 0;
    world.respawnPoint = { x: world.boss.arena.x0 + 6, y: (12 - 4) * config.TILE };
  }
  const step = 1 / 60;
  world.updateDeath = function () { if (this.player.deathTimer > 0.6) { this.lives = 9; this.respawn(); this.player.setPower(config.POWER.FIRE); } };
  for (let i = 0; i < Math.round(seconds / step); i++) {
    if (boss && world.boss && !world.boss.defeated) {
      bossThink(world, input, i);
      if (world.boss.vulnStomp && i % 30 > 18) input.state.jump = true;   // روی سر رئیس
    } else {
      input.state.right = true;
      input.state.run = true;
      input.state.jump = (i % 46) < 26;
      input.state.fire = (i % 40) < 10;
    }
    input.poll();
    world.update(step, input);
    renderer.render(world, step);
  }
  return canvas;
}

cells.push(scene({ levelIndex: 0, seconds: 4.2 }));
cells.push(scene({ levelIndex: 5, seconds: 3.4, atPx: 1500 }));
cells.push(scene({ levelIndex: 3, seconds: 9, boss: true, atPx: 2620 }));
cells.push(scene({ levelIndex: 7, seconds: 12, boss: true, atPx: 2990 }));

const gap = 8;
const cv = composite(COLS * CELL_W + gap * (COLS + 1), 2 * CELL_H + gap * 3, (g) => {
  g.fillStyle = '#12102a';
  g.fillRect(0, 0, COLS * CELL_W + gap * (COLS + 1), 2 * CELL_H + gap * 3);
  cells.forEach((cell, i) => {
    const col = i % COLS, row = Math.floor(i / COLS);
    g.drawImage(cell, gap + col * (CELL_W + gap), gap + row * (CELL_H + gap));
  });
});
const outDir = join(here, '../docs');
mkdirSync(outDir, { recursive: true });
saveCanvasPng(cv, join(outDir, 'gallery.png'));
console.log('✅ گالری ساخته شد: docs/gallery.png');
