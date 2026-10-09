extends Control
## Workshop: the car on a lift, colour swatches, three stat bars, and the start button.

const CarDraw = preload("res://scripts/car_draw.gd")
const Data = preload("res://scripts/data.gd")
const UIButton = preload("res://scripts/ui_button.gd")

const SWATCH_Y := 880.0
const SWATCH_R := 46.0

var _car: Dictionary = {}
var _index := 0
var _color := Color("#2e7d4f")


func _ready() -> void:
	set_anchors_preset(Control.PRESET_FULL_RECT)
	mouse_filter = Control.MOUSE_FILTER_STOP
	gui_input.connect(_on_input)
	_car = Data.car_by_id(App.pending_car_id)
	_index = int(Save.data.get("car_color", 0))
	_color = Data.color_for(_car, _index)

	var back := UIButton.new()
	back.text = Strings.t("result_map")
	back.fill = App.COL_BLUE
	back.position = Vector2(60, 920)
	back.size = Vector2(300, 120)
	back.pressed.connect(func(): App.goto(App.SCENE_MAP))
	add_child(back)

	var start := UIButton.new()
	start.text = Strings.t("menu_start")
	start.fill = App.COL_GREEN
	start.position = Vector2(1560, 920)
	start.size = Vector2(300, 120)
	start.pressed.connect(_start)
	add_child(start)


func _process(_delta: float) -> void:
	queue_redraw()


func _draw() -> void:
	var s := size
	draw_rect(Rect2(0, 0, s.x, 640), Color("#b9733f"))
	var brick := Color("#a2612f")
	for row in 8:
		var y := float(row) * 80.0
		var offset := 0.0 if row % 2 == 0 else 80.0
		for col in 14:
			draw_rect(Rect2(float(col) * 160.0 - offset, y, 160.0, 80.0), brick, false, 3.0)
	draw_rect(Rect2(0, 640, s.x, s.y - 640), Color("#4a4a52"))
	draw_rect(Rect2(0, 640, s.x, 10), Color("#f2a93b"))

	draw_rect(Rect2(560, 706, 800, 24), Color(0, 0, 0, 0.25))
	# Wheel bottoms rest on the lift at y = 706; origin = lift - 2 * radius * scale.
	var lift_scale := 2.6
	CarDraw.draw_car_at(self, Vector2(960, 706.0 - 2.0 * CarDraw.WHEEL_R * lift_scale), lift_scale, _color, 0.0)

	draw_string(App.font_bold, Vector2(0, 110), Strings.t("garage_title"), HORIZONTAL_ALIGNMENT_CENTER, s.x, 96, App.COL_PAPER)

	_stat(0, Strings.t("garage_power"), float(_car.torque) / 20000.0)
	_stat(1, Strings.t("garage_grip"), float(_car.grip) / 1.6)
	_stat(2, Strings.t("garage_tank"), float(_car.tank) / 120.0)

	var cols: Array = _car.colors
	for i in cols.size():
		var c := _swatch_center(i)
		draw_circle(c, SWATCH_R + 6.0, App.COL_INK)
		draw_circle(c, SWATCH_R, Color(String(cols[i])))
		if i == _index:
			draw_arc(c, SWATCH_R + 16.0, 0.0, TAU, 40, App.COL_SAFFRON, 6.0)


func _stat(row: int, label: String, value: float) -> void:
	var y := 720.0 + float(row) * 56.0
	draw_string(App.font_bold, Vector2(560, y + 30), label, HORIZONTAL_ALIGNMENT_RIGHT, 220, 36, App.COL_PAPER)
	draw_rect(Rect2(810, y + 8, 500, 26), Color(0.1, 0.1, 0.12, 0.6))
	draw_rect(Rect2(814, y + 12, 492.0 * clampf(value, 0.0, 1.0), 18), App.COL_SAFFRON)


func _swatch_center(i: int) -> Vector2:
	var count: int = _car.colors.size()
	var x := 960.0 + (float(i) - float(count - 1) * 0.5) * 124.0
	return Vector2(x, SWATCH_Y)


func _on_input(event: InputEvent) -> void:
	if not ((event is InputEventMouseButton or event is InputEventScreenTouch) and event.is_pressed()):
		return
	var p: Vector2 = event.position
	for i in _car.colors.size():
		if p.distance_to(_swatch_center(i)) < SWATCH_R + 10.0:
			_set_color(i)
			return


func _set_color(i: int) -> void:
	_index = i
	App.pending_color = i
	Save.data.car_color = i
	Save.save_game()
	var target := Data.color_for(_car, i)
	var tw := create_tween()
	tw.tween_property(self, "_color", target, 0.35).set_trans(Tween.TRANS_SINE).set_ease(Tween.EASE_OUT)


func _start() -> void:
	App.pending_car_id = String(_car.id)
	App.goto(App.SCENE_RUN)
