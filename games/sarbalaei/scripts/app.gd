extends Node
## App shell: shared colours, fonts, language, and the curtain used for scene changes.

const FONT_REGULAR := "res://assets/fonts/Vazirmatn-Regular.ttf"
const FONT_BOLD := "res://assets/fonts/Vazirmatn-Bold.ttf"

const COL_SKY := Color("#9ed8ef")
const COL_PAPER := Color("#f6ecd6")
const COL_INK := Color("#2b2a33")
const COL_TEAL := Color("#1f7a8c")
const COL_BLUE := Color("#2f6fa3")
const COL_SAFFRON := Color("#f2a93b")
const COL_RED := Color("#c8553d")
const COL_GREEN := Color("#5fa45a")

const SCENE_SPLASH := "res://scenes/splash.tscn"
const SCENE_MAP := "res://scenes/map.tscn"
const SCENE_GARAGE := "res://scenes/garage.tscn"
const SCENE_RUN := "res://scenes/run.tscn"
const SCENE_RESULT := "res://scenes/result.tscn"

var language := "fa"
var pending_route_id := "chalus"
var pending_car_id := "kuhnavard"
var last_result: Dictionary = {}

var font_regular: Font
var font_bold: Font

var _layer: CanvasLayer
var _curtain: ColorRect
var _busy := false


func _ready() -> void:
	language = String(Save.data.get("language", "fa"))
	font_regular = _load_font(FONT_REGULAR)
	font_bold = _load_font(FONT_BOLD)
	# Every Control without its own font uses Vazirmatn, so Persian shapes correctly.
	ThemeDB.fallback_font = font_regular
	ThemeDB.fallback_font_size = 34

	_layer = CanvasLayer.new()
	_layer.layer = 100
	add_child(_layer)
	_curtain = ColorRect.new()
	_curtain.color = COL_INK
	_curtain.set_anchors_preset(Control.PRESET_FULL_RECT)
	_curtain.mouse_filter = Control.MOUSE_FILTER_IGNORE
	_curtain.modulate.a = 0.0
	_layer.add_child(_curtain)


## Loads a TTF from raw bytes, so the project does not depend on editor import.
func _load_font(path: String) -> Font:
	var bytes := FileAccess.get_file_as_bytes(path)
	if bytes.is_empty():
		push_error("missing font: " + path)
		return ThemeDB.fallback_font
	var f := FontFile.new()
	f.data = bytes
	return f


## Fades to black, switches scene, and fades back in.
func goto(scene_path: String) -> void:
	if _busy:
		return
	_busy = true
	_curtain.mouse_filter = Control.MOUSE_FILTER_STOP
	var tw := create_tween()
	tw.tween_property(_curtain, "modulate:a", 1.0, 0.22).set_trans(Tween.TRANS_SINE)
	tw.tween_callback(func(): get_tree().change_scene_to_file(scene_path))
	tw.tween_property(_curtain, "modulate:a", 0.0, 0.32).set_trans(Tween.TRANS_SINE)
	tw.tween_callback(func():
		_busy = false
		_curtain.mouse_filter = Control.MOUSE_FILTER_IGNORE)
