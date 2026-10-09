extends Node
## Sound and music (autoload "Audio"). Music is two looping stems: the pad always plays,
## the pulse layer fades in as the sky gets more dangerous. Loop points are set from the
## stream's real length and the mix rate, never from byte counts (LESSONS L34, L53).

const SFX_POOL := 8

var _sfx: Array[AudioStreamPlayer] = []
var _pad: AudioStreamPlayer
var _pulse: AudioStreamPlayer
var _intensity := 0.0


func _ready() -> void:
	for i in SFX_POOL:
		var p := AudioStreamPlayer.new()
		p.bus = "Master"
		add_child(p)
		_sfx.append(p)
	_pad = _music_player("music_pad", -10.0)
	_pulse = _music_player("music_pulse", -40.0)
	Save.changed.connect(_apply_music_state)


func _music_player(asset: String, base_db: float) -> AudioStreamPlayer:
	var p := AudioStreamPlayer.new()
	p.bus = "Master"
	var s := Assets.sound(asset)
	if s != null:
		s.loop_mode = AudioStreamWAV.LOOP_FORWARD
		s.loop_begin = 0
		s.loop_end = int(round(s.get_length() * float(s.mix_rate)))
	p.stream = s
	p.volume_db = base_db
	add_child(p)
	return p


func play(asset: String, pitch: float = 1.0, volume_db: float = 0.0) -> void:
	if not bool(Save.setting("sfx")):
		return
	var s := Assets.sound(asset)
	if s == null:
		return
	for p in _sfx:
		if not p.playing:
			p.stream = s
			p.pitch_scale = pitch
			p.volume_db = volume_db
			p.play()
			return
	# all voices busy: steal the oldest, which is the first in the pool
	_sfx[0].stream = s
	_sfx[0].pitch_scale = pitch
	_sfx[0].volume_db = volume_db
	_sfx[0].play()


func start_music() -> void:
	_apply_music_state()
	if bool(Save.setting("music")):
		if not _pad.playing:
			_pad.play()
		if not _pulse.playing:
			_pulse.play()


func stop_music() -> void:
	_pad.stop()
	_pulse.stop()


## 0..1, driven by the wave number from the game view.
func set_intensity(x: float) -> void:
	_intensity = clampf(x, 0.0, 1.0)
	_apply_music_state()


func haptic(ms: int) -> void:
	if bool(Save.setting("vibration")):
		Input.vibrate_handheld(ms)


## Volumes only. Playback is handled by start_music / stop_music, so this never recurses.
func _apply_music_state() -> void:
	if _pad == null:
		return
	var on := bool(Save.setting("music"))
	_pad.volume_db = -10.0 if on else -80.0
	_pulse.volume_db = lerpf(-40.0, -6.0, _intensity) if on else -80.0
	if not on:
		stop_music()
