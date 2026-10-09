extends Control
## Opening screen: the title pops in, a car rolls across the hills, and a tap continues.

const CarDraw = preload("res://scripts/car_draw.gd")
const ThemeDraw = preload("res://scripts/theme_draw.gd")

const ROAD_Y := 870.0
const CAR_SCALE := 2.0

var _x := -300.0
var _done := false
var _title: Label


func _ready() -> void:
	set_anchors_preset(Control.PRESET_FULL_RECT)
	mouse_filter = Control.MOUSE_FILTER_STOP
	gui_input.connect(_on_input)

	_title = Label.new()
	_title.text = Strings.t("app_name")
	_title.position = Vector2(0, 110)
	_title.size = Vector2(1920, 240)
	_title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	_title.pivot_offset = Vector2(960, 120)
	_title.scale = Vector2(0.2, 0.2)
	_title.add_theme_font_override("font", App.font_bold)
	_title.add_theme_font_size_override("font_size", 210)
	_title.add_theme_color_override("font_color", App.COL_PAPER)
	_title.add_theme_color_override("font_outline_color", App.COL_INK)
	_title.add_theme_constant_override("outline_size", 16)
	add_child(_title)
	var pop := create_tween()
	pop.tween_property(_title, "scale", Vector2.ONE, 0.8).set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)

	var hint := Label.new()
	hint.text = Strings.t("splash_tap")
	hint.position = Vector2(0, 960)
	hint.size = Vector2(1920, 60)
	hint.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	hint.add_theme_font_override("font", App.font_bold)
	hint.add_theme_font_size_override("font_size", 46)
	hint.add_theme_color_override("font_color", App.COL_INK)
	add_child(hint)
	var blink := create_tween().set_loops()
	blink.tween_property(hint, "modulate:a", 0.25, 0.8)
	blink.tween_property(hint, "modulate:a", 1.0, 0.8)

	get_tree().create_timer(4.0).timeout.connect(_continue)


func _process(delta: float) -> void:
	_x += 420.0 * delta
	if _x > 2200.0:
		_x = -300.0
	queue_redraw()


func _draw() -> void:
	ThemeDraw.draw_sky(self, size, App.COL_SKY, Color("#f6ecd6"))
	ThemeDraw.draw_hills(self, size, 700.0, 120.0, 0.8, Color("#9cc6a0"))
	ThemeDraw.draw_hills(self, size, 800.0, 70.0, 2.1, Color("#6fae5c"))
	draw_rect(Rect2(0, ROAD_Y, size.x, size.y - ROAD_Y), Color("#8b5e3c"))
	draw_rect(Rect2(0, ROAD_Y, size.x, 18), Color("#5fa45a"))
	# Wheel bottoms sit on the road line: origin = road - 2 * wheel radius * scale.
	var origin := Vector2(_x, ROAD_Y - 2.0 * CarDraw.WHEEL_R * CAR_SCALE)
	CarDraw.draw_car_at(self, origin, CAR_SCALE, App.COL_RED, _x / CarDraw.WHEEL_R)


func _on_input(event: InputEvent) -> void:
	if (event is InputEventMouseButton or event is InputEventScreenTouch) and event.is_pressed():
		_continue()


func _continue() -> void:
	if _done:
		return
	_done = true
	App.goto(App.SCENE_MAP)
