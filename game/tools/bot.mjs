/* tools/bot.mjs — ربات آزمون: مثل یک بازیکن تازه‌کار بازی می‌کند (برای اعتبارسنجی مراحل) */
import { TILE } from '../www/js/config.js';
import { SOLID_TILES, tileAt } from '../www/js/levels.js';

const HAZARD = new Set([13, 14]); // آب و نیزه

export function makeBot(world, input, opts = {}) {
  const aggressive = opts.aggressive !== false;
  let holdFrames = 0, gapFrames = 2, groundRow = 12, stallX = 0, stallFrames = 0;
  return function think(i) {
    const p = world.player;
    const lv = world.level;
    input.state.right = true;
    input.state.left = false;
    input.state.run = true;

    if (p.onGround) groundRow = Math.floor((p.y + p.h + 2) / TILE);
    const feetTy = p.onGround ? Math.floor((p.y + p.h + 2) / TILE) : groundRow;
    const frontTx = Math.floor((p.x + p.w) / TILE);
    const speed = Math.abs(p.vx);

    let gapDist = 99, hazardDist = 99, wallDist = 99;
    for (let k = 0; k <= 5; k++) {
      const tx = frontTx + k;
      const under = tileAt(lv, tx, feetTy);
      const knee = tileAt(lv, tx, feetTy - 1);
      const knee2 = tileAt(lv, tx, feetTy - 2);
      let floorFound = false, waterFound = false;
      for (let d = 1; d <= 5; d++) {
        const t = tileAt(lv, tx, feetTy + d);
        if (SOLID_TILES.has(t)) { floorFound = true; break; }
        if (t === 13) { waterFound = true; break; }
      }
      if (HAZARD.has(under) || HAZARD.has(knee) || waterFound) hazardDist = Math.min(hazardDist, k);
      // گودال واقعی: خانهٔ زیر پا خالی است و تا ۵ خانه پایین‌تر هم زمینی نیست
      if (!SOLID_TILES.has(under) && !floorFound) gapDist = Math.min(gapDist, k);
      if (SOLID_TILES.has(knee) || SOLID_TILES.has(knee2)) wallDist = Math.min(wallDist, k);
    }

    let enemyDist = 99;
    for (const e of world.entities) {
      if ((e.kind === 'enemy' && !e.removeMe && !e.squashed) || e.kind === 'shell') {
        const d = (e.x - (p.x + p.w)) / TILE;
        if (d > -1 && d < 4 && Math.abs(e.cy - p.cy) < 30) enemyDist = Math.min(enemyDist, d);
      }
    }

    let wantJump = false;
    const clearSpeed = Math.max(80, speed);
    const takeoffDist = 1.0 + clearSpeed / 300;
    // گیرکردن روی زمین = دیوار یا پله؛ بپر
    if (p.onGround) {
      if (Math.abs(p.x - stallX) < 1.2) stallFrames++;
      else { stallFrames = 0; stallX = p.x; }
    } else stallFrames = 0;
    const stuckOnWall = stallFrames > 10;
    const pitNear = gapDist <= 5 && !stuckOnWall;   // اگر پشت مانع گیر کرده‌ایم، پرش را برای پله نگه دار
    if (p.onGround) {
      if (gapDist <= takeoffDist) wantJump = true;
      else if (!pitNear && hazardDist <= takeoffDist) wantJump = true;
      else if ((!pitNear && (wallDist <= 1.05 || enemyDist <= 1.2)) || stuckOnWall) wantJump = true;
    } else if (p.coyote > 0) {
      if (gapDist <= 1.2 || hazardDist <= 1.2 || wallDist <= 1.05) wantJump = true;
    }
    if (p.vy < 0) wantJump = true;   // نگه‌داشتن دکمه تا اوج پرش (پرش کامل)
    if (world.boss && world.boss.active && !world.boss.defeated) {
      const b = world.boss;
      if ((b.vulnStomp && Math.abs(b.cx - p.cx) < 55) || (b.type === 'dragon' && b.state === 'dive' && Math.abs(b.cx - p.cx) < 70)) wantJump = true;
    }

    // پرش دوم با پَر سیمرغ وقتی در حال افتادن روی گودال هستیم (مثل بازیکن واقعی)
    const needSave = !p.onGround && p.vy > 60 && (gapDist <= 1 || hazardDist <= 1) && !p.doubleJumped && typeof p.canDoubleJump === 'function' && p.canDoubleJump();

    // نگه‌داشتن دکمه برای پرش کامل + یک فریم رهاسازی تا لبهٔ تازه ساخته شود
    if (holdFrames > 0) {
      input.state.jump = true;
      holdFrames--;
      gapFrames = 0;
    } else {
      input.state.jump = false;
      if (wantJump && gapFrames >= 1) { holdFrames = 26; gapFrames = 0; }
      else gapFrames++;
    }
    if (!p.onGround && p.vy > 30 && !needSave) holdFrames = 0;
    if (needSave && gapFrames >= 1) { holdFrames = 18; gapFrames = 0; wantJump = false; }

    if (aggressive) {
      input.state.fire = (enemyDist < 3.5 || (world.boss && world.boss.active)) && (i % 30) < 12;
    }
    return { gapDist, hazardDist, wallDist, enemyDist };
  };
}
