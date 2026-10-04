/* game.js — هستهٔ اجرای مرحله: بازیکن، دشمنان، دوربین، امتیاز و پایان مرحله */

import {
  TILE, LEVEL_TIME, POWER, STAR_TIME, FEATHER_TIME, CAM_LERP, DEATH_Y, FIXED_DT, LEVELS, THEMES,
} from './config.js';
import { buildLevel, T, tileAt, setTile, BUMPABLE, isSolid, isHazard } from './levels.js';
import { Player } from './player.js';
import { Enemy, Boss, Item, Fireball, Debris, Shell, Entity } from './entities.js';
import { FX } from './fx.js';
import { Sound } from './audio.js';
import { clamp, rand, fa, pick } from './utils.js';

export class World {
  constructor(opts) {
    this.opts = opts;
    this.save = opts.save;
    this.character = opts.character;
    this.endless = !!opts.endless;
    this.levelIndex = opts.levelIndex ?? 0;
    const baseDef = LEVELS[this.levelIndex];
    if (this.endless) {
      const def = { ...baseDef, length: Math.floor(baseDef.length * 2.6), diff: Math.min(8, baseDef.diff + 3), boss: null, hazard: ['void'], enemies: ['ladybug', 'turtle', 'bee', 'ghost', 'spikeball'] };
      LEVELS.push(def);
      this.level = buildLevel(LEVELS.length - 1, {});
      LEVELS.pop();
      this.level.name = 'دوی بی‌پایان';
    } else {
      this.level = buildLevel(this.levelIndex, {});
    }
    this.fx = new FX();
    this.entities = [];
    this.boss = null;
    this.fireballs = [];
    this.coins = 0;
    this.score = 0;
    this.lives = 3 + (this.save?.livesBonus || 0) + (this.perk('life') ? 1 : 0);
    if (this.endless) this.lives = 3;
    this.time = this.endless ? 200 : this.level.time;
    this.timeAcc = 0;
    this.state = 'playing';       // playing | dying | complete | gameover
    this.completed = false;
    this.checkpoint = null;
    this.hurtCount = 0;
    this.kills = 0;
    this.mushrooms = 0;
    this.maxCombo = 0;
    this.paused = false;
    this.camera = { x: 0, y: 0, targetX: 0, targetY: 0 };
    this.transition = 0;
    this.starKills = 0;
    this.onEvent = opts.onEvent || (() => {});
    this.spawnAll();
    this.respawnPoint = { ...this.level.spawn };
    this.player = new Player(this, this.respawnPoint.x, this.respawnPoint.y, this.character);
    this.updateCamera(1);
  }

  perk(id) { return this.save?.owned?.includes(id); }

  spawnAll() {
    for (const e of this.level.entities) {
      switch (e.type) {
        case 'enemy': {
          if (e.enemy === 'plant' || !e.enemy) {
            const en = new Enemy(e.enemy || 'ladybug', e.x, e.y, e);
            this.entities.push(en);
          } else if (e.enemy === 'ghost' || e.enemy === 'bee') {
            this.entities.push(new Enemy(e.enemy, e.x, e.y, e));
          } else {
            const en = new Enemy(e.enemy, e.x, e.y, e);
            en.falling = false;
            this.entities.push(en);
          }
          break;
        }
        case 'gift': {
          const item = new Item(e.item === 'fire' ? 'flower' : e.item === 'star' ? 'star' : e.item === 'feather' ? 'feather' : 'mushroom', e.x, e.y);
          item.staticItem = true;
          this.entities.push(item);
          break;
        }
        case 'boss': {
          this.boss = new Boss(e.boss, e.x, e.y, e.arena);
          this.entities.push(this.boss);
          break;
        }
        case 'flag': this.flag = e; break;
        case 'checkpoint': this.checkpoints = (this.checkpoints || []).concat([e]); break;
        default: break;
      }
    }
  }

  /* ------------------------------ به‌روزرسانی ----------------------------- */
  update(dt, input) {
    this.input = input;
    if (this.paused) return;
    this.fx.update(dt);
    if (this.state === 'playing') {
      this.timeAcc += dt;
      this.time = Math.max(0, this.time - dt);
      if (this.time <= 0) { this.player.hurt({ source: 'time' }); this.time = 999; }
    }
    const player = this.player;
    player.update(dt, input);

    for (let i = this.entities.length - 1; i >= 0; i--) {
      const e = this.entities[i];
      e.update(dt, this);
      if (e.removeMe) this.entities.splice(i, 1);
    }
    for (let i = this.fireballs.length - 1; i >= 0; i--) {
      const f = this.fireballs[i];
      f.update(dt, this);
      if (f.removeMe) this.fireballs.splice(i, 1);
    }
    // خانه‌های تخریب‌شدنی زیر پای بازیکن
    this.updateCrumble(dt);
    // ایست‌های بازگشت (پرچم میانی)
    for (const cp of (this.checkpoints || [])) {
      if (!cp.taken && Math.abs(this.player.cx - cp.x) < 18 && Math.abs(this.player.cy - (cp.y + 40)) < 70) {
        cp.taken = true;
        this.respawnPoint = { x: cp.x - 8, y: cp.y + 62 };
        this.fx.add('text', cp.x, cp.y - 8, { text: 'ایستگاه ذخیره!', color: '#8ce0ff', size: 10, life: 1.4 });
        this.fx.add('sparkle', cp.x, cp.y);
        Sound.checkpoint();
        this.onEvent('checkpoint', { cp });
      }
    }
    // رسیدن به پرچم
    if (this.flag && !this.completed && player.x + player.w > this.flag.x - 2 && player.x < this.flag.x + 12) {
      if (this.boss && this.level.boss && !this.boss.defeated) {
        // پرچم پایان تا شکست رئیس قفل است (رئیس قابل دور زدن نیست)
        if (this.flagLockAt === undefined || this.time < this.flagLockAt - 2.4) {
          this.flagLockAt = this.time;
          this.onEvent('flagLocked', { boss: this.boss.type });
        }
      } else {
        this.completeLevel(true, player);
      }
    }
    this.updateCamera(dt);
    if (player.dead && this.state === 'dying') this.updateDeath(dt);
  }

  updateCrumble(dt) {
    const p = this.player;
    const lvl = this.level;
    if (!p) return;
    const tx = Math.floor(p.cx / TILE);
    const ty = Math.floor((p.y + p.h + 1) / TILE);
    for (let x = tx - 1; x <= tx + 1; x++) {
      if (tileAt(lvl, x, ty) === T.CRUMBLE) {
        const key = x + ',' + ty;
        this.crumbleTimers = this.crumbleTimers || new Map();
        const t = (this.crumbleTimers.get(key) || 0) + dt;
        this.crumbleTimers.set(key, t);
        if (t > 0.45 && p.onGround) {
          setTile(lvl, x, ty, T.EMPTY);
          this.fx.add('brick', x * TILE + 8, ty * TILE + 8, { color: this.level.themeDef.stone });
          Sound.brick();
          this.crumbleTimers.delete(key);
        }
      }
    }
  }

  updateDeath(dt) {
    if (this.player.deathTimer > 1.1) {
      this.lives--;
      if (this.lives <= 0) {
        this.lives = 0;
        if (this.state !== 'gameover') { this.state = 'gameover'; this.onEvent('gameover'); }
      } else {
        this.respawn();
      }
    }
  }

  /* نزدیک‌ترین نقطهٔ امن به نقطهٔ تولد: هرگز روی خار/آب/دیوار ظاهر نشو */
  findSafeSpawn(x, y) {
    const level = this.level;
    const rowOf = (py) => Math.round(py / TILE);
    const colValid = (tx, ty) => {
      // زیر پا باید جامد باشد (نه خار/آب) و دو خانهٔ تن بازیکن خالی و بی‌خطر
      if (isHazard(level, tx, ty)) return false;
      if (isHazard(level, tx, ty - 1)) return false;
      if (isSolid(level, tx, ty) || isSolid(level, tx, ty - 1) || isSolid(level, tx, ty - 2)) return false;
      const below = tileAt(level, tx, ty + 1);
      if (below === T.CRUMBLE) return false;
      return isSolid(level, tx, ty + 1) || tileAt(level, tx, ty + 1) === T.SEMI;
    };
    const baseCol = clamp(Math.round(x / TILE), 1, level.w - 2);
    const baseRow = clamp(rowOf(y), 2, level.h - 2);
    for (let d = 0; d <= 8; d++) {
      for (const col of (d === 0 ? [baseCol] : [baseCol - d, baseCol + d])) {
        if (col < 1 || col > level.w - 2) continue;
        for (let dy = 0; dy <= 8; dy++) {
          for (const row of (dy === 0 ? [baseRow] : [baseRow - dy, baseRow + dy])) {
            if (row < 2 || row > level.h - 2) continue;
            if (colValid(col, row)) return { x: col * TILE + 4, y: (row - 1) * TILE };
          }
        }
      }
    }
    return { ...level.spawn };
  }

  respawn() {
    const level = this.level;
    // پاک‌کردن دشمنان نزدیک محل تولد
    const sp = this.findSafeSpawn(this.respawnPoint.x, this.respawnPoint.y);
    for (const e of this.entities) {
      if ((e.kind === 'enemy' || e.kind === 'shell') && Math.abs(e.x - sp.x) < 220 && e.kind === 'enemy') { e.x = (e.startX ?? e.x); }
    }
    this.player = new Player(this, sp.x, sp.y, this.character);
    this.player.power = POWER.SMALL;
    this.player.invuln = 2.2;
    this.time = Math.max(60, this.time);
    this.state = 'playing';
    this.fireballs.length = 0;
    // نبرد رئیس: آسیبی که به رئیس زده شده حفظ می‌شود تا نبرد برای موبایل منصفانه بماند
    if (this.boss && this.boss.active && !this.boss.defeated) {
      this.boss.hp = Math.max(1, this.boss.hp);
      this.boss.x = this.boss.arena.x0 + 20;
      this.boss.state = this.boss.type === 'div' ? 'idle' : 'fly';
      this.boss.stateT = 0.6;
      this.boss.invuln = 0;
      this.boss.groundHold = 0;
    }
  }

  /* -------------------------------- دوربین ------------------------------- */
  updateCamera(dt, snap = false) {
    const p = this.player;
    const view = this.opts.view || { w: 400, h: 240 };
    const lookAhead = clamp(p.vx * 0.35, -60, 60);
    this.camera.targetX = clamp(p.cx + lookAhead - view.w / 2, 0, Math.max(0, this.level.pixelW - view.w));
    this.camera.targetY = clamp(p.cy - view.h * 0.62, -40, Math.max(0, this.level.pixelH - view.h));
    const k = snap ? 1 : clamp(CAM_LERP * dt, 0, 1);
    const ok = Number.isFinite(this.camera.targetX) && Number.isFinite(this.camera.targetY);
    if (!ok) { this.camera.targetX = 0; this.camera.targetY = 0; }
    this.camera.x += (this.camera.targetX - this.camera.x) * k;
    this.camera.y += (this.camera.targetY - this.camera.y) * k;
    if (!Number.isFinite(this.camera.x)) this.camera.x = 0;
    if (!Number.isFinite(this.camera.y)) this.camera.y = 0;
    if (this.fx.shake > 0) {
      this.camera.sx = rand(-1, 1) * this.fx.shake;
      this.camera.sy = rand(-1, 1) * this.fx.shake;
    } else { this.camera.sx = 0; this.camera.sy = 0; }
  }

  /* -------------------------------- کمکی‌ها ------------------------------ */
  addEffect(type, x, y, opts) { this.fx.add(type, x, y, opts); }
  addScore(n, x, y, showText = true) {
    this.score += n;
    if (showText && x !== undefined) this.fx.add('text', x, y, { text: fa(n), color: '#ffffff', size: 9 });
  }
  addCoins(n, x, y) {
    this.coins += n;
    this.score += n * 100;
    if (x !== undefined) this.fx.add('coinPop', x, y, { text: '+۱۰۰' });
    Sound.coin();
    this.onEvent('coin', { coins: this.coins });
  }
  spendCoins(n) {
    this.coins = Math.max(0, this.coins - n);
  }
  countPlayerFireballs() { return this.fireballs.filter((f) => !f.fromEnemy).length; }
  spawnFireball(x, y, dir) { this.fireballs.push(new Fireball(x, y, dir, false)); }
  spawnEnemyFireball(x, y, dir) { this.fireballs.push(new Fireball(x, y, dir, true)); }

  stompEnemy(enemy, player) {
    if (enemy.def.shellable) {
      // تبدیل به لاک
      const shell = new Shell(enemy.x, enemy.y + enemy.h - 12, Math.sign(player.cx - enemy.cx) || 1);
      this.entities.push(shell);
      enemy.removeMe = true;
      player.bounce();
      this.addScore(100, enemy.cx, enemy.y);
      Sound.stomp();
    } else {
      enemy.squash();
      player.bounce();
      this.kills++;
      this.addScore(enemy.def.points, enemy.cx, enemy.y);
      this.fx.add('poof', enemy.cx, enemy.cy);
      Sound.stomp();
    }
    this.onEvent('stomp', { enemy });
  }

  killEnemy(enemy, source) {
    if (enemy.removeMe && !enemy.flippedDeath) return;
    this.kills++;
    this.addScore(enemy.def?.points || 200, enemy.cx, enemy.y);
    if (source === 'star') {
      this.starKills++;
      this.fx.add('sparkle', enemy.cx, enemy.cy, { color: '#fff2a8' });
    } else {
      this.fx.add('poof', enemy.cx, enemy.cy);
    }
    if (typeof enemy.flipDeath === 'function') enemy.flipDeath();
    else if (typeof enemy.poof === 'function') enemy.poof();
    else enemy.removeMe = true;
    Sound.kick();
    this.onEvent('kill', { enemy, source });
  }

  hitBoss(boss, source) {
    if (source === 'stomp' || source === 'fire' || source === 'shell' || source === 'star') {
      const killed = boss.hit(source);
      this.addScore(500, boss.cx, boss.y);
      this.fx.add('shock', boss.cx, boss.cy, { color: '#ffd166', max: 46 });
      if (source === 'stomp') this.player.bounce(-330);
      if (killed) {
        this.fx.add('confetti', boss.cx, boss.cy);
        this.fx.add('shock', boss.cx, boss.cy, { max: 90, color: '#ffffff' });
        this.onBossDefeated();
      }
    }
  }

  onBossDefeated() {
    const b = this.boss;
    if (!b || b.reported) return;
    b.reported = true;
    this.addScore(4000, b.cx, b.y, true);
    this.fx.add('text', b.cx, b.y - 12, { text: b.type === 'div' ? 'دیو شکست خورد!' : 'اژدها شکست خورد!', color: '#ffd166', size: 12, life: 1.8 });
    Sound.victory();
    this.onEvent('bossDefeated', { boss: b });
  }

  /** ضربه از پایین به خانه‌های بالای سر */
  onHeadBump({ tx, ty, tile }, player) {
    if (!BUMPABLE.has(tile)) { Sound.bump(); return; }
    const above = tileAt(this.level, tx, ty - 1);
    if (tile === T.QUESTION || tile === T.BRICK_COIN) {
      setTile(this.level, tx, ty, T.USED);
      const isCoinBrick = tile === T.BRICK_COIN;
      if (isCoinBrick) {
        this.addCoins(1, tx * TILE + 8, ty * TILE);
        this.fx.add('blockCoin', tx * TILE + 8, ty * TILE - 4);
        player.stompCount += 0;
      } else {
        this.popItemFromBlock(tx, ty);
      }
      this.fx.add('shock', tx * TILE + 8, ty * TILE + 4, { max: 20, color: '#ffe6a8', shake: 2 });
      Sound.bump();
    } else if (tile === T.CRUMBLE) {
      setTile(this.level, tx, ty, T.EMPTY);
      this.fx.add('brick', tx * TILE + 8, ty * TILE + 8, { color: this.level.themeDef.stone });
      Sound.brick();
    } else if (tile === T.HIDDEN) {
      setTile(this.level, tx, ty, T.USED);
      this.addCoins(3, tx * TILE + 8, ty * TILE);
      Sound.brick();
    } else if (tile === T.BRICK) {
      if (player.big) {
        setTile(this.level, tx, ty, T.EMPTY);
        for (let i = 0; i < 4; i++) this.entities.push(new Debris(tx * TILE + (i % 2) * 8, ty * TILE + Math.floor(i / 2) * 8, (i % 2 ? 1 : -1) * rand(40, 110), rand(-260, -150), tile));
        this.fx.add('brick', tx * TILE + 8, ty * TILE + 8);
        Sound.brick();
      } else {
        this.fx.add('shock', tx * TILE + 8, ty * TILE + 4, { max: 18, color: '#e8dcc0', shake: 1.6 });
        Sound.bump();
      }
    }
  }

  popItemFromBlock(tx, ty) {
    const roll = Math.random();
    const kind = roll < 0.05 ? 'star' : roll < 0.2 ? 'flower' : roll < 0.28 ? 'mushroom1up' : 'mushroom';
    const item = new Item(kind, tx * TILE + 1, ty * TILE - 16, { emerge: true });
    item.emerge = 16;
    item.emergeFrom = ty * TILE;
    item.staticItem = true;
    this.entities.push(item);
    this.fx.add('sparkle', tx * TILE + 8, ty * TILE);
    Sound.powerup();
  }

  pickPower(kind) {
    const p = this.player;
    switch (kind) {
      case 'mushroom':
        this.mushrooms++;
        this.addScore(1000, p.cx, p.y - 6);
        this.fx.add('sparkle', p.cx, p.cy);
        if (p.power === POWER.SMALL) { p.setPower(POWER.BIG); Sound.powerup(); }
        else { Sound.coin(); }
        this.onEvent('mushroom', {});
        break;
      case 'flower':
        if (p.power < POWER.FIRE) { p.setPower(POWER.FIRE); Sound.powerup(); }
        this.addScore(1000, p.cx, p.y - 6);
        this.fx.add('sparkle', p.cx, p.cy, { color: '#ffb02b' });
        break;
      case 'star':
        p.star = STAR_TIME;
        this.addScore(1000, p.cx, p.y - 6);
        Sound.star();
        this.fx.add('text', p.cx, p.y - 16, { text: 'ستارهٔ اقبال!', color: '#fff2a8', size: 11, life: 1.4 });
        break;
      case 'feather':
        p.feather = FEATHER_TIME;
        this.addScore(1000, p.cx, p.y - 6);
        Sound.oneUp();
        this.fx.add('featherBurst', p.cx, p.cy);
        break;
      default: break;
    }
  }

  pickOneUp() {
    this.lives++;
    this.addScore(2000, this.player.cx, this.player.y - 6);
    Sound.oneUp();
    this.fx.add('text', this.player.cx, this.player.y - 16, { text: '۱ جان اضافه!', color: '#8ce0ff', size: 11, life: 1.4 });
    this.onEvent('oneup', {});
  }

  kickShell() {
    this.addScore(200);
    this.fx.add('dust', this.player.cx, this.player.y + this.player.h);
  }

  onLandingCombo(player) {
    this.maxCombo = Math.max(this.maxCombo, player.combo);
    if (player.combo >= 5) this.onEvent('combo5', { combo: player.combo });
    if (player.combo > 2) {
      this.addScore(player.combo * 100, player.cx, player.y - 8);
      this.fx.add('text', player.cx, player.y - 18, { text: `${fa(player.combo)} ضربه پیاپی!`, color: '#ffd166', size: 10, life: 1.2 });
    }
  }

  onPlayerHurt(info, died) {
    this.hurtCount++;
    if (died) this.fx.add('shock', this.player.cx, this.player.cy, { max: 40, color: '#ff8a80' });
    this.onEvent('hurt', { info, died });
  }

  onPlayerDeath(fell) {
    this.state = 'dying';
    this.player.dead = true;
    this.fx.add('shock', this.player.cx, this.player.cy, { max: 34, color: '#ffffff' });
    this.onEvent('death', { fell });
  }

  completeLevel(touchedFlag, player) {
    if (this.completed) return;
    this.completed = true;
    this.state = 'complete';
    this.player.frozen = true;
    player.startFlagSlide(this.flag);
    this.onEvent('flag', {});
  }

  onFlagLanded() {
    // امتیاز پایان مرحله بر اساس زمان و سکه
    const timeBonus = Math.floor(Math.max(0, this.time) * 10);
    this.addScore(timeBonus);
    this.state = 'complete';
    const stars = this.computeStars();
    Sound.victory();
    this.onEvent('levelComplete', {
      score: this.score + timeBonus, coins: this.coins, timeBonus, stars, kills: this.kills,
      hurt: this.hurtCount, mushrooms: this.mushrooms, bossDefeated: this.boss ? this.boss.defeated : true,
    });
  }

  computeStars() {
    let s = 1;
    if (this.coins >= Math.max(12, Math.floor(LEVELS[this.levelIndex].coins * 0.5))) s++;
    if (this.hurtCount === 0 && (!this.boss || this.boss.defeated)) s++;
    return Math.min(3, s);
  }

  onEventNoop() {}
}
