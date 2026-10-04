/* physics.js — برخورد با خانه‌های مرحله (جامد، نیم‌جامد، خطرناک) */

import { TILE } from './config.js';
import { T, isSolid, isSemi, tileAt } from './levels.js';

/** حرکت افقی با برخورد */
export function moveX(e, dx, level) {
  const res = { hitLeft: false, hitRight: false };
  if (dx === 0) return res;
  e.x += dx;
  const top = Math.floor((e.y + 1) / TILE);
  const bottom = Math.floor((e.y + e.h - 1) / TILE);
  if (dx > 0) {
    const tx = Math.floor((e.x + e.w - 1) / TILE);
    for (let ty = top; ty <= bottom; ty++) {
      if (isSolid(level, tx, ty)) {
        e.x = tx * TILE - e.w;
        res.hitRight = true;
        break;
      }
    }
  } else {
    const tx = Math.floor(e.x / TILE);
    for (let ty = top; ty <= bottom; ty++) {
      if (isSolid(level, tx, ty)) {
        e.x = (tx + 1) * TILE;
        res.hitLeft = true;
        break;
      }
    }
  }
  return res;
}

/** حرکت عمودی با برخورد (+ سکوهای نیم‌جامد و برخورد از پایین با خانه‌های نشانه‌دار) */
export function moveY(e, dy, level, opts = {}) {
  const res = { onGround: false, hitCeil: false, ceilTile: null, landTile: null };
  if (dy === 0) return res;
  const prevBottom = e.y + e.h;
  e.y += dy;
  const left = Math.floor((e.x + 1) / TILE);
  const right = Math.floor((e.x + e.w - 1) / TILE);
  if (dy > 0) {
    const ty = Math.floor((e.y + e.h - 1) / TILE);
    for (let tx = left; tx <= right; tx++) {
      const t = tileAt(level, tx, ty);
      const solid = isSolid(level, tx, ty);
      const semi = opts.useSemi && isSemi(level, tx, ty) && prevBottom <= ty * TILE + 2;
      if (solid || semi) {
        e.y = ty * TILE - e.h;
        res.onGround = true;
        res.landTile = t;
        break;
      }
    }
  } else {
    const ty = Math.floor(e.y / TILE);
    for (let tx = left; tx <= right; tx++) {
      if (isSolid(level, tx, ty)) {
        e.y = (ty + 1) * TILE;
        res.hitCeil = true;
        res.ceilTile = { tx, ty, tile: tileAt(level, tx, ty) };
        break;
      }
    }
  }
  return res;
}

/** آیا زیر پای موجود زمین هست؟ (برای چرخش در لبه‌ها) */
export function groundAhead(level, e, dir) {
  const probeX = dir > 0 ? e.x + e.w + 2 : e.x - 2;
  const tx = Math.floor(probeX / TILE);
  const ty = Math.floor((e.y + e.h + 2) / TILE);
  const t = tileAt(level, tx, ty);
  return isSolid(level, tx, ty) || isSemi(level, tx, ty);
}

/** برخورد دو مستطیل با ضریب تلورانس */
export function overlap(a, b, tol = 0.5) {
  return a.x + tol < b.x + b.w && a.x + a.w - tol > b.x && a.y + tol < b.y + b.h && a.y + a.h - tol > b.y;
}

export function tileCenterBelow(e) {
  return { tx: Math.floor((e.x + e.w / 2) / TILE), ty: Math.floor((e.y + e.h + 1) / TILE) };
}
