extends Control
## Trip postcard: the outcome, stats that reveal one by one, stars, and a stamp.

const ThemeDraw = preload("res://scripts/theme_draw.gd")
const UIButton = preload("res://scripts/ui_button.gd")

const CARD_CENTER := Vector2(960, 470)
const CARD_HALF := Vector2(500, 380)
const CARD_TILT := -0.035

var _r: Dictionary = {}
var _reveal := 0.0
var _stars_t := 0.0
var _stamp_t := 0.0


func _ready() -> void:
	set_anchors_preset(Control.PRESET_FULL_RECT)
	_r = App.last_result
	if _r.is_empty():
		App.goto(App.SCENE_MAP)
		return

	var fade := create_tween()
	fade.tween_property(self, "_reveal", 1.0, 1.0).set_trans(Tween.TRANS_SINE)
	var stars := create_tween()
	stars.tween_interval(0.9)
	stars.tween_property(self, "_stars_t", 1.0, 0.9).set_trans(Tween.TRANS_BACK)
	var stamp := create_tween()
	stamp.tween_interval(1.9)
	stamp.tween_property(self, "_stamp_t", 1.0, 0.45).set_trans(Tween.TRANS_BOUNCE)

	var again := UIButton.new()
	again.text = Strings.t("result_again")
	again.fill = App.COL_SAFFRON
	again.position = Vector2(560, 905)
	again.size = Vector2(300, 115)
	again.pressed.connect(func(): App.goto(App.SCENE_RUN))
	add_child(again)

	var to_map := UIButton.new()
	to_map.text = Strings.t("result_map")
	to_map.fill = App.COL_BLUE
	to_map.position = Vector2(1060, 905)
	to_map.size = Vector2(300, 115)
	to_map.pressed.connect(func(): App.goto(App.SCENE_MAP))
	add_child(to_map)


func _process(_delta: float) -> void:
	queue_redraw()


func _draw() -> void:
	if _r.is_empty():
		return
	ThemeDraw.draw_sky(self, size, App.COL_SKY, Color("#f6ecd6"))

	# Card: slightly tilted, like a postcard set down on the table.
	draw_set_transform(CARD_CENTER, CARD_TILT, Vector2.ONE)
	draw_rect(Rect2(-CARD_HALF + Vector2(12, 14), CARD_HALF * 2.0), Color(0, 0, 0, 0.22))
	draw_rect(Rect2(-CARD_HALF, CARD_HALF * 2.0), App.COL_PAPER)
	draw_rect(Rect2(-CARD_HALF, CARD_HALF * 2.0), App.COL_INK, false, 6.0)

	var ink := Color(App.COL_INK, _reveal)
	var finished: bool = _r.finished
	var title := Strings.t("result_finished") if finished else Strings.t(_reason_key(String(_r.reason)))
	draw_string(App.font_bold, Vector2(-CARD_HALF.x, -290), title, HORIZONTAL_ALIGNMENT_CENTER, CARD_HALF.x * 2.0, 70, ink)
	draw_string(App.font_bold, Vector2(-CARD_HALF.x, -228), Strings.t(String(_r.route_name)),
		HORIZONTAL_ALIGNMENT_CENTER, CARD_HALF.x * 2.0, 44, Color(App.COL_INK, _reveal * 0.8))

	var rows := [
		Strings.t("result_distance") + ": " + Strings.num(_r.distance_m) + " " + Strings.t("unit_meter"),
		Strings.t("result_coins") + ": " + Strings.num(_r.coins),
		Strings.t("result_time") + ": " + Strings.num(_r.time_s) + " " + Strings.t("unit_sec"),
		Strings.t("result_best") + ": " + Strings.num(_r.best_m) + " " + Strings.t("unit_meter"),
	]
	for i in rows.size():
		draw_string(App.font_bold, Vector2(-CARD_HALF.x + 80, -150.0 + float(i) * 62.0), rows[i],
			HORIZONTAL_ALIGNMENT_LEFT, -1, 40, Color(App.COL_INK, _reveal))

	var stars: int = _r.stars
	for i in 3:
		var delay := float(i) * 0.3
		var grow := clampf((_stars_t * 1.6) - delay, 0.0, 1.0)
		var col := App.COL_SAFFRON if i < stars else Color("#c9c4b8")
		ThemeDraw.draw_star(self, Vector2(-160.0 + float(i) * 160.0, 150.0), 62.0 * grow, col)

	draw_set_transform(Vector2.ZERO, 0.0, Vector2.ONE)
	_draw_stamp(finished)


func _draw_stamp(finished: bool) -> void:
	if _stamp_t <= 0.0:
		return
	var scale_k := lerpf(2.4, 1.0, _stamp_t)
	var col := App.COL_GREEN if finished else App.COL_RED
	draw_set_transform(CARD_CENTER + Vector2(300, 170), -0.22, Vector2(scale_k, scale_k))
	var box := Rect2(-170, -48, 340, 96)
	draw_rect(box, Color(col, _stamp_t * 0.9), false, 8.0)
	var text := Strings.t("stamp_done") if finished else Strings.t("stamp_try")
	draw_string(App.font_bold, Vector2(-170, 18), text, HORIZONTAL_ALIGNMENT_CENTER, 340, 52, Color(col, _stamp_t * 0.9))
	draw_set_transform(Vector2.ZERO, 0.0, Vector2.ONE)


func _reason_key(reason: String) -> String:
	match reason:
		"flipped":
			return "result_flipped"
		"no_fuel":
			return "result_no_fuel"
		"stuck":
			return "result_stuck"
		_:
			return "result_fell"
