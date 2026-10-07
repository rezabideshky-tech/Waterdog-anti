extends Node2D
class_name Terrain
## Terrain.gd — زمین بی‌پایان ساخته‌شده با کد (تپه، دره، جادهٔ صاف)
## پروفایل ارتفاع با چند لایه سینوس بذردار ساخته می‌شود تا هر اجرا کمی تفاوت داشته باشد.

const STEP := 22.0          # فاصلهٔ نمونه‌گیری
const PPM := 24.0           # پیکسل بر متر
const BASE_Y := 420.0

var level := {}
var run_seed := 1
var points := PackedVector2Array()
var items := []             # { kind: coin|fuel|nitro|gem, pos: Vector2, taken: bool, node: Node2D }
var decorations := []
var _line: Line2D
var _edge: Line2D
var _fill_poly: Polygon2D
var _coin_tex: Texture2D
var _fuel_tex: Texture2D
var _nitro_tex: Texture2D
var _gem_tex: Texture2D
var rng := RandomNumberGenerator.new()
var total_world := 0.0

func build(level_data: Dictionary, seed_value: int) -> void:
	level = level_data
	run_seed = seed_value
	rng.seed = seed_value
	_coin_tex = UI.tex("icon_coin")
	_fuel_tex = UI.tex("icon_fuel")
	_nitro_tex = UI.tex("icon_nitro")
	_gem_tex = UI.tex("icon_gem")
	var total_px := float(level["length"]) * PPM + 1400.0
	total_world = total_px
	points = PackedVector2Array()
	var x := -600.0
	var hills := float(level["hills"])
	var rough := float(level["rough"])
	var phases := [rng.randf() * TAU, rng.randf() * TAU, rng.randf() * TAU, rng.randf() * TAU]
	var amps := [52.0 * hills, 26.0 * hills * rough, 12.0 * rough, 5.0 * rough]
	var wavs := [520.0, 240.0, 110.0, 46.0]
	while x < total_px:
		var y := BASE_Y
		# ناحیهٔ شروع صاف
		var ease_start := clampf((x - 300.0) / 900.0, 0.0, 1.0)
		# چند بخش صاف (جادهٔ آسفالت)
		var flat := sin(x / 1700.0 + phases[0]) > 0.55
		var flat_mul := 0.25 if flat else 1.0
		for i in amps.size():
			y += sin(x / wavs[i] + phases[i]) * amps[i] * ease_start * flat_mul
		# شیب کلی رو به بالا (سربالایی ملایم)
		y -= sin(x / 4200.0) * 60.0 * ease_start
		points.append(Vector2(x, y))
		x += STEP
	_draw_terrain()
	_place_decor()
	_place_items()

# --------------------------------------------------------------- ارتفاع
func height_at(x: float) -> float:
	if points.size() < 2:
		return BASE_Y
	var i := int(floor((x - points[0].x) / STEP))
	if i < 0:
		return points[0].y
	if i >= points.size() - 1:
		return points[points.size() - 1].y
	var a := points[i]
	var b := points[i + 1]
	var t := clampf((x - a.x) / STEP, 0.0, 1.0)
	return lerpf(a.y, b.y, t)

func slope_at(x: float) -> float:
	var d := 16.0
	return (height_at(x + d) - height_at(x - d)) / (d * 2.0)

func grip_at(x: float) -> float:
	match str(level.get("terrain", "asphalt")):
		"asphalt": return 1.0
		"grass": return 0.88
		"sand": return 0.74
		"snow": return 0.72
		"rock": return 0.92
		_: return 0.9

func finish_x() -> float:
	return float(level["length"]) * PPM

# --------------------------------------------------------------- ساخت تصویر
func _draw_terrain() -> void:
	var surface: Color = Color(str(level["surface"]))
	var fill: Color = Color(str(level["fill"]))
	# بدنهٔ زمین
	_fill_poly = Polygon2D.new()
	var poly := PackedVector2Array()
	for p in points:
		poly.append(p)
	poly.append(Vector2(points[points.size() - 1].x, BASE_Y + 1400.0))
	poly.append(Vector2(points[0].x, BASE_Y + 1400.0))
	_fill_poly.polygon = poly
	_fill_poly.color = fill
	add_child(_fill_poly)
	# لایهٔ زیرین تیره‌تر (عمق)
	var deep := Polygon2D.new()
	var dpoly := PackedVector2Array()
	for p in points:
		dpoly.append(Vector2(p.x, p.y + 120.0))
	dpoly.append(Vector2(points[points.size() - 1].x, BASE_Y + 1400.0))
	dpoly.append(Vector2(points[0].x, BASE_Y + 1400.0))
	deep.polygon = dpoly
	deep.color = Color(fill.r * 0.65, fill.g * 0.6, fill.b * 0.55, 1.0)
	add_child(deep)
	# نوار سطح با تکسچر اختصاصی
	_edge = Line2D.new()
	_edge.points = points
	_edge.width = 14.0
	_edge.default_color = surface.darkened(0.25)
	_edge.joint_mode = Line2D.LINE_JOINT_ROUND
	add_child(_edge)
	_line = Line2D.new()
	_line.points = points
	_line.width = 22.0
	var t := UI.tex_at("res://assets/terrain/%s.png" % str(level["terrain"]))
	if t != null:
		_line.texture = t
		_line.texture_mode = Line2D.LINE_TEXTURE_TILE
	_line.default_color = surface
	_line.joint_mode = Line2D.LINE_JOINT_ROUND
	add_child(_line)

func _place_decor() -> void:
	var deco: Array = level.get("deco", [])
	if deco.is_empty():
		return
	var x := 260.0
	while x < total_world:
		var name := str(deco[rng.randi() % deco.size()])
		var t := UI.tex_at("res://assets/deco/%s.png" % name)
		if t != null:
			var s := Sprite2D.new()
			s.texture = t
			var sc := rng.randf_range(0.55, 1.15)
			s.scale = Vector2(sc, sc)
			s.position = Vector2(x, height_at(x) + 6.0)
			s.offset = Vector2(0, -t.get_height() * 0.5)
			s.flip_h = rng.randf() < 0.5
			s.z_index = -1 if rng.randf() < 0.5 else 0
			add_child(s)
			decorations.append(s)
		x += rng.randf_range(180.0, 420.0)

func _place_items() -> void:
	var step := 260.0
	var x := 700.0
	var n := 0
	while x < total_world - 400.0:
		# دستهٔ سکه در قوس
		var count := rng.randi_range(3, 6)
		var arc := rng.randf() < 0.45
		for i in count:
			var cx := x + i * 74.0
			var cy := height_at(cx) - 70.0
			if arc:
				cy -= sin(float(i) / float(maxi(count - 1, 1)) * PI) * 120.0
			_spawn_item("coin", Vector2(cx, cy))
		n += 1
		x += step * rng.randf_range(0.85, 1.35)
		# بنزین و نیترو
		if rng.randf() < 0.34:
			var fx := x
			_spawn_item("fuel", Vector2(fx, height_at(fx) - 62.0))
		if rng.randf() < 0.22:
			var nx := x + 120.0
			_spawn_item("nitro", Vector2(nx, height_at(nx) - 62.0))
		if rng.randf() < 0.06:
			var gx := x + 60.0
			_spawn_item("gem", Vector2(gx, height_at(gx) - 96.0))
	# خط پایان
	var f := Sprite2D.new()
	f.texture = UI.tex_at("res://assets/deco/barrier.png")
	f.position = Vector2(finish_x(), height_at(finish_x()) - 20.0)
	f.scale = Vector2(2.2, 2.2)
	add_child(f)

func _spawn_item(kind: String, pos: Vector2) -> void:
	var s := Sprite2D.new()
	match kind:
		"fuel": s.texture = _fuel_tex
		"nitro": s.texture = _nitro_tex
		"gem": s.texture = _gem_tex
		_: s.texture = _coin_tex
	s.position = pos
	var sc := 0.55 if kind == "coin" else 0.62
	s.scale = Vector2(sc, sc)
	if kind == "gem":
		s.scale = Vector2(0.7, 0.7)
	s.visible = false
	add_child(s)
	items.append({ "kind": kind, "pos": pos, "taken": false, "node": s, "active": false })

## انیمیشن شناور یک آیتم (وقتی بازیکن نزدیک شد)
func animate_item(it: Dictionary) -> void:
	if bool(it["active"]) or bool(it["taken"]):
		return
	it["active"] = true
	var node: Sprite2D = it["node"]
	if node == null or not is_instance_valid(node):
		return
	node.visible = true
	var p: Vector2 = it["pos"]
	var tw := node.create_tween().set_loops()
	tw.tween_property(node, "position:y", p.y - 9.0, 0.9 + rng.randf() * 0.4).set_trans(Tween.TRANS_SINE).set_ease(Tween.EASE_IN_OUT)
	tw.tween_property(node, "position:y", p.y, 0.9 + rng.randf() * 0.4).set_trans(Tween.TRANS_SINE).set_ease(Tween.EASE_IN_OUT)
