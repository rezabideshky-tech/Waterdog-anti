/* tools/fake-audio.mjs — WebAudio ساختگی و سخت‌گیر برای آزمون‌ها
 * هر پارامتر نامعتبر (NaN/Infinity/منفی) خطا می‌دهد، درست مثل مرورگر واقعی.
 * استفاده: const { calls, Ctx } = installFakeWebAudio();
 */

export const calls = { osc: 0, buffer: 0, filter: 0, gain: 0, start: 0, bufferStart: 0, resume: 0 };

function chkNum(x, what) {
  if (typeof x !== 'number' || !Number.isFinite(x)) throw new Error(`مقدار نامعتبر برای ${what}: ${x}`);
  return x;
}

export class Param {
  constructor(value = 0) { this.value = value; this.events = []; }
  setValueAtTime(v, t) {
    chkNum(v, 'setValueAtTime.value'); chkNum(t, 'setValueAtTime.time');
    if (t < 0) throw new Error('زمان منفی در AudioParam');
    this.events.push(['set', v, t]); this.value = v; return this;
  }
  linearRampToValueAtTime(v, t) {
    chkNum(v, 'linearRamp.value'); chkNum(t, 'linearRamp.time');
    this.events.push(['lin', v, t]); this.value = v; return this;
  }
  exponentialRampToValueAtTime(v, t) {
    chkNum(v, 'exponentialRamp.value'); chkNum(t, 'exponentialRamp.time');
    if (v <= 0) throw new Error('ramp نمایی به مقدار صفر/منفی — مرورگر خطا می‌دهد');
    if (t < 0) throw new Error('زمان منفی در ramp');
    this.events.push(['exp', v, t]); this.value = v; return this;
  }
  cancelScheduledValues(t) { chkNum(t, 'cancel.time'); return this; }
}

class Node {
  constructor(ctx, type) { this.ctx = ctx; this.type = type; this.outputs = []; }
  connect(dest) { this.outputs.push(dest); return dest; }
  disconnect() { this.outputs.length = 0; }
  start(t = 0) { chkNum(t, 'start.time'); this.started = t; calls.start++; if (this.type === 'buffer') calls.bufferStart++; }
  stop(t = 0) { chkNum(t, 'stop.time'); this.stopped = t; }
}

export class Ctx {
  constructor() {
    this.currentTime = 0;
    this.sampleRate = 48000;
    this.state = 'suspended';
    this.destination = { name: 'destination' };
  }
  resume() { this.state = 'running'; calls.resume++; return Promise.resolve(); }
  createGain() { calls.gain++; const n = new Node(this, 'gain'); n.gain = new Param(1); return n; }
  createOscillator() { calls.osc++; const n = new Node(this, 'osc'); n.frequency = new Param(440); n.detune = new Param(0); n.type = 'sine'; return n; }
  createBufferSource() { calls.buffer++; const n = new Node(this, 'buffer'); n.buffer = null; n.loop = false; n.playbackRate = new Param(1); return n; }
  createBiquadFilter() { calls.filter++; const n = new Node(this, 'filter'); n.frequency = new Param(350); n.Q = new Param(1); n.gain = new Param(0); n.type = 'lowpass'; return n; }
  createBuffer(channels, len, sampleRate) {
    chkNum(len, 'createBuffer.length'); chkNum(sampleRate, 'createBuffer.sampleRate');
    if (len <= 0) throw new Error('طول بافر صفر است');
    const data = new Float32Array(len);
    return { length: len, numberOfChannels: channels, sampleRate, getChannelData: () => data };
  }
}

/** AudioContext و webkitAudioContext را در محیط آزمون نصب می‌کند */
export function installFakeWebAudio() {
  globalThis.AudioContext = Ctx;
  globalThis.webkitAudioContext = Ctx;
  return { calls, Ctx };
}
