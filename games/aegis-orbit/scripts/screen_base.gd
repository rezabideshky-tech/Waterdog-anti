extends Control
## Base for full-screen views. The UI is (re)built once the view has a real size, and
## again whenever that size changes (LESSONS L4: nothing is positioned from constants).

var shell
var _built_for := Vector2.ZERO


func _init(p_shell) -> void:
	shell = p_shell
	mouse_filter = Control.MOUSE_FILTER_IGNORE


func _ready() -> void:
	resized.connect(_on_resized)
	_on_resized()


func _on_resized() -> void:
	if size.x < 2.0 or size.y < 2.0 or size == _built_for:
		return
	_built_for = size
	clear_children()
	_build()


func clear_children() -> void:
	for c in get_children():
		remove_child(c)
		c.queue_free()


## Unit scale: 1.0 on a 1000 px short side. Clamped so tiny and huge screens stay usable.
func unit() -> float:
	var s := size
	if s.x < 2.0 or s.y < 2.0:
		s = get_viewport_rect().size
	return clampf(minf(s.x, s.y) / 1000.0, 0.5, 1.4)


func _build() -> void:
	pass
