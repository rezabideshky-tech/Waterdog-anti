/* fx.js — افکت‌های ذره‌ای، متن‌های شناور، لرزش دوربین و فلش */

import { rand, clamp, fa } from './utils.js';
import { TILE } from './config.js';

export class FX {
  constructor() {
    this.parts = [];
    this.texts = [];
    this.rings = [];
    this.shake = 0;
    this.flash = 0;
    this.flashColor = '#fff';
    this.slowmo = 0;
  }

  clear() { this.parts.length = 0; this.texts.length = 0; this.rings.length = 0; this.shake = 0; }

  add(type, x, y, opts = {}) {
    switch (type) {
      case 'dust':
        for (let i = 0; i < 5; i++) this.parts.push({ t: 'dust', x, y, vx: rand(-40, 40), vy: rand(-60, -10), life: 0.42, max: 0.42, r: rand(2, 3.6), c: opts.color || '#e0d6c0' });
        break;
      case 'poof':
        for (let i = 0; i < 9; i++) this.parts.push({ t: 'dust', x: x + rand(-7, 7), y: y - rand(0, 12), vx: rand(-55, 55), vy: rand(-80, -20), life: 0.5, max: 0.5, r: rand(3, 5.5), c: '#ffffff' });
        break;
      case 'fireBurst':
        for (let i = 0; i < 12; i++) this.parts.push({ t: 'spark', x, y, vx: rand(-120, 120), vy: rand(-140, 40), life: 0.35, max: 0.35, r: rand(2, 4), c: i % 3 ? '#ffb02b' : '#ff5a2b' });
        break;
      case 'featherBurst':
        for (let i = 0; i < 10; i++) this.parts.push({ t: 'feather', x, y, vx: rand(-70, 70), vy: rand(-60, 30), life: 0.7, max: 0.7, r: rand(2, 3.4), c: i % 2 ? '#8ce0ff' : '#dff6ff', rot: rand(0, 6.28) });
        break;
      case 'sparkle':
        for (let i = 0; i < 6; i++) this.parts.push({ t: 'spark', x, y, vx: rand(-60, 60), vy: rand(-90, -20), life: 0.45, max: 0.45, r: rand(1.5, 3), c: opts.color || '#ffe37a' });
        break;
      case 'splash':
        for (let i = 0; i < 12; i++) this.parts.push({ t: 'dust', x, y, vx: rand(-90, 90), vy: rand(-190, -60), life: 0.6, max: 0.6, r: rand(2, 3.6), c: '#8fd8ff', gravity: 500 });
        break;
      case 'shock':
        this.rings.push({ x, y, r: 6, max: opts.max || 60, life: 0.5, maxLife: 0.5, c: opts.color || '#ffd166', w: 3 });
        this.shake = Math.max(this.shake, opts.shake ?? 4);
        break;
      case 'starTrail':
        this.parts.push({ t: 'star', x, y, vx: rand(-20, 20), vy: rand(-40, -10), life: 0.5, max: 0.5, r: rand(2, 3.5), c: opts.color || '#fff2a8', rot: rand(0, 6.28) });
        break;
      case 'brick':
        for (let i = 0; i < 4; i++) {
          this.parts.push({
            t: 'brick', x, y, vx: (i % 2 ? 1 : -1) * rand(40, 110), vy: rand(-260, -150),
            life: 2, max: 2, r: 4, c: opts.color || '#d9a066', rot: rand(0, 6.28), spin: rand(-8, 8), gravity: 900,
          });
        }
        this.shake = Math.max(this.shake, 2.5);
        break;
      case 'confetti':
        for (let i = 0; i < 40; i++) {
          this.parts.push({
            t: 'brick', x: x + rand(-60, 60), y: y - rand(0, 30), vx: rand(-70, 70), vy: rand(-220, -80),
            life: 2.4, max: 2.4, r: 3, c: ['#ffd166', '#ef476f', '#06d6a0', '#118ab2', '#ffffff'][i % 5],
            rot: rand(0, 6.28), spin: rand(-10, 10), gravity: 420,
          });
        }
        break;
      case 'text':
        this.texts.push({ x, y, text: opts.text || '', life: opts.life || 0.9, max: opts.life || 0.9, c: opts.color || '#ffffff', size: opts.size || 10, vy: opts.vy ?? -34 });
        break;
      case 'coinPop':
        this.parts.push({ t: 'coin', x, y, vx: 0, vy: -230, life: 0.55, max: 0.55, r: 6, c: '#f5c542', gravity: 700 });
        this.add('text', x + 4, y - 14, { text: opts.text || '+۱', color: '#ffe37a', size: 9 });
        break;
      case 'blockCoin':
        this.parts.push({ t: 'coin', x, y, vx: 0, vy: -300, life: 0.5, max: 0.5, r: 6, c: '#f5c542', gravity: 900 });
        break;
      default: break;
    }
  }

  update(dt) {
    this.shake = Math.max(0, this.shake - dt * 14);
    this.flash = Math.max(0, this.flash - dt * 3.4);
    for (let i = this.parts.length - 1; i >= 0; i--) {
      const p = this.parts[i];
      p.life -= dt;
      if (p.life <= 0) { this.parts.splice(i, 1); continue; }
      p.x += p.vx * dt; p.y += p.vy * dt;
      if (p.gravity) p.vy += p.gravity * dt;
      if (p.spin) p.rot += p.spin * dt;
    }
    for (let i = this.texts.length - 1; i >= 0; i--) {
      const t = this.texts[i];
      t.life -= dt; t.y += t.vy * dt;
      if (t.life <= 0) this.texts.splice(i, 1);
    }
    for (let i = this.rings.length - 1; i >= 0; i--) {
      const r = this.rings[i];
      r.life -= dt;
      r.r = r.max * (1 - r.life / r.maxLife);
      if (r.life <= 0) this.rings.splice(i, 1);
    }
  }

  draw(g, cam) {
    for (const p of this.parts) {
      const a = clamp(p.life / p.max, 0, 1);
      g.globalAlpha = a;
      g.fillStyle = p.c;
      const x = p.x - cam.x, y = p.y - cam.y;
      if (p.t === 'brick') {
        g.save(); g.translate(x, y); g.rotate(p.rot); g.fillRect(-p.r, -p.r, p.r * 2, p.r * 2); g.restore();
      } else if (p.t === 'coin') {
        const sq = Math.abs(Math.sin(p.life * 22));
        g.beginPath(); g.ellipse(x, y, Math.max(1, p.r * sq), p.r, 0, 0, Math.PI * 2); g.fill();
        g.fillStyle = '#c8901c';
        g.beginPath(); g.ellipse(x, y, Math.max(0.5, p.r * sq * 0.5), p.r * 0.6, 0, 0, Math.PI * 2); g.fill();
      } else if (p.t === 'star' || p.t === 'feather') {
        g.save(); g.translate(x, y); g.rotate(p.rot || p.life * 6);
        g.fillRect(-p.r, -1, p.r * 2, 2); g.fillRect(-1, -p.r, 2, p.r * 2);
        g.restore();
      } else {
        g.beginPath(); g.arc(x, y, p.r * (0.6 + 0.4 * a), 0, Math.PI * 2); g.fill();
      }
    }
    g.globalAlpha = 1;
    for (const r of this.rings) {
      g.globalAlpha = clamp(r.life / r.maxLife, 0, 1) * 0.8;
      g.strokeStyle = r.c; g.lineWidth = r.w;
      g.beginPath(); g.arc(r.x - cam.x, r.y - cam.y, r.r, 0, Math.PI * 2); g.stroke();
    }
    g.globalAlpha = 1;
    for (const t of this.texts) {
      const a = clamp(t.life / t.max, 0, 1);
      g.globalAlpha = a;
      g.font = `bold ${t.size}px Vazirmatn, system-ui, sans-serif`;
      g.textAlign = 'center';
      g.fillStyle = 'rgba(0,0,0,0.55)';
      g.fillText(t.text, t.x - cam.x + 1, t.y - cam.y + 1);
      g.fillStyle = t.c;
      g.fillText(t.text, t.x - cam.x, t.y - cam.y);
    }
    g.globalAlpha = 1;
    g.textAlign = 'left';
  }

  drawFlash(g, w, h) {
    if (this.flash <= 0) return;
    g.globalAlpha = clamp(this.flash, 0, 0.85);
    g.fillStyle = this.flashColor;
    g.fillRect(0, 0, w, h);
    g.globalAlpha = 1;
  }
}
