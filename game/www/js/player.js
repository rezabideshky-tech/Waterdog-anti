/* player.js — شخصیت بازیکن: حرکت، پرش، قدرت‌ها، آسیب و مرگ */

import {
  GRAVITY, MAX_FALL, WALK_ACCEL, RUN_ACCEL, MAX_WALK, MAX_RUN, FRICTION, AIR_ACCEL,
  JUMP_VEL, JUMP_CUT, STOMP_BOUNCE, STOMP_BOUNCE_RUN, POWER, STAR_TIME, FEATHER_TIME,
  INVULN_TIME, TILE, DEATH_Y,
} from './config.js';
import { approach, clamp } from './utils.js';
import { moveX, moveY, groundAhead } from './physics.js';
import { T, tileAt, setTile, BUMPABLE, isHazard } from './levels.js';
import { Sound } from './audio.js';

const HITBOX = {
  small: { w: 11, h: 15, ox: -2, oy: -3 },
  big: { w: 12, h: 22, ox: -2, oy: -3 },
};

export class Player {
  constructor(game, x, y, character) {
    this.game = game;
    this.character = character;
    this.power = POWER.SMALL;
    this.setSize('small');
    this.x = x; this.y = y - this.h;
    this.vx = 0; this.vy = 0;
    this.facing = 1;
    this.onGround = false;
    this.coyote = 0;
    this.jumpBuffer = 0;
    this.jumping = false;
    this.anim = 'idle';
    this.animTime = 0;
    this.invuln = 0;
    this.star = 0;
    this.feather = 0;
    this.doubleJumped = false;
    this.frozen = false;         // هنگام پایان مرحله
    this.dead = false;
    this.deathTimer = 0;
    this.combo = 0;
    this.fireCooldown = 0;
    this.crouching = false;
    this.sliding = 0;
    this.flagSlide = null;
    this.stompCount = 0;
  }

  setSize(kind) {
    const hb = HITBOX[kind];
    const oldBottom = this.y + this.h;
    this.sizeKind = kind;
    this.w = hb.w; this.h = hb.h; this.ox = hb.ox; this.oy = hb.oy;
    if (this.y !== undefined) this.y = oldBottom - this.h;
  }

  get cx() { return this.x + this.w / 2; }
  get cy() { return this.y + this.h / 2; }

  get big() { return this.power >= POWER.BIG; }
  get fireMode() { return this.power === POWER.FIRE; }

  get spriteKey() {
    const sizeKey = this.big ? 'big' : 'small';
    let kind = 'normal';
    if (this.star > 0) kind = (Math.floor(this.star * 14) % 2) ? 'star1' : 'star2';
    else if (this.fireMode) kind = 'fire';
    const state = this.anim;
    return `${sizeKey}.${kind}_${state}`;
  }

  /** تبدیل قدرت با بزرگ‌شدن/کوچک‌شدن */
  setPower(p) {
    const wasBig = this.big;
    this.power = p;
    const nowBig = this.big;
    if (wasBig !== nowBig) {
      this.setSize(nowBig ? 'big' : 'small');
      this.game.addEffect('poof', this.x + this.w / 2, this.y + this.h);
    }
  }

  update(dt, input) {
    const level = this.game.level;
    const ch = this.character;

    if (this.dead) {
      this.deathTimer += dt;
      this.vy = Math.min(this.vy + GRAVITY * dt, MAX_FALL);
      this.y += this.vy * dt;
      return;
    }

    if (this.invuln > 0) this.invuln -= dt;
    if (this.star > 0) this.star -= dt;
    if (this.fireCooldown > 0) this.fireCooldown -= dt;
    if (this.feather > 0) this.feather -= dt;

    /* ---------------------------- پایان مرحله ---------------------------- */
    if (this.flagSlide) {
      this.updateFlagSlide(dt);
      return;
    }
    if (this.frozen) {
      this.vx = approach(this.vx, 0, FRICTION * dt);
      this.animTime += dt;
      return;
    }

    /* ------------------------------- حرکت ------------------------------- */
    const crouching = this.big && input.state.down && this.onGround;
    this.crouching = crouching && Math.abs(this.vx) < 30;
    const axis = this.crouching ? 0 : input.axis;
    const running = input.runHeld && !this.crouching;
    const maxSpeed = (running ? MAX_RUN : MAX_WALK) * ch.speed;
    const accel = this.onGround ? (running ? RUN_ACCEL : WALK_ACCEL) : AIR_ACCEL;

    if (axis !== 0) {
      this.vx = approach(this.vx, axis * maxSpeed, accel * dt);
      // پرش روی سرعت بالا حفظ می‌شود
      if (!this.onGround && Math.abs(this.vx) > maxSpeed && Math.sign(this.vx) === axis) {
        this.vx = approach(this.vx, axis * maxSpeed, accel * 0.35 * dt);
      }
      this.facing = axis;
    } else if (this.onGround) {
      this.vx = approach(this.vx, 0, FRICTION * dt);
    } else {
      this.vx = approach(this.vx, 0, FRICTION * 0.12 * dt);
    }

    /* -------------------------------- پرش ------------------------------- */
    if (this.onGround) { this.coyote = 0.09; this.doubleJumped = false; }
    else this.coyote = Math.max(0, this.coyote - dt);
    if (input.pressed.jump) this.jumpBuffer = 0.11;
    else this.jumpBuffer = Math.max(0, this.jumpBuffer - dt);

    if (this.jumpBuffer > 0 && this.coyote > 0) {
      this.vy = JUMP_VEL * ch.jump;
      this.onGround = false;
      this.jumping = true;
      this.jumpBuffer = 0; this.coyote = 0;
      this.game.addEffect('dust', this.x + this.w / 2, this.y + this.h);
      (this.big ? Sound.bigJump() : Sound.jump());
    } else if (this.jumpBuffer > 0 && !this.onGround && this.canDoubleJump() && !this.doubleJumped) {
      this.vy = JUMP_VEL * 0.86 * ch.jump;
      this.doubleJumped = true;
      this.jumpBuffer = 0;
      this.jumping = true;
      this.game.addEffect('featherBurst', this.x + this.w / 2, this.y + this.h);
      Sound.jump();
    }
    if (!input.jumpHeld && this.jumping && this.vy < 0) {
      this.vy *= JUMP_CUT;
      this.jumping = false;
    }
    if (this.vy > 0) this.jumping = false;

    /* پرش نرم با پَر سیمرغ (سقوط آهسته) */
    let g = GRAVITY;
    if (this.feather > 0 && this.vy > 0 && input.jumpHeld) g *= 0.55;

    /* ------------------------------ شوت آتش ----------------------------- */
    if (this.fireMode && input.pressed.fire && this.fireCooldown <= 0 && this.game.countPlayerFireballs() < 2) {
      this.fireCooldown = 0.34;
      this.game.spawnFireball(this.x + (this.facing > 0 ? this.w : 0), this.y + this.h * 0.35, this.facing);
      this.animTime = 0;
      Sound.fire();
    }

    /* ------------------------------ فیزیک ------------------------------- */
    this.vy = Math.min(this.vy + g * dt, MAX_FALL);
    const wasGround = this.onGround;
    const rx = moveX(this, this.vx * dt, level);
    if (rx.hitLeft && this.vx < 0) this.vx = 0;
    if (rx.hitRight && this.vx > 0) this.vx = 0;
    const ry = moveY(this, this.vy * dt, level, { useSemi: true });
    const fallingVelocity = this.vy;
    if (ry.onGround) {
      if (!wasGround && fallingVelocity > 260) {
        this.game.addEffect('dust', this.x + this.w / 2, this.y + this.h);
        if (this.combo > 1) {
          this.game.onLandingCombo(this);
        }
        this.combo = 0;
      }
      this.vy = 0;
    }
    this.onGround = ry.onGround || (this.vy >= 0 && this.probeGround());

    if (ry.hitCeil && ry.ceilTile) this.game.onHeadBump(ry.ceilTile, this);

    /* -------------------------- خانه‌های خطرناک --------------------------- */
    this.checkHazards();

    /* ---------------------- سقوط از پایین مرحله = مرگ -------------------- */
    if (this.y > level.h * TILE + DEATH_Y - 200) this.kill(true);

    /* ------------------------------ انیمیشن ----------------------------- */
    this.animTime += dt;
    if (!this.onGround) this.anim = this.vy < -20 ? 'jump' : 'fall';
    else if (this.crouching) this.anim = 'crouch';
    else if (Math.abs(this.vx) > 6) {
      const skid = Math.sign(this.vx) !== Math.sign(input.axis) && input.axis !== 0;
      this.anim = skid ? 'skid' : 'run' + (Math.floor(this.animTime * (Math.abs(this.vx) > 150 ? 14 : 9)) % 3 + 1);
    } else this.anim = 'idle';

    /* افتادن در آب */
    const midTx = Math.floor((this.x + this.w / 2) / TILE);
    const midTy = Math.floor((this.y + this.h * 0.6) / TILE);
    if (tileAt(level, midTx, midTy) === T.WATER) this.kill(true);
  }

  canDoubleJump() {
    if (this.game.perk('permFeather')) return true;
    return this.feather > 0;
  }

  probeGround() {
    const level = this.game.level;
    const ty = Math.floor((this.y + this.h + 1) / TILE);
    const l = Math.floor((this.x + 1) / TILE), r = Math.floor((this.x + this.w - 1) / TILE);
    for (let tx = l; tx <= r; tx++) {
      const t = tileAt(level, tx, ty);
      if (t === T.GROUND || t === T.GROUND_DARK || t === T.BLOCK || t === T.PIPE_TL || t === T.PIPE_TR || t === T.USED || t === T.BRICK || t === T.BRICK_COIN || t === T.QUESTION || t === T.CRUMBLE) return true;
    }
    return false;
  }

  checkHazards() {
    const level = this.game.level;
    const x0 = Math.floor((this.x + 2) / TILE), x1 = Math.floor((this.x + this.w - 3) / TILE);
    const y0 = Math.floor((this.y + 2) / TILE), y1 = Math.floor((this.y + this.h - 1) / TILE);
    for (let ty = y0; ty <= y1; ty++) {
      for (let tx = x0; tx <= x1; tx++) {
        const t = tileAt(level, tx, ty);
        if (t === T.SPIKE) { this.hurt({ source: 'spike', tx, ty }); return; }
        if (t === T.WATER) { this.kill(true); return; }
      }
    }
  }

  /** آسیب دیدن: کوچک‌شدن یا مرگ */
  hurt(info = {}) {
    if (this.dead || this.star > 0 || this.invuln > 0 || this.frozen) return false;
    if (this.game.perk('bankShield') && this.game.coins > 0) this.game.spendCoins(10);
    if (this.big) {
      this.setPower(POWER.SMALL);
      this.invuln = INVULN_TIME;
      this.game.addEffect('poof', this.x + this.w / 2, this.y + this.h);
      Sound.powerdown();
      this.game.onPlayerHurt(info, false);
    } else {
      this.game.onPlayerHurt(info, true);
      this.kill(false);
    }
    return true;
  }

  kill(fell) {
    if (this.dead) return;
    this.dead = true;
    this.deathTimer = 0;
    this.vy = fell ? 0 : -420;
    this.vx = 0;
    this.game.onPlayerDeath(fell);
    Sound.die();
  }

  /** پرش روی دشمن */
  bounce(strength = STOMP_BOUNCE) {
    const held = this.game.input?.runHeld && this.game.input?.state?.jump;
    this.vy = strength * (held ? STOMP_BOUNCE_RUN / STOMP_BOUNCE : 1);
    this.onGround = false;
    this.jumping = false;
    this.combo++;
    this.stompCount++;
  }

  startFlagSlide(flag) {
    this.flagSlide = { flag, t: 0, side: this.x + this.w / 2 < flag.x ? -1 : 1 };
    this.vx = 0; this.vy = 0;
    this.x = flag.x - this.w / 2 + this.flagSlide.side * 3;
    this.facing = 1;
  }

  updateFlagSlide(dt) {
    const fs = this.flagSlide;
    fs.t += dt;
    const level = this.game.level;
    const groundY = (12 - (this.big ? 1.5 : 1)) * TILE;
    if (this.y < groundY) {
      this.y = Math.min(groundY, this.y + 150 * dt);
      this.anim = 'run2';
    } else {
      this.anim = 'idle';
      this.y = groundY;
      if (fs.t > 1.6) this.frozen = true;
      if (!fs.done) { fs.done = true; this.game.onFlagLanded(); }
    }
  }
}
