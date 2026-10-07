extends Node2D
class_name Level
## Level.gd — ساخت یک مرحلهٔ کامل: آسمان، لایه‌های پس‌زمینه، زمین، ماشین، دوربین و HUD

signal finished(dist: float, coins: int, gems: int, stunts: int, air_max: float)
signal failed(reason: String)
signal paused()
signal resumed()

const CORRECT := 0.0

var level_id := "tehran"
var level := {}
var terrain: Terrain
var car: CarRig
var camera: Camera2D
var hud: Node
var sky: Node2D
var world_root: Node2D
var bg_layers: Array[Node2D] = []
var coins := 0
var gems := 0
var stunts := 0
var air_max := 0.0
var started := false
var ended := false
var elapsed := 0.0
var combo := 0
var combo_timer := 0.0
var _item_cursor := 0
var _item_timer := 0.0

func setup(id: String, seed_value: int) -> void:
	level_id = id
	level = Game.level_data(id).duplicate(true)
	_setup_sky()
	world_root = Node2D.new()
	world_root.name = "World"
	add_child(world_root)
	terrain = Terrain.new()
	terrain.name = "Terrain"
	world_root.add_child(terrain)
	terrain.build(level, seed_value)
	_setup_car()
	_setup_camera()

func _setup_sky() -> void:
	sky = Node2D.new()
	sky.name = "Sky"
	add_child(sky)

func _ready() -> void:
	pass

func start() -> void:
	started = true
	elapsed = 0.0
	Snd.start_engine()

func _process(dt: float) -> void:
	if not started or ended:
		return
	elapsed += dt
	if combo_timer > 0.0:
		combo_timer -= dt
		if combo_timer <= 0.0:
			combo = 0
	if car == null:
		return
	# سرعت موتور صدا
	Snd.engine_rpm(car.rpm_ratio() * (1.18 if car.nitro_active else 1.0))
	# سکه‌ها و آیتم‌ها (فقط نزدیک بازیکن، برای کارایی روی موبایل)
	_item_timer -= dt
	if _item_timer <= 0.0:
		_item_timer = 0.15
		_window_items()
		_check_items()
	# دوربین
	if camera:
		var target := car.position + Vector2(clampf(car.vel.x * 0.55, -260.0, 320.0), -120.0 - clampf(absf(car.vel.y) * 0.1, 0.0, 90.0))
		target.y = minf(target.y, terrain.height_at(car.position.x) - 90.0)
		camera.position = camera.position.lerp(target, clampf(dt * 4.2, 0.0, 1.0))
		var spd := car.speed_kmh()
		camera.zoom = camera.zoom.lerp(Vector2.ONE * (1.02 - clampf(spd / 140.0, 0.0, 0.16)), dt * 2.0)
	# افکت حرکت لایه‌های پس‌زمینه
	_update_bg()
	# پایان با بنزین
	if car.fuel <= 0.0 and absf(car.vel.x) < 8.0 and started:
		_fail("سوخت تمام شد")
	# پایان مسیر
	if car.position.x >= terrain.finish_x() and not ended:
		_finish()

func _setup_car() -> void:
	car = CarRig.new()
	car.name = "Car"
	car.position = Vector2(120.0, terrain.height_at(120.0) - 60.0)
	world_root.add_child(car)
	car.setup(Game.car, terrain)
	car.safe_pos = car.position
	car.landed.connect(_on_landed)
	car.crashed.connect(_on_crash)
	car.flipped.connect(_on_flip)
	car.rollover.connect(_on_rollover)
	car.fuel_empty.connect(func(): if hud and hud.has_method("toast"): hud.toast("سوخت تمام شد!"))

func _setup_camera() -> void:
	camera = Camera2D.new()
	camera.name = "Cam"
	camera.position = car.position
	camera.position_smoothing_enabled = false
	camera.limit_bottom = 900
	add_child(camera)
	camera.make_current()

func _update_bg() -> void:
	var cam_x := camera.position.x if camera else 0.0
	for layer in bg_layers:
		var speed: float = layer.get_meta("speed", 0.1)
		layer.position.x = cam_x * speed

func add_bg_layer(tex: Texture2D, speed: float, y: float, scale_mul: float, z: int) -> void:
	if tex == null:
		return
	var l := Node2D.new()
	l.set_meta("speed", speed)
	l.z_index = z
	var vw := 1280.0
	var tile := tex.get_width() * scale_mul
	var n := int(ceil(vw / tile)) + 3
	for i in n:
		var s := Sprite2D.new()
		s.texture = tex
		s.scale = Vector2(scale_mul, scale_mul)
		s.centered = false
		s.position = Vector2(-vw * 0.5 + i * tile, y)
		l.add_child(s)
	sky.add_child(l)
	bg_layers.append(l)

## آیتم‌های نزدیک را فعال (نمایان + انیمیشن) می‌کند
func _window_items() -> void:
	var cx := car.position.x
	var items: Array = terrain.items
	while _item_cursor < items.size() and float(items[_item_cursor]["pos"].x) < cx - 900.0:
		_item_cursor += 1
	var i := _item_cursor
	while i < items.size() and float(items[i]["pos"].x) < cx + 1600.0:
		if not items[i]["taken"]:
			terrain.animate_item(items[i])
		i += 1

func _check_items() -> void:
	var cx := car.position.x
	var items: Array = terrain.items
	var i := _item_cursor
	while i < items.size() and float(items[i]["pos"].x) < cx + 260.0:
		var it: Dictionary = items[i]
		i += 1
		if it["taken"]:
			continue
		var p: Vector2 = it["pos"]
		if p.distance_to(car.position + Vector2(0, -6)) < 62.0 or absf(p.x - cx) < 40.0 and absf(p.y - car.position.y) < 90.0:
			_take_item(it)

func _take_item(it: Dictionary) -> void:
	it["taken"] = true
	var node: Node2D = it["node"]
	if node and is_instance_valid(node):
		var tw := node.create_tween()
		tw.tween_property(node, "position:y", node.position.y - 40.0, 0.28)
		tw.parallel().tween_property(node, "modulate:a", 0.0, 0.28)
		tw.parallel().tween_property(node, "scale", Vector2(1.35, 1.35), 0.28)
		tw.tween_callback(node.queue_free)
	match str(it["kind"]):
		"fuel":
			car.fuel = minf(car.fuel_max, car.fuel + car.fuel_max * 0.45)
			_popup(car.position + Vector2(0, -70), "بنزین پر شد", Color(1, 0.86, 0.3))
			Snd.play("coin")
		"nitro":
			car.nitro_charge = car.nitro_max
			_popup(car.position + Vector2(0, -70), "نیترو پر شد!", Color(0.45, 0.85, 1))
			Snd.play("pop")
		"gem":
			gems += 1
			_popup(car.position + Vector2(0, -80), "الماس +۱", Color(0.6, 0.9, 1))
			Snd.play("gem")
		_:
			coins += 1
			if hud and hud.has_method("add_coin"):
				hud.add_coin(1)
			Snd.play("coin", randf_range(0.95, 1.1))

func _popup(pos: Vector2, text: String, col: Color) -> void:
	if world_root == null:
		return
	var l := UI.label(text, 30, col)
	l.position = pos
	world_root.add_child(l)
	var tw := l.create_tween().set_parallel(true)
	tw.tween_property(l, "position:y", pos.y - 60.0, 0.85)
	tw.tween_property(l, "modulate:a", 0.0, 0.85).set_delay(0.25)
	tw.chain().tween_callback(l.queue_free)

func _on_landed(impact: float, air: float) -> void:
	air_max = maxf(air_max, air)
	if air > 0.55:
		stunts += 1
		var bonus := int(2 + air * 4.0)
		coins += bonus
		if hud and hud.has_method("add_coin"):
			hud.add_coin(bonus)
		_popup(car.position + Vector2(0, -110), "پرش عالی! +%d" % bonus, Color(0.6, 1, 0.7))
		combo = minf(combo + 1, 9)
		combo_timer = 4.0
		Snd.play("land")
		if hud and hud.has_method("camera_shake"):
			hud.camera_shake(minf(air * 5.0, 8.0))
	if camera:
		_shake(minf(air * 4.0, 7.0), 0.22)

func _on_flip(air: float) -> void:
	coins += 25
	if hud and hud.has_method("add_coin"):
		hud.add_coin(25)
	_popup(car.position + Vector2(0, -140), "چرخش کامل! +۲۵", Color(1, 0.8, 0.3))
	Snd.play("win")

func _on_crash(impact: float) -> void:
	Snd.play("crash")
	_shake(minf(impact * 0.35, 10.0), 0.3)
	_popup(car.position + Vector2(0, -100), "آخ!", Color(1, 0.5, 0.4))
	if car.damage >= 1.0:
		_fail("ماشین داغون شد")

func _on_rollover() -> void:
	_popup(car.position + Vector2(0, -120), "دوباره!", Color(1, 0.9, 0.5))
	Snd.play("fail")
	car.respawn()
	if camera:
		camera.position = car.position

func _shake(power: float, dur: float) -> void:
	if camera == null:
		return
	var base := camera.offset
	var t := create_tween()
	var steps := 6
	for i in steps:
		t.tween_property(camera, "offset", Vector2(randf_range(-power, power), randf_range(-power, power)), dur / steps)
	t.tween_property(camera, "offset", base, 0.05)

func distance_m() -> float:
	if car == null:
		return 0.0
	return maxf(0.0, car.position.x) / Terrain.PPM

func progress() -> float:
	return clampf(car.position.x / maxf(1.0, terrain.finish_x()), 0.0, 1.0)

func _finish() -> void:
	if ended:
		return
	ended = true
	Snd.stop_engine()
	Snd.play("win")
	finished.emit(distance_m(), coins, gems, stunts, air_max)

func _fail(reason: String) -> void:
	if ended:
		return
	ended = true
	Snd.stop_engine()
	Snd.play("fail")
	failed.emit(reason)
