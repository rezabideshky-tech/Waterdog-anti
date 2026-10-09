extends Button
## A chunky button with a flat shadow and a springy press animation.
## Set `fill` before adding the button to the tree.

var fill := Color("#1f7a8c")
var text_color := Color("#fff8ea")

var _spring: Tween


func _ready() -> void:
	focus_mode = Control.FOCUS_NONE
	add_theme_font_override("font", App.font_bold)
	add_theme_font_size_override("font_size", 44)
	for key in ["font_color", "font_pressed_color", "font_hover_color", "font_focus_color"]:
		add_theme_color_override(key, text_color)
	add_theme_stylebox_override("normal", _box(fill))
	add_theme_stylebox_override("hover", _box(fill))
	add_theme_stylebox_override("pressed", _box(fill.darkened(0.2)))
	add_theme_stylebox_override("focus", _box(fill))
	pivot_offset = size * 0.5
	resized.connect(func(): pivot_offset = size * 0.5)
	button_down.connect(_press)
	button_up.connect(_release)


func _box(c: Color) -> StyleBoxFlat:
	var sb := StyleBoxFlat.new()
	sb.bg_color = c
	sb.set_corner_radius_all(22)
	sb.set_border_width_all(4)
	sb.border_color = App.COL_INK
	sb.shadow_color = Color(0.0, 0.0, 0.0, 0.28)
	sb.shadow_size = 6
	sb.shadow_offset = Vector2(0.0, 6.0)
	return sb


func _press() -> void:
	_animate(0.92)


func _release() -> void:
	_animate(1.0)


func _animate(target: float) -> void:
	if _spring:
		_spring.kill()
	_spring = create_tween()
	_spring.tween_property(self, "scale", Vector2.ONE * target, 0.14) \
		.set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)
