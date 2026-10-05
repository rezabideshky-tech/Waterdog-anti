extends Node2D
class_name CarRig
## CarRig.gd — فیزیک ماشین (بدنهٔ صلب + دو چرخ با فنر و کمک‌فنر)
## حرکت روی زمین با نیروی مماسیِ چرخ‌ها، پرش، کنترل در هوا، واژگونی، بنزین و نیترو.

signal landed(impact: float, air_time: float)
signal crashed(impact: float)
signal flipped(air_time: float)
signal rollover()
signal fuel_empty()
signal nitro_used()

const GRAVITY := 1400.0        # پیکسل بر ثانیه² (هماهنگ با project.godot)
const PPM := 24.0              # پیکسل بر متر
const VISUAL := 0.55           # مقیاس تصویرِ ماشین در بازی
const AIR_DRAG := 7.0e-4
const MAX_ANG := 8.0

var stats := {}
var terrain = null
var car_id := "peykan"

# حالت بدنه
var vel := Vector2.ZERO
var ang_vel := 0.0
var mass := 1000.0
var inertia := 5.1e6
var drive_force := 780000.0
var susp_k := 90000.0
var susp_c := 4200.0
var drag := 7.0e-4

# ورودی
var throttle := 0.0
var brake := 0.0
var steer := 0.0
var nitro := false

# وضعیت
var on_ground := false
var wheels := []
var air_time := 0.0
var air_rot := 0.0
var flips := 0
var max_speed_kmh := 0.0
var fuel := 40.0
var fuel_max := 40.0
var nitro_charge := 1.0
var nitro_max := 1.0
var nitro_active := false
var damage := 0.0
var dead := false
var air_max := 0.0
var safe_pos := Vector2.ZERO
var safe_angle := 0.0
var _flip_timer := 0.0
var _crash_cool := 0.0

var _visual_root: Node2D
var _paint: Sprite2D
var _wheel_nodes: Array[Node2D] = []
var _dust_timer := 0.0
var _squat := 0.0
var _grip_mul := 1.0
var _fuel_warned := false
var world_scale := 1.0
var visual_scale := 1.0
var _top := 0.0
var _bottom := 0.0
var _left := 0.0
var _right := 0.0
var half_len := 200.0

func setup(id: String, ter) -> void:
	car_id = id
	terrain = ter
	stats = Game.car_stats(id)
	mass = 1000.0 * float(stats["mass"])
	# هندسهٔ واقعی از روی تصویر ماشین (تولید خودکار)
	var geo := CarGeometry.of(id)
	world_scale = float(stats["scale"]) * VISUAL
	var wb := float(geo["wb"]) * world_scale
	var wr := float(geo["wr"]) * world_scale
	var wy := float(geo["wy"]) * world_scale
	_top = float(geo["top"]) * world_scale
	_bottom = float(geo["bottom"]) * world_scale
	_left = float(geo["left"]) * world_scale
	_right = float(geo["right"]) * world_scale
	half_len = wb
	inertia = mass * (wb * wb * 0.09 + 1400.0)
	drive_force = float(stats["power"]) * mass * 780.0
	susp_k = 90.0 * mass * float(stats["susp"])
	susp_c = 1.5 * sqrt(susp_k * mass * 0.5)
	# سرعت نهایی از مشخصات ماشین
	var top_px: float = float(stats["top"]) / 0.115
	drag = (drive_force / mass) / maxf(top_px * top_px, 1.0)
	fuel_max = float(stats["fuel"])
	fuel = fuel_max
	nitro_max = 1.0 + 0.35 * float(Game.upg_level(id, "turbo"))
	nitro_charge = nitro_max
	var drive := 0.55 if float(stats["mass"]) < 1.9 else 0.85
	var geo_wr := float(geo["wr"])
	wheels = [
		{ "off": Vector2(-wb * 0.5, wy), "r": geo_wr, "pen": 0.0, "ground": false,
			"spin": 0.0, "slip": 0.0, "drive": drive, "load": 0.0 },
		{ "off": Vector2(wb * 0.5, wy), "r": geo_wr, "pen": 0.0, "ground": false,
			"spin": 0.0, "slip": 0.0, "drive": 1.0, "load": 0.0 },
	]
	_build_visual()

func _build_visual() -> void:
	if _visual_root:
		_visual_root.queue_free()
	_wheel_nodes.clear()
	_visual_root = Node2D.new()
	add_child(_visual_root)
	var s := world_scale
	_paint = Sprite2D.new()
	_paint.texture = UI.tex_at("res://assets/cars/%s_paint.png" % car_id)
	_paint.modulate = Game.paint_color()
	_paint.scale = Vector2(s, s)
	_paint.z_index = 2
	_visual_root.add_child(_paint)
	var detail := Sprite2D.new()
	detail.texture = UI.tex_at("res://assets/cars/%s_detail.png" % car_id)
	detail.scale = Vector2(s, s)
	detail.z_index = 3
	_visual_root.add_child(detail)
	visual_scale = s
	var ws := CarGeometry.wheel_scale(car_id, s)
	for i in wheels.size():
		var w: Dictionary = wheels[i]
		var holder := Node2D.new()
		holder.scale = Vector2(ws, ws)
		holder.position = w["off"]
		var tire := Sprite2D.new()
		tire.texture = UI.tex_at("res://assets/wheels/tire.png")
		tire.z_index = 1
		holder.add_child(tire)
		var rim := Sprite2D.new()
		rim.texture = UI.tex_at("res://assets/wheels/rim_%d.png" % Game.rim)
		rim.z_index = 2
		holder.add_child(rim)
		_visual_root.add_child(holder)
		_wheel_nodes.append(holder)

func refresh_look() -> void:
	if _paint:
		_paint.modulate = Game.paint_color()
	for h in _wheel_nodes:
		for c in h.get_children():
			if c is Sprite2D and c.z_index == 2:
				c.texture = UI.tex_at("res://assets/wheels/rim_%d.png" % Game.rim)

# ------------------------------------------------------------------ حلقهٔ فیزیک
func _physics_process(dt: float) -> void:
	if dead:
		_fall(dt)
		return
	_crash_cool = maxf(0.0, _crash_cool - dt)
	_update_nitro(dt)
	_update_fuel(dt)

	var force := Vector2(0.0, GRAVITY * mass)
	var torque := 0.0
	var any_ground := false
	var fwd := Vector2.RIGHT.rotated(rotation)
	var dir := signf(fwd.x)
	if dir == 0.0:
		dir = 1.0
	var grounded := _grounded()

	for i in wheels.size():
		var w: Dictionary = wheels[i]
		var rel: Vector2 = w["off"].rotated(rotation)
		var world := position + rel
		var r: float = w["r"] * world_scale
		var gy: float = _height(world.x)
		var pen: float = (world.y + r) - gy
		w["pen"] = maxf(0.0, pen)
		w["ground"] = pen > 0.0
		var spin_vel := Vector2(-rel.y, rel.x) * ang_vel
		var pvel := vel + spin_vel
		var n := _normal(world.x)
		var t := Vector2(-n.y, n.x)
		if t.x < 0.0:
			t = -t                       # مماس همیشه رو به جلو (راست)
		w["pen"] = maxf(0.0, pen)
		if pen > 0.0:
			any_ground = true
			var vn := pvel.dot(n)
			var f_n := susp_k * pen - susp_c * vn
			f_n = clampf(f_n, 0.0, mass * GRAVITY * 5.0)
			w["load"] = f_n
			force += n * f_n
			torque += rel.cross(n * f_n)
			var vt := pvel.dot(t)
			var grip: float = float(stats["grip"]) * _grip_mul
			var traction := f_n * grip * 1.25
			var f_t := 0.0
			if throttle > 0.03:
				f_t = drive_force * throttle * float(w["drive"]) * dir
			elif brake > 0.03:
				if vel.dot(fwd) > 30.0:
					f_t = -drive_force * brake * 0.85 * dir
				else:
					f_t = -drive_force * 0.5 * brake * dir
			# مقاومت غلتشی و ترمز موتور (بدون گاز)
			if throttle <= 0.03 and brake <= 0.03:
				f_t += -vt * mass * 0.35
			else:
				f_t += -vt * mass * 0.06
			var f_t_max := traction
			f_t = clampf(f_t, -f_t_max, f_t_max)
			force += t * f_t
			torque += rel.cross(t * f_t)
			w["slip"] = clampf(absf(f_t) / maxf(traction, 1.0), 0.0, 1.0)
			w["spin"] = float(w["spin"]) + (vt / maxf(r, 1.0)) * dt
		else:
			w["slip"] = 0.0
			w["load"] = 0.0
			# چرخ در هوا: گاز = چرخش عقب (جلو بالا)، ترمز = چرخش جلو
			var air_in := throttle - brake
			if absf(air_in) > 0.05:
				ang_vel -= air_in * dir * 4.4 * dt
			w["spin"] = float(w["spin"]) + (throttle - brake) * dir * 9.0 * dt

	# گشتاور فرمان (لمس افقی) — چرخش ملایم روی زمین
	if absf(steer) > 0.05:
		var spd := absf(vel.x)
		var factor := clampf(spd / 300.0, 0.0, 1.0)
		ang_vel += steer * dir * 1.6 * factor * dt

	# کمک تعادل روی زمین (به نفع بازیکن)
	if grounded:
		if absf(rotation) < deg_to_rad(65.0):
			var correct := -rotation * 1.6 - ang_vel * 0.35
			ang_vel += clampf(correct, -1.4, 1.4) * dt

	# ادغام
	var accel := force / mass
	vel += accel * dt
	vel -= vel * vel.length() * drag * dt
	ang_vel += (torque / inertia) * dt
	ang_vel = clampf(ang_vel, -MAX_ANG, MAX_ANG)
	ang_vel *= (1.0 - 4.0 * dt) if any_ground else (1.0 - 0.25 * dt)
	position += vel * dt
	rotation += ang_vel * dt

	_chassis_collision(dt)
	_update_air(dt, any_ground)
	_update_visual(dt, any_ground)
	max_speed_kmh = maxf(max_speed_kmh, absf(vel.x) * 0.115)
	_update_safe()

func _fall(dt: float) -> void:
	vel.y += GRAVITY * dt
	vel *= (1.0 - 1.0 * dt)
	position += vel * dt
	rotation += ang_vel * dt
	_update_visual(dt, false)

func _update_nitro(dt: float) -> void:
	var was := nitro_active
	nitro_active = nitro and nitro_charge > 0.015 and throttle > 0.15
	if nitro_active and not was:
		nitro_used.emit()
	if nitro_active:
		nitro_charge = maxf(0.0, nitro_charge - dt * 0.30)
	else:
		nitro_charge = minf(nitro_max, nitro_charge + dt * 0.055)
	nitro = nitro and nitro_charge > 0.015

func _update_fuel(dt: float) -> void:
	if throttle > 0.05:
		var drain := (0.30 + (0.22 if nitro_active else 0.0)) * throttle
		fuel = maxf(0.0, fuel - dt * drain)
	if fuel <= 0.0 and not _fuel_warned:
		_fuel_warned = true
		fuel_empty.emit()

## برخورد بدنه (سقف/دماغه/عقب) با زمین
func _chassis_collision(dt: float) -> void:
	var pts := [
		Vector2(_left * 0.92, _top * 0.9), Vector2(_right * 0.92, _top * 0.9), Vector2(0, _top),
		Vector2(_left, _bottom * 0.85), Vector2(_right, _bottom * 0.85), Vector2(0, _bottom),
	]
	for p in pts:
		var rel: Vector2 = p.rotated(rotation)
		var world := position + rel
		var gy: float = _height(world.x)
		if world.y <= gy:
			continue
		var n := _normal(world.x)
		var vn := (vel + Vector2(-rel.y, rel.x) * ang_vel).dot(n)
		var depth := world.y - gy
		position += n * minf(depth, 10.0) * dt * 18.0
		if vn < 0.0:
			vel -= n * vn * 1.3
			vel *= 0.84
			ang_vel *= 0.6
			var impact := absf(vn) * 0.09 + depth * 0.25
			if _crash_cool <= 0.0 and impact > 9.0:
				_crash_cool = 0.5
				damage = minf(1.0, damage + impact * 0.0035)
				crashed.emit(impact)
		break

func _update_air(dt: float, grounded: bool) -> void:
	if grounded:
		if air_time > 0.45:
			air_max = maxf(air_max, air_time)
			landed.emit(air_time * 60.0, air_time)
		if air_time > 0.5 and absf(air_rot) > TAU * 0.88:
			flips += 1
			flipped.emit(air_time)
		air_time = 0.0
		air_rot = 0.0
	else:
		air_time += dt
		air_rot += ang_vel * dt
	# واژگونی: برعکس و بی‌حرکت
	var up := Vector2.UP.rotated(rotation)
	if up.y > -0.12 and absf(vel.x) < 70.0 and grounded and absf(rotation) > 1.6:
		_flip_timer += dt
		if _flip_timer > 2.4:
			_flip_timer = 0.0
			rollover.emit()
	else:
		_flip_timer = maxf(0.0, _flip_timer - dt * 0.6)

func _update_safe() -> void:
	if on_ground and absf(ang_vel) < 2.5 and Vector2.UP.rotated(rotation).y < -0.7:
		safe_pos = position
		safe_angle = rotation

func respawn() -> void:
	position = safe_pos + Vector2(0, -60)
	rotation = safe_angle
	vel = Vector2.ZERO
	ang_vel = 0.0
	damage = maxf(0.0, damage - 0.25)

func refuel() -> void:
	fuel = fuel_max

# ------------------------------------------------------------------ کمک‌ها
func _height(x: float) -> float:
	if terrain and terrain.has_method("height_at"):
		return terrain.height_at(x)
	return 400.0

func _normal(x: float) -> Vector2:
	var d := 16.0
	var h1: float = _height(x - d)
	var h2: float = _height(x + d)
	var t := Vector2(d * 2.0, h2 - h1).normalized()
	return Vector2(t.y, -t.x)

func _grounded() -> bool:
	if terrain == null or wheels.is_empty():
		return false
	for w in wheels:
		var rel: Vector2 = w["off"].rotated(rotation)
		var world := position + rel
		if world.y + float(w["r"]) * world_scale > _height(world.x):
			on_ground = true
			return true
	on_ground = false
	return false

func speed_kmh() -> float:
	return absf(vel.x) * 0.115

func rpm_ratio() -> float:
	var top: float = maxf(float(stats.get("top", 130)) / 0.115, 1.0)
	return clampf(absf(vel.x) / top, 0.0, 1.0)

func set_grip(mul: float) -> void:
	_grip_mul = mul

# ------------------------------------------------------------------ تصویر
func _update_visual(dt: float, grounded: bool) -> void:
	if _visual_root == null:
		return
	for i in wheels.size():
		var w: Dictionary = wheels[i]
		var holder: Node2D = _wheel_nodes[i]
		var off: Vector2 = w["off"]
		holder.position = off + Vector2(0, minf(float(w["pen"]), 18.0) * 0.5)
		holder.rotation = float(w["spin"])
	# نشستن بدنه هنگام گاز/ترمز
	var target := 0.0
	if grounded:
		target = (brake - throttle) * 0.06
	_squat = lerpf(_squat, target, clampf(dt * 6.0, 0.0, 1.0))
	_visual_root.rotation = _squat
	_visual_root.position = Vector2(0, absf(_squat) * 30.0)
	# گرد و خاک
	_dust_timer -= dt
	if _dust_timer <= 0.0:
		var slip := false
		for w in wheels:
			if w["ground"] and float(w["slip"]) > 0.55:
				slip = true
		if (slip and absf(vel.x) > 40.0) or (nitro_active and absf(vel.x) > 80.0):
			_dust_timer = 0.045
			var back: Vector2 = wheels[0]["off"] * 0.9
			_spawn_puff(position + back.rotated(rotation), nitro_active)

func _spawn_puff(at: Vector2, hot: bool) -> void:
	var p := Sprite2D.new()
	p.texture = UI.tex_at("res://assets/fx/%s.png" % ("spark" if hot else "dust"))
	p.position = at
	p.z_index = 1
	p.scale = Vector2(0.45, 0.45)
	p.modulate = Color(1, 1, 1, 0.7)
	add_child(p)
	var tw := p.create_tween().set_parallel(true)
	tw.tween_property(p, "scale", Vector2(1.4, 1.4), 0.5)
	tw.tween_property(p, "modulate:a", 0.0, 0.55)
	tw.tween_property(p, "position:y", at.y - 18.0, 0.55)
	tw.chain().tween_callback(p.queue_free)
