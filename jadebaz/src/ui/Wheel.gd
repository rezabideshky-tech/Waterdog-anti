extends Control
class_name Wheel
## Wheel.gd — گردونهٔ شانس با ۸ جایزه؛ رسم با کد و چرخش با انیمیشن فنری.

signal spin_finished(index: int)

var prizes: Array = []
var angle := 0.0
var spinning := false
var result_index := 0
var _labels := ["۲۰۰", "۵۰۰", "۳ جم", "۱۲۰۰", "۸ جم", "کلید", "۳۰۰۰", "۲۰ جم"]
const COLORS := [
	Color("#ffcf4a"), Color("#38c06a"), Color("#4a9cff"), Color("#e8467c"),
	Color("#f08a1e"), Color("#7a5bd6"), Color("#5ff5c8"), Color("#ff6b5a"),
]

func _ready() -> void:
	custom_minimum_size = Vector2(340, 340)
	mouse_filter = Control.MOUSE_FILTER_IGNORE

func spin() -> void:
	if spinning:
		return
	spinning = true
	result_index = randi() % maxi(1, prizes.size() if prizes.size() > 0 else 8)
	var seg := TAU / float(maxi(1, _labels.size()))
	# زاویهٔ هدف: نشانگر بالا (‎-PI/2) روی مرکز قطعهٔ برنده
	var target := -PI * 0.5 - (float(result_index) + 0.5) * seg
	var turns := TAU * float(randi_range(4, 6))
	var final := target + turns
	var t := create_tween()
	t.set_trans(Tween.TRANS_QUINT).set_ease(Tween.EASE_OUT)
	t.tween_property(self, "angle", final, 3.1)
	t.tween_callback(func():
		spinning = false
		spin_finished.emit(result_index))

func _process(_dt: float) -> void:
	queue_redraw()

func _draw() -> void:
	var c := size * 0.5
	var r := minf(size.x, size.y) * 0.46
	var n := _labels.size()
	var seg := TAU / float(n)
	draw_circle(c, r + 12, Color("#1b2740"))
	draw_arc(c, r + 8, 0, TAU, 64, Color("#ffcf4a"), 6.0, true)
	for i in n:
		var a0 := angle + i * seg
		var a1 := a0 + seg
		var col: Color = COLORS[i % COLORS.size()]
		var pts := PackedVector2Array([c])
		var steps := 12
		for s in steps + 1:
			var a := lerpf(a0, a1, float(s) / float(steps))
			pts.append(c + Vector2(cos(a), sin(a)) * r)
		draw_colored_polygon(pts, col)
		draw_line(c, c + Vector2(cos(a0), sin(a0)) * r, Color(1, 1, 1, 0.25), 2.0)
		# برچسب
		var f := UI.font(true)
		if f:
			var mid := (a0 + a1) * 0.5
			var pos := c + Vector2(cos(mid), sin(mid)) * (r * 0.68)
			var txt: String = str(_labels[i % _labels.size()])
			var fs := 22
			var ts := f.get_string_size(txt, HORIZONTAL_ALIGNMENT_CENTER, -1, fs)
			draw_string(f, pos + Vector2(-ts.x * 0.5, 8), txt, HORIZONTAL_ALIGNMENT_CENTER, -1, fs, Color(0.12, 0.09, 0.03))
	draw_circle(c, r * 0.18, Color("#f6f8fa"))
	draw_circle(c, r * 0.12, Color("#ffcf4a"))
	# نشانگر بالا
	var tip := PackedVector2Array([Vector2(c.x, c.y - r - 26), Vector2(c.x - 16, c.y - r + 4), Vector2(c.x + 16, c.y - r + 4)])
	draw_colored_polygon(tip, Color("#e8402a"))
