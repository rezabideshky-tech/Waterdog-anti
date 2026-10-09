extends Node

signal changed

const OrbitLogic = preload("res://scripts/orbit_logic.gd")
const Achievements = preload("res://scripts/achievements.gd")

## Persistence (autoload "Save"). Atomic writes plus a backup, so a crash or a corrupt
## file never wipes the player's progress (LESSONS L32). Everything here is plain data;
## the rules that interpret it live in Achievements / OrbitLogic.

const DEFAULT_PATH := "user://save.json"
const RECORDS_KEEP := 10

var path: String = DEFAULT_PATH
var data: Dictionary = {}


func _ready() -> void:
	load_data()


static func defaults() -> Dictionary:
	return {
		"version": 1,
		"settings": {"sfx": true, "music": true, "vibration": true, "language": "en"},
		"tutorial_done": false,
		"stats": {
			"games": 0, "best_score": 0, "best_combo": 0, "best_wave": 1,
			"total_blocks": 0, "total_armor": 0, "total_novas": 0, "daily_done": 0,
		},
		"achievements": [],
		"records": [],
		"daily": {"date": "", "score": -1},
	}


func load_data() -> void:
	var parsed = _read_json(path)
	if parsed == null:
		parsed = _read_json(path + ".bak")
	data = defaults()
	if parsed is Dictionary:
		_merge_into(data, parsed)


func save_data() -> bool:
	var tmp := path + ".tmp"
	var f := FileAccess.open(tmp, FileAccess.WRITE)
	if f == null:
		return false
	f.store_string(JSON.stringify(data, "\t"))
	f.close()
	if FileAccess.file_exists(path):
		DirAccess.copy_absolute(path, path + ".bak")
		DirAccess.remove_absolute(path)
	var err := DirAccess.rename_absolute(tmp, path)
	changed.emit()
	return err == OK


func reset_progress() -> void:
	var lang: String = data.settings.language
	var settings: Dictionary = data.settings
	data = defaults()
	data.settings = settings
	data.settings.language = lang
	save_data()


## Called once when a run ends. Returns the achievement ids unlocked by this run.
func commit_run(logic) -> Array:
	var s: Dictionary = data.stats
	s.games += 1
	s.best_score = maxi(s.best_score, logic.score)
	s.best_combo = maxi(s.best_combo, logic.best_combo)
	s.best_wave = maxi(s.best_wave, logic.wave())
	s.total_blocks += logic.blocks
	s.total_armor += logic.armor_breaks
	s.total_novas += logic.novas_used
	if logic.mode == "daily":
		s.daily_done += 1
	add_record(logic.score, logic.mode, logic.wave(), logic.best_combo)
	var fresh := Achievements.newly_unlocked(s, data.achievements)
	for id in fresh:
		data.achievements.append(id)
	save_data()
	return fresh


func add_record(score: int, mode: String, wave: int, combo: int) -> void:
	var recs: Array = data.records
	recs.append({"score": score, "mode": mode, "wave": wave, "combo": combo, "date": today_key()})
	recs.sort_custom(func(a, b): return a.score > b.score)
	while recs.size() > RECORDS_KEEP:
		recs.pop_back()


static func today_key() -> String:
	## LOCAL calendar date (LESSONS L31: Godot's time helpers default to UTC otherwise).
	var d := Time.get_date_dict_from_system(false)
	return "%04d-%02d-%02d" % [d.year, d.month, d.day]


func daily_attempted_today() -> bool:
	return data.daily.date == today_key()


func daily_score_today() -> int:
	return data.daily.score if daily_attempted_today() else -1


## Consumed at the START of the daily run, so quitting mid-run cannot be used to retry.
func start_daily_attempt() -> void:
	data.daily = {"date": today_key(), "score": -1}
	save_data()


func record_daily_score(score: int) -> void:
	data.daily = {"date": today_key(), "score": score}
	save_data()


func setting(key: String) -> Variant:
	return data.settings.get(key)


func set_setting(key: String, value: Variant) -> void:
	data.settings[key] = value
	save_data()


func _read_json(p: String) -> Variant:
	if not FileAccess.file_exists(p):
		return null
	var text := FileAccess.get_file_as_string(p)
	if text.is_empty():
		return null
	## JSON instance API: a corrupt file returns null quietly, so the backup path takes over.
	var parser := JSON.new()
	if parser.parse(text) != OK:
		return null
	return parser.data if parser.data is Dictionary else null


## Copies saved values over defaults, key by key, so new keys added in updates get defaults.
func _merge_into(dst: Dictionary, src: Dictionary) -> void:
	for k in src:
		if dst.has(k):
			if dst[k] is Dictionary and src[k] is Dictionary:
				_merge_into(dst[k], src[k])
			else:
				dst[k] = src[k]
