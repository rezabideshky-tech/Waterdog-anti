/* tools/harness.mjs — شبیه‌ساز DOM برای اجرای بازی در Node (بدون مرورگر)
 * رندر با @napi-rs/canvas (Skia) انجام می‌شود؛ برای تست خودکار و اسکرین‌شات.
 */
import { writeFileSync } from 'node:fs';

// وابستگی رندر؛ اگر نصب نشده باشد، پیام فارسی روشن بده (نه stack trace)
let Canvas, createCanvas, GlobalFonts;
try {
  ({ Canvas, createCanvas, GlobalFonts } = await import('@napi-rs/canvas'));
} catch (e) {
  console.error('\n❌ برای اجرای آزمون‌ها و ابزارهای توسعه، اول وابستگی‌ها را نصب کن:\n');
  console.error('     cd game/tools && npm install\n');
  process.exit(2);
}

globalThis.OffscreenCanvas = Canvas;

const listeners = new Map();

class FakeClassList {
  constructor() { this.set = new Set(); }
  add(...c) { c.forEach((x) => this.set.add(x)); }
  remove(...c) { c.forEach((x) => this.set.delete(x)); }
  toggle(c, on) { if (on === undefined) on = !this.set.has(c); on ? this.set.add(c) : this.set.delete(c); return on; }
  contains(c) { return this.set.has(c); }
}

class FakeElement {
  constructor(tag = 'div') {
    this.tagName = String(tag).toUpperCase();
    this.children = [];
    this._style = {};
    this.style = this._style;
    this.classList = new FakeClassList();
    this.dataset = {};
    this.attributes = {};
    this.textContent = '';
    this._html = '';
    this.parentNode = null;
    this._cv = this.tagName === 'CANVAS' ? createCanvas(300, 150) : null;
    this._w = 300; this._h = 150;
  }
  get width() { return this._cv ? this._cv.width : this._w; }
  set width(v) { if (this._cv) this._cv.width = +v; else this._w = +v; }
  get height() { return this._cv ? this._cv.height : this._h; }
  set height(v) { if (this._cv) this._cv.height = +v; else this._h = +v; }
  get innerHTML() { return this._html; }
  set innerHTML(v) {
    this._html = String(v); this.children = [];
    const re = /<(\w+)([^>]*)>/g; let m;
    while ((m = re.exec(this._html))) {
      const el = new FakeElement(m[1]);
      const attrs = m[2] || '';
      const idm = /id="([^"]+)"/.exec(attrs); if (idm) { el.id = idm[1]; el.attributes.id = idm[1]; }
      const cl = /class="([^"]+)"/.exec(attrs); if (cl) el.classList.add(...cl[1].split(/\s+/));
      const dre = /data-([\w-]+)="([^"]*)"/g; let dm;
      while ((dm = dre.exec(attrs))) {
        const key = dm[1].replace(/-(\w)/g, (_, c) => c.toUpperCase());
        el.dataset[key] = dm[2]; el.attributes['data-' + dm[1]] = dm[2];
      }
      this.children.push(el); el.parentNode = this;
    }
  }
  getContext(type, opts) { return this._cv ? this._cv.getContext(type, opts) : null; }
  toBuffer(kind) { return this._cv ? this._cv.toBuffer(kind === 'image/jpeg' ? 'image/jpeg' : 'image/png') : Buffer.alloc(0); }
  encode(kind = 'png') { return this.toBuffer('image/' + kind); }
  appendChild(c) { this.children.push(c); c.parentNode = this; return c; }
  removeChild(c) { this.children = this.children.filter((x) => x !== c); return c; }
  insertBefore(c, ref) { const i = this.children.indexOf(ref); i < 0 ? this.children.push(c) : this.children.splice(i, 0, c); c.parentNode = this; return c; }
  setAttribute(k, v) { this.attributes[k] = String(v); if (k === 'id') this.id = v; if (k === 'width') this.width = +v; if (k === 'height') this.height = +v; }
  getAttribute(k) { return this.attributes[k] ?? null; }
  removeAttribute(k) { delete this.attributes[k]; }
  addEventListener(t, fn) { const a = listeners.get(this) || []; a.push([t, fn]); listeners.set(this, a); }
  removeEventListener() {}
  dispatchEvent(t, ev = {}) { (listeners.get(this) || []).filter(([n]) => n === t).forEach(([, fn]) => fn({ preventDefault() {}, stopPropagation() {}, target: this, ...ev })); }
  querySelector(sel) { return findAll(this, sel)[0] || null; }
  querySelectorAll(sel) { return findAll(this, sel); }
  closest(sel) { let n = this; while (n) { if (matchesSel(n, sel)) return n; n = n.parentNode; } return null; }
  contains(el) { let n = el; while (n) { if (n === this) return true; n = n.parentNode; } return false; }
  getBoundingClientRect() { return { x: 0, y: 0, left: 0, top: 0, width: this.width || 400, height: this.height || 300 }; }
  focus() {}
  get firstChild() { return this.children[0]; }
}

export function makeCanvasEl(w = 300, h = 150) {
  const cv = createCanvas(w, h);
  cv.id = ''; cv.attributes = {}; cv.dataset = {}; cv.style = {};
  cv.classList = new FakeClassList();
  cv.appendChild = (c) => c;
  cv.addEventListener = () => {};
  cv.removeEventListener = () => {};
  cv.setAttribute = (k, v) => { cv.attributes[k] = String(v); if (k === 'width') cv.width = +v; if (k === 'height') cv.height = +v; if (k === 'id') cv.id = v; };
  cv.getAttribute = (k) => cv.attributes[k] ?? null;
  cv.focus = () => {};
  return cv;
}

export function installDom(opts = {}) {
  const doc = new FakeElement('#document');
  doc.createElement = (tag) => (String(tag).toLowerCase() === 'canvas' ? makeCanvasEl(300, 150) : new FakeElement(tag));
  doc.getElementById = (id) => findById(doc, id);
  doc.querySelector = (sel) => findAll(doc, sel)[0] || null;
  doc.querySelectorAll = (sel) => findAll(doc, sel);
  doc.readyState = 'complete';
  doc.hidden = false;
  doc.body = new FakeElement('body');
  doc.documentElement = new FakeElement('html');
  doc.head = new FakeElement('head');
  doc.addEventListener = () => {};
  doc.removeEventListener = () => {};

  const win = {
    devicePixelRatio: 1,
    innerWidth: 900, innerHeight: 520,
    addEventListener: () => {}, removeEventListener: () => {},
    requestAnimationFrame: (fn) => setTimeout(() => fn(Date.now()), 16),
    cancelAnimationFrame: (id) => clearTimeout(id),
    localStorage: makeStorage(),
    navigator: { userAgent: 'node-harness', vibrate: () => {}, maxTouchPoints: 1 },
    performance: { now: () => Number(process.hrtime.bigint() / 1000000n) },
    document: doc,
    location: { href: 'http://localhost/', search: '', protocol: 'http:' },
    matchMedia: () => ({ matches: false, addEventListener() {} }),
    setTimeout, clearTimeout, setInterval, clearInterval, console,
    screen: { width: 900, height: 520, orientation: { addEventListener() {} } },
    isSecureContext: true,
  };
  if (opts.html) {
    doc.body.innerHTML = opts.html;
    doc.innerHTML = opts.html;
  }
  win.window = win;
  win.self = win;
  globalThis.window = win;
  globalThis.document = doc;
  globalThis.devicePixelRatio = 1;
  globalThis.requestAnimationFrame = win.requestAnimationFrame;
  globalThis.cancelAnimationFrame = win.cancelAnimationFrame;
  try { globalThis.navigator = win.navigator; } catch (e) { /* فقط‌خواندنی */ }
  globalThis.localStorage = win.localStorage;
  globalThis.screen = win.screen;
  globalThis.matchMedia = win.matchMedia;
  globalThis.performance = win.performance;
  globalThis.Image = class { constructor() { this.width = 0; this.height = 0; } set src(v) { this._src = v; } };
  return { doc, win };
}

function makeStorage() {
  const m = new Map();
  return {
    getItem: (k) => (m.has(k) ? m.get(k) : null),
    setItem: (k, v) => m.set(k, String(v)),
    removeItem: (k) => m.delete(k),
    clear: () => m.clear(),
    key: (i) => [...m.keys()][i],
    get length() { return m.size; },
  };
}

function matchesSimple(el, s) {
  const m = /^(?:([\w-]+))?((?:[.#][\w-]+|\[[^\]]+\])*)$/.exec(s);
  if (!m) return false;
  const tag = m[1]; const rest = m[2] || '';
  if (tag && el.tagName !== tag.toUpperCase()) return false;
  for (const tok of rest.match(/[.#][\w-]+|\[[^\]]+\]/g) || []) {
    if (tok[0] === '.') { if (!el.classList.contains(tok.slice(1))) return false; }
    else if (tok[0] === '#') { if (el.id !== tok.slice(1) && el.attributes.id !== tok.slice(1)) return false; }
    else {
      const am = /\[([\w-]+)(?:=["']?([^\]"']*)["']?)?\]/.exec(tok);
      if (!am) return false;
      const v = el.attributes[am[1]];
      if (v === undefined || (am[2] !== undefined && v !== am[2])) return false;
    }
  }
  return true;
}

/* پشتیبانی از نسل‌ها: "div.a span" */
function matchesSel(el, sel) {
  for (const part of sel.split(',')) {
    const parts = part.trim().split(/\s+/).filter(Boolean);
    if (!parts.length) continue;
    if (!matchesSimple(el, parts[parts.length - 1])) continue;
    let ok = true;
    let node = el;
    for (let i = parts.length - 2; i >= 0 && ok; i--) {
      let p = node.parentNode;
      while (p && !matchesSimple(p, parts[i])) p = p.parentNode;
      if (!p) ok = false; else node = p;
    }
    if (ok) return true;
  }
  return false;
}

function findAll(node, sel, out = []) {
  for (const c of node.children) {
    if (matchesSel(c, sel)) out.push(c);
    findAll(c, sel, out);
  }
  return out;
}

function findById(node, id) {
  for (const c of node.children) {
    if (c.id === id || c.attributes.id === id) return c;
    const f = findById(c, id);
    if (f) return f;
  }
  return null;
}

/** ثبت فونت فارسی برای رندر متن در تست‌ها */
export function registerFonts(dir) {
  for (const f of ['Vazirmatn-700.woff2', 'Vazirmatn-400.woff2', 'Vazirmatn-900.woff2']) {
    try { GlobalFonts.registerFromPath(dir + '/' + f, 'Vazirmatn'); } catch (e) { /* فونت اختیاری */ }
  }
}

/** ذخیرهٔ canvas به‌صورت PNG (با بزرگ‌نمایی اختیاری و بدون هموارسازی) */
export function saveCanvasPng(canvas, path, scale = 1) {
  if (scale === 1) { writeFileSync(path, canvas.toBuffer('image/png')); return path; }
  const w = Math.max(1, Math.round(canvas.width * scale));
  const h = Math.max(1, Math.round(canvas.height * scale));
  const out = createCanvas(w, h);
  const g = out.getContext('2d');
  g.imageSmoothingEnabled = false;
  g.drawImage(canvas, 0, 0, w, h);
  writeFileSync(path, out.toBuffer('image/png'));
  return path;
}

export function composite(width, height, drawFn) {
  const cv = createCanvas(Math.max(1, width), Math.max(1, height));
  const g = cv.getContext('2d');
  g.imageSmoothingEnabled = false;
  drawFn(g);
  return cv;
}
