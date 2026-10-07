/* input.js — ورودی بازی: کلید، ماوس و لمس (دکمه‌های شیشه‌ای برای موبایل) */

const KEYMAP = {
  ArrowLeft: 'left', KeyA: 'left',
  ArrowRight: 'right', KeyD: 'right',
  ArrowUp: 'up', KeyW: 'up',
  ArrowDown: 'down', KeyS: 'down',
  Space: 'jump', KeyZ: 'jump', KeyJ: 'jump',
  ShiftLeft: 'run', ShiftRight: 'run', KeyX: 'run', KeyK: 'run',
  KeyL: 'fire', KeyC: 'fire',
  Enter: 'start', Escape: 'pause', KeyP: 'pause',
  KeyM: 'mute',
};

export class Input {
  constructor(root) {
    this.root = root || (typeof document !== 'undefined' ? document.body : null);
    this.state = { left: false, right: false, up: false, down: false, jump: false, run: false, fire: false, start: false, pause: false, mute: false };
    this.pressed = {};      // لبهٔ فشردن در همین فریم
    this.released = {};
    this.prev = {};
    this.anyInput = false;
    this.pointer = { x: 0, y: 0, down: false, justDown: false };
    this._touchButtons = [];
    this._bindKeyboard();
    this._bindPointer();
  }

  _bindKeyboard() {
    if (typeof window === 'undefined') return;
    window.addEventListener('keydown', (e) => {
      const act = KEYMAP[e.code];
      if (!act) return;
      if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Space'].includes(e.code)) e.preventDefault();
      this.state[act] = true;
      this.anyInput = true;
    });
    window.addEventListener('keyup', (e) => {
      const act = KEYMAP[e.code];
      if (!act) return;
      this.state[act] = false;
    });
    window.addEventListener('blur', () => {
      for (const k in this.state) this.state[k] = false;
    });
  }

  _bindPointer() {
    if (typeof window === 'undefined') return;
    const upd = (e) => {
      const t = e.touches && e.touches[0];
      this.pointer.x = (t ? t.clientX : e.clientX) || 0;
      this.pointer.y = (t ? t.clientY : e.clientY) || 0;
    };
    window.addEventListener('pointerdown', (e) => { upd(e); this.pointer.down = true; this.pointer.justDown = true; }, { passive: true });
    window.addEventListener('pointerup', (e) => { upd(e); this.pointer.down = false; }, { passive: true });
    window.addEventListener('pointermove', upd, { passive: true });
  }

  /** اتصال دکمه‌های لمسی صفحه (عنصرهای DOM در index.html) */
  bindTouchButtons(selectors) {
    if (typeof document === 'undefined') return;
    const map = selectors || {
      '#btn-left': 'left', '#btn-right': 'right', '#btn-jump': 'jump', '#btn-run': 'run', '#btn-fire': 'fire',
    };
    for (const sel in map) {
      const el = document.querySelector(sel);
      if (!el) continue;
      const act = map[sel];
      const on = (e) => { e.preventDefault(); e.stopPropagation?.(); this.state[act] = true; this.anyInput = true; el.classList.add('active'); };
      const off = (e) => { e.preventDefault(); e.stopPropagation?.(); this.state[act] = false; el.classList.remove('active'); };
      el.addEventListener('touchstart', on, { passive: false });
      el.addEventListener('touchend', off, { passive: false });
      el.addEventListener('touchcancel', off, { passive: false });
      el.addEventListener('mousedown', on);
      el.addEventListener('mouseup', off);
      el.addEventListener('mouseleave', off);
      this._touchButtons.push({ el, act });
    }
    // کشیدن انگشت روی دکمه‌های جهت‌دار
    const pad = document.getElementById('touch-controls');
    if (pad) {
      pad.addEventListener('touchstart', (e) => e.preventDefault(), { passive: false });
      pad.addEventListener('touchmove', (e) => e.preventDefault(), { passive: false });
    }
  }

  /** لبه‌های فشردن/رهاکردن را برای همین فریم محاسبه می‌کند */
  poll() {
    for (const k in this.state) {
      const now = !!this.state[k];
      const was = !!this.prev[k];
      this.pressed[k] = now && !was;
      this.released[k] = !now && was;
      this.prev[k] = now;
    }
    this.pressed.jump = this.pressed.jump || this.pressed.up;
    this.pointer.justDown = this.pointer.justDown || false;
    const jd = this.pointer.justDown;
    this.pointer.justDown = false;
    return jd;
  }

  /** جهت افقی به‌صورت عدد */
  get axis() { return (this.state.right ? 1 : 0) - (this.state.left ? 1 : 0); }
  get jumpHeld() { return this.state.jump; }
  get runHeld() { return this.state.run || this.state.fire; }

  /** پاک‌کردن حالت‌ها (هنگام مکث یا تعویض صفحه) */
  clear() {
    for (const k in this.state) this.state[k] = false;
    for (const k in this.prev) this.prev[k] = false;
  }
}
