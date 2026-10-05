extends Control
class_name TouchWheel
## TouchWheel.gd — غربال فرمان لمسی (گاز / ترمز) با انیمیشن فشرده‌شدن

signal value_changed(v: float)

@export var label_text := "گاز"
@export var accent := Color(0.35, 0.78, 0.35)
@export var max_angle := 26.0
@export var spring := true

var value := 0.0
var _held := false
var _touch_index := -1
var _press := 0.0
var _rng := RandomNumberGenerator.new()

func _ready() -> void:
	custom_minimum_size = Vector2(230, 230)
	mouse_filter = Control.MOUSE_FILTER_STOP
	_rng.randomize()

func _gui_input(event: InputEvent) -> void:
	if event is InputEventScreenTouch:
		if event.pressed and not _held:
			_held = true
			_touch_index = event.index
			_press = 1.0
			accept_event()
		elif not event.pressed and event.index == _touch_index:
			_release()
			accept_event()
	elif event is InputEventScreenDrag and _held and event.index == _touch_index:
		_angle_from(event.position)
		accept_event()

func _input(event: InputEvent) -> void:
	# پشتیبانی از کلیک ماوس در ادیتور و نسخهٔ دسکتاپ
	if event is InputEventMouseButton and event.button_index == MOUSE_BUTTON_LEFT:
		if event.pressed and get_global_rect().has_point(event.position) and not _held:
			_held = true
			_touch_index = -2
			_press = 1.0
		elif not event.pressed and _touch_index == -2:
			_release()

func _angle_from(local: Vector2) -> void:
	var c := size * 0.5
	var v := (local - c).normalized()
	if v.x <= 0.0:
		return
	value = clampf(v.x, 0.15, 1.0)

func _release() -> void:
	_held = false
	_touch_index = -1
	value = 0.0
	value_changed.emit(0.0)

func _process(dt: float) -> void:
	_press = lerpf(_press, 1.0 if _held else 0.0, clampf(dt * 14.0, 0.0, 1.0))
	if _held:
		value_changed.emit(value)
	queue_redraw()

func _draw() -> void:
	var c := size * 0.5
	var r := minf(size.x, size.y) * 0.42 * (1.0 - 0.06 * _press)
	var col := accent
	# سایه
	draw_circle(c + Vector2(0, 5), r + 4, Color(0, 0, 0, 0.28))
	# حلقهٔ بیرونی
	draw_circle(c, r + 6, Color(0.09, 0.09, 0.13, 0.85))
	draw_arc(c, r + 3, 0, TAU, 48, col.darkened(0.3), 5.0, true)
	# بدنهٔ غربال
	draw_circle(c, r, Color(0.16, 0.16, 0.2, 0.96))
	draw_circle(c, r * 0.86, Color(0.2, 0.2, 0.26, 0.98))
	# پره‌ها
	var ang := deg_to_rad(max_angle) * (value if _held else 0.0)
	for i in 8:
		var a := TAU * float(i) / 8.0 + ang
		var p1 := c + Vector2(cos(a), sin(a)) * (r * 0.32)
		var p2 := c + Vector2(cos(a), sin(a)) * (r * 0.78)
		draw_line(p1, p2, Color(0.34, 0.34, 0.4, 0.9), 5.0, true)
	draw_circle(c, r * 0.3, col.darkened(0.25))
	draw_circle(c, r * 0.22, col)
	# حلقهٔ پرشدگی
	if _held:
		draw_arc(c, r + 3, -PI * 0.5, -PI * 0.5 + TAU * value, 40, Color(1, 1, 1, 0.85), 4.0, true)
	# برچسب
	var f := UI.font(false)
	if f:
		var fs := int(r * 0.42)
		var ts := f.get_string_size(label_text, HORIZONTAL_ALIGNMENT_CENTER, -1, fs)
		draw_string(f, c + Vector2(-ts.x * 0.5, r * 0.14), label_text, HORIZONTAL_ALIGNMENT_CENTER, -1, fs, Color(1, 1, 1, 0.9))
