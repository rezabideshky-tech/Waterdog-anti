extends Node
## Snd.gd — صداهای سنتزی بازی (بدون فایل صوتی)
## موتور، بوق، سکه، نیترو، تصادف، پیروزی و یک موسیقی بی‌پایان در دستگاه شور.

const RATE := 22050

var _bank := {}
var _pool: Array[AudioStreamPlayer] = []
var _next := 0
var _music: AudioStreamPlayer
var _engine: AudioStreamPlayer
var music_on := true
var sfx_on := true

func _ready() -> void:
	process_mode = Node.PROCESS_MODE_ALWAYS
	_build_bank()
	for i in 8:
		var p := AudioStreamPlayer.new()
		p.bus = "Master"
		add_child(p)
		_pool.append(p)
	_music = AudioStreamPlayer.new()
	_music.bus = "Master"
	_music.volume_db = -12.0
	add_child(_music)
	_engine = AudioStreamPlayer.new()
	_engine.bus = "Master"
	_engine.volume_db = -18.0
	add_child(_engine)

# ------------------------------------------------------------------ ساخت موج
func _to_wav(samples: PackedFloat32Array, loop := false) -> AudioStreamWAV:
	var n := samples.size()
	var bytes := PackedByteArray()
	bytes.resize(n * 2)
	for i in n:
		var v := clampf(samples[i], -1.0, 1.0)
		bytes.encode_s16(i * 2, int(round(v * 32000.0)))
	var w := AudioStreamWAV.new()
	w.format = AudioStreamWAV.FORMAT_16_BITS
	w.mix_rate = RATE
	w.stereo = false
	w.data = bytes
	if loop:
		w.loop_mode = AudioStreamWAV.LOOP_FORWARD
		w.loop_begin = 0
		w.loop_end = n
	return w

func _env(i: int, n: int, attack := 0.01, release := 0.25) -> float:
	var t := float(i) / float(n)
	var a := clampf(t / attack, 0.0, 1.0)
	var r := clampf((1.0 - t) / release, 0.0, 1.0)
	return a * r

func _tone(freq: float, dur: float, kind := "sine", vol := 0.6, slide := 0.0) -> AudioStreamWAV:
	var n := int(dur * RATE)
	var s := PackedFloat32Array()
	s.resize(n)
	var phase := 0.0
	for i in n:
		var t := float(i) / float(n)
		var f := freq + slide * t
		phase += TAU * f / float(RATE)
		var v := 0.0
		match kind:
			"square": v = 1.0 if fmod(phase, TAU) < PI else -1.0
			"saw": v = 2.0 * fmod(phase / TAU, 1.0) - 1.0
			"tri": v = 1.0 - 4.0 * absf(fmod(phase / TAU, 1.0) - 0.5)
			"noise": v = randf() * 2.0 - 1.0
			_: v = sin(phase)
		s[i] = v * vol * _env(i, n, 0.02, 0.6)
	return _to_wav(s)

func _noise_hit(dur: float, vol := 0.7, low := 0.0) -> AudioStreamWAV:
	var n := int(dur * RATE)
	var s := PackedFloat32Array()
	s.resize(n)
	var last := 0.0
	for i in n:
		var raw := randf() * 2.0 - 1.0
		last = lerpf(last, raw, 0.35 + low)
		s[i] = last * vol * _env(i, n, 0.004, 0.9)
	return _to_wav(s)

func _chord(freqs: Array, dur: float, vol := 0.4) -> AudioStreamWAV:
	var n := int(dur * RATE)
	var s := PackedFloat32Array()
	s.resize(n)
	for i in n:
		var v := 0.0
		for f in freqs:
			v += sin(TAU * float(f) * float(i) / float(RATE)) * 0.5
		s[i] = v / float(freqs.size()) * vol * _env(i, n, 0.02, 0.7)
	return _to_wav(s)

## موتور: موج اره‌ای پرنوسان که در بازی با pitch_scale بالا/پایین می‌رود
func _engine_loop() -> AudioStreamWAV:
	var dur := 0.5
	var n := int(dur * RATE)
	var s := PackedFloat32Array()
	s.resize(n)
	for i in n:
		var t := float(i) / float(RATE)
		var phase := fmod(58.0 * t, 1.0)
		var saw := 2.0 * phase - 1.0
		var rumble := sin(TAU * 29.0 * t) * 0.5
		var click := 0.0
		if fmod(t, 1.0 / 29.0) < 0.004:
			click = 0.6
		s[i] = (saw * 0.35 + rumble * 0.5 + click * 0.3) * 0.55
	return _to_wav(s, true)

## موسیقی: چهار ثانیه بی‌پایان در دستگاه شور با باس و ریتم
func _music_loop() -> AudioStreamWAV:
	var dur := 4.0
	var n := int(dur * RATE)
	var s := PackedFloat32Array()
	s.resize(n)
	var shur := [0.0, 1.0, 3.0, 5.0, 7.0, 8.0, 10.0, 12.0]
	var root := 220.0
	var note_len := 0.25
	var notes := [0, 2, 3, 4, 3, 2, 0, 4, 5, 4, 3, 2, 1, 0, 2, 3]
	for i in n:
		var t := float(i) / float(RATE)
		var beat := int(t / note_len)
		var deg := int(notes[beat % notes.size()])
		var f := root * pow(2.0, shur[deg % shur.size()] / 12.0)
		var local := fmod(t, note_len) / note_len
		var env := clampf(1.0 - local, 0.0, 1.0)
		var v := sin(TAU * f * t) * 0.22 * env
		v += sin(TAU * f * 2.0 * t) * 0.06 * env
		# باس هر دو ضرب
		var bass_env := clampf(1.0 - fmod(t, 0.5) / 0.5, 0.0, 1.0)
		v += sin(TAU * (root / 2.0) * t) * 0.20 * bass_env
		# ضرب تنبک‌مانند
		var beat_pos := fmod(t, 0.25)
		if beat_pos < 0.02:
			v += (randf() * 2.0 - 1.0) * 0.16 * (1.0 - beat_pos / 0.02)
		s[i] = v
	return _to_wav(s, true)

func _build_bank() -> void:
	_bank["click"] = _tone(880.0, 0.07, "square", 0.35)
	_bank["back"] = _tone(440.0, 0.09, "square", 0.32)
	_bank["coin"] = _chord([1180.0, 1560.0], 0.10, 0.35)
	_bank["gem"] = _chord([1320.0, 1760.0, 2200.0], 0.16, 0.30)
	_bank["buy"] = _chord([880.0, 1180.0, 1560.0], 0.22, 0.30)
	_bank["denied"] = _tone(220.0, 0.20, "saw", 0.35, -80.0)
	_bank["crash"] = _noise_hit(0.45, 0.85, 0.2)
	_bank["land"] = _noise_hit(0.18, 0.5, 0.3)
	_bank["nitro"] = _tone(300.0, 0.45, "saw", 0.35, 900.0)
	_bank["engine_start"] = _tone(90.0, 0.5, "saw", 0.4, 240.0)
	_bank["horn"] = _chord([330.0, 415.0], 0.35, 0.4)
	_bank["win"] = _chord([523.0, 659.0, 784.0, 1047.0], 0.9, 0.4)
	_bank["fail"] = _chord([392.0, 330.0, 262.0], 0.9, 0.4)
	_bank["wheel"] = _tone(660.0, 0.09, "tri", 0.3)
	_bank["star"] = _chord([784.0, 1046.0, 1318.0], 0.35, 0.32)
	_bank["pop"] = _tone(520.0, 0.08, "tri", 0.3, 200.0)
	_bank["engine"] = _engine_loop()
	_bank["music"] = _music_loop()

# ------------------------------------------------------------------ پخش
func play(name: String, pitch := 1.0, vol_db := 0.0) -> void:
	if not sfx_on:
		return
	var st = _bank.get(name)
	if st == null:
		return
	var p := _pool[_next]
	_next = (_next + 1) % _pool.size()
	p.stream = st
	p.pitch_scale = clampf(pitch, 0.4, 3.0)
	p.volume_db = vol_db
	p.play()

func start_engine() -> void:
	if _engine.playing:
		return
	_engine.stream = _bank["engine"]
	_engine.pitch_scale = 0.55
	_engine.play()

func stop_engine() -> void:
	if _engine.playing:
		_engine.stop()

## سرعت موتور: rpm بین ۰ و ۱
func engine_rpm(r: float) -> void:
	if not sfx_on:
		_engine.volume_db = -80.0
		return
	if not _engine.playing:
		start_engine()
	_engine.pitch_scale = clampf(0.5 + r * 1.5, 0.4, 2.6)
	_engine.volume_db = -30.0 + r * 8.0

func start_music() -> void:
	if not music_on or _music.playing:
		return
	_music.stream = _bank["music"]
	_music.play()

func stop_music() -> void:
	if _music.playing:
		_music.stop()

func set_music(on: bool) -> void:
	music_on = on
	if on:
		start_music()
	else:
		stop_music()
	Game.settings["music"] = on

func set_sfx(on: bool) -> void:
	sfx_on = on
	if not on:
		stop_engine()
	Game.settings["sound"] = on
