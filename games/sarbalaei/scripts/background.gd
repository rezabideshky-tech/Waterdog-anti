extends Node2D
## Parallax backdrop for a drive, drawn in code: sky, sun, far ridges, near hills.

var route: Dictionary = {}
var cam: Node2D = null


func setup(r: Dictionary) -> void:
	route = r
	z_index = -20


func _process(_delta: float) -> void:
	queue_redraw()


func _draw() -> void:
	if route.is_empty():
		return
	var c := cam.global_position if cam != null else Vector2.ZERO
	draw_rect(Rect2(c - Vector2(1600, 1100), Vector2(3200, 2200)), App.COL_SKY)
	var sun := c * 0.12 + Vector2(-420, -300)
	draw_circle(sun, 92.0, Color("#ffe39a"))
	_ridge(c, 0.3, -120.0, 150.0, 0.0021, 1.3, Color(route.far))
	_ridge(c, 0.6, 40.0, 90.0, 0.0047, 0.4, Color(route.near))


func _ridge(c: Vector2, factor: float, base_offset: float, amp: float, freq: float, phase: float, col: Color) -> void:
	var off := c.x * factor
	var base := c.y * factor + base_offset
	var pts := PackedVector2Array()
	var x := off - 1700.0
	while x <= off + 1700.0:
		var h := sin(x * freq + phase) * amp + sin(x * freq * 2.7 + phase) * amp * 0.35
		pts.append(Vector2(x, base - absf(h)))
		x += 60.0
	pts.append(Vector2(off + 1700.0, c.y + 1200.0))
	pts.append(Vector2(off - 1700.0, c.y + 1200.0))
	draw_colored_polygon(pts, col)
