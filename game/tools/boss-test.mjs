/* آزمون خودکار نبرد رئیس‌ها: آیا یک بازیکن ماهر می‌تواند رئیس را شکست دهد؟
   اجرا: node tools/boss-test.mjs
   رئیس «دیو» در مرحلهٔ چهارم و رئیس «اژدها» در مرحلهٔ هشتم شبیه‌سازی می‌شود. */
import { installDom } from './harness.mjs';
import fs from 'node:fs';
const log = (...a) => fs.writeSync(1, a.join(' ') + '\n');
installDom();
const config = await import('../www/js/config.js');
const { buildSprites, buildBossSprites } = await import('../www/js/sprites.js');
const { World } = await import('../www/js/game.js');
const { Input } = await import('../www/js/input.js');
const { loadSave } = await import('../www/js/utils.js');
const { tileAt } = await import('../www/js/levels.js');
const { bossThink } = await import('./bossai.mjs');
buildSprites(); buildBossSprites();

const SIM_SECONDS = 90;

function fight(levelIndex) {
  const save = loadSave();
  save.owned = ['permFeather'];
  const hits = [];
  const states = {};
  const world = new World({
    save, character: config.CHARACTERS[0], levelIndex, view: { w: 440, h: 240 },
    onEvent: (t, d) => {
      if (process.env.DBG && t === 'hurt') {
        log(`   [hurt] src=${JSON.stringify(d.info)} died=${d.died} state=${world.boss.state} pX=${world.player.x.toFixed(0)} pY=${world.player.y.toFixed(0)} bX=${world.boss.x.toFixed(0)} dist=${Math.abs(world.boss.cx - world.player.cx).toFixed(0)}`);
      }
    },
  });
  const input = new Input(null);
  const b = world.boss;
  let pl = world.player;
  // ورود به میدان رئیس با گل آتش (مثل بازیکن واقعی که آیتم می‌گیرد)
  pl.setPower(config.POWER.FIRE);
  pl.x = b.arena.x0 + 10;
  pl.y = (12 - 2) * config.TILE;
  // بازیکن واقعی در ورودی میدان رئیس ایستگاه ذخیره می‌گیرد؛ آزمون هم همان نقطه را نقطهٔ تولد می‌گیرد
  world.respawnPoint = { x: b.arena.x0 + 6, y: (12 - 4) * config.TILE };
  let deaths = 0;
  let lastHp = b.hp;

  for (let i = 0; i < 60 * SIM_SECONDS && !b.defeated; i++) {
    pl = world.player;
    states[b.state] = (states[b.state] || 0) + 1;
    const prevHp = b.hp;
    if (pl.dead) { deaths++; world.lives = 9; world.respawn(); world.player.setPower(config.POWER.FIRE); world.player.invuln = 2; continue; }

    // هوش مصنوعی نبرد (مشترک با validate.mjs)
    bossThink(world, input, i);

    // پرهیز از خار/آب پیش رو (وقتی رئیس شکست خورده)
    if (b.defeated && !input.state.jump && pl.onGround && (input.state.left || input.state.right)) {
      const tx = Math.floor((pl.cx + (input.state.right ? 22 : -22)) / config.TILE);
      const ty = Math.floor((pl.y + pl.h + 2) / config.TILE);
      const t = tileAt(world.level, tx, ty);
      if (t === 14 || t === 13) input.state.jump = true;
    }
    input.poll();
    world.update(1 / 60, input);

    if (b.hp < prevHp) {
      hits.push(b.hp);
      log(`   ضربه به رئیس: جان ${b.hp}/${b.maxHp} (حالت ${b.state})`);
    }
  }

  const ok = b.defeated === true;
  const stateStr = Object.entries(states).map(([k, v]) => `${k}=${v}`).sort((a, x) => x.split('=')[1] - a.split('=')[1]).join(' ');
  log(`${ok ? '✅' : '❌'} ${b.type}: ${ok ? 'شکست خورد' : 'شکست نخورد'} | ضربه‌ها: ${hits.length} | جان نهایی: ${b.hp}/${b.maxHp} | مرگ‌ها: ${deaths} | حالت‌ها: ${stateStr}`);
  return ok;
}

let fails = 0;
for (const idx of [3, 7]) if (!fight(idx)) fails++;
log(fails ? `\n❌ ${fails} مورد ناموفق` : '\n✅ همهٔ رئیس‌ها شکست‌پذیرند');
process.exit(fails ? 1 : 0);
