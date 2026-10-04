/* tools/validate.mjs — آزمون خودکار: آیا همهٔ مراحل قابل‌گذرند؟ (ربات نامیرا تا پرچم می‌دود) */
import { installDom } from './harness.mjs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
const here = dirname(fileURLToPath(import.meta.url));
installDom();
const config = await import('../www/js/config.js');
const levels = await import('../www/js/levels.js');
const { buildSprites, buildBossSprites } = await import('../www/js/sprites.js');
const { World } = await import('../www/js/game.js');
const { Input } = await import('../www/js/input.js');
const { loadSave } = await import('../www/js/utils.js');
const { makeBot } = await import('./bot.mjs');
const { bossThink } = await import('./bossai.mjs');
buildSprites(); buildBossSprites();

const results = [];
for (let idx = 0; idx < config.LEVELS.length; idx++) {
  const save = loadSave();
  save.owned = process.env.NOFEATHER ? [] : ['permFeather'];
  const world = new World({ save, character: config.CHARACTERS[0], levelIndex: idx, view: { w: 440, h: 240 }, onEvent: () => {} });
  let falls = 0;
  const patchPlayer = () => {
    const pl = world.player;
    pl.hurt = () => false;
    pl.setPower(config.POWER.FIRE);
  };
  patchPlayer();
  let resetProgress = null;
  const deathSpots = [];
  world.updateDeath = function (dt) {
    if (this.player.deathTimer > 0.6) {
      falls++;
      if (deathSpots) deathSpots.push(Math.round(this.player.x / 16));
      this.lives = 3; this.respawn(); patchPlayer(); if (resetProgress) resetProgress();
    }
  };
  // در مرحله‌های رئیس‌دار، ربات ابتدا رئیس را شکست می‌دهد و بعد به پرچم می‌رسد
  const input = new Input(null);
  input.state.right = true;
  const step = 1 / 60;
  let maxX = 0, stuckAt = 0, stuckTime = 0, reached = false, t = 0;
  resetProgress = () => { maxX = world.player.x; stuckTime = 0; };
  const limit = config.LEVELS[idx].boss ? 300 : 180; // ثانیه (نبرد رئیس وقت می‌برد)
  const bossKilled = () => world.boss && world.boss.defeated;
  const think = makeBot(world, input);
  for (let i = 0; i < limit / step; i++) {
    t += step;
    const p = world.player;
    // در میدان رئیس، ربات راه‌رفتن جای خود را به ربات نبرد می‌دهد
    if (world.boss && world.boss.active && !world.boss.defeated) {
      bossThink(world, input, i);
    } else {
      think(i);
    }
    input.poll();
    world.update(step, input);
    if (world.state === 'complete') { reached = true; break; }
    const inBossFight = world.boss && world.boss.active && !world.boss.defeated;
    if (p.x > maxX + 6) { maxX = p.x; stuckTime = 0; }
    else if (!inBossFight) stuckTime += step;   // نبرد رئیس ذاتاً بی‌حرکت است
    if (stuckTime > 12) {
      stuckAt = Math.round(p.x / config.TILE);
      if (process.env.DUMP) {
        const lv2 = world.level;
        const tx0 = Math.max(0, Math.round(p.x / 16) - 8);
        console.log('  --- LEVEL', idx + 1, 'dump around tile', stuckAt, 'player tile', Math.round(p.x / 16), 'onGround', p.onGround, 'y', Math.round(p.y));
        for (let r = 4; r < 15; r++) {
          let line = '';
          for (let c = tx0; c < tx0 + 26; c++) {
            const t = levels.tileAt(lv2, c, r);
            line += t === 0 ? '.' : t.toString(36);
          }
          console.log('  r' + String(r).padStart(2) + ' ' + line);
        }
        console.log('  entities:', world.entities.filter(e => Math.abs(e.x - p.x) < 160).map(e => e.kind + (e.type ? ':' + e.type : '') + (e.enemy ? ':' + e.enemy : '') + '@' + Math.round(e.x / 16)).join(' '));
      }
      break;
    }
    if (world.state === 'gameover') break;
  }
  results.push({ level: idx + 1, reached, time: t.toFixed(1), maxTile: Math.round(maxX / config.TILE), stuckTile: stuckAt, bossDefeated: bossKilled() });
    const hist = {};
  for (const t of deathSpots) hist[t] = (hist[t] || 0) + 1;
  const top = Object.entries(hist).sort((a, b) => b[1] - a[1]).slice(0, 4).map(([k, v]) => k + '×' + v).join(' ');
  console.log(`level ${idx + 1}: ${reached ? '✅ رسید' : '❌ نرسید'} در ${t.toFixed(1)}s | سقوط=${falls} | بیشترین خانه=${Math.round(maxX / config.TILE)}${stuckAt ? ' | گیرکرد در خانه ' + stuckAt : ''}${world.boss ? ' | رئیس: ' + (bossKilled() ? 'شکست خورد ✅' : world.boss.hp + '/' + world.boss.maxHp) : ''}${top ? ' | محل سقوط‌ها: ' + top : ''}`);
}
const bad = results.filter((r) => !r.reached);
console.log(bad.length ? `\n❌ ${bad.length} مرحله مشکلات عبور دارد: ${bad.map((b) => b.level).join(', ')}` : '\n✅ همهٔ مراحل قابل عبورند');
process.exit(0);
