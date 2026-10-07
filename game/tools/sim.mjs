/* tools/sim.mjs — شبیه‌سازی خودکار بازی در Node برای تست و اسکرین‌شات
 * استفاده: node tools/sim.mjs [level] [seconds] [prefix] [--god] [--at=px]
 */
import { installDom, saveCanvasPng, registerFonts } from './harness.mjs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdirSync } from 'node:fs';

const here = dirname(fileURLToPath(import.meta.url));
installDom();
registerFonts(join(here, '../www/assets/fonts'));

const args = process.argv.slice(2);
const flags = args.filter((a) => a.startsWith('--'));
const pos = args.filter((a) => !a.startsWith('--'));
const levelIndex = Number(pos[0] ?? 0);
const seconds = Number(pos[1] ?? 14);
const prefix = pos[2] ?? 'sim';
const god = flags.includes('--god');
const atPx = Number((flags.find((f) => f.startsWith('--at=')) || '').split('=')[1] || 0);
const outDir = join(here, '../.arena/tmp');
mkdirSync(outDir, { recursive: true });

const config = await import('../www/js/config.js');
const levels = await import('../www/js/levels.js');
const { buildSprites, buildBossSprites } = await import('../www/js/sprites.js');
const { World } = await import('../www/js/game.js');
const { Renderer } = await import('../www/js/render.js');
const { Input } = await import('../www/js/input.js');
const { loadSave } = await import('../www/js/utils.js');

const canvas = document.createElement('canvas');
const renderer = new Renderer(canvas);
const view = renderer.resize(880, 480, 1);
buildSprites();
buildBossSprites();
renderer.buildAtlas(config.THEMES[config.LEVELS[levelIndex].theme]);

const errors = [];
const world = new World({
  save: loadSave(),
  character: config.CHARACTERS[0],
  levelIndex,
  view,
  onEvent: (t, d) => {
    if (['levelComplete', 'gameover', 'bossDefeated', 'death', 'checkpoint'].includes(t)) {
      console.log('EVENT', t, d ? JSON.stringify(d).slice(0, 100) : '');
    }
  },
});
if (god) {
  const origHurt = world.player.hurt.bind(world.player);
  world.player.hurt = () => false;
  world.player.kill = () => {};
  world.addCoins(30, 0, 0);
  world.player.setPower(config.POWER.FIRE);
}
if (atPx) {
  world.player.x = atPx;
  world.player.y = (12 - 3) * config.TILE;
  world.updateCamera(1, true);
}

const input = new Input(null);
input.state.right = true;
const step = 1 / 60;
const frames = Math.round(seconds / step);
const shotEvery = Math.max(1, Math.round(frames / 6));
let shots = 0;

function botThink() {
  const p = world.player;
  if (!p || p.dead) return;
  const lv = world.level;
  input.state.right = true;
  input.state.run = true;
  const dir = 1;
  const speed = Math.abs(p.vx) + 40;
  const probe = 10 + speed * 0.06;
  const tx = Math.floor((p.x + p.w + probe) / config.TILE);
  const feetTy = Math.floor((p.y + p.h + 2) / config.TILE);
  const at1 = levels.tileAt(lv, tx, feetTy);
  const at2 = levels.tileAt(lv, tx + 1, feetTy);
  const at3 = levels.tileAt(lv, tx + 2, feetTy);
  const wall = levels.tileAt(lv, tx, feetTy - 1) !== 0 || levels.tileAt(lv, tx + 1, feetTy - 1) !== 0;
  const spikeAhead = at1 === 14 || at2 === 14 || at3 === 14;
  const waterAhead = levels.tileAt(lv, tx, feetTy - 1) === 13 || levels.tileAt(lv, tx + 1, feetTy - 1) === 13;
  const gap = (at1 === 0 && at2 === 0) || at2 === 0 || spikeAhead || waterAhead;
  const inAir = !p.onGround;
  // پرش روی دشمن نزدیک (مثل بازیکن واقعی)
  let enemyAhead = false;
  for (const e of world.entities) {
    if ((e.kind === 'enemy' || e.kind === 'shell') && !e.removeMe && e.x > p.x && e.x - p.x < 30 && Math.abs(e.cy - p.cy) < 26) enemyAhead = true;
  }
  input.state.jump = wall || gap || enemyAhead || (inAir && p.vy > 0 && (at1 === 0 || at2 === 0));
  // شوت آتش به سمت دشمن نزدیک
  let shoot = false;
  for (const e of world.entities) {
    if (e.kind === 'enemy' && !e.removeMe && e.x > p.x && e.x - p.x < 150 && Math.abs(e.y - p.y) < 40) shoot = true;
  }
  if (world.boss && world.boss.active) shoot = true;
  input.state.fire = shoot && (Math.random() < 0.3);
}

try {
  for (let i = 0; i < frames; i++) {
    botThink();
    input.poll();
    world.update(step, input);
    renderer.render(world, step);
    if (i % shotEvery === 0) {
      saveCanvasPng(canvas, join(outDir, `${prefix}-${levelIndex}-${String(shots).padStart(2, '0')}.png`));
      shots++;
    }
  }
} catch (e) {
  errors.push(e);
  console.error('ERROR during sim:', e.message, '\n', e.stack.split('\n').slice(0, 4).join('\n'));
}

const p = world.player;
console.log(`SIM DONE level=${levelIndex} prefix=${prefix} steps=${frames} x=${Math.round(p.x)} vx=${Math.round(p.vx)} onGround=${p.onGround} score=${world.score} coins=${world.coins} lives=${world.lives} state=${world.state} kills=${world.kills} mush=${world.mushrooms} boss=${(world.boss ? world.boss.type + ':' + world.boss.hp + '/' + world.boss.maxHp : 'none')} errors=${errors.length}`);
process.exit(errors.length ? 1 : 0);
