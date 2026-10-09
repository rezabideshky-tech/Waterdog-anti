"""Synthesise every sound effect and the two music stems. No samples, no licence risk.

Run:  python3 tools/gen_audio.py   (needs numpy)

Output: assets/audio/*.wav  - 22050 Hz, mono, 16-bit PCM (uncompressed on purpose: Godot's
WAV importer would otherwise QOA-compress and break byte-based loop maths, LESSONS L34).

Music: two stems that loop seamlessly over the same length.
  music_pad.wav   - warm chord pad (always on)
  music_pulse.wav - rhythmic layer, faded in as the run intensifies (LESSONS L16)
"""
from pathlib import Path
import wave
import numpy as np

SR = 22050
OUT = Path(__file__).resolve().parent.parent / "assets" / "audio"
rng = np.random.default_rng(7)


def env(n: int, attack: float, decay: float) -> np.ndarray:
    t = np.arange(n) / SR
    a = np.minimum(t / max(attack, 1e-4), 1.0)
    d = np.exp(-t / max(decay, 1e-4))
    return a * d


def sweep(f0: float, f1: float, dur: float) -> np.ndarray:
    n = int(SR * dur)
    t = np.arange(n) / SR
    f = f0 + (f1 - f0) * (t / dur)
    phase = 2 * np.pi * np.cumsum(f) / SR
    return np.sin(phase)


def noise(dur: float) -> np.ndarray:
    return rng.uniform(-1, 1, int(SR * dur))


def lowpass(x: np.ndarray, k: float) -> np.ndarray:
    y = np.zeros_like(x)
    acc = 0.0
    for i in range(len(x)):
        acc += k * (x[i] - acc)
        y[i] = acc
    return y


def write(name: str, x: np.ndarray, peak: float = 0.8) -> None:
    x = np.asarray(x, dtype=np.float64)
    m = np.max(np.abs(x)) or 1.0
    x = x / m * peak
    pcm = (np.clip(x, -1, 1) * 32767).astype("<i2")
    OUT.mkdir(parents=True, exist_ok=True)
    with wave.open(str(OUT / name), "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(pcm.tobytes())
    print(name, len(pcm), "frames")


def tone(freq: float, dur: float, decay: float, harmonics=(1.0,)) -> np.ndarray:
    n = int(SR * dur)
    t = np.arange(n) / SR
    s = np.zeros(n)
    for i, h in enumerate(harmonics, start=1):
        s += h * np.sin(2 * np.pi * freq * i * t)
    return s * env(n, 0.005, decay)


def sfx() -> None:
    # block: bright upward blip
    write("sfx_block.wav", sweep(880, 1320, 0.12) * env(int(SR * 0.12), 0.003, 0.05))
    # armored hit: metallic clang with inharmonic partials
    dur = 0.35
    clang = tone(420, dur, 0.12, (1.0, 0.6, 0.35)) + 0.5 * tone(1180, dur, 0.07) + 0.25 * tone(1730, dur, 0.05)
    write("sfx_armor.wav", clang)
    # leak: low thud + crackle
    n = int(SR * 0.55)
    thud = sweep(120, 38, 0.55)[:n] * env(n, 0.002, 0.18)
    crackle = lowpass(noise(0.55), 0.15)[:n] * env(n, 0.001, 0.09)
    write("sfx_leak.wav", thud + 0.45 * crackle)
    # nova: rising filtered whoosh + harmonic swell
    dur = 0.9
    n = int(SR * dur)
    wh = lowpass(noise(dur), 0.02) * np.linspace(0.2, 1.0, n)
    sw = sweep(180, 1600, dur) * 0.5 * np.linspace(0.1, 1.0, n)
    write("sfx_nova.wav", (wh * 0.9 + sw) * env(n, 0.15, 0.45))
    # ui click
    write("sfx_click.wav", tone(1046, 0.05, 0.02))
    # combo tick (pitch is scaled at runtime)
    write("sfx_combo.wav", tone(660, 0.09, 0.04, (1.0, 0.3)))
    # achievement: C5 E5 G5 C6 arpeggio
    notes = [523.25, 659.25, 783.99, 1046.5]
    parts = []
    for i, f in enumerate(notes):
        seg = tone(f, 0.22, 0.12, (1.0, 0.4, 0.2))
        pad = np.zeros(int(SR * 0.13 * 4 + SR * 0.25))
        start = int(SR * 0.13 * i)
        pad[start:start + len(seg)] += seg
        parts.append(pad)
    write("sfx_achievement.wav", np.sum(parts, axis=0))
    # game over: descending minor
    notes = [392.0, 349.23, 293.66, 220.0]
    parts = []
    for i, f in enumerate(notes):
        seg = tone(f, 0.45, 0.3, (1.0, 0.35))
        pad = np.zeros(int(SR * 0.3 * 3 + SR * 0.6))
        start = int(SR * 0.3 * i)
        pad[start:start + len(seg)] += seg
        parts.append(pad)
    write("sfx_gameover.wav", np.sum(parts, axis=0))


def music() -> None:
    bpm = 100.0
    beat = 60.0 / bpm
    bars = 8
    beats = bars * 4
    length = beats * beat
    n = int(SR * length)
    t = np.arange(n) / SR
    # Am - F - C - G progression, two bars each, loops exactly
    chords = [
        (220.00, 261.63, 329.63), (174.61, 220.00, 261.63),
        (261.63, 329.63, 392.00), (196.00, 246.94, 293.66),
    ]
    pad = np.zeros(n)
    for bar in range(bars):
        root, third, fifth = chords[(bar // 2) % len(chords)]
        s0 = int(bar * 4 * beat * SR)
        s1 = int((bar + 1) * 4 * beat * SR)
        seg_t = t[s0:s1] - t[s0]
        voice = sum(np.sin(2 * np.pi * f * seg_t) + 0.3 * np.sin(2 * np.pi * f * 2 * seg_t)
                    for f in (root, third, fifth))
        fade = np.minimum(seg_t / 0.4, 1.0) * np.minimum((s1 - s0 - np.arange(s1 - s0)) / (0.4 * SR), 1.0)
        pad[s0:s1] += voice * fade
    pad = lowpass(pad, 0.08)
    write("music_pad.wav", pad, peak=0.55)

    pulse = np.zeros(n)
    # eighth-note hats
    step = beat / 2
    for k in range(int(length / step)):
        s = int(k * step * SR)
        hat = noise(0.05) * env(int(SR * 0.05), 0.001, 0.012)
        pulse[s:s + len(hat)] += 0.35 * hat
    # bass on quarter notes following the chord roots
    for b in range(beats):
        root = chords[((b // 8) % len(chords))][0] / 2
        s = int(b * beat * SR)
        seg = tone(root, beat * 0.9, 0.18)
        pulse[s:s + len(seg)] += seg[: max(0, min(len(seg), n - s))]
    write("music_pulse.wav", lowpass(pulse, 0.4), peak=0.6)


if __name__ == "__main__":
    sfx()
    music()
