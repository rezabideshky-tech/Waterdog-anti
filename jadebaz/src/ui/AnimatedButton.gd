extends Button
class_name AnimatedButton
## دکمهٔ انیمیشنی: بزرگ‌شدن نرم، فشار فنری، درخشش و لرزش کوتاه.

@export var kind := "green"          # green | orange | blue | red | gray
@export var pop_on_hover := true
@export var sound := "click"

var _shine: ColorRect
var _tween: Tween

func _ready() -> void:
	focus_mode = Control.FOCUS_NONE
	mouse_filter = Control.MOUSE_FILTER_STOP
	custom_minimum_size = Vector2(maxf(custom_minimum_size.x, 180), maxf(custom_minimum_size.y, 74))
	pivot_offset = size / 2.0
	resized.connect(func(): pivot_offset = size / 2.0)
	# درخشش متحرک روی دکمه
	_shine = ColorRect.new()
	_shine.color = Color(1, 1, 1, 0.14)
	_shine.mouse_filter = Control.MOUSE_FILTER_IGNORE
	_shine.position = Vector2(-220, 0)
	_shine.size = Vector2(120, 400)
	add_child(_shine)
	_shine.rotation = -0.25
	var tw := create_tween().set_loops()
	tw.tween_property(_shine, "position:x", size.x + 240.0, 2.6).set_delay(1.2 + randf() * 1.5)
	tw.tween_interval(1.4)
	mouse_entered.connect(_on_hover)
	mouse_exited.connect(_on_exit)
	button_down.connect(_on_down)
	button_up.connect(_on_up)
	pressed.connect(_on_pressed)

func _on_hover() -> void:
	if pop_on_hover:
		_scale_to(1.05, 0.12)

func _on_exit() -> void:
	_scale_to(1.0, 0.12)

func _on_down() -> void:
	_scale_to(0.94, 0.07)

func _on_up() -> void:
	_scale_to(1.03, 0.12)

func _on_pressed() -> void:
	if sound != "":
		Snd.play(sound, randf_range(0.95, 1.08))
	pivot_offset = size / 2.0

func _scale_to(s: float, t: float) -> void:
	if _tween and _tween.is_running():
		_tween.kill()
	_tween = create_tween()
	_tween.set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)
	_tween.tween_property(self, "scale", Vector2(s, s), t)

## تکان کوتاه برای جلب توجه
func wiggle() -> void:
	var t := create_tween()
	t.set_trans(Tween.TRANS_ELASTIC).set_ease(Tween.EASE_OUT)
	t.tween_property(self, "rotation_degrees", -3.0, 0.09)
	t.tween_property(self, "rotation_degrees", 3.0, 0.09)
	t.tween_property(self, "rotation_degrees", 0.0, 0.12)
