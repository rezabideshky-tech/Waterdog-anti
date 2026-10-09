extends GutTest
## Terrain and pickups must be deterministic, flat at the start, and climb to the end.

const TerrainGen = preload("res://scripts/terrain_gen.gd")


func test_same_seed_gives_same_profile() -> void:
	var a := TerrainGen.generate(11, 3000.0, 60.0, 0.2)
	var b := TerrainGen.generate(11, 3000.0, 60.0, 0.2)
	assert_eq(a.size(), b.size())
	assert_eq(a[a.size() - 1], b[b.size() - 1])


func test_different_seed_changes_bumps() -> void:
	var a := TerrainGen.generate(11, 3000.0, 60.0, 0.2)
	var b := TerrainGen.generate(12, 3000.0, 60.0, 0.2)
	assert_ne(a[50].y, b[50].y)


func test_starts_flat_then_climbs() -> void:
	var pts := TerrainGen.generate(11, 5200.0, 60.0, 0.2)
	assert_almost_eq(pts[0].y, 0.0, 0.001, "first point is at ground zero")
	assert_almost_eq(TerrainGen.ground_y_at(pts, 200.0), 0.0, 0.001, "spawn area is flat")
	assert_lt(TerrainGen.ground_y_at(pts, 5000.0), -500.0, "far end is well above the start (screen y is down)")


func test_x_is_strictly_increasing_and_ends_at_length() -> void:
	var pts := TerrainGen.generate(37, 7600.0, 80.0, 0.31)
	for i in range(pts.size() - 1):
		assert_gt(pts[i + 1].x, pts[i].x)
	assert_almost_eq(pts[pts.size() - 1].x, 7600.0, 0.001)


func test_ground_interpolates_between_points() -> void:
	var pts := PackedVector2Array([Vector2(0, 0), Vector2(100, -50)])
	assert_almost_eq(TerrainGen.ground_y_at(pts, 50.0), -25.0, 0.001)
	assert_almost_eq(TerrainGen.ground_y_at(pts, 500.0), -50.0, 0.001, "past the end, hold the last height")
	assert_almost_eq(TerrainGen.ground_y_at(pts, -10.0), 0.0, 0.001, "before the start, hold the first height")


func test_pickups_are_deterministic_and_inside_route() -> void:
	var pts := TerrainGen.generate(11, 5200.0, 60.0, 0.2)
	var a := TerrainGen.pickups(pts, 11, 5200.0, 1400.0)
	var b := TerrainGen.pickups(pts, 11, 5200.0, 1400.0)
	assert_eq(a.coins.size(), b.coins.size())
	assert_gt(a.coins.size(), 10, "enough coins to matter")
	assert_gt(a.fuel.size(), 1, "several fuel cans on a long route")
	for c in a.coins:
		assert_gt(c.x, 400.0)
		assert_lt(c.x, 5200.0)
	for f in a.fuel:
		assert_gt(f.x, 600.0, "no can right on the start line")
