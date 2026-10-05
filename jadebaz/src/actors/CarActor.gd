extends Node2D
class_name CarActor
## CarActor.gd — نمایش ماشین در منو/گاراژ/فروشگاه (بدون فیزیک، فقط انیمیشن)
## بدنه = لایهٔ رنگ‌شده + لایهٔ جزئیات، دو چرخِ چرخان، سایه و نشستن روی کمک‌فنر.

var car_id := "peykan"
var paint_color := Color.WHITE
var rim := 1
var scale_mul := 1.0
var speed := 0.0                 # سرعت چرخش چرخ‌ها (رادیان بر ثانیه)
var bob := true
var wheel_nodes: Array[Node2D] = []
var body_root: Node2D
var _t := 0.0
var _base_y := 0.0
var _susp := 0.0
var shadow: Sprite2D

func setup(id: String, color: Color, rim_id := 1, mul := 1.0) -> void:
	car_id = id
	paint_color = color
	rim = rim_id
	scale_mul = mul
	_build()

func _build() -> void:
	for c in get_children():
		c.queue_free()
	wheel_nodes.clear()
	var geo := CarGeometry.of(car_id)
	var s := float(Game.car_data(car_id)["scale"]) * scale_mul
	var wb := float(geo["wb"]) * s
	var wy := float(geo["wy"]) * s
	var wr := float(geo["wr"]) * s
	# سایه (بیضی کشیده زیر ماشین)
	var pts := PackedVector2Array()
	for i in 24:
		var a := TAU * float(i) / 24.0
		pts.append(Vector2(cos(a), sin(a) * 0.2) * (wb * 0.75))
	var poly := Polygon2D.new()
	poly.polygon = pts
	poly.color = Color(0, 0, 0, 0.22)
	poly.position = Vector2(0, wy + wr * 0.7)
	poly.z_index = -2
	add_child(poly)
	body_root = Node2D.new()
	add_child(body_root)
	var paint := Sprite2D.new()
	paint.texture = UI.tex_at("res://assets/cars/%s_paint.png" % car_id)
	paint.modulate = paint_color
	paint.scale = Vector2(s, s)
	paint.z_index = 2
	paint.name = "paint"
	body_root.add_child(paint)
	var detail := Sprite2D.new()
	detail.texture = UI.tex_at("res://assets/cars/%s_detail.png" % car_id)
	detail.scale = Vector2(s, s)
	detail.z_index = 3
	body_root.add_child(detail)
	var ws := CarGeometry.wheel_scale(car_id, s)
	for i in 2:
		var holder := Node2D.new()
		var sign_x := -1.0 if i == 0 else 1.0
		holder.position = Vector2(sign_x * wb * 0.5, wy)
		holder.scale = Vector2(ws, ws)
		var tire := Sprite2D.new()
		tire.texture = UI.tex_at("res://assets/wheels/tire.png")
		tire.z_index = 1
		holder.add_child(tire)
		var r := Sprite2D.new()
		r.texture = UI.tex_at("res://assets/wheels/rim_%d.png" % rim)
		r.z_index = 2
		holder.add_child(r)
		add_child(holder)
		wheel_nodes.append(holder)

func set_look(color: Color, rim_id: int) -> void:
	paint_color = color
	rim = rim_id
	var p := body_root.get_node_or_null("paint")
	if p:
		p.modulate = color
	for h in wheel_nodes:
		for c in h.get_children():
			if c is Sprite2D and c.z_index == 2:
				c.texture = UI.tex_at("res://assets/wheels/rim_%d.png" % rim)

func _process(dt: float) -> void:
	_t += dt
	for h in wheel_nodes:
		h.rotation += speed * dt
	if body_root == null:
		return
	# نشستن روی کمک‌فنر: بالا-پایین ملایم
	var target := sin(_t * 5.0) * 1.6 if bob else 0.0
	_susp = lerpf(_susp, target, clampf(dt * 6.0, 0.0, 1.0))
	body_root.position = Vector2(0, _susp)
