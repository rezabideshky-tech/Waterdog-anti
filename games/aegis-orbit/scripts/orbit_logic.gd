extends RefCounted
## Pure game rules for Aegis Orbit. No nodes, no rendering, no input and no clock:
## everything advances through step(dt) and an injected seed. That is what lets the
## whole rule set run under headless tests, and it is what makes the daily sky
## identical for every player on the same date (the spawn schedule depends only on
## elapsed time and the seeded RNG, never on what the player did).
##
## Geometry is polar around the planet centre, in abstract units:
##   PLANET_R  planet surface         RING_R   shield ring (catch line)
##   SPAWN_R   where rocks appear     angles in radians, 0 = right, +y is down (screen)

const PLANET_R := 100.0
const RING_R := 150.0
const SPAWN_R := 640.0
const BOUNCE_R := RING_R + 90.0
const SHIELD_HALF := 0.42
const SHIELD_SPEED := 7.0
const WAVE_SECONDS := 18.0
const MAX_NOVA := 2
const MAX_STEP := 0.1

const POINTS := {"rock": 10, "armor": 25, "comet": 20, "nova": 5}

var mode: String = "classic"
var seed_value: int = 0
var lives: int = 3
var score: int = 0
var combo: int = 0
var best_combo: int = 0
var blocks: int = 0
var armor_breaks: int = 0
var novas_used: int = 0
var nova_charges: int = 0
var elapsed: float = 0.0
var over: bool = false
var shield_a: float = 0.0
var target_a: float = 0.0
## Each rock: {id:int, kind:String, r:float, a:float, omega:float, v:float, hp:int}
var rocks: Array = []
## Drained by the view once per frame: {type:String, ...}
var events: Array = []

var _rng := RandomNumberGenerator.new()
var _next_id := 1
var _spawn_timer := 0.8
var _burst_left := 0
var _burst_timer := 0.0
var _wave_seen := 1
var _nova_enabled := true


# ---------------------------------------------------------------- setup ----

static func daily_seed(date_key: String) -> int:
	## date_key is "YYYY-MM-DD" in the player's LOCAL calendar (LESSONS L31).
	var parts := date_key.split("-")
	if parts.size() != 3:
		return 1
	var value := int(parts[0]) * 10000 + int(parts[1]) * 100 + int(parts[2])
	return value * 7919 + 12345


static func mode_lives(p_mode: String) -> int:
	return 2 if p_mode == "daily" else 3


static func mode_nova_enabled(p_mode: String) -> bool:
	return p_mode != "daily"


func setup(p_mode: String, p_seed: int) -> void:
	mode = p_mode
	seed_value = p_seed
	_rng.seed = p_seed
	lives = mode_lives(p_mode)
	_nova_enabled = mode_nova_enabled(p_mode)
	score = 0
	combo = 0
	best_combo = 0
	blocks = 0
	armor_breaks = 0
	novas_used = 0
	nova_charges = 0
	elapsed = 0.0
	over = false
	shield_a = 0.0
	target_a = 0.0
	rocks.clear()
	events.clear()
	_next_id = 1
	_spawn_timer = 0.8
	_burst_left = 0
	_burst_timer = 0.0
	_wave_seen = 1


# ------------------------------------------------------------- queries ----

func wave() -> int:
	return 1 + int(elapsed / WAVE_SECONDS)


func multiplier() -> int:
	if combo >= 20:
		return 4
	if combo >= 10:
		return 3
	if combo >= 5:
		return 2
	return 1


func spawn_interval(w: int) -> float:
	return maxf(0.42, 1.25 - 0.09 * float(w - 1))


func nova_enabled() -> bool:
	return _nova_enabled


func shield_contains(a: float) -> bool:
	return absf(wrapf(a - shield_a, -PI, PI)) <= SHIELD_HALF


## Angle where a rock will cross the ring if nothing changes. Used by the bot and the view.
func arrival_angle(rk: Dictionary) -> float:
	var t: float = (rk.r - RING_R) / rk.v
	return wrapf(rk.a + rk.omega * t, -PI, PI)


func arrival_time(rk: Dictionary) -> float:
	return (rk.r - RING_R) / rk.v


# ------------------------------------------------------------- actions ----

func set_target(a: float) -> void:
	target_a = wrapf(a, -PI, PI)


func try_nova() -> bool:
	if over or not _nova_enabled or nova_charges <= 0:
		return false
	nova_charges -= 1
	novas_used += 1
	var kept: Array = []
	var cleared := 0
	var m := multiplier()
	for rk in rocks:
		if rk.r > RING_R:
			score += POINTS["nova"] * m
			cleared += 1
			events.append({"type": "destroy", "kind": rk.kind, "a": rk.a, "r": rk.r})
		else:
			kept.append(rk)
	rocks = kept
	events.append({"type": "nova", "cleared": cleared})
	return true


func drain_events() -> Array:
	var out := events
	events = []
	return out


# ----------------------------------------------------------- simulation ----

func step(dt: float) -> void:
	if over or dt <= 0.0:
		return
	dt = minf(dt, MAX_STEP)
	elapsed += dt
	_update_shield(dt)
	_update_spawns(dt)
	_update_rocks(dt)


func _update_shield(dt: float) -> void:
	var diff := wrapf(target_a - shield_a, -PI, PI)
	var max_move := SHIELD_SPEED * dt
	shield_a = wrapf(shield_a + clampf(diff, -max_move, max_move), -PI, PI)


func _update_spawns(dt: float) -> void:
	var w := wave()
	if w != _wave_seen:
		_wave_seen = w
		events.append({"type": "wave", "wave": w})
		if w % 4 == 0:
			_burst_left = 5
			_burst_timer = 0.0
	_spawn_timer -= dt
	if _spawn_timer <= 0.0:
		_spawn_one()
		_spawn_timer = spawn_interval(w) * (0.85 + 0.3 * _rng.randf())
	if _burst_left > 0:
		_burst_timer -= dt
		if _burst_timer <= 0.0:
			_spawn_one()
			_burst_left -= 1
			_burst_timer = 0.22


func _spawn_one() -> void:
	var w := wave()
	var roll := _rng.randf()
	var armor_p := 0.0
	if w >= 3:
		armor_p = minf(0.45, 0.12 + 0.06 * float(w - 3))
	var comet_p := 0.12 if w >= 5 else 0.0
	var kind := "rock"
	if roll < comet_p:
		kind = "comet"
	elif roll < comet_p + armor_p:
		kind = "armor"
	var v := minf(180.0, 75.0 + 14.0 * float(w - 1))
	if kind == "comet":
		v *= 1.9
	elif kind == "armor":
		v *= 0.7
	var omega := 0.0
	if w >= 4 and kind != "comet" and _rng.randf() < 0.45:
		omega = _rng.randf_range(0.25, 0.7) * (1.0 if _rng.randf() < 0.5 else -1.0)
	var a := _rng.randf_range(-PI, PI)
	rocks.append({
		"id": _next_id, "kind": kind, "r": SPAWN_R, "a": a,
		"omega": omega, "v": v, "hp": 2 if kind == "armor" else 1,
	})
	_next_id += 1


func _update_rocks(dt: float) -> void:
	var keep: Array = []
	for rk in rocks:
		var prev_r: float = rk.r
		var new_r: float = prev_r - rk.v * dt
		rk.a = wrapf(rk.a + rk.omega * dt, -PI, PI)
		if prev_r > RING_R and new_r <= RING_R and shield_contains(rk.a):
			rk.hp -= 1
			if rk.hp > 0:
				# armored rock: knocked back out to the bounce radius, comes in again
				rk.r = BOUNCE_R
				events.append({"type": "armor_hit", "a": rk.a, "r": RING_R})
				keep.append(rk)
			else:
				_score_block(rk)
			continue
		if new_r <= PLANET_R:
			_on_leak(rk)
			continue
		rk.r = new_r
		keep.append(rk)
	rocks = keep


func _score_block(rk: Dictionary) -> void:
	var pts: int = POINTS[rk.kind] * multiplier()
	score += pts
	combo += 1
	blocks += 1
	if rk.kind == "armor":
		armor_breaks += 1
	best_combo = maxi(best_combo, combo)
	events.append({"type": "block", "kind": rk.kind, "a": rk.a, "r": RING_R, "pts": pts, "combo": combo})
	if _nova_enabled and combo % 10 == 0 and nova_charges < MAX_NOVA:
		nova_charges += 1
		events.append({"type": "nova_ready", "charges": nova_charges})


func _on_leak(rk: Dictionary) -> void:
	if over:
		return
	lives -= 1
	combo = 0
	events.append({"type": "leak", "a": rk.a, "kind": rk.kind, "lives": lives})
	if lives <= 0:
		over = true
		events.append({"type": "gameover", "score": score})
