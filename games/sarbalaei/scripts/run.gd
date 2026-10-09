extends Node2D
## One drive on one route. Builds the world, applies the rules every physics step,
## then hands the outcome to the result screen.

const Terrain = preload("res://scripts/terrain.gd")
const Car = preload("res://scripts/car.gd")
const Pickup = preload("res://scripts/pickup.gd")
const Background = preload("res://scripts/background.gd")
const Hud = preload("res://scripts/hud.gd")
const Rules = preload("res://scripts/run_rules.gd")
const TerrainGen = preload("res://scripts/terrain_gen.gd")
const Data = preload("res://scripts/data.gd")

const START_X := 200.0
const FALL_Y := 1500.0
const PICKUP_RADIUS := 62.0
const RESULT_DELAY_S := 1.4

var route: Dictionary = {}
var car_spec: Dictionary = {}
var length_px := 0.0
var points: PackedVector2Array = PackedVector2Array()

var car
var cam: Camera2D
var hud
var terrain
var background
var pickups: Array = []

var coins := 0
var coins_taken := 0
var coins_total := 0
var fuel := 100.0
var elapsed := 0.0
var best_x := START_X
var running := false
var stuck_t := 0.0
var crash_t := 0.0
var gas_held := false
var brake_held := false


func _ready() -> void:
	route = Data.route_by_id(App.pending_route_id)
	car_spec = Data.car_by_id(App.pending_car_id)
	length_px = float(route.length)
	points = TerrainGen.generate(int(route.seed), length_px, float(route.amp), float(route.slope))

	background = Background.new()
	add_child(background)
	background.setup(route)

	terrain = Terrain.new()
	add_child(terrain)
	terrain.setup(points, Color(String(route.soil)), Color(String(route.grass)))

	var layout := TerrainGen.pickups(points, int(route.seed), length_px, float(route.fuel_every))
	for p in layout.coins:
		_spawn_pickup(p, "coin")
	for p in layout.fuel:
		_spawn_pickup(p, "fuel")
	coins_total = layout.coins.size()

	car = Car.new()
	car.position = Vector2(START_X, TerrainGen.ground_y_at(points, START_X) - 70.0)
	add_child(car)
	var color_index := int(Save.data.get("car_color", 0))
	car.build(car_spec, Data.color_for(car_spec, color_index))
	car.landed.connect(_on_landed)
	fuel = float(car_spec.tank)

	cam = Camera2D.new()
	cam.position_smoothing_enabled = true
	cam.position_smoothing_speed = 5.0
	cam.zoom = Vector2(0.85, 0.85)
	add_child(cam)
	cam.make_current()
	background.cam = cam

	hud = Hud.new()
	add_child(hud)
	hud.gas_changed.connect(func(v: bool): gas_held = v)
	hud.brake_changed.connect(func(v: bool): brake_held = v)

	running = true


func _process(_delta: float) -> void:
	# Coins spin like flipping coins. Cheap and purely visual.
	for p in pickups:
		if p.kind == "coin" and not p.taken:
			p.node.scale = Vector2(absf(cos(elapsed * 3.0 + p.pos.x * 0.01)), 1.0)


func _physics_process(delta: float) -> void:
	if not running:
		return
	elapsed += delta
	var g := gas_held or Input.is_action_pressed("ui_right")
	var b := brake_held or Input.is_action_pressed("ui_left")
	car.set_inputs(1.0 if g else 0.0, 1.0 if b else 0.0)

	var speed: float = car.speed_px()
	fuel = maxf(0.0, fuel - Rules.fuel_used(car.gas, speed, delta, float(car_spec.fuel_rate)))
	_collect_pickups()

	var pos: Vector2 = car.body_position()
	best_x = maxf(best_x, pos.x)
	cam.global_position = pos + Vector2(220.0, -60.0)
	hud.set_state({
		"progress": clampf((best_x - START_X) / (length_px - START_X), 0.0, 1.0),
		"fuel": fuel / float(car_spec.tank),
		"coins": coins,
		"kmh": Rules.kmh(speed),
	})

	if pos.x >= length_px:
		_end(true, "finished")
		return
	if pos.y > FALL_Y:
		_end(false, "fell")
		return
	crash_t = crash_t + delta if (car.chassis_on_ground() and Rules.is_flipped(car.body_angle())) else 0.0
	if crash_t >= 1.2:
		_end(false, "flipped")
		return
	stuck_t = stuck_t + delta if (g and speed < 6.0) else 0.0
	if Rules.is_stuck(speed, car.gas, stuck_t):
		_end(false, "stuck")
		return
	if fuel <= 0.0 and speed < 25.0:
		_end(false, "no_fuel")


func _spawn_pickup(pos: Vector2, kind: String) -> void:
	var node := Pickup.new()
	node.kind = kind
	node.position = pos
	add_child(node)
	pickups.append({"node": node, "kind": kind, "taken": false, "pos": pos})


func _collect_pickups() -> void:
	var pos: Vector2 = car.body_position()
	for p in pickups:
		if p.taken:
			continue
		if pos.distance_to(p.pos) < PICKUP_RADIUS:
			p.taken = true
			p.node.visible = false
			if p.kind == "coin":
				coins_taken += 1
				coins += Rules.COIN_VALUE
			else:
				fuel = minf(float(car_spec.tank), fuel + Rules.FUEL_CAN)
				hud.toast(Strings.t("toast_fuel"))


func _on_landed(air_s: float, spin: float) -> void:
	var bonus := Rules.air_bonus(air_s, spin)
	if bonus > 0:
		coins += bonus
		hud.toast(Strings.t("toast_air") + "  +" + Strings.num(bonus))


func _end(finished: bool, reason: String) -> void:
	if not running:
		return
	running = false
	car.set_inputs(0.0, 0.0)

	var dist_m := Rules.distance_m(minf(best_x, length_px) - START_X)
	var stars := Rules.stars(finished, Rules.coin_ratio(coins_taken, coins_total), elapsed, float(route.par_s))
	var payout := coins + int(dist_m)
	Save.add_coins(payout)
	var best_m := Save.record_run(String(route.id), dist_m, stars)

	App.last_result = {
		"route_id": String(route.id),
		"route_name": String(route.name_key),
		"finished": finished,
		"reason": reason,
		"distance_m": dist_m,
		"best_m": best_m,
		"coins": payout,
		"coins_taken": coins_taken,
		"coins_total": coins_total,
		"stars": stars,
		"time_s": elapsed,
		"par_s": float(route.par_s),
	}

	# A short slow-motion beat so the crash or the finish lands, then the postcard.
	Engine.time_scale = 0.4
	await get_tree().create_timer(RESULT_DELAY_S, true, false, true).timeout
	Engine.time_scale = 1.0
	App.goto(App.SCENE_RESULT)
