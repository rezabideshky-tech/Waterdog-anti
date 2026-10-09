extends Node2D
## A coin or a fuel can, drawn in code.

var kind := "coin"


func _draw() -> void:
	if kind == "coin":
		draw_circle(Vector2.ZERO, 22.0, Color("#e0a526"))
		draw_circle(Vector2.ZERO, 16.0, Color("#ffd65a"))
		draw_arc(Vector2.ZERO, 16.0, 0.0, TAU, 24, Color("#b7801a"), 3.0)
	else:
		draw_rect(Rect2(-18, -24, 36, 46), Color("#c8553d"))
		draw_rect(Rect2(-10, -32, 20, 10), Color("#7a2e20"))
		draw_rect(Rect2(-10, -6, 20, 12), Color("#f6ecd6"))
