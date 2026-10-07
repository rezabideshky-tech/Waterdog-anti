extends Control
class_name Gauge
## Gauge.gd — عقربه/کمان سنج (سرعت، دور موتور، بنزین، نیترو)

enum Kind { SPEED, RPM, FUEL, NITRO }

@export var kind: Kind = Kind.SPEED
@export var title := ""
@export var accent := Color(0.95, 0.6, 0.2)

var ratio := 0.0
var _shown := 0.0
var _text := ""
var _warn := false

func _ready() -> void:
	custom_minimum_size = Vector2(150, 150)
	mouse_filter = Control.MOUSE_FILTER_IGNORE

func _process(dt: float) -> void:
	_shown = lerpf(_shown, clampf(ratio, 0.0, 1.0), clampf(dt * 9.0, 0.0, 1.0))
	if kind == Kind.FUEL and _shown < 0.22:
		_warn = fmod(Time.get_ticks_msec() / 1000.0, 0.8) < 0.4
	else:
		_warn = false
	queue_redraw()

func _draw() -> void:
	var c := size * 0.5
	var r := minf(size.x, size.y) * 0.42
	var col := accent
	if _warn:
		col = Color(1, 0.35, 0.3)
	# پشت‌زمینه
	draw_circle(c, r + 5, Color(0.07, 0.08, 0.12, 0.8))
	draw_arc(c, r, PI * 0.75, PI * 2.25, 48, Color(0.28, 0.3, 0.36, 0.9), 7.0, true)
	# کمان مقدار
	var span := PI * 1.5 * clampf(_shown, 0.001, 1.0)
	draw_arc(c, r, PI * 0.75, PI * 0.75 + span, 48, col, 7.0, true)
	if kind == Kind.RPM and _shown > 0.82:
		draw_arc(c, r, PI * 1.55, PI * 2.25, 20, Color(1, 0.3, 0.3, 0.85), 7.0, true)
	# متن وسط
	var f := UI.font(true)
	if f == null:
		return
	var fs := int(r * 0.5)
	var txt := _text
	if txt == "":
		txt = "%d%%" % int(_shown * 100.0)
	var ts := f.get_string_size(txt, HORIZONTAL_ALIGNMENT_CENTER, -1, fs)
	draw_string(f, c + Vector2(-ts.x * 0.5, r * 0.22), txt, HORIZONTAL_ALIGNMENT_CENTER, -1, fs, Color(1, 1, 1, 0.95))
	if title != "":
		var fs2 := int(r * 0.28)
		var ts2 := f.get_string_size(title, HORIZONTAL_ALIGNMENT_CENTER, -1, fs2)
		draw_string(f, c + Vector2(-ts2.x * 0.5, r * 0.62), title, HORIZONTAL_ALIGNMENT_CENTER, -1, fs2, Color(1, 1, 1, 0.55))

func set_text(t: String) -> void:
	_text = t
