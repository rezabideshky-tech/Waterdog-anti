extends RefCounted
## Procedural hill profile and pickup layout. Pure functions, deterministic per seed.
## Units are pixels. +x is forward. Screen y points down, so climbing makes y go negative.

const STEP := 40.0
const FLAT_UNTIL := 400.0


## Returns the ground profile from x = 0 to x = length.
static func generate(seed_value: int, length: float, amp: float, slope: float) -> PackedVector2Array:
	var rng := RandomNumberGenerator.new()
	rng.seed = seed_value
	var phases := Vector3(rng.randf() * TAU, rng.randf() * TAU, rng.randf() * TAU)
	var pts := PackedVector2Array()
	var x := 0.0
	while x < length:
		pts.append(Vector2(x, height_at(x, amp, slope, phases)))
		x += STEP
	pts.append(Vector2(length, height_at(length, amp, slope, phases)))
	return pts


## Height of the ground at x. Exactly flat for the first FLAT_UNTIL pixels, then the climb and bumps fade in.
static func height_at(x: float, amp: float, slope: float, phases: Vector3) -> float:
	var ramp := maxf(0.0, x - FLAT_UNTIL)
	var fade := clampf((x - FLAT_UNTIL) / 300.0, 0.0, 1.0)
	var wave := sin(x * 0.0061 + phases.x) * 0.55 \
		+ sin(x * 0.0137 + phases.y) * 0.30 \
		+ sin(x * 0.0313 + phases.z) * 0.15
	return -slope * ramp - amp * wave * fade


## Linear interpolation of the profile at x.
static func ground_y_at(pts: PackedVector2Array, x: float) -> float:
	if pts.size() == 0:
		return 0.0
	if x <= pts[0].x:
		return pts[0].y
	for i in range(pts.size() - 1):
		var a := pts[i]
		var b := pts[i + 1]
		if x <= b.x:
			var t := (x - a.x) / maxf(b.x - a.x, 0.0001)
			return lerpf(a.y, b.y, t)
	return pts[pts.size() - 1].y


## Coins in short arcs, and fuel cans at a steady spacing. Positions are in world pixels.
static func pickups(pts: PackedVector2Array, seed_value: int, length: float, fuel_every: float) -> Dictionary:
	var rng := RandomNumberGenerator.new()
	rng.seed = seed_value * 7919 + 13
	var coins: Array = []
	var fuel: Array = []
	var x := 420.0
	while x < length - 200.0:
		if rng.randf() < 0.8:
			var n := rng.randi_range(3, 6)
			for i in n:
				var cx := x + float(i) * 46.0
				coins.append(Vector2(cx, ground_y_at(pts, cx) - 110.0))
		x += rng.randf_range(260.0, 480.0)
	var fx := 700.0
	while fx < length - 300.0:
		fuel.append(Vector2(fx, ground_y_at(pts, fx) - 70.0))
		fx += fuel_every
	return {"coins": coins, "fuel": fuel}
