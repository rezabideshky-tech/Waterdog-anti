extends RefCounted
const OrbitLogic = preload("res://scripts/orbit_logic.gd")

## A simple, honest policy used for autoplay and bot tests. It only reads the public
## rule state and picks the angle where the nearest rock will cross the ring. It has no
## special access, so when it dies it dies the way a careful human could.


static func choose_target(logic) -> float:
	var best_t := INF
	var best_angle: float = logic.shield_a
	for rk in logic.rocks:
		if rk.r <= OrbitLogic.RING_R:
			continue
		var t: float = logic.arrival_time(rk)
		if t < best_t:
			best_t = t
			best_angle = logic.arrival_angle(rk)
	return best_angle


## Runs one full game with the bot and returns a summary. Used by tests and autoplay.
static func play_game(seed_value: int, mode: String, max_seconds: float, dt: float) -> Dictionary:
	var logic := OrbitLogic.new()
	logic.setup(mode, seed_value)
	var invariant_failures := 0
	var t := 0.0
	while not logic.over and t < max_seconds:
		logic.set_target(choose_target(logic))
		logic.step(dt)
		t += dt
		if logic.nova_charges > 0 and _should_nova(logic):
			logic.try_nova()
		invariant_failures += _check_invariants(logic)
		logic.drain_events()
	return {
		"score": logic.score, "blocks": logic.blocks, "best_combo": logic.best_combo,
		"wave": logic.wave(), "seconds": t, "over": logic.over,
		"invariant_failures": invariant_failures, "novas": logic.novas_used,
	}


static func _should_nova(logic) -> bool:
	# Use the charge only when several rocks are inside the danger band at once.
	var near := 0
	for rk in logic.rocks:
		if rk.r < OrbitLogic.RING_R + 260.0:
			near += 1
	return near >= 3


static func _check_invariants(logic) -> int:
	var bad := 0
	if logic.lives < 0 or logic.lives > 3:
		bad += 1
	if logic.combo < 0 or logic.score < 0:
		bad += 1
	for rk in logic.rocks:
		if rk.r < OrbitLogic.PLANET_R or rk.r > OrbitLogic.SPAWN_R + 1.0:
			bad += 1
		if rk.hp <= 0:
			bad += 1
	return bad
