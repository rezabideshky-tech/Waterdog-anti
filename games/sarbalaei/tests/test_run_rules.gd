extends GutTest
## Pure rules for a drive: units, stars, fuel, stuck and flip checks, and air bonuses.

const Rules = preload("res://scripts/run_rules.gd")


func test_distance_is_ten_pixels_per_metre() -> void:
	assert_almost_eq(Rules.distance_m(520.0), 52.0, 0.001)
	assert_almost_eq(Rules.distance_m(-30.0), 0.0, 0.001, "never negative")


func test_speed_conversion() -> void:
	# 1000 px/s = 100 m/s = 360 km/h
	assert_almost_eq(Rules.kmh(1000.0), 360.0, 0.001)


func test_no_stars_without_finishing() -> void:
	assert_eq(Rules.stars(false, 1.0, 10.0, 70.0), 0)


func test_finish_gives_one_star_and_bonuses_stack() -> void:
	assert_eq(Rules.stars(true, 0.0, 999.0, 70.0), 1)
	assert_eq(Rules.stars(true, 0.5, 999.0, 70.0), 2, "half the coins is a second star")
	assert_eq(Rules.stars(true, 0.5, 60.0, 70.0), 3, "under the target time is a third star")


func test_fuel_burns_more_with_throttle_and_speed() -> void:
	var idle := Rules.fuel_used(0.0, 0.0, 1.0, 2.0)
	var full := Rules.fuel_used(1.0, 0.0, 1.0, 2.0)
	var fast := Rules.fuel_used(1.0, 1200.0, 1.0, 2.0)
	assert_gt(idle, 0.0, "idling still burns fuel")
	assert_gt(full, idle)
	assert_gt(fast, full)


func test_fuel_scales_with_time_step() -> void:
	assert_almost_eq(Rules.fuel_used(1.0, 300.0, 0.5, 2.0), Rules.fuel_used(1.0, 300.0, 1.0, 2.0) * 0.5, 0.0001)


func test_stuck_needs_gas_low_speed_and_time() -> void:
	assert_false(Rules.is_stuck(0.0, 0.0, 10.0), "not pressing gas is not stuck")
	assert_false(Rules.is_stuck(50.0, 1.0, 10.0), "moving is not stuck")
	assert_false(Rules.is_stuck(0.0, 1.0, 2.0), "too soon")
	assert_true(Rules.is_stuck(0.0, 1.0, 5.0))


func test_flip_is_more_than_110_degrees_off_upright() -> void:
	assert_false(Rules.is_flipped(deg_to_rad(30.0)))
	assert_false(Rules.is_flipped(deg_to_rad(-100.0)))
	assert_true(Rules.is_flipped(deg_to_rad(150.0)))
	assert_true(Rules.is_flipped(deg_to_rad(-170.0)))
	assert_true(Rules.is_flipped(deg_to_rad(200.0)), "wraps around a full turn")


func test_air_bonus_needs_real_air_time() -> void:
	assert_eq(Rules.air_bonus(0.3, 0.0), 0)
	assert_eq(Rules.air_bonus(1.0, 0.0), 20)
	assert_eq(Rules.air_bonus(1.0, TAU), 70, "one full turn adds 50")


func test_coin_ratio_is_clamped() -> void:
	assert_almost_eq(Rules.coin_ratio(0, 0), 0.0, 0.001)
	assert_almost_eq(Rules.coin_ratio(5, 10), 0.5, 0.001)
	assert_almost_eq(Rules.coin_ratio(20, 10), 1.0, 0.001)
