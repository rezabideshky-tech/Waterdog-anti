extends GutTest
## The reference bot plays whole games through the pure rules. It must never break an
## invariant, must finish, and must reach a real wave (a balance and regression check).

const OrbitBot = preload("res://scripts/bot.gd")


func test_bot_games_keep_every_invariant() -> void:
	for seed_value in [1, 2, 3, 4, 5]:
		var r: Dictionary = OrbitBot.play_game(seed_value, "classic", 900.0, 1.0 / 60.0)
		assert_eq(r.invariant_failures, 0, "invariant broken for seed %d" % seed_value)
		assert_true(r.over, "game must end (seed %d)" % seed_value)


func test_bot_reaches_real_play() -> void:
	var r: Dictionary = OrbitBot.play_game(11, "classic", 900.0, 1.0 / 60.0)
	assert_gt(r.score, 0)
	assert_gt(r.blocks, 5)
	assert_gte(r.wave, 2, "the bot should survive the first wave")


func test_daily_games_are_deterministic_for_the_bot() -> void:
	var a: Dictionary = OrbitBot.play_game(777, "daily", 900.0, 1.0 / 60.0)
	var b: Dictionary = OrbitBot.play_game(777, "daily", 900.0, 1.0 / 60.0)
	assert_eq(a.score, b.score, "same date seed, same bot, same score")
	assert_eq(a.blocks, b.blocks)
