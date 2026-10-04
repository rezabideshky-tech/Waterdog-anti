/* audio.js — موسیقی و افکت‌ها با WebAudio (بدون فایل صوتی؛ همه‌چیز سنتز می‌شود)
 * دستگاه‌های ایرانی: شور، ماهور، همایون — برای حال‌وهوای ایرانی بازی.
 */

const SCALES = {
  shur:     [0, 1, 3, 5, 7, 8, 10],
  mahur:    [0, 2, 4, 5, 7, 9, 11],
  homayoun: [0, 1, 4, 5, 7, 8, 11],
  chahargah:[0, 1, 4, 5, 7, 8, 11],
  endless:  [0, 2, 3, 5, 7, 8, 11],
};

const ROOT = { shur: 55, mahur: 65.41, homayoun: 61.74, chahargah: 58.27, endless: 73.42 };

/** ملودی‌های کوتاهِ بازی: دنباله‌ای از [درجه، طول] */
const MELODIES = {
  shur: [[0, 1], [2, 1], [3, 1], [4, 2], [3, 1], [2, 1], [0, 2], [4, 1], [5, 1], [4, 1], [3, 1], [2, 2], [0, 2]],
  mahur: [[0, 1], [2, 1], [4, 1], [5, 2], [4, 1], [2, 1], [7, 2], [5, 1], [4, 1], [2, 2], [0, 2]],
  homayoun: [[0, 1], [1, 1], [3, 1], [4, 2], [3, 1], [1, 1], [0, 2], [4, 1], [5, 1], [6, 1], [5, 1], [4, 2], [3, 2]],
  endless: [[0, 1], [2, 1], [4, 1], [6, 1], [5, 1], [4, 1], [2, 1], [0, 1]],
};

export class AudioEngine {
  constructor() {
    this.ctx = null;
    this.enabledSfx = true;
    this.enabledMusic = true;
    this.musicOn = false;
    this.theme = 'shur';
    this.tempo = 116;
    this._step = 0;
    this._nextTime = 0;
    this._timer = null;
    this._master = null;
    this.sfxGain = null;
    this.musicGain = null;
    this._noise = null;
  }

  /** ساخت زمینهٔ صوتی (باید بعد از نخستین لمس کاربر باشد) */
  init() {
    if (this.ctx) return true;
    const AC = globalThis.AudioContext || globalThis.webkitAudioContext;
    if (!AC) return false;
    try {
      this.ctx = new AC();
      this._master = this.ctx.createGain();
      this._master.gain.value = 0.9;
      this._master.connect(this.ctx.destination);
      this.sfxGain = this.ctx.createGain();
      this.sfxGain.gain.value = 0.5;
      this.sfxGain.connect(this._master);
      this.musicGain = this.ctx.createGain();
      this.musicGain.gain.value = 0.22;
      this.musicGain.connect(this._master);
      // بافر نویز برای افکت‌های ضربه
      const len = this.ctx.sampleRate * 0.5;
      const buf = this.ctx.createBuffer(1, len, this.ctx.sampleRate);
      const data = buf.getChannelData(0);
      for (let i = 0; i < len; i++) data[i] = Math.random() * 2 - 1;
      this._noise = buf;
      return true;
    } catch (e) { this.ctx = null; return false; }
  }

  resume() {
    this.init();
    if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume?.();
  }

  setSfx(on) { this.enabledSfx = on; }
  setMusic(on) { this.enabledMusic = on; if (!on) this.stopMusic(); else if (this.musicOn) this.startMusic(this.theme); }

  /* ------------------------------- افکت‌ها ------------------------------- */
  tone(freq, dur = 0.12, type = 'square', vol = 0.6, when = 0, slideTo = null) {
    if (!this.enabledSfx || !this.init()) return;
    const ctx = this.ctx;
    const t = ctx.currentTime + when;
    const osc = ctx.createOscillator();
    const g = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t);
    if (slideTo) osc.frequency.exponentialRampToValueAtTime(Math.max(30, slideTo), t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(vol, t + 0.008);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    osc.connect(g); g.connect(this.sfxGain);
    osc.start(t); osc.stop(t + dur + 0.02);
  }

  noise(dur = 0.12, vol = 0.5, when = 0, hp = 400) {
    if (!this.enabledSfx || !this.init()) return;
    const ctx = this.ctx;
    const t = ctx.currentTime + when;
    const src = ctx.createBufferSource();
    src.buffer = this._noise;
    const f = ctx.createBiquadFilter();
    f.type = 'bandpass'; f.frequency.value = hp; f.Q.value = 0.9;
    const g = ctx.createGain();
    g.gain.setValueAtTime(vol, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    src.connect(f); f.connect(g); g.connect(this.sfxGain);
    src.start(t); src.stop(t + dur + 0.02);
  }

  jump()   { this.tone(420, 0.16, 'square', 0.4, 0, 780); }
  bigJump(){ this.tone(360, 0.2, 'square', 0.45, 0, 900); }
  stomp()  { this.tone(180, 0.1, 'square', 0.5, 0, 90); this.noise(0.08, 0.35, 0, 900); }
  kick()   { this.noise(0.1, 0.4, 0, 500); this.tone(140, 0.09, 'triangle', 0.4); }
  coin()   { this.tone(988, 0.07, 'square', 0.35); this.tone(1319, 0.18, 'square', 0.32, 0.06); }
  fireCoin(){ this.tone(988, 0.06, 'square', 0.3); this.tone(1568, 0.16, 'square', 0.3, 0.05); this.tone(2093, 0.12, 'triangle', 0.2, 0.1); }
  bump()   { this.tone(120, 0.08, 'square', 0.4, 0, 90); }
  brick()  { this.noise(0.22, 0.5, 0, 1200); this.tone(220, 0.12, 'square', 0.3, 0, 110); }
  powerup(){ [523, 659, 784, 1047].forEach((f, i) => this.tone(f, 0.12, 'square', 0.35, i * 0.06)); }
  powerdown(){ [784, 587, 440, 330].forEach((f, i) => this.tone(f, 0.13, 'triangle', 0.35, i * 0.07)); }
  fire()   { this.tone(720, 0.1, 'sawtooth', 0.3, 0, 240); }
  hurt()   { this.tone(300, 0.2, 'square', 0.4, 0, 120); }
  die()    { [523, 494, 440, 392, 349, 262].forEach((f, i) => this.tone(f, 0.18, 'square', 0.4, i * 0.12)); }
  oneUp()  { [659, 784, 988, 1319].forEach((f, i) => this.tone(f, 0.14, 'triangle', 0.35, i * 0.08)); }
  star()   { [784, 988, 1175, 988].forEach((f, i) => this.tone(f, 0.09, 'square', 0.28, i * 0.05)); }
  checkpoint(){ [659, 880, 1047].forEach((f, i) => this.tone(f, 0.14, 'triangle', 0.34, i * 0.09)); }
  bossHit(){ this.noise(0.16, 0.5, 0, 300); this.tone(160, 0.2, 'sawtooth', 0.4, 0, 80); }
  bossRoar(){ this.tone(90, 0.5, 'sawtooth', 0.45, 0, 60); this.noise(0.4, 0.3, 0, 200); }
  spring(){ this.tone(300, 0.18, 'triangle', 0.4, 0, 1000); }
  pauseBlip(){ this.tone(660, 0.07, 'square', 0.25); }
  victory() { [523, 659, 784, 1047, 1319].forEach((f, i) => this.tone(f, 0.2, 'square', 0.35, i * 0.13)); }
  gameOver() { [392, 349, 311, 262, 196].forEach((f, i) => this.tone(f, 0.3, 'triangle', 0.4, i * 0.2)); }
  uiClick() { this.tone(880, 0.05, 'square', 0.22); }
  uiBack()  { this.tone(440, 0.07, 'square', 0.22); }
  buy()     { [880, 1175, 1568].forEach((f, i) => this.tone(f, 0.1, 'triangle', 0.3, i * 0.06)); }
  denied()  { this.tone(220, 0.18, 'sawtooth', 0.3, 0, 160); }

  /* -------------------------------- موسیقی ------------------------------- */
  startMusic(theme = 'shur') {
    this.theme = theme in SCALES ? theme : 'shur';
    this.musicOn = true;
    if (!this.enabledMusic || !this.init()) return;
    if (this._timer) clearInterval(this._timer);
    this._nextTime = this.ctx.currentTime + 0.1;
    this._step = 0;
    this._timer = setInterval(() => this._schedule(), 60);
  }

  stopMusic() {
    this.musicOn = false;
    if (this._timer) { clearInterval(this._timer); this._timer = null; }
  }

  setTheme(theme, tempo) {
    this.theme = theme in SCALES ? theme : 'shur';
    if (tempo) this.tempo = tempo;
  }

  _schedule() {
    if (!this.ctx || !this.enabledMusic) return;
    const spb = 60 / this.tempo / 2; // هر قدم = یک هشتم
    while (this._nextTime < this.ctx.currentTime + 0.35) {
      this._playStep(this._step, this._nextTime);
      this._nextTime += spb;
      this._step++;
    }
  }

  _playStep(step, time) {
    const scale = SCALES[this.theme] || SCALES.shur;
    const root = ROOT[this.theme] || 55;
    const melody = MELODIES[this.theme] || MELODIES.shur;
    const total = melody.reduce((a, m) => a + m[1], 0);
    // ملودی
    let acc = 0, note = melody[0];
    for (const m of melody) { if (step % total >= acc && step % total < acc + m[1]) { note = m; break; } acc += m[1]; }
    const deg = note[0];
    const base = root * 4;
    const freq = base * Math.pow(2, scale[deg % scale.length] / 12 + Math.floor(deg / scale.length));
    if (step % 2 === 0) this._musicNote(freq, 0.16, 'triangle', 0.5, time);
    // باس
    if (step % 4 === 0) {
      const bf = root * 2 * Math.pow(2, scale[(step / 4 | 0) % scale.length] / 12);
      this._musicNote(bf, 0.22, 'sawtooth', 0.32, time);
    }
    // ضرب تند
    if (step % 2 === 1) this._musicPerc(0.05, time, 2600);
    if (step % 8 === 4) this._musicPerc(0.09, time, 300);
  }

  _musicNote(freq, dur, type, vol, time) {
    if (!this.enabledMusic) return;
    const ctx = this.ctx;
    const osc = ctx.createOscillator();
    const g = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    g.gain.setValueAtTime(0.0001, time);
    g.gain.linearRampToValueAtTime(vol, time + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, time + dur);
    osc.connect(g); g.connect(this.musicGain);
    osc.start(time); osc.stop(time + dur + 0.02);
  }

  _musicPerc(vol, time, hp) {
    if (!this.enabledMusic) return;
    const ctx = this.ctx;
    const src = ctx.createBufferSource();
    src.buffer = this._noise;
    const f = ctx.createBiquadFilter();
    f.type = 'highpass'; f.frequency.value = hp;
    const g = ctx.createGain();
    g.gain.setValueAtTime(vol, time);
    g.gain.exponentialRampToValueAtTime(0.0001, time + 0.08);
    src.connect(f); f.connect(g); g.connect(this.musicGain);
    src.start(time); src.stop(time + 0.1);
  }
}

export const Sound = new AudioEngine();
