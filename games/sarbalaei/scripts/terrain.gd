extends Node2D
## Ground: one filled collision polygon, drawn as soil with a grass lip and a few pebbles.

const DEPTH := 2400.0

var top: PackedVector2Array = PackedVector2Array()
var soil_color := Color("#8b5e3c")
var grass_color := Color("#5fa45a")
var length_px := 0.0


func setup(points: PackedVector2Array, soil: Color, grass: Color) -> void:
	top = points
	soil_color = soil
	grass_color = grass
	length_px = points[points.size() - 1].x

	var poly := PackedVector2Array(points)
	poly.append(Vector2(length_px, DEPTH))
	poly.append(Vector2(0.0, DEPTH))
	var body := StaticBody2D.new()
	var cp := CollisionPolygon2D.new()
	cp.polygon = poly
	body.add_child(cp)
	# Invisible side walls keep the car on the route.
	for wall_x in [-60.0, length_px + 60.0]:
		var ws := CollisionShape2D.new()
		var rect := RectangleShape2D.new()
		rect.size = Vector2(40, 8000)
		ws.shape = rect
		ws.position = Vector2(wall_x, 0)
		body.add_child(ws)
	add_child(body)
	queue_redraw()


func _draw() -> void:
	if top.size() < 2:
		return
	var poly := PackedVector2Array(top)
	poly.append(Vector2(length_px, DEPTH))
	poly.append(Vector2(0.0, DEPTH))
	draw_colored_polygon(poly, soil_color)
	draw_polyline(top, grass_color, 26.0)
	draw_polyline(top, grass_color.darkened(0.3), 4.0)
	var rng := RandomNumberGenerator.new()
	rng.seed = 5
	for i in 260:
		var base := top[rng.randi() % top.size()]
		var p := base + Vector2(rng.randf_range(-20.0, 20.0), rng.randf_range(40.0, 420.0))
		draw_circle(p, rng.randf_range(4.0, 12.0), soil_color.darkened(0.28))
