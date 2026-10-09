extends GutTest
## Save file round-trip and unlock rules, using a temporary file.

const TEST_PATH := "user://sarbalaei_test_save.json"


func before_each() -> void:
	Save.path = TEST_PATH
	if FileAccess.file_exists(TEST_PATH):
		DirAccess.remove_absolute(ProjectSettings.globalize_path(TEST_PATH))
	Save.load_game()


func after_each() -> void:
	Save.path = Save.DEFAULT_PATH
	if FileAccess.file_exists(TEST_PATH):
		DirAccess.remove_absolute(ProjectSettings.globalize_path(TEST_PATH))


func test_defaults_when_no_file() -> void:
	assert_eq(Save.total_coins(), 0)
	assert_eq(Save.stars_of("chalus"), 0)


func test_coins_survive_a_reload() -> void:
	Save.add_coins(120)
	Save.add_coins(30)
	Save.load_game()
	assert_eq(Save.total_coins(), 150)


func test_best_distance_only_goes_up() -> void:
	Save.record_run("chalus", 300.0, 1)
	Save.record_run("chalus", 120.0, 0)
	assert_almost_eq(Save.best_of("chalus"), 300.0, 0.001)


func test_stars_only_go_up() -> void:
	Save.record_run("chalus", 500.0, 2)
	Save.record_run("chalus", 500.0, 1)
	assert_eq(Save.stars_of("chalus"), 2)


func test_second_route_locked_until_first_has_a_star() -> void:
	assert_true(Save.is_unlocked("chalus"))
	assert_false(Save.is_unlocked("gilan"))
	Save.record_run("chalus", 520.0, 1)
	assert_true(Save.is_unlocked("gilan"))
	assert_false(Save.is_unlocked("damavand"))


func test_wrong_shape_file_falls_back_to_defaults() -> void:
	var f := FileAccess.open(TEST_PATH, FileAccess.WRITE)
	f.store_string("[1, 2, 3]")
	f.close()
	Save.load_game()
	assert_eq(Save.total_coins(), 0)
