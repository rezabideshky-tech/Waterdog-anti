extends RefCounted
## Original vehicle artwork, drawn in code (no image files).
## Local space: origin at the chassis centre, +x forward, +y down, roughly 250 px long.

const WHEEL_R := 34.0
const WHEEL_OFFSETS := [Vector2(-80, 34), Vector2(80, 34)]

const BODY := [
	Vector2(-122, 20), Vector2(-124, -6), Vector2(-104, -18), Vector2(-72, -20),
	Vector2(30, -20), Vector2(86, -20), Vector2(112, -14), Vector2(124, -2), Vector2(124, 20),
]
const CABIN := [
	Vector2(-70, -20), Vector2(-46, -58), Vector2(38, -60), Vector2(70, -22),
]
const WINDOW_BACK := [
	Vector2(-62, -24), Vector2(-44, -52), Vector2(-8, -52), Vector2(-8, -24),
]
const WINDOW_FRONT := [
	Vector2(2, -24), Vector2(2, -52), Vector2(34, -54), Vector2(60, -24),
]


static func draw_body(ci: CanvasItem, body_color: Color) -> void:
	var outline := Color("#1d1f26")
	var cabin := PackedVector2Array(CABIN)
	ci.draw_colored_polygon(cabin, body_color.darkened(0.14))
	ci.draw_polyline(_closed(CABIN), outline, 3.0)
	ci.draw_colored_polygon(PackedVector2Array(WINDOW_BACK), Color("#bfe6f2"))
	ci.draw_colored_polygon(PackedVector2Array(WINDOW_FRONT), Color("#bfe6f2"))
	var body := PackedVector2Array(BODY)
	ci.draw_colored_polygon(body, body_color)
	ci.draw_polyline(_closed(BODY), outline, 3.0)
	# Side stripe and a roof rack.
	ci.draw_line(Vector2(-118, 4), Vector2(118, 4), body_color.darkened(0.35), 4.0)
	ci.draw_line(Vector2(-50, -58), Vector2(36, -62), outline, 5.0)
	ci.draw_line(Vector2(-44, -62), Vector2(-44, -70), outline, 4.0)
	ci.draw_line(Vector2(30, -64), Vector2(30, -72), outline, 4.0)
	# Lights and bumper.
	ci.draw_rect(Rect2(112, -6, 12, 9), Color("#ffd65a"))
	ci.draw_rect(Rect2(-124, -6, 10, 9), Color("#d1433a"))
	ci.draw_rect(Rect2(-124, 14, 250, 6), outline)


static func draw_wheel(ci: CanvasItem, radius: float) -> void:
	ci.draw_circle(Vector2.ZERO, radius, Color("#22242a"))
	ci.draw_circle(Vector2.ZERO, radius * 0.62, Color("#a7aeb8"))
	for k in 5:
		var a := float(k) * TAU / 5.0
		ci.draw_line(Vector2.ZERO, Vector2(cos(a), sin(a)) * radius * 0.56, Color("#4b505a"), 3.0)
	ci.draw_circle(Vector2.ZERO, radius * 0.16, Color("#4b505a"))


## Draws a whole car at `origin` with uniform scale `s`. Wheels turn by `wheel_angle`.
static func draw_car_at(ci: CanvasItem, origin: Vector2, s: float, body_color: Color, wheel_angle := 0.0) -> void:
	ci.draw_set_transform(origin, 0.0, Vector2(s, s))
	draw_body(ci, body_color)
	for off in WHEEL_OFFSETS:
		ci.draw_set_transform(origin + off * s, wheel_angle, Vector2(s, s))
		draw_wheel(ci, WHEEL_R)
	ci.draw_set_transform(Vector2.ZERO, 0.0, Vector2.ONE)


static func _closed(points: Array) -> PackedVector2Array:
	var out := PackedVector2Array(points)
	out.append(points[0])
	return out
