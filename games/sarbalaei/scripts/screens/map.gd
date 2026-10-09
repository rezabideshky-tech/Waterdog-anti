extends Control
## Trip map: a winding road from the Alborz foothills to Damavand. Tap a town to select it,
## then start. The car drives to the chosen town.

const CarDraw = preload("res://scripts/car_draw.gd")
const ThemeDraw = preload("res://scripts/theme_draw.gd")
const Data = preload("res://scripts/data.gd")
const UIButton = preload("res://scripts/ui_button.gd")

const NODE_R := 66.0
const HIT_R := 96.0
const CAR_LIFT := 135.0

var _selected := "chalus"
var _car_pos := Vector2.ZERO


func _ready() -> void:
	set_anchors_preset(Control.PRESET_FULL_RECT)
	mouse_filter = Control.MOUSE_FILTER_STOP
	gui_input.connect(_on_input)

	var wanted := App.pending_route_id
	_selected = wanted if Save.is_unlocked(wanted) else "chalus"
	_car_pos = _node_pos(_selected)

	var garage := UIButton.new()
	garage.text = Strings.t("menu_garage")
	garage.fill = App.COL_BLUE
	garage.position = Vector2(60, 900)
	garage.size = Vector2(320, 120)
	garage.pressed.connect(func(): App.goto(App.SCENE_GARAGE))
	add_child(garage)

	var start := UIButton.new()
	start.text = Strings.t("menu_start")
	start.fill = App.COL_GREEN
	start.position = Vector2(1540, 900)
	start.size = Vector2(320, 120)
	start.pressed.connect(_start)
	add_child(start)


func _process(_delta: float) -> void:
	queue_redraw()


func _draw() -> void:
	ThemeDraw.draw_sky(self, size, App.COL_SKY, Color("#f6ecd6"))
	ThemeDraw.draw_hills(self, size, 1000.0, 260.0, 0.4, Color("#c9d8b0"))
	ThemeDraw.draw_hills(self, size, 1080.0, 140.0, 1.9, Color("#8fbf76"))

	for i in range(Data.ROUTES.size() - 1):
		_draw_road(_node_pos(Data.ROUTES[i].id), _node_pos(Data.ROUTES[i + 1].id))

	draw_string(App.font_bold, Vector2(0, 110), Strings.t("map_title"), HORIZONTAL_ALIGNMENT_CENTER, size.x, 90, App.COL_INK)
	draw_string(App.font_bold, Vector2(60, 90), Strings.t("coins_label") + "  " + Strings.num(Save.total_coins()),
		HORIZONTAL_ALIGNMENT_LEFT, -1, 46, App.COL_INK)

	for r in Data.ROUTES:
		_draw_node(r)

	var sel := _node_pos(_selected)
	draw_arc(sel, NODE_R + 24.0, 0.0, TAU, 56, App.COL_SAFFRON, 8.0)
	CarDraw.draw_car_at(self, _car_pos + Vector2(0, -CAR_LIFT), 0.9, App.COL_RED, _car_pos.x * 0.03)

	_draw_info_panel()


func _draw_node(r: Dictionary) -> void:
	var p := Vector2(float(r.map_x), float(r.map_y))
	var unlocked := Save.is_unlocked(String(r.id))
	draw_circle(p, NODE_R + 8.0, App.COL_INK)
	draw_circle(p, NODE_R, App.COL_PAPER if unlocked else Color("#b9b5ac"))
	draw_circle(p, NODE_R - 14.0, Color(String(r.grass)) if unlocked else Color("#8f8b84"))
	if not unlocked:
		draw_rect(Rect2(p.x - 20, p.y - 4, 40, 32), Color("#4a4a4a"))
		draw_arc(p + Vector2(0, -4), 16.0, PI, TAU, 16, Color("#4a4a4a"), 6.0)
	var label := Strings.t(String(r.name_key))
	draw_string(App.font_bold, p + Vector2(-180, NODE_R + 58.0), label, HORIZONTAL_ALIGNMENT_CENTER, 360, 46, App.COL_INK)
	var stars := Save.stars_of(String(r.id))
	for k in 3:
		var col := App.COL_SAFFRON if k < stars else Color("#c9c4b8")
		ThemeDraw.draw_star(self, p + Vector2(-44.0 + float(k) * 44.0, NODE_R + 112.0), 17.0, col)


func _draw_info_panel() -> void:
	var box := Rect2(520, 860, 880, 180)
	draw_rect(box, App.COL_PAPER)
	draw_rect(box, App.COL_INK, false, 5.0)
	var r := Data.route_by_id(_selected)
	var unlocked := Save.is_unlocked(_selected)
	draw_string(App.font_bold, box.position + Vector2(0, 66), Strings.t(String(r.name_key)),
		HORIZONTAL_ALIGNMENT_CENTER, box.size.x, 60, App.COL_INK)
	var line := ""
	if unlocked:
		line = Strings.t("map_best") + ": " + Strings.num(Save.best_of(_selected)) + " " + Strings.t("unit_meter") \
			+ "    " + Strings.t("map_par") + ": " + Strings.num(float(r.par_s)) + " " + Strings.t("unit_sec")
	else:
		line = Strings.t("map_locked")
	draw_string(App.font_bold, box.position + Vector2(0, 130), line, HORIZONTAL_ALIGNMENT_CENTER, box.size.x, 34, App.COL_INK)


func _draw_road(a: Vector2, b: Vector2) -> void:
	var mid := (a + b) * 0.5 + Vector2(0, -170)
	var pts := PackedVector2Array()
	for i in 41:
		var t := float(i) / 40.0
		var q := (1.0 - t) * (1.0 - t) * a + 2.0 * (1.0 - t) * t * mid + t * t * b
		pts.append(q)
	draw_polyline(pts, Color("#8b5e3c"), 40.0)
	for i in range(0, pts.size() - 1, 2):
		draw_line(pts[i], pts[i + 1], App.COL_PAPER, 4.0)


func _node_pos(id: String) -> Vector2:
	var r := Data.route_by_id(id)
	return Vector2(float(r.map_x), float(r.map_y))


func _on_input(event: InputEvent) -> void:
	if not ((event is InputEventMouseButton or event is InputEventScreenTouch) and event.is_pressed()):
		return
	var p: Vector2 = event.position
	for r in Data.ROUTES:
		if p.distance_to(_node_pos(String(r.id))) < HIT_R:
			_select(String(r.id))
			return


func _select(id: String) -> void:
	if id == _selected:
		return
	if not Save.is_unlocked(id):
		# A locked town explains itself in the info panel; the car stays where it is.
		return
	_selected = id
	App.pending_route_id = id
	var tw := create_tween()
	tw.tween_property(self, "_car_pos", _node_pos(id), 0.7).set_trans(Tween.TRANS_CUBIC).set_ease(Tween.EASE_IN_OUT)


func _start() -> void:
	if not Save.is_unlocked(_selected):
		return
	App.pending_route_id = _selected
	App.goto(App.SCENE_RUN)
