extends Control
const UIKit = preload("res://scripts/ui_kit.gd")

## Vector icons drawn in code. No emoji anywhere in the UI: emoji glyphs are missing from
## most fonts and render as blank boxes (factory LESSON L22).

@export var kind: String = "heart"
@export var color: Color = Color.WHITE
@export var plate: bool = false


func _init(p_kind: String = "heart", p_color: Color = Color.WHITE, p_plate: bool = false) -> void:
	kind = p_kind
	color = p_color
	plate = p_plate
	mouse_filter = Control.MOUSE_FILTER_IGNORE


func _draw() -> void:
	var s := minf(size.x, size.y)
	var c := size * 0.5
	var r := s * 0.36
	if plate:
		draw_circle(c, s * 0.5, UIKit.C_PLATE)
		draw_arc(c, s * 0.5 - 1.0, 0.0, TAU, 48, UIKit.C_BORDER, 2.0, true)
	match kind:
		"heart":
			var hc := c + Vector2(0, -r * 0.1)
			draw_circle(hc + Vector2(-r * 0.5, -r * 0.1), r * 0.55, color)
			draw_circle(hc + Vector2(r * 0.5, -r * 0.1), r * 0.55, color)
			draw_colored_polygon(PackedVector2Array([
				hc + Vector2(-r * 1.02, r * 0.05), hc + Vector2(r * 1.02, r * 0.05), hc + Vector2(0, r * 1.05),
			]), color)
		"pause":
			draw_rect(Rect2(c + Vector2(-r * 0.72, -r * 0.75), Vector2(r * 0.5, r * 1.5)), color)
			draw_rect(Rect2(c + Vector2(r * 0.22, -r * 0.75), Vector2(r * 0.5, r * 1.5)), color)
		"star":
			var pts := PackedVector2Array()
			for i in 10:
				var rad := r if i % 2 == 0 else r * 0.45
				var ang := -PI * 0.5 + i * PI / 5.0
				pts.append(c + Vector2(cos(ang), sin(ang)) * rad)
			draw_colored_polygon(pts, color)
		"nova":
			draw_circle(c, r * 0.45, color)
			for i in 8:
				var ang := i * TAU / 8.0
				draw_line(c + Vector2(cos(ang), sin(ang)) * r * 0.7, c + Vector2(cos(ang), sin(ang)) * r * 1.15, color, maxf(2.0, s * 0.06), true)
		"speaker":
			var body := PackedVector2Array([
				c + Vector2(-r * 0.9, -r * 0.35), c + Vector2(-r * 0.4, -r * 0.35), c + Vector2(0.1 * r, -r * 0.85),
				c + Vector2(0.1 * r, r * 0.85), c + Vector2(-r * 0.4, r * 0.35), c + Vector2(-r * 0.9, r * 0.35),
			])
			draw_colored_polygon(body, color)
			draw_arc(c + Vector2(0.1 * r, 0), r * 0.7, -0.9, 0.9, 16, color, maxf(2.0, s * 0.06), true)
		"speaker_off":
			var body2 := PackedVector2Array([
				c + Vector2(-r * 0.9, -r * 0.35), c + Vector2(-r * 0.4, -r * 0.35), c + Vector2(0.1 * r, -r * 0.85),
				c + Vector2(0.1 * r, r * 0.85), c + Vector2(-r * 0.4, r * 0.35), c + Vector2(-r * 0.9, r * 0.35),
			])
			draw_colored_polygon(body2, color)
			draw_line(c + Vector2(0.4 * r, -0.5 * r), c + Vector2(r, 0.5 * r), color, maxf(2.0, s * 0.06), true)
			draw_line(c + Vector2(r, -0.5 * r), c + Vector2(0.4 * r, 0.5 * r), color, maxf(2.0, s * 0.06), true)
		"note":
			draw_circle(c + Vector2(-r * 0.35, r * 0.5), r * 0.35, color)
			draw_rect(Rect2(c + Vector2(-r * 0.05, -r * 0.9), Vector2(r * 0.14, r * 1.4)), color)
			draw_line(c + Vector2(r * 0.09, -r * 0.9), c + Vector2(r * 0.6, -r * 0.5), color, maxf(2.0, s * 0.08), true)
		"vibrate":
			draw_rect(Rect2(c + Vector2(-r * 0.45, -r * 0.8), Vector2(r * 0.9, r * 1.6)), color, false, maxf(2.0, s * 0.07))
			draw_arc(c, r * 1.05, -0.7, 0.7, 14, color, maxf(2.0, s * 0.06), true)
			draw_arc(c, r * 1.05, PI - 0.7, PI + 0.7, 14, color, maxf(2.0, s * 0.06), true)
		"back":
			draw_polyline(PackedVector2Array([c + Vector2(r * 0.3, -r * 0.8), c + Vector2(-r * 0.6, 0), c + Vector2(r * 0.3, r * 0.8)]), color, maxf(2.5, s * 0.09), true)
		"check":
			draw_polyline(PackedVector2Array([c + Vector2(-r * 0.7, 0), c + Vector2(-r * 0.15, r * 0.55), c + Vector2(r * 0.75, -r * 0.5)]), color, maxf(2.5, s * 0.1), true)
		"lock":
			draw_rect(Rect2(c + Vector2(-r * 0.6, -r * 0.1), Vector2(r * 1.2, r * 0.95)), color)
			draw_arc(c + Vector2(0, -r * 0.1), r * 0.45, PI, TAU, 16, color, maxf(2.0, s * 0.08), true)
		"shield":
			var sh := PackedVector2Array()
			for i in 13:
				var ang := -PI * 0.5 + (i - 6) * 0.42
				sh.append(c + Vector2(cos(ang), sin(ang)) * r * 1.02)
			draw_polyline(sh, color, maxf(2.5, s * 0.09), true)
		"gear_line":
			# three stacked bars (used as a menu glyph)
			for i in 3:
				draw_rect(Rect2(c + Vector2(-r * 0.9, -r * 0.7 + i * r * 0.7), Vector2(r * 1.8, maxf(2.0, s * 0.09))), color)
		_:
			draw_circle(c, r * 0.5, color)
