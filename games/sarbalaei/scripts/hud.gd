extends CanvasLayer
## In-drive HUD: fuel bar, coins, progress, speed dial, two pedal buttons, and a pause sheet.
## Coordinates are in the 1920x1080 design space.

signal gas_changed(held: bool)
signal brake_changed(held: bool)

const UIButton = preload("res://scripts/ui_button.gd")

var state := {"progress": 0.0, "fuel": 1.0, "coins": 0, "kmh": 0.0}

var _root: Control
var _pause: Control
var _card: Control
var _toast: Label
var _toast_tween: Tween


func _ready() -> void:
	layer = 10
	process_mode = Node.PROCESS_MODE_ALWAYS

	_root = Control.new()
	_root.set_anchors_preset(Control.PRESET_FULL_RECT)
	_root.mouse_filter = Control.MOUSE_FILTER_IGNORE
	_root.draw.connect(_draw_hud)
	add_child(_root)

	var brake := UIButton.new()
	brake.text = Strings.t("hud_brake")
	brake.fill = App.COL_RED
	brake.position = Vector2(50, 735)
	brake.size = Vector2(320, 275)
	brake.button_down.connect(func(): brake_changed.emit(true))
	brake.button_up.connect(func(): brake_changed.emit(false))
	_root.add_child(brake)

	var gas := UIButton.new()
	gas.text = Strings.t("hud_gas")
	gas.fill = App.COL_GREEN
	gas.position = Vector2(1550, 735)
	gas.size = Vector2(320, 275)
	gas.button_down.connect(func(): gas_changed.emit(true))
	gas.button_up.connect(func(): gas_changed.emit(false))
	_root.add_child(gas)

	var pause_btn := UIButton.new()
	pause_btn.text = "II"
	pause_btn.fill = App.COL_BLUE
	pause_btn.position = Vector2(1790, 30)
	pause_btn.size = Vector2(100, 90)
	pause_btn.pressed.connect(open_pause)
	_root.add_child(pause_btn)

	_toast = Label.new()
	_toast.position = Vector2(460, 170)
	_toast.size = Vector2(1000, 80)
	_toast.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	_toast.add_theme_font_override("font", App.font_bold)
	_toast.add_theme_font_size_override("font_size", 60)
	_toast.add_theme_color_override("font_color", App.COL_SAFFRON)
	_toast.add_theme_color_override("font_outline_color", App.COL_INK)
	_toast.add_theme_constant_override("outline_size", 10)
	_toast.modulate.a = 0.0
	_root.add_child(_toast)

	_pause = _build_pause()
	add_child(_pause)


func set_state(s: Dictionary) -> void:
	state = s
	_root.queue_redraw()


func toast(text: String) -> void:
	_toast.text = text
	if _toast_tween:
		_toast_tween.kill()
	_toast.modulate.a = 1.0
	_toast_tween = create_tween()
	_toast_tween.tween_interval(0.9)
	_toast_tween.tween_property(_toast, "modulate:a", 0.0, 0.5)


func open_pause() -> void:
	get_tree().paused = true
	_pause.visible = true
	_card.scale = Vector2(0.7, 0.7)
	_card.pivot_offset = _card.size * 0.5
	var tw := create_tween()
	tw.tween_property(_card, "scale", Vector2.ONE, 0.3).set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)


func _build_pause() -> Control:
	var panel := Control.new()
	panel.set_anchors_preset(Control.PRESET_FULL_RECT)
	panel.mouse_filter = Control.MOUSE_FILTER_STOP
	panel.visible = false

	var dim := ColorRect.new()
	dim.set_anchors_preset(Control.PRESET_FULL_RECT)
	dim.color = Color(0.0, 0.0, 0.0, 0.55)
	panel.add_child(dim)

	_card = Control.new()
	_card.position = Vector2(660, 170)
	_card.size = Vector2(600, 740)
	_card.draw.connect(_draw_card)
	panel.add_child(_card)

	var title := Label.new()
	title.text = Strings.t("pause_title")
	title.position = Vector2(0, 40)
	title.size = Vector2(600, 90)
	title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	title.add_theme_font_override("font", App.font_bold)
	title.add_theme_font_size_override("font_size", 64)
	title.add_theme_color_override("font_color", App.COL_INK)
	_card.add_child(title)

	var resume := _menu_button(Strings.t("pause_resume"), App.COL_GREEN, Vector2(120, 190))
	resume.pressed.connect(func():
		get_tree().paused = false
		_pause.visible = false)
	_card.add_child(resume)

	var restart := _menu_button(Strings.t("pause_restart"), App.COL_SAFFRON, Vector2(120, 330))
	restart.pressed.connect(func():
		get_tree().paused = false
		App.goto(App.SCENE_RUN))
	_card.add_child(restart)

	var exit_btn := _menu_button(Strings.t("pause_exit"), App.COL_BLUE, Vector2(120, 470))
	exit_btn.pressed.connect(func():
		get_tree().paused = false
		App.goto(App.SCENE_MAP))
	_card.add_child(exit_btn)
	return panel


func _menu_button(text: String, col: Color, pos: Vector2) -> Button:
	var b := UIButton.new()
	b.text = text
	b.fill = col
	b.position = pos
	b.size = Vector2(360, 100)
	return b


func _draw_card() -> void:
	_card.draw_rect(Rect2(Vector2.ZERO, _card.size), App.COL_PAPER)
	_card.draw_rect(Rect2(Vector2.ZERO, _card.size), App.COL_INK, false, 6.0)


func _draw_hud() -> void:
	var ci := _root
	var font: Font = App.font_bold
	var progress: float = float(state.progress)
	var fuel: float = float(state.fuel)

	# Fuel: a bar with a label above it.
	ci.draw_string(font, Vector2(50, 46), Strings.t("hud_fuel"), HORIZONTAL_ALIGNMENT_LEFT, -1, 36, App.COL_INK)
	ci.draw_rect(Rect2(50, 60, 330, 34), Color(0.1, 0.12, 0.16, 0.5))
	var fuel_col := App.COL_GREEN if fuel > 0.3 else App.COL_RED
	ci.draw_rect(Rect2(54, 64, 322.0 * fuel, 26), fuel_col)

	# Coins.
	ci.draw_circle(Vector2(66, 146), 20.0, Color("#e0a526"))
	ci.draw_circle(Vector2(66, 146), 14.0, Color("#ffd65a"))
	ci.draw_string(font, Vector2(100, 160), Strings.num(state.coins), HORIZONTAL_ALIGNMENT_LEFT, -1, 46, App.COL_INK)

	# Progress along the route, from the start (left) to the finish flag (right).
	ci.draw_rect(Rect2(560, 60, 800, 14), Color(0.1, 0.12, 0.16, 0.45))
	ci.draw_rect(Rect2(560, 60, 800.0 * progress, 14), App.COL_SAFFRON)
	ci.draw_circle(Vector2(560.0 + 800.0 * progress, 67), 13.0, App.COL_PAPER)
	ci.draw_line(Vector2(1372, 30), Vector2(1372, 96), App.COL_INK, 5.0)
	ci.draw_colored_polygon(PackedVector2Array([Vector2(1372, 30), Vector2(1408, 44), Vector2(1372, 58)]), App.COL_RED)

	# Speed dial, bottom centre-right.
	var center := Vector2(1330, 880)
	var r := 120.0
	var a0 := deg_to_rad(135.0)
	var a1 := deg_to_rad(405.0)
	ci.draw_circle(center, r + 10.0, App.COL_INK)
	ci.draw_circle(center, r, App.COL_PAPER)
	ci.draw_arc(center, r - 18.0, a0, a1, 64, App.COL_RED, 10.0)
	for i in 13:
		var a := lerpf(a0, a1, float(i) / 12.0)
		var dir := Vector2(cos(a), sin(a))
		ci.draw_line(center + dir * (r - 40.0), center + dir * (r - 28.0), App.COL_INK, 4.0)
	var kmh: float = float(state.kmh)
	var needle := lerpf(a0, a1, clampf(kmh / 120.0, 0.0, 1.0))
	ci.draw_line(center, center + Vector2(cos(needle), sin(needle)) * (r - 36.0), App.COL_INK, 6.0)
	ci.draw_circle(center, 12.0, App.COL_INK)
	ci.draw_string(font, center + Vector2(-80, 74), Strings.num(kmh) + " " + Strings.t("unit_kmh"),
		HORIZONTAL_ALIGNMENT_CENTER, 160, 26, App.COL_INK)
