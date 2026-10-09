extends GutTest
## Data integrity and text coverage. Missing keys or broken unlock chains fail here.

const Data = preload("res://scripts/data.gd")


func test_route_ids_are_unique_and_requirements_exist() -> void:
	var seen := {}
	for r in Data.ROUTES:
		assert_false(seen.has(r.id), "duplicate route id %s" % r.id)
		seen[r.id] = true
	for r in Data.ROUTES:
		if String(r.requires) != "":
			assert_true(seen.has(r.requires), "%s requires unknown route %s" % [r.id, r.requires])


func test_routes_have_map_position_and_colours() -> void:
	for r in Data.ROUTES:
		assert_gt(float(r.length), 1000.0)
		assert_true(r.has("map_x") and r.has("map_y"))
		assert_true(r.has("soil") and r.has("grass") and r.has("far") and r.has("near"))


func test_cars_have_colours_and_positive_stats() -> void:
	for c in Data.CARS:
		assert_gt(c.colors.size(), 0)
		assert_gt(float(c.tank), 0.0)
		assert_gt(float(c.torque), 0.0)
		assert_true(c.drive in ["all", "rear", "front"])


func test_color_index_is_clamped() -> void:
	var car := Data.car_by_id("kuhnavard")
	assert_eq(Data.color_for(car, 999), Color(String(car.colors[car.colors.size() - 1])))
	assert_eq(Data.color_for(car, -4), Color(String(car.colors[0])))


func test_every_text_key_has_persian_and_english() -> void:
	for key in Strings.TABLE:
		var entry: Dictionary = Strings.TABLE[key]
		assert_true(entry.has("fa"), "%s has no Persian text" % key)
		assert_true(entry.has("en"), "%s has no English text" % key)
		assert_gt(String(entry.fa).length(), 0, "%s Persian text is empty" % key)


func test_text_keys_used_by_data_exist() -> void:
	for r in Data.ROUTES:
		assert_true(Strings.TABLE.has(r.name_key), "missing text for %s" % r.name_key)
	for c in Data.CARS:
		assert_true(Strings.TABLE.has(c.name_key), "missing text for %s" % c.name_key)


func test_numbers_use_persian_digits_in_persian() -> void:
	App.language = "fa"
	assert_eq(Strings.num(1234), "۱۲۳۴")
	App.language = "en"
	assert_eq(Strings.num(1234), "1234")
	App.language = "fa"


func test_unknown_key_returns_the_key() -> void:
	assert_eq(Strings.t("no_such_key"), "no_such_key")
