extends GutTest
## Rule tests for the pure game logic. No nodes, no clock: everything is driven by step().

const OrbitLogic = preload("res://scripts/orbit_logic.gd")

var logic


func before_each() -> void:
	logic = OrbitLogic.new()
	logic.setup("classic", 42)


func _rock(a: float, r: float, v: float = 100.0, kind: String = "rock", hp: int = 1, omega: float = 0.0) -> Dictionary:
	return {"id": 99, "kind": kind, "r": r, "a": a, "omega": omega, "v": v, "hp": hp}


func test_daily_seed_is_stable_and_date_specific() -> void:
	assert_eq(OrbitLogic.daily_seed("2026-10-09"), OrbitLogic.daily_seed("2026-10-09"))
	assert_ne(OrbitLogic.daily_seed("2026-10-09"), OrbitLogic.daily_seed("2026-10-10"))
	assert_eq(OrbitLogic.daily_seed("2026-10"), 1, "a malformed date falls back to seed 1")


func test_mode_rules() -> void:
	assert_eq(OrbitLogic.mode_lives("classic"), 3)
	assert_eq(OrbitLogic.mode_lives("daily"), 2)
	assert_true(OrbitLogic.mode_nova_enabled("classic"))
	assert_false(OrbitLogic.mode_nova_enabled("daily"))


func test_multiplier_steps_with_combo() -> void:
	logic.combo = 4
	assert_eq(logic.multiplier(), 1)
	logic.combo = 5
	assert_eq(logic.multiplier(), 2)
	logic.combo = 10
	assert_eq(logic.multiplier(), 3)
	logic.combo = 20
	assert_eq(logic.multiplier(), 4)


func test_spawn_interval_tightens_then_floors() -> void:
	assert_almost_eq(logic.spawn_interval(1), 1.25, 0.0001)
	assert_true(logic.spawn_interval(2) < logic.spawn_interval(1))
	assert_almost_eq(logic.spawn_interval(60), 0.42, 0.0001)


func test_shield_contains_handles_the_angle_seam() -> void:
	logic.shield_a = PI - 0.1
	assert_true(logic.shield_contains(-PI + 0.1), "shield must cover the seam at +-PI")
	assert_false(logic.shield_contains(0.0))


func test_arrival_time_and_angle_are_predictive() -> void:
	var rk := _rock(0.0, OrbitLogic.RING_R + 100.0, 100.0, "rock", 1, 0.5)
	assert_almost_eq(logic.arrival_time(rk), 1.0, 0.0001)
	assert_almost_eq(logic.arrival_angle(rk), 0.5, 0.0001)


func test_shield_moves_with_a_speed_limit() -> void:
	logic.set_target(1.0)
	logic.step(0.1)
	assert_almost_eq(logic.shield_a, OrbitLogic.SHIELD_SPEED * 0.1, 0.001)
	for i in 10:
		logic.step(0.1)
	assert_almost_eq(logic.shield_a, 1.0, 0.001, "shield must settle on the target")


func test_blocked_rock_scores_and_builds_combo() -> void:
	logic.rocks = [_rock(0.0, OrbitLogic.RING_R + 5.0)]
	logic.step(0.1)
	assert_eq(logic.score, 10)
	assert_eq(logic.combo, 1)
	assert_eq(logic.blocks, 1)
	assert_true(logic.rocks.is_empty())


func test_missed_rock_leaks_and_resets_combo() -> void:
	logic.shield_a = PI
	logic.target_a = PI
	logic.combo = 7
	logic.rocks = [_rock(0.0, OrbitLogic.PLANET_R + 5.0)]
	logic.step(0.1)
	assert_eq(logic.lives, 2)
	assert_eq(logic.combo, 0)
	assert_eq(logic.score, 0)


func test_armored_rock_needs_two_hits() -> void:
	var rk := _rock(0.0, OrbitLogic.RING_R + 5.0, 100.0, "armor", 2)
	logic.rocks = [rk]
	logic.step(0.1)
	assert_eq(logic.score, 0, "first hit must not score")
	assert_eq(logic.rocks.size(), 1)
	assert_almost_eq(logic.rocks[0].r, OrbitLogic.BOUNCE_R, 0.001)
	logic.rocks[0].r = OrbitLogic.RING_R + 5.0
	logic.step(0.1)
	assert_eq(logic.score, 25)
	assert_eq(logic.armor_breaks, 1)
	assert_true(logic.rocks.is_empty())


func test_game_over_fires_once_and_lives_never_go_negative() -> void:
	logic.lives = 1
	logic.shield_a = PI
	logic.target_a = PI
	logic.rocks = [_rock(0.0, OrbitLogic.PLANET_R + 5.0), _rock(0.5, OrbitLogic.PLANET_R + 5.0)]
	logic.step(0.1)
	assert_true(logic.over)
	assert_eq(logic.lives, 0)
	var over_events := 0
	for ev in logic.drain_events():
		if ev.type == "gameover":
			over_events += 1
	assert_eq(over_events, 1)


func test_nova_clears_only_outer_rocks_and_scores() -> void:
	logic.nova_charges = 1
	logic.rocks = [_rock(0.0, 300.0), _rock(1.0, 120.0)]
	assert_true(logic.try_nova())
	assert_eq(logic.score, OrbitLogic.POINTS["nova"])
	assert_eq(logic.rocks.size(), 1, "rocks inside the ring are not cleared by nova")
	assert_eq(logic.nova_charges, 0)
	assert_eq(logic.novas_used, 1)
	assert_false(logic.try_nova(), "no charge left")


func test_nova_charge_every_ten_blocks_and_is_capped() -> void:
	logic.combo = 9
	logic._score_block({"kind": "rock"})
	assert_eq(logic.nova_charges, 1)
	logic.nova_charges = OrbitLogic.MAX_NOVA
	logic.combo = 19
	logic._score_block({"kind": "rock"})
	assert_eq(logic.nova_charges, OrbitLogic.MAX_NOVA, "charges are capped")


func test_daily_mode_has_no_nova() -> void:
	logic.setup("daily", 7)
	assert_false(logic.nova_enabled())
	logic.nova_charges = 0
	logic.combo = 9
	logic._score_block({"kind": "rock"})
	assert_eq(logic.nova_charges, 0)


func test_daily_sky_is_identical_whatever_the_player_does() -> void:
	## Same date seed, different player input: the spawn schedule must not change.
	var a = OrbitLogic.new()
	a.setup("daily", OrbitLogic.daily_seed("2026-10-09"))
	var b = OrbitLogic.new()
	b.setup("daily", OrbitLogic.daily_seed("2026-10-09"))
	for i in 150:
		a.set_target(0.0)
		a.step(0.1)
		b.set_target(sin(float(i) * 0.3) * 2.0)
		b.step(0.1)
	assert_eq(a._next_id, b._next_id, "same number of rocks spawned")
	assert_almost_eq(a.elapsed, b.elapsed, 0.0001)


func test_different_seeds_give_different_skies() -> void:
	var a = OrbitLogic.new()
	a.setup("classic", 1)
	var b = OrbitLogic.new()
	b.setup("classic", 2)
	for i in 10:
		a.step(0.1)
		b.step(0.1)
	assert_ne(str(a.rocks), str(b.rocks))


func test_wave_progression() -> void:
	logic.elapsed = 0.0
	assert_eq(logic.wave(), 1)
	logic.elapsed = OrbitLogic.WAVE_SECONDS
	assert_eq(logic.wave(), 2)
	logic.elapsed = OrbitLogic.WAVE_SECONDS * 5.0
	assert_eq(logic.wave(), 6)


func test_drain_events_empties_the_queue() -> void:
	logic.rocks = [_rock(0.0, OrbitLogic.RING_R + 5.0)]
	logic.step(0.1)
	assert_false(logic.drain_events().is_empty())
	assert_true(logic.drain_events().is_empty())
