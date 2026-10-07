extends Node
class_name UI
## UI.gd — ساخت تم بازی و اجزای رابط کاربری (همه با کد، بدون صحنهٔ آماده)

const FONT_PATH := "res://assets/fonts/Vazirmatn-Bold.ttf"
const FONT_BLACK := "res://assets/fonts/Vazirmatn-Black.ttf"
const FONT_REGULAR := "res://assets/fonts/Vazirmatn-Regular.ttf"

const COL_GOLD := Color("#ffcf4a")
const COL_TEAL := Color("#5ff5c8")
const COL_RED := Color("#ff6b5a")
const COL_INK := Color("#f2f6ff")
const COL_DIM := Color("#bcc6dd")
const COL_PANEL := Color("#1e2a44")

static var _tex_cache := {}
static var _theme: Theme

# ------------------------------------------------------------------- ابزار
static func tex(name: String) -> Texture2D:
	if _tex_cache.has(name):
		return _tex_cache[name]
	var p := "res://assets/ui/%s.png" % name
	var t: Texture2D = null
	if ResourceLoader.exists(p):
		t = load(p)
	_tex_cache[name] = t
	return t

static func tex_at(path: String) -> Texture2D:
	if _tex_cache.has(path):
		return _tex_cache[path]
	var t: Texture2D = load(path) if ResourceLoader.exists(path) else null
	_tex_cache[path] = t
	return t

static func font(black := false, regular := false) -> Font:
	var p := FONT_BLACK if black else (FONT_REGULAR if regular else FONT_PATH)
	return load(p)

# ------------------------------------------------------------------- تم
static func theme() -> Theme:
	if _theme != null:
		return _theme
	var th := Theme.new()
	th.default_font = font()
	th.default_font_size = 28
	# دکمه‌ها
	_btn_style(th, "green", "btn_green")
	_btn_style(th, "orange", "btn_orange")
	_btn_style(th, "blue", "btn_blue")
	_btn_style(th, "red", "btn_red")
	_btn_style(th, "gray", "btn_gray")
	th.set_stylebox(_btn_sb("btn_gray", 0.86), "disabled", "Button")
	th.set_color("font_color", "Button", Color.WHITE)
	th.set_color("font_pressed_color", "Button", Color(1, 1, 1, 0.9))
	th.set_color("font_hover_color", "Button", Color.WHITE)
	th.set_color("font_disabled_color", "Button", Color(1, 1, 1, 0.45))
	th.set_color("font_shadow_color", "Button", Color(0, 0, 0, 0.35))
	th.set_constant("shadow_offset_x", "Button", 0)
	th.set_constant("shadow_offset_y", "Button", 4)
	# برچسب‌ها
	th.set_color("font_color", "Label", COL_INK)
	th.set_color("font_shadow_color", "Label", Color(0, 0, 0, 0.45))
	th.set_constant("shadow_offset_x", "Label", 0)
	th.set_constant("shadow_offset_y", "Label", 3)
	# پنل
	var psb := StyleBoxFlat.new()
	psb.bg_color = Color(0.09, 0.12, 0.2, 0.92)
	psb.corner_radius_top_left = 26
	psb.corner_radius_top_right = 26
	psb.corner_radius_bottom_left = 26
	psb.corner_radius_bottom_right = 26
	psb.border_width_bottom = 6
	psb.border_color = Color(0, 0, 0, 0.35)
	psb.content_margin_left = 22
	psb.content_margin_right = 22
	psb.content_margin_top = 18
	psb.content_margin_bottom = 18
	th.set_stylebox(psb, "panel", "PanelContainer")
	# پروگرس‌بار
	var bg := StyleBoxFlat.new()
	bg.bg_color = Color(0, 0, 0, 0.42)
	bg.corner_radius_top_left = 12
	bg.corner_radius_top_right = 12
	bg.corner_radius_bottom_left = 12
	bg.corner_radius_bottom_right = 12
	th.set_stylebox(bg, "background", "ProgressBar")
	var fg := StyleBoxFlat.new()
	fg.bg_color = COL_TEAL
	fg.corner_radius_top_left = 12
	fg.corner_radius_top_right = 12
	fg.corner_radius_bottom_left = 12
	fg.corner_radius_bottom_right = 12
	th.set_stylebox(fg, "fill", "ProgressBar")
	return th

static func _btn_sb(name: String, mod: float = 1.0) -> StyleBoxTexture:
	var sb := StyleBoxTexture.new()
	var t := tex(name)
	if t != null:
		sb.texture = t
	sb.set_texture_margin_all(20)
	sb.set_content_margin_all(14)
	sb.modulate_color = Color(mod, mod, mod, 1)
	return sb

static func _btn_style(th: Theme, kind: String, texname: String) -> void:
	th.set_stylebox(_btn_sb(texname), kind, "Button")
	th.set_stylebox(_btn_sb(texname, 1.06), kind + "_on", "Button")

# ------------------------------------------------------------------- اجزا
static func button(text: String, kind := "green", w := 260.0, h := 84.0, font_size := 30) -> AnimatedButton:
	var b := AnimatedButton.new()
	b.kind = kind
	b.text = text
	b.custom_minimum_size = Vector2(w, h)
	b.add_theme_font_size_override("font_size", font_size)
	b.add_theme_color_override("font_color", Color.WHITE)
	var sb := _btn_sb("btn_" + kind)
	b.add_theme_stylebox_override("normal", sb)
	b.add_theme_stylebox_override("hover", _btn_sb("btn_" + kind, 1.08))
	b.add_theme_stylebox_override("pressed", _btn_sb("btn_" + kind, 0.88))
	b.add_theme_stylebox_override("focus", StyleBoxEmpty.new())
	b.add_theme_stylebox_override("disabled", _btn_sb("btn_gray", 0.8))
	return b

static func icon_button(icon_name: String, size := 76.0) -> AnimatedButton:
	var b := AnimatedButton.new()
	b.kind = "blue"
	b.custom_minimum_size = Vector2(size, size)
	b.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	var sb := _btn_sb("btn_blue")
	b.add_theme_stylebox_override("normal", sb)
	b.add_theme_stylebox_override("hover", _btn_sb("btn_blue", 1.08))
	b.add_theme_stylebox_override("pressed", _btn_sb("btn_blue", 0.88))
	b.add_theme_stylebox_override("focus", StyleBoxEmpty.new())
	var tr := TextureRect.new()
	tr.texture = tex(icon_name)
	tr.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	tr.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	tr.mouse_filter = Control.MOUSE_FILTER_IGNORE
	tr.set_anchors_preset(Control.PRESET_FULL_RECT)
	tr.offset_left = size * 0.2
	tr.offset_top = size * 0.2
	tr.offset_right = -size * 0.2
	tr.offset_bottom = -size * 0.2
	b.add_child(tr)
	return b

static func label(text: String, size := 28, color := COL_INK, black := false) -> Label:
	var l := Label.new()
	l.text = text
	l.add_theme_font_override("font", font(black))
	l.add_theme_font_size_override("font_size", size)
	l.add_theme_color_override("font_color", color)
	return l

static func title(text: String, size := 46) -> Label:
	var l := label(text, size, Color("#fff3c4"), true)
	l.add_theme_color_override("font_shadow_color", Color(0, 0, 0, 0.55))
	l.add_theme_constant_override("shadow_offset_y", 5)
	return l

static func panel_style(color := COL_PANEL, radius := 26, alpha := 0.94) -> StyleBoxFlat:
	var sb := StyleBoxFlat.new()
	sb.bg_color = Color(color.r, color.g, color.b, alpha)
	sb.corner_radius_top_left = radius
	sb.corner_radius_top_right = radius
	sb.corner_radius_bottom_left = radius
	sb.corner_radius_bottom_right = radius
	sb.border_width_top = 3
	sb.border_width_bottom = 6
	sb.border_color = Color(0, 0, 0, 0.35)
	sb.content_margin_left = 20
	sb.content_margin_right = 20
	sb.content_margin_top = 16
	sb.content_margin_bottom = 16
	return sb

## پنل ساده (بدون چیدمان خودکار) برای صفحه‌هایی که اجزا را با مختصات می‌گذارند
static func frame(color := COL_PANEL, radius := 26) -> Panel:
	var p := Panel.new()
	p.add_theme_stylebox_override("panel", panel_style(color, radius))
	p.mouse_filter = Control.MOUSE_FILTER_PASS
	return p

static func panel(color := COL_PANEL, radius := 26) -> PanelContainer:
	var p := PanelContainer.new()
	p.add_theme_stylebox_override("panel", panel_style(color, radius))
	return p

## چیپ ارز: آیکون + عدد
static func chip(icon_name: String, value: String, w := 190.0) -> PanelContainer:
	var p := panel(Color("#16203a"), 22)
	p.custom_minimum_size = Vector2(w, 60)
	var h := HBoxContainer.new()
	h.add_theme_constant_override("separation", 8)
	h.alignment = BoxContainer.ALIGNMENT_CENTER
	var ic := TextureRect.new()
	ic.texture = tex(icon_name)
	ic.custom_minimum_size = Vector2(40, 40)
	ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	var lb := label(value, 28, COL_GOLD)
	lb.name = "value"
	h.add_child(ic)
	h.add_child(lb)
	p.add_child(h)
	p.set_meta("label", lb)          # دسترسی سریع به برچسب عدد برای به‌روزرسانی
	return p

## نوار بالای صفحه‌ها: دکمهٔ بازگشت + عنوان + ارزها
static func header(title_text: String, on_back: Callable) -> HBoxContainer:
	var h := HBoxContainer.new()
	h.add_theme_constant_override("separation", 14)
	var back := button("‹  بازگشت", "gray", 190, 72, 26)
	back.pressed.connect(on_back)
	h.add_child(back)
	var t := title(title_text, 40)
	t.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	t.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	h.add_child(t)
	var coins_chip := chip("icon_coin", Game.money(Game.coins))
	coins_chip.name = "coins"
	var gems_chip := chip("icon_gem", Game.fa(Game.gems), 130)
	gems_chip.name = "gems"
	h.add_child(coins_chip)
	h.add_child(gems_chip)
	# به‌روزرسانی خودکار
	var upd := func():
		var c := coins_chip.get_node_or_null("HBoxContainer/Label")
		if c: c.text = Game.money(Game.coins)
		var gm := gems_chip.get_node_or_null("HBoxContainer/Label")
		if gm: gm.text = Game.fa(Game.gems)
	Game.coins_changed.connect(upd)
	Game.gems_changed.connect(upd)
	return h

## کارت ماشین/آیتم در قفسه‌ها
static func card(w := 300.0, h := 380.0) -> PanelContainer:
	var p := panel(Color("#22304f"), 24)
	p.custom_minimum_size = Vector2(w, h)
	return p

static func spacer(h := 12.0) -> Control:
	var c := Control.new()
	c.custom_minimum_size = Vector2(0, h)
	return c

static func stretch() -> Control:
	var c := Control.new()
	c.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	c.size_flags_vertical = Control.SIZE_EXPAND_FILL
	return c

## صحنهٔ ماشین: بدنهٔ رنگ‌شده + جزئیات + چرخ‌ها (برای قفسه، فروشگاه و جاده)
static func car_sprite(car_id: String, color: Color, rim := 1, scale_mul := 1.0) -> Node2D:
	var root := Node2D.new()
	var d := Game.car_data(car_id)
	var s := float(d["scale"]) * scale_mul
	var paint := Sprite2D.new()
	paint.texture = tex_at("res://assets/cars/%s_paint.png" % car_id)
	paint.modulate = color
	paint.name = "paint"
	var detail := Sprite2D.new()
	detail.texture = tex_at("res://assets/cars/%s_detail.png" % car_id)
	detail.name = "detail"
	root.add_child(paint)
	root.add_child(detail)
	root.scale = Vector2(s, s)
	return root

## ستاره‌ها
static func stars_row(count: int, size := 34.0) -> HBoxContainer:
	var h := HBoxContainer.new()
	h.add_theme_constant_override("separation", 4)
	for i in 3:
		var tr := TextureRect.new()
		tr.texture = tex("icon_star")
		tr.custom_minimum_size = Vector2(size, size)
		tr.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		tr.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		tr.modulate = Color(1, 1, 1, 1) if i < count else Color(0.25, 0.3, 0.4, 0.85)
		h.add_child(tr)
	return h

# ---------------------------------------------------------- انتقال بین صفحه‌ها
static func fade_in(node: CanvasItem, dur := 0.28) -> void:
	node.modulate = Color(1, 1, 1, 0)
	var t := node.create_tween()
	t.tween_property(node, "modulate:a", 1.0, dur)

static func slide_in(node: Control, from_y := 40.0, dur := 0.35) -> void:
	node.modulate = Color(1, 1, 1, 0)
	var start := node.position
	node.position = start + Vector2(0, from_y)
	var t := node.create_tween().set_parallel(true)
	t.set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)
	t.tween_property(node, "modulate:a", 1.0, dur)
	t.tween_property(node, "position", start, dur)

static func pop_in(node: Control, dur := 0.3) -> void:
	node.pivot_offset = node.size / 2.0
	node.scale = Vector2(0.7, 0.7)
	node.modulate = Color(1, 1, 1, 0)
	var t := node.create_tween().set_parallel(true)
	t.set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)
	t.tween_property(node, "scale", Vector2.ONE, dur)
	t.tween_property(node, "modulate:a", 1.0, dur)
