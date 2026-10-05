extends Screen
## Settings.gd — تنظیمات: صدا، موسیقی، لرزش، کیفیت، نام راننده و پاک‌کردن پیشرفت

var toggles := {}
var name_edit: LineEdit
var confirm_box: Control

func build() -> void:
	add_bg("sky_night", "city_night", "mtn_dusk")
	top_bar("تنظیمات")
	var panel := UI.panel(Color("#16203a"), 30)
	panel.position = Vector2(240, 130)
	panel.size = Vector2(800, 500)
	content.add_child(panel)
	var v := VBoxContainer.new()
	v.add_theme_constant_override("separation", 18)
	panel.add_child(v)

	v.add_child(_toggle_row("sound", "صدای بازی", "icon_sound"))
	v.add_child(_toggle_row("music", "موسیقی پس‌زمینه", "icon_music"))
	v.add_child(_toggle_row("shake", "لرزش دوربین در تصادف", "icon_shake"))
	v.add_child(_toggle_row("quality", "کیفیت بالا (روی گوشی ضعیف خاموش کن)", "icon_settings"))

	var name_row := HBoxContainer.new()
	name_row.add_theme_constant_override("separation", 14)
	var nl := UI.label("نام راننده", 26)
	nl.custom_minimum_size = Vector2(320, 40)
	name_row.add_child(nl)
	name_edit = LineEdit.new()
	name_edit.custom_minimum_size = Vector2(340, 60)
	name_edit.text = Game.player_name
	name_edit.add_theme_font_override("font", UI.font())
	name_edit.add_theme_font_size_override("font_size", 24)
	name_row.add_child(name_edit)
	var save_name := UI.button("ذخیره", "green", 140, 62, 22)
	save_name.pressed.connect(func():
		Game.player_name = name_edit.text.strip_edges() if name_edit.text.strip_edges() != "" else "راننده"
		Game.save_game()
		Snd.play("click")
		_toast("نام ذخیره شد"))
	name_row.add_child(save_name)
	v.add_child(name_row)

	var reset := UI.button("پاک کردن همهٔ پیشرفت", "red", 380, 80, 24)
	reset.pressed.connect(func(): _ask_reset())
	v.add_child(reset)

	var info := UI.label("جاده‌باز — نسخهٔ ۱.۰  |  ساخته‌شده با عشق برای جاده‌های ایران", 20, UI.COL_DIM)
	info.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(info)

func _toggle_row(key: String, title: String, icon: String) -> HBoxContainer:
	var h := HBoxContainer.new()
	h.add_theme_constant_override("separation", 14)
	var ic := TextureRect.new()
	ic.texture = UI.tex(icon)
	ic.custom_minimum_size = Vector2(48, 48)
	ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	h.add_child(ic)
	var l := UI.label(title, 25)
	l.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	h.add_child(l)
	var cb := CheckButton.new()
	cb.button_pressed = bool(Game.settings.get(key, true))
	cb.focus_mode = Control.FOCUS_NONE
	cb.toggled.connect(func(on: bool):
		Game.settings[key] = on
		Game.save_game()
		Snd.play("click")
		if key == "music":
			Snd.set_music(on)
		elif key == "sound":
			Snd.set_sfx(on))
	h.add_child(cb)
	toggles[key] = cb
	return h

func _ask_reset() -> void:
	if confirm_box:
		return
	confirm_box = Control.new()
	confirm_box.set_anchors_preset(Control.PRESET_FULL_RECT)
	var dim := ColorRect.new()
	dim.color = Color(0, 0, 0, 0.6)
	dim.set_anchors_preset(Control.PRESET_FULL_RECT)
	confirm_box.add_child(dim)
	var p := UI.panel(Color("#2a1520"), 26)
	p.position = Vector2(390, 260)
	p.size = Vector2(500, 220)
	var v := VBoxContainer.new()
	v.add_theme_constant_override("separation", 16)
	p.add_child(v)
	var t := UI.label("مطمئنی؟ همهٔ سکه‌ها، ماشین‌ها و ستاره‌ها پاک می‌شوند.", 24, Color("#ffd0cc"))
	t.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	t.custom_minimum_size = Vector2(440, 70)
	t.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(t)
	var row := HBoxContainer.new()
	row.add_theme_constant_override("separation", 20)
	row.alignment = BoxContainer.ALIGNMENT_CENTER
	var yes := UI.button("بله، پاک کن", "red", 220, 76, 24)
	yes.pressed.connect(func():
		Game.reset_all()
		Snd.play("crash")
		confirm_box.queue_free()
		confirm_box = null
		refresh()
		_toast("همه چیز از نو شد!"))
	var no := UI.button("نه", "gray", 160, 76, 24)
	no.pressed.connect(func():
		confirm_box.queue_free()
		confirm_box = null)
	row.add_child(yes)
	row.add_child(no)
	v.add_child(row)
	confirm_box.add_child(p)
	add_child(confirm_box)
	UI.pop_in(p, 0.24)

func refresh() -> void:
	for k in toggles.keys():
		toggles[k].button_pressed = bool(Game.settings.get(k, true))
	if name_edit:
		name_edit.text = Game.player_name

func _toast(text: String) -> void:
	var l := UI.label(text, 28, Color(1, 0.92, 0.6))
	l.position = Vector2(330, 60)
	l.size = Vector2(620, 46)
	l.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	l.z_index = 20
	content.add_child(l)
	var t := l.create_tween()
	t.tween_property(l, "modulate:a", 0.0, 1.5).set_delay(0.8)
	t.tween_callback(l.queue_free)
