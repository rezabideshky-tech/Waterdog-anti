extends GutTest
## Save, achievements and records, tested on a private save file (never the player's).

const SaveScript = preload("res://scripts/save.gd")
const Achievements = preload("res://scripts/achievements.gd")
const OrbitLogic = preload("res://scripts/orbit_logic.gd")

var save
var logic


func before_each() -> void:
	save = SaveScript.new()
	save.path = "user://test_save_%d.json" % Time.get_ticks_usec()
	save.load_data()
	logic = OrbitLogic.new()
	logic.setup("classic", 5)


func after_each() -> void:
	DirAccess.remove_absolute(save.path)
	DirAccess.remove_absolute(save.path + ".bak")
	DirAccess.remove_absolute(save.path + ".tmp")
	save.free()


func test_defaults_when_no_file() -> void:
	assert_eq(save.data.stats.games, 0)
	assert_eq(save.data.settings.language, "en")
	assert_false(save.data.tutorial_done)


func test_commit_run_updates_stats_and_records() -> void:
	logic.score = 320
	logic.blocks = 12
	logic.combo = 0
	logic.best_combo = 6
	var fresh: Array = save.commit_run(logic)
	assert_eq(save.data.stats.games, 1)
	assert_eq(save.data.stats.best_score, 320)
	assert_eq(save.data.stats.total_blocks, 12)
	assert_eq(save.data.records.size(), 1)
	assert_true(fresh.has("first_block"))


func test_achievements_unlock_once() -> void:
	save.data.stats.total_blocks = 150
	var first: Array = Achievements.newly_unlocked(save.data.stats, save.data.achievements)
	assert_true(first.has("blocks_100"))
	var second: Array = Achievements.newly_unlocked(save.data.stats, first)
	assert_false(second.has("blocks_100"), "already unlocked must not repeat")


func test_achievement_progress_is_capped() -> void:
	var p: Vector2i = Achievements.progress({"total_blocks": 500}, "blocks_100")
	assert_eq(p, Vector2i(100, 100))


func test_records_keep_only_top_ten_sorted() -> void:
	for i in 15:
		save.add_record(i * 10, "classic", 1, 0)
	assert_eq(save.data.records.size(), 10)
	assert_eq(save.data.records[0].score, 140)
	assert_eq(save.data.records[9].score, 50)


func test_daily_attempt_is_consumed_at_start() -> void:
	assert_false(save.daily_attempted_today())
	save.start_daily_attempt()
	assert_true(save.daily_attempted_today())
	assert_eq(save.daily_score_today(), -1, "quitting mid-run must not allow a retry score")
	save.record_daily_score(777)
	assert_eq(save.daily_score_today(), 777)


func test_save_round_trips_through_disk() -> void:
	save.set_setting("language", "fa")
	save.data.stats.games = 9
	save.save_data()
	var again = SaveScript.new()
	again.path = save.path
	again.load_data()
	assert_eq(again.data.settings.language, "fa")
	assert_eq(int(again.data.stats.games), 9)
	again.free()


func test_corrupt_file_falls_back_to_backup() -> void:
	save.data.stats.games = 4
	save.save_data()
	save.data.stats.games = 5
	save.save_data()  # the first version is now the .bak
	var f := FileAccess.open(save.path, FileAccess.WRITE)
	f.store_string("{ this is not json")
	f.close()
	var again = SaveScript.new()
	again.path = save.path
	again.load_data()
	assert_eq(int(again.data.stats.games), 4, "a corrupt save must restore the backup, never wipe")
	again.free()


func test_reset_keeps_only_language() -> void:
	save.set_setting("language", "fa")
	save.data.stats.games = 30
	save.reset_progress()
	assert_eq(save.data.stats.games, 0)
	assert_eq(save.data.settings.language, "fa")
