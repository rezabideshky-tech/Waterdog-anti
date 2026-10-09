extends Control
## Draws the playing field (ring, shield, rocks, particles). Pure presentation: it reads
## the view's state and never changes it.

var view


func _init(p_view) -> void:
	view = p_view
	mouse_filter = Control.MOUSE_FILTER_IGNORE


func _draw() -> void:
	view.paint(self)
