extends Control
class_name Screen
## Screen.gd — پایهٔ همهٔ صفحه‌ها: ناوبری، ساخت تنبل، انیمیشن ورود
## هر صفحه با build() یک بار ساخته می‌شود و با refresh() هر بار که نمایش داده می‌شود تازه‌سازی می‌گردد.

signal nav(to: String, payload: Dictionary)

var app: Node                     # main.gd
var payload := {}
var built := false
var content: Control              # ظرف اصلی محتوا (برای انیمیشن ورود)
var bg_layer: Node2D              # پس‌زمینهٔ اختصاصی هر صفحه

func _ready() -> void:
	set_anchors_preset(Control.PRESET_FULL_RECT)
	mouse_filter = Control.MOUSE_FILTER_PASS
	content = Control.new()
	content.set_anchors_preset(Control.PRESET_FULL_RECT)
	content.mouse_filter = Control.MOUSE_FILTER_PASS
	add_child(content)
	if not built:
		build()
		built = true

func build() -> void: pass
func refresh() -> void: pass

func go(to: String, data := {}) -> void:
	nav.emit(to, data)

func show_screen(data := {}) -> void:
	payload = data
	visible = true
	refresh()
	UI.fade_in(self, 0.26)
	if content:
		content.position = Vector2(0, 26)
		var t := create_tween()
		t.set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)
		t.tween_property(content, "position", Vector2.ZERO, 0.34)

func hide_screen() -> void:
	visible = false

# ---------------------------------------------------------------- کمکی‌ها
func add_bg(sky_name: String, bg1: String, bg2: String, tint := Color(1, 1, 1)) -> void:
	bg_layer = Node2D.new()
	bg_layer.z_index = -10
	add_child(bg_layer)
	var sky := Sprite2D.new()
	sky.texture = UI.tex_at("res://assets/bg/%s.png" % sky_name)
	sky.centered = false
	sky.scale = Vector2(1.4, 1.4)
	sky.modulate = tint
	bg_layer.add_child(sky)
	var mid := Sprite2D.new()
	mid.texture = UI.tex_at("res://assets/bg/%s.png" % bg2)
	mid.centered = false
	mid.scale = Vector2(1.3, 1.3)
	mid.position = Vector2(0, 150)
	bg_layer.add_child(mid)
	var near := Sprite2D.new()
	near.texture = UI.tex_at("res://assets/bg/%s.png" % bg1)
	near.centered = false
	near.scale = Vector2(1.25, 1.25)
	near.position = Vector2(0, 300)
	bg_layer.add_child(near)
	_animate_bg()

func _animate_bg() -> void:
	if bg_layer == null:
		return
	var children := bg_layer.get_children()
	for i in children.size():
		var n: Node2D = children[i]
		var t := n.create_tween().set_loops()
		var dur := 9.0 + i * 3.0
		var y0 := n.position.y
		t.tween_property(n, "position:y", y0 - 8.0 - i * 4.0, dur).set_trans(Tween.TRANS_SINE).set_ease(Tween.EASE_IN_OUT)
		t.tween_property(n, "position:y", y0, dur).set_trans(Tween.TRANS_SINE).set_ease(Tween.EASE_IN_OUT)

func top_bar(title_text: String, with_back := true) -> HBoxContainer:
	var h: HBoxContainer
	if with_back:
		h = UI.header(title_text, func(): go("menu"))
	else:
		h = HBoxContainer.new()
		h.add_theme_constant_override("separation", 14)
		var t := UI.title(title_text, 38)
		t.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		t.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		h.add_child(t)
		h.add_child(UI.chip("icon_coin", Game.money(Game.coins)))
		h.add_child(UI.chip("icon_gem", Game.fa(Game.gems), 130))
	h.position = Vector2(24, 16)
	h.size = Vector2(1232, 80)
	content.add_child(h)
	return h

func card_button(title_text: String, subtitle: String, icon_name: String, w: float, cb: Callable) -> PanelContainer:
	var p := UI.panel(Color("#1b2740"), 26)
	p.custom_minimum_size = Vector2(w, 150)
	var v := VBoxContainer.new()
	v.add_theme_constant_override("separation", 6)
	var ic := TextureRect.new()
	ic.texture = UI.tex(icon_name)
	ic.custom_minimum_size = Vector2(56, 56)
	ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	ic.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	v.add_child(ic)
	var t := UI.label(title_text, 26)
	t.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(t)
	if subtitle != "":
		var s := UI.label(subtitle, 18, UI.COL_DIM)
		s.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
		v.add_child(s)
	p.add_child(v)
	p.gui_input.connect(func(e: InputEvent):
		if e is InputEventMouseButton and e.pressed and e.button_index == MOUSE_BUTTON_LEFT:
			Snd.play("click")
			cb.call()
			p.scale = Vector2(0.95, 0.95)
			var tw := p.create_tween()
			tw.tween_property(p, "scale", Vector2.ONE, 0.18).set_trans(Tween.TRANS_BACK)
	)
	content.add_child(p)
	return p
