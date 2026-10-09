extends RefCounted
## Shared screen art, drawn in code: sky gradient and rolling hills.


static func draw_sky(ci: CanvasItem, area: Vector2, top: Color, bottom: Color) -> void:
	var bands := 24
	for i in bands:
		var t := float(i) / float(bands - 1)
		var y := area.y * float(i) / float(bands)
		ci.draw_rect(Rect2(0.0, y, area.x, area.y / float(bands) + 1.0), top.lerp(bottom, t))


static func draw_hills(ci: CanvasItem, area: Vector2, base_y: float, amp: float, phase: float, color: Color) -> void:
	var pts := PackedVector2Array()
	var x := 0.0
	while x <= area.x + 40.0:
		var h := sin(x * 0.0042 + phase) * 0.6 + sin(x * 0.0113 + phase * 1.7) * 0.4
		pts.append(Vector2(x, base_y - amp * h))
		x += 40.0
	pts.append(Vector2(area.x + 40.0, area.y))
	pts.append(Vector2(0.0, area.y))
	ci.draw_colored_polygon(pts, color)


static func draw_star(ci: CanvasItem, center: Vector2, radius: float, color: Color) -> void:
	var pts := PackedVector2Array()
	for i in 10:
		var a := -PI / 2.0 + float(i) * PI / 5.0
		var r := radius if i % 2 == 0 else radius * 0.45
		pts.append(center + Vector2(cos(a), sin(a)) * r)
	ci.draw_colored_polygon(pts, color)
