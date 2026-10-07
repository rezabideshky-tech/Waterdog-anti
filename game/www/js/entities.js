/* entities.js — دشمنان، رئیس‌ها، آیتم‌ها، گلولهٔ آتش و قطعات تخریب */

import { GRAVITY, MAX_FALL, TILE, ENEMY_BOUNCE, FIXED_DT } from './config.js';
import { moveX, moveY, overlap, groundAhead } from './physics.js';
import { T, tileAt, setTile } from './levels.js';
import { Sprites } from './sprites.js';
const Sprite = Sprites;
import { rand, clamp, pick } from './utils.js';
import { Sound } from './audio.js';

export class Entity {
  constructor(x, y, w, h) {
    this.x = x; this.y = y; this.w = w; this.h = h;
    this.vx = 0; this.vy = 0;
    this.dead = false;
    this.gravity = true;
    this.onGround = false;
    this.facing = -1;
    this.removeMe = false;
    this.kind = 'entity';
  }
  get cx() { return this.x + this.w / 2; }
  get cy() { return this.y + this.h / 2; }
  update() {}
  draw() {}
}

/* ================================ دشمنان ================================ */
const ENEMY_DEFS = {
  ladybug:   { w: 13, h: 13, speed: 34,  sprite: 'ladybug', stomp: true,  anim: 0.18, points: 100, shellable: false },
  turtle:    { w: 13, h: 14, speed: 26,  sprite: 'turtle',  stomp: true,  anim: 0.16, points: 100, shellable: true },
  bee:       { w: 13, h: 13, speed: 46,  sprite: 'bee',     stomp: true,  anim: 0.10, points: 200, fly: true },
  spikeball: { w: 13, h: 13, speed: 58,  sprite: 'spikeball', stomp: false, anim: 0.1, points: 200, armored: true },
  ghost:     { w: 15, h: 18, speed: 24,  sprite: 'ghost',   stomp: false, anim: 0.25, points: 200, fly: true, spooky: true },
  plant:     { w: 13, h: 20, speed: 0,   sprite: 'plant',   stomp: false, anim: 0.2,  points: 200, pipey: true },
};

export class Enemy extends Entity {
  constructor(type, x, y, opts = {}) {
    const def = ENEMY_DEFS[type] || ENEMY_DEFS.ladybug;
    super(x + (16 - def.w) / 2, y + (16 - def.h), def.w, def.h);
    this.type = type;
    this.def = def;
    this.dir = opts.dir || -1;
    this.speedScale = opts.speedScale || 1;
    this.t = Math.random() * 2;
    this.animT = 0;
    this.frame = 0;
    this.kind = 'enemy';
    this.squashed = 0;
    this.falling = def.fly ? false : true;
    this.baseY = this.y;
    this.startX = this.x;
    this.plantBase = opts.plantBase ?? null;
    this.turnCooldown = 0;
    this.hurtFlash = 0;
    this.puff = 0;
    if (def.pipey) {
      this.baseY = this.y;
      this.plantPhase = Math.random() * Math.PI * 2;
      this.upTime = 0;
    }
    if (def.fly) this.baseY = this.y - 8;
    this.gravity = !def.fly;
  }

  get spriteList() { return Sprite.enemy[this.def.sprite] || Sprite.enemy.ladybug; }

  update(dt, game) {
    this.t += dt;
    this.animT += dt;
    if (this.hurtFlash > 0) this.hurtFlash -= dt;
    if (this.squashed > 0) {
      this.squashed -= dt;
      if (this.squashed <= 0) this.removeMe = true;
      return;
    }
    const level = game.level;
    const player = game.player;

    if (this.def.pipey) {
      // گیاه گلدان: بالا و پایین رفتن از لوله
      const cycle = (Math.sin(this.t * 1.4 + this.plantPhase) + 1) / 2;
      const hidden = this.baseY + this.h + 4;
      this.y = hidden - cycle * (this.h + 6);
      this.frame = cycle > 0.5 ? 1 : 0;
      this.active = cycle > 0.35;
      return;
    }

    if (this.def.fly) {
      // پرواز: موج سینوسی
      this.x += this.dir * this.def.speed * this.speedScale * dt;
      const wave = Math.sin(this.t * 2.4) * 12;
      const chase = this.def.spooky && player && Math.abs(player.x - this.x) < 190;
      if (chase) {
        const dirToPlayer = Math.sign(player.cx - this.cx) || 1;
        this.x += dirToPlayer * this.def.speed * 0.9 * dt;
        this.y += clamp(player.cy - this.cy, -22, 22) * dt * 1.4;
      } else {
        this.y = this.baseY + wave;
      }
      if (this.x < 8 || this.x + this.w > level.pixelW - 8) { this.dir *= -1; this.x = clamp(this.x, 8, level.pixelW - 8 - this.w); }
      // محدودهٔ نوسان افقی برای حشره‌ها
      if (!chase && Math.abs(this.x - this.startX) > 60) { this.dir *= -1; this.startX = this.x; }
      this.frame = Math.floor(this.animT / this.def.anim) % this.spriteList.length;
      return;
    }

    // روی زمین
    this.vy = Math.min(this.vy + GRAVITY * dt, MAX_FALL);
    const rx = moveX(this, this.dir * this.def.speed * this.speedScale * dt, level);
    if (rx.hitLeft || rx.hitRight) this.dir *= -1;
    const ry = moveY(this, this.vy * dt, level, { useSemi: true });
    this.onGround = ry.onGround;
    if (ry.onGround) this.vy = 0;
    // چرخش در لبهٔ سکو (مگر آنکه افتادن آزاد باشد)
    if (this.onGround && !this.falling && this.turnCooldown <= 0) {
      if (!groundAhead(level, this, this.dir)) { this.dir *= -1; this.turnCooldown = 0.2; }
    }
    if (this.turnCooldown > 0) this.turnCooldown -= dt;
    if (this.y > level.pixelH + 80) this.removeMe = true;
    this.frame = Math.floor(this.animT / this.def.anim) % this.spriteList.length;
    // برخورد با بازیکن
    if (player && !player.dead) this.tryTouchPlayer(game, player);
  }

  tryTouchPlayer(game, player) {
    if (!overlap(this, player, 2)) return;
    const stomping = player.vy > 40 && (player.y + player.h) - this.y < this.h * 0.7;
    if (player.star > 0) { game.killEnemy(this, 'star'); return; }
    if (this.def.stomp && stomping) {
      game.stompEnemy(this, player);
    } else if (this.def.armored && stomping) {
      player.hurt({ source: 'spike' });
    } else {
      player.hurt({ source: 'enemy', enemy: this });
    }
  }

  squash() {
    this.squashed = 0.35;
    this.vx = 0;
    this.def = { ...this.def, speed: 0 };
  }
  flipDeath() {
    this.dead = true;
    this.gravity = true;
    this.vy = -260;
    this.vx = this.facing * -1 * 30;
    this.flippedDeath = true;
    this.removeMe = false;
    this.update = (dt) => {
      this.vy = Math.min(this.vy + GRAVITY * dt, MAX_FALL);
      this.x += this.vx * dt; this.y += this.vy * dt;
      if (this.y > 4000) this.removeMe = true;
    };
  }
  poof() { this.puff = 1; this.removeMe = true; }
}

/** لاک‌پشت پس از لگد زدن به لاک تبدیل می‌شود */
export class Shell extends Entity {
  constructor(x, y, dir) {
    super(x, y, 13, 12);
    this.kind = 'shell';
    this.dir = dir || 1;
    this.moving = false;
    this.t = 0;
    this.animT = 0;
    this.idleTimer = 0;
    this.def = { speed: 230, points: 200 };
  }
  update(dt, game) {
    this.t += dt;
    const level = game.level;
    const player = game.player;
    if (this.moving) {
      const rx = moveX(this, this.dir * this.def.speed * dt, level);
      if (rx.hitLeft || rx.hitRight) { this.dir *= -1; Sound.bump(); }
      // برخورد با دشمن‌های دیگر
      for (const e of game.entities) {
        if (e.kind === 'enemy' && !e.dead && !e.removeMe && overlap(this, e, 2)) {
          game.killEnemy(e, 'shell');
        }
      }
      if (game.boss && game.boss.active && overlap(this, game.boss, 4)) game.hitBoss(game.boss, 'shell');
      this.idleTimer += dt;
      if (this.idleTimer > 6) this.moving = false;
    } else {
      this.idleTimer = 0;
    }
    this.vy = Math.min(this.vy + GRAVITY * dt, MAX_FALL);
    const ry = moveY(this, this.vy * dt, level, { useSemi: true });
    if (ry.onGround) this.vy = 0;
    this.onGround = ry.onGround;
    if (this.y > level.pixelH + 80) this.removeMe = true;
    if (player && !player.dead && overlap(this, player, 2)) {
      const stomping = player.vy > 40 && (player.y + player.h) - this.y < this.h * 0.7;
      if (player.star > 0) { this.poof(); game.killEnemy(this, 'star'); }
      else if (stomping) {
        if (this.moving) { this.moving = false; this.idleTimer = 0; }
        else { this.moving = true; this.dir = Math.sign(player.cx - this.cx) || 1; game.kickShell(); }
        player.bounce(ENEMY_BOUNCE);
        game.addScore(200);
        Sound.kick();
      } else if (this.moving) {
        player.hurt({ source: 'shell' });
      } else {
        this.moving = true;
        this.dir = Math.sign(player.cx - this.cx) || 1;
        game.kickShell();
        Sound.kick();
      }
    }
  }
  draw(g, cam) { drawSpriteCentered(g, Sprite.enemy.shell[0], this, cam, this.dir, this.moving ? Math.floor(this.t * 20) % 2 : 0); }
  poof() { this.removeMe = true; }
}

function drawSpriteCentered(g, spriteSet, e, cam, dir, frame = 0) {
  const frames = Array.isArray(spriteSet) ? spriteSet : [spriteSet];
  const cv = frames[frame % frames.length];
  const img = dir >= 0 ? (cv.r || cv) : (cv.l || cv);
  const dx = Math.round(e.x + e.w / 2 - img.width / 2 - cam.x + (e.spriteOffsetX || 0));
  const dy = Math.round(e.y + e.h - img.height - cam.y + (e.spriteOffsetY || 0));
  g.drawImage(img, dx, dy);
}

/* ================================== رئیس ================================ */
export class Boss extends Entity {
  constructor(type, x, y, arena) {
    const big = type === 'div';
    super(x, y, big ? 54 : 76, big ? 48 : 44);
    this.type = type;
    this.kind = 'boss';
    this.arena = arena;
    this.hp = big ? 3 : 4;
    this.maxHp = this.hp;
    this.dir = -1;
    this.t = 0;
    this.state = 'idle';
    this.stateT = 0;
    this.active = false;
    this.hurtFlash = 0;
    this.invuln = 0;
    this.baseY = y;
    this.swoopT = 0;
    this.defeated = false;
    this.intro = 0;
    this.def = { points: big ? 5000 : 8000 };
    if (type === 'dragon') { this.y = this.baseY - 40; }
    this.shootCd = 1.5;
    this.groundHold = 0;
  }

  get vulnStomp() { return this.type === 'div' ? this.state === 'roar' : this.state === 'dive'; }

  update(dt, game) {
    if (this.defeated) {
      this.t += dt;
      this.y += 20 * dt;
      if (this.t > 3) this.removeMe = true;
      return;
    }
    const player = game.player;
    this.t += dt;
    if (this.hurtFlash > 0) this.hurtFlash -= dt;
    if (this.invuln > 0) this.invuln -= dt;
    if (!this.active) {
      const inArena = player && player.cx > this.arena.x0 - 24 && player.cx < this.arena.x1 + 70;
      if (player && (inArena || Math.abs(player.cx - this.cx) < 240)) {
        this.active = true;
        this.intro = 1.2;
        Sound.bossRoar();
      }
      return;
    }
    if (this.intro > 0) { this.intro -= dt; return; }

    this.stateT -= dt;
    const spawnFire = () => {
      const px = this.type === 'div' ? this.x + (this.dir > 0 ? this.w : -8) : this.x + (this.dir > 0 ? this.w - 16 : 0);
      const py = this.type === 'div' ? this.y + 8 : this.y + 18;
      game.spawnEnemyFireball(px, py, this.dir);
      Sound.fire();
    };

    if (this.type === 'div') {
      // دیو: راه‌رفتن، گاهی توقف و غرش (فرصت پرش روی سر)
      const speed = 40 + (this.maxHp - this.hp) * 16;
      if (this.state === 'idle' || this.state === 'walk') {
        this.x += this.dir * speed * dt;
        if (this.x < this.arena.x0) { this.x = this.arena.x0; this.dir = 1; }
        if (this.x + this.w > this.arena.x1) { this.x = this.arena.x1 - this.w; this.dir = -1; }
        if (this.stateT <= 0) {
          this.state = Math.random() < 0.45 ? 'roar' : 'walk';
          this.stateT = this.state === 'roar' ? 1.1 : rand(1.4, 2.6);
          if (this.state === 'roar') { Sound.bossRoar(); game.addEffect('shock', this.cx, this.y + this.h); }
        }
        // گاهی گرز پرتاب می‌کند
        this.shootCd -= dt;
        if (this.shootCd <= 0) { this.shootCd = 2.4; spawnFire(); }
      } else if (this.state === 'roar') {
        if (this.stateT <= 0) { this.state = 'walk'; this.stateT = rand(1.2, 2); }
      }
      this.dir = player && player.cx < this.cx ? -1 : 1;
    } else {
      // اژدها: پرواز موجی، شیرجه و نفس آتش
      this.x += this.dir * 70 * dt;
      if (this.x < this.arena.x0) { this.x = this.arena.x0; this.dir = 1; }
      if (this.x + this.w > this.arena.x1) { this.x = this.arena.x1 - this.w; this.dir = -1; }
      const hover = this.baseY - 46 + Math.sin(this.t * 2) * 10;
      if (this.state === 'fly' || this.state === 'idle') {
        this.y = hover;
        if (this.stateT <= 0) {
          const wantsDive = Math.random() < 0.5;
          this.state = wantsDive ? 'windup' : 'breath';
          this.stateT = wantsDive ? 0.75 : 1.4;
          this.diveFrom = this.y;
          // نقطهٔ فرود در ابتدای هشدار قفل می‌شود تا بازیکن فرصت فرار داشته باشد
          if (wantsDive && player) this.diveX = player.cx;
        }
      } else if (this.state === 'breath') {
        this.y = hover;
        if (this.stateT < 1.0 && !this.didBreath) { this.didBreath = true; spawnFire(); spawnFire(); }
        if (this.stateT <= 0) { this.state = 'fly'; this.stateT = rand(1, 1.8); this.didBreath = false; }
      } else if (this.state === 'windup') {
        // هشدار پیش از شیرجه: کمی بالا می‌رود تا بازیکن فرصت فرار داشته باشد
        this.y = hover - 8 - Math.max(0, 0.75 - this.stateT) * 10;
        if (this.stateT <= 0) {
          this.state = 'dive';
          this.stateT = 1.9;
        }
      } else if (this.state === 'dive') {
        // شیرجهٔ عمودی روی نقطهٔ هدف (قابل فرار) و ماندن کوتاه روی زمین
        const target = 12 * TILE - this.h;
        if (this.diveX !== undefined) {
          const ddx = this.diveX - (this.x + this.w / 2);
          if (Math.abs(ddx) > 6) this.x += Math.sign(ddx) * Math.min(Math.abs(ddx), 210 * dt);
        }
        this.y = Math.min(target, this.y + 230 * dt);
        if (this.y >= target - 0.5) {
          this.groundHold += dt;
          this.y = target;
          if (this.groundHold > 0.75 || this.stateT <= -0.6) { this.state = 'rise'; this.stateT = 0.7; this.groundHold = 0; }
        }
        this.stateT = Math.min(this.stateT, 0.9);
        if (player && !player.dead && overlap(this, player, 4)) {
          const fromAbove = player.vy > -60 && player.y + player.h - this.y < this.h * 0.75 && player.cy < this.cy;
          if (fromAbove) game.hitBoss(this, 'stomp');
          else if (player.star <= 0) player.hurt({ source: 'boss' });
        }
      } else if (this.state === 'rise') {
        this.y = Math.max(hover, this.y - 260 * dt);
        if (this.stateT <= 0) { this.state = 'fly'; this.stateT = rand(0.8, 1.6); }
      }
      this.dir = player && player.cx < this.cx ? -1 : 1;
    }

    if (player && !player.dead && overlap(this, player, 3)) {
      const stompTol = this.type === 'dragon' ? 0.85 : 0.7;
      const fromAbove = player.vy > -60 && player.y + player.h - this.y < this.h * stompTol && player.cy < this.cy;
      if (player.star > 0) game.hitBoss(this, 'star');
      else if (fromAbove && this.vulnStomp) game.hitBoss(this, 'stomp');
      else if (fromAbove && !this.vulnStomp) { player.bounce(ENEMY_BOUNCE); }
      else player.hurt({ source: 'boss' });
    }
  }

  hit(source) {
    if (this.invuln > 0 || this.defeated) return false;
    this.hp--;
    this.invuln = 1.1;
    this.hurtFlash = 0.4;
    Sound.bossHit();
    if (this.hp <= 0) { this.defeated = true; this.t = 0; return true; }
    return false;
  }
}

/* =============================== آیتم‌ها ================================ */
export class Item extends Entity {
  constructor(type, x, y, opts = {}) {
    super(x, y, 14, 14);
    this.kind = 'item';
    this.type = type;
    this.emerge = opts.emerge ? 16 : 0;
    this.emergeFrom = y;
    this.vx = opts.vx || 0;
    this.staticItem = type === 'star' || type === 'flower';
    if (this.staticItem) { this.vx = 0; this.y = y; }
    if (type === 'coin') { this.w = 12; this.h = 14; }
    this.t = Math.random() * 3;
  }
  update(dt, game) {
    this.t += dt;
    const level = game.level;
    if (this.emerge > 0) {
      this.emerge -= 40 * dt;
      this.y -= 40 * dt;
      if (this.emerge <= 0) {
        this.staticItem = false;
        if (this.type !== 'coin' && this.type !== 'star') this.vx = this.dir || 42;
      }
      return;
    }
    if (this.type === 'coin' && this.t > 0.55) { this.removeMe = true; }
    if (this.staticItem) {
      // ثابت روی خانه؛ منتظر برداشتن
    } else {
      this.vy = Math.min(this.vy + GRAVITY * dt, MAX_FALL);
      const rx = moveX(this, this.vx * dt, level);
      if (rx.hitLeft || rx.hitRight) this.vx *= -1;
      const ry = moveY(this, this.vy * dt, level, { useSemi: true });
      if (ry.onGround) this.vy = 0;
      if (this.type === 'mushroom1up' || this.type === 'mushroom' || this.type === 'mushroomLife') {
        if (Math.abs(this.vx) < 5) this.vx = 42;
      }
      if (this.y > level.pixelH + 60) this.removeMe = true;
    }
    const player = game.player;
    if (player && !player.dead && !player.frozen && overlap(this, player, 3)) this.collect(game, player);
  }
  collect(game, player) {
    this.removeMe = true;
    switch (this.type) {
      case 'mushroom': game.pickPower('mushroom'); break;
      case 'mushroom1up': case 'mushroomLife': game.pickOneUp(); break;
      case 'flower': game.pickPower('flower'); break;
      case 'star': game.pickPower('star'); break;
      case 'feather': game.pickPower('feather'); break;
      case 'coin': game.addCoins(1, this.cx, this.cy); break;
      default: break;
    }
  }
}

/* ============================== گلولهٔ آتش ============================== */
export class Fireball extends Entity {
  constructor(x, y, dir, fromEnemy = false) {
    super(x, y, 8, 8);
    this.kind = 'fireball';
    this.dir = dir;
    this.fromEnemy = fromEnemy;
    this.t = 0;
    this.vx = dir * (fromEnemy ? 150 : 250);
    this.vy = fromEnemy ? 0 : 40;
    this.gravity = !fromEnemy;
  }
  update(dt, game) {
    this.t += dt;
    const level = game.level;
    if (this.gravity) {
      this.vy = Math.min(this.vy + GRAVITY * 0.5 * dt, 320);
      const rx = moveX(this, this.vx * dt, level);
      if (rx.hitLeft || rx.hitRight) { this.burst(game); return; }
      const ry = moveY(this, this.vy * dt, level);
      if (ry.onGround) { this.vy = -170; }
      if (ry.hitCeil) { this.burst(game); return; }
    } else {
      this.x += this.vx * dt;
      this.y += this.vy * dt;
    }
    if (this.x < 0 || this.x > level.pixelW || this.y > level.pixelH) { this.removeMe = true; return; }
    if (this.fromEnemy) {
      const p = game.player;
      if (p && !p.dead && overlap(this, p, 2)) { p.hurt({ source: 'fire' }); this.burst(game); }
    } else {
      for (const e of game.entities) {
        if ((e.kind === 'enemy' || e.kind === 'shell') && !e.removeMe && overlap(this, e, 2)) {
          game.killEnemy(e, 'fire'); this.burst(game); return;
        }
      }
      if (game.boss && game.boss.active && !game.boss.defeated && overlap(this, game.boss, 3)) {
        game.hitBoss(game.boss, 'fire');
        this.burst(game);
      }
    }
  }
  burst(game) {
    this.removeMe = true;
    game.addEffect('fireBurst', this.cx, this.cy);
  }
}

/* =========================== قطعات تخریب (آجر) ========================== */
export class Debris extends Entity {
  constructor(x, y, vx, vy, tile) {
    super(x, y, 8, 8);
    this.vx = vx; this.vy = vy;
    this.tile = tile;
    this.spin = rand(-6, 6);
    this.rot = rand(0, 6.28);
    this.t = 0;
  }
  update(dt, game) {
    this.t += dt;
    this.vy += GRAVITY * 0.75 * dt;
    this.x += this.vx * dt;
    this.y += this.vy * dt;
    this.rot += this.spin * dt;
    if (this.y > game.level.pixelH + 200) this.removeMe = true;
  }
}
