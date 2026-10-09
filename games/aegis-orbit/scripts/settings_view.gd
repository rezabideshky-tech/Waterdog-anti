extends "res://scripts/screen_base.gd"
const UIKit = preload("res://scripts/ui_kit.gd")
const IconDraw = preload("res://scripts/icon_draw.gd")

## Settings: sound, music, vibration, language, reset (with confirmation) and about.

var _confirm: Control = null


func _build() -> void:
	var u := unit()
	var root := MarginContainer.new()
	UIKit.fill_parent(root)
	root.mouse_filter = Control.MOUSE_FILTER_IGNORE
	root.add_theme_constant_override("margin_left", int(36 * u))
	root.add_theme_constant_override("margin_right", int(36 * u))
	root.add_theme_constant_override("margin_top", int(60 * u))
	root.add_theme_constant_override("margin_bottom", int(40 * u))
	add_child(root)

	var col := VBoxContainer.new()
	col.add_theme_constant_override("separation", int(22 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	root.add_child(col)

	var header := HBoxContainer.new()
	header.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_child(header)
	var back := UIKit.button(I18n.t("back"), false, Vector2(200 * u, 72 * u), int(34 * u))
	back.pressed.connect(func(): shell.show_menu())
	header.add_child(back)
	var title := UIKit.label(I18n.t("set_title"), int(50 * u), UIKit.C_GOLD, true)
	title.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	header.add_child(title)
	var spacer := Control.new()
	spacer.custom_minimum_size = Vector2(200 * u, 72 * u)
	spacer.mouse_filter = Control.MOUSE_FILTER_IGNORE
	header.add_child(spacer)

	var panel := UIKit.panel(UIKit.C_PANEL, int(28 * u))
	col.add_child(panel)
	var rows := VBoxContainer.new()
	rows.add_theme_constant_override("separation", int(14 * u))
	rows.mouse_filter = Control.MOUSE_FILTER_IGNORE
	panel.add_child(rows)
	rows.add_child(_toggle_row("sfx", I18n.t("set_sfx"), "speaker", u))
	rows.add_child(_toggle_row("music", I18n.t("set_music"), "note", u))
	rows.add_child(_toggle_row("vibration", I18n.t("set_vibration"), "vibrate", u))
	rows.add_child(_language_row(u))

	var reset := UIKit.button(I18n.t("set_reset"), false, Vector2(0, 88 * u), int(38 * u))
	reset.custom_minimum_size = Vector2(0, 88 * u)
	reset.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	reset.custom_minimum_size.x = clampf(size.x * 0.72, 260.0, 620.0 * u)
	reset.pressed.connect(func(): _ask_reset())
	col.add_child(reset)

	var about := UIKit.label(I18n.t("set_about"), int(28 * u), UIKit.C_MUTED)
	about.custom_minimum_size = Vector2(0, 60 * u)
	col.add_child(about)


func _toggle_row(key: String, caption: String, icon: String, u: float) -> Control:
	var on := bool(Save.setting(key))
	var row := HBoxContainer.new()
	row.custom_minimum_size = Vector2(0, 84 * u)
	row.add_theme_constant_override("separation", int(16 * u))
	row.mouse_filter = Control.MOUSE_FILTER_IGNORE
	var kind := icon
	if key == "sfx" and not on:
		kind = "speaker_off"
	var ic := IconDraw.new(kind, UIKit.C_TEAL if on else UIKit.C_MUTED, true)
	ic.custom_minimum_size = Vector2(64 * u, 64 * u)
	ic.size_flags_vertical = Control.SIZE_SHRINK_CENTER
	row.add_child(ic)
	var cap := UIKit.label(caption, int(36 * u), UIKit.C_TEXT, false, HORIZONTAL_ALIGNMENT_LEFT)
	cap.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	row.add_child(cap)
	var state := UIKit.button(I18n.t("set_on") if on else I18n.t("set_off"), on, Vector2(190 * u, 72 * u), int(34 * u))
	state.pressed.connect(_on_toggle.bind(key, on))
	row.add_child(state)
	return row


func _on_toggle(key: String, was_on: bool) -> void:
	Save.set_setting(key, not was_on)
	if key == "music":
		if was_on:
			Audio.stop_music()
		else:
			Audio.start_music()
	shell.show_settings()


func _language_row(u: float) -> Control:
	var row := HBoxContainer.new()
	row.custom_minimum_size = Vector2(0, 84 * u)
	row.add_theme_constant_override("separation", int(16 * u))
	row.mouse_filter = Control.MOUSE_FILTER_IGNORE
	var ic := IconDraw.new("star", UIKit.C_TEAL, true)
	ic.custom_minimum_size = Vector2(64 * u, 64 * u)
	ic.size_flags_vertical = Control.SIZE_SHRINK_CENTER
	row.add_child(ic)
	var cap := UIKit.label(I18n.t("set_language"), int(36 * u), UIKit.C_TEXT, false, HORIZONTAL_ALIGNMENT_LEFT)
	cap.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	row.add_child(cap)
	var other := "فارسی" if I18n.language == "en" else "English"
	var lang := UIKit.button(other, false, Vector2(190 * u, 72 * u), int(34 * u))
	lang.pressed.connect(func(): I18n.set_language("fa" if I18n.language == "en" else "en"))
	row.add_child(lang)
	return row


func _ask_reset() -> void:
	if is_instance_valid(_confirm):
		return
	var u := unit()
	var root := UIKit.dim_backdrop(0.65)
	var cc := CenterContainer.new()
	UIKit.fill_parent(cc)
	cc.mouse_filter = Control.MOUSE_FILTER_PASS
	root.add_child(cc)
	var p := UIKit.panel(UIKit.C_PANEL, int(34 * u))
	p.custom_minimum_size = Vector2(minf(size.x * 0.86, 640.0 * u), 0)
	p.mouse_filter = Control.MOUSE_FILTER_STOP
	cc.add_child(p)
	var col := VBoxContainer.new()
	col.add_theme_constant_override("separation", int(18 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	p.add_child(col)
	var msg := UIKit.label(I18n.t("set_reset_ask"), int(34 * u), UIKit.C_TEXT)
	msg.custom_minimum_size = Vector2(0, 140 * u)
	msg.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	col.add_child(msg)
	var yes := UIKit.button(I18n.t("set_reset_yes"), true, Vector2(clampf(size.x * 0.6, 220.0, 520.0 * u), 84 * u), int(38 * u))
	yes.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	yes.pressed.connect(func():
		Save.reset_progress()
		_close_confirm()
		shell.toast(I18n.t("set_reset_done"))
		shell.show_settings())
	col.add_child(yes)
	var no := UIKit.button(I18n.t("set_reset_no"), false, Vector2(clampf(size.x * 0.6, 220.0, 520.0 * u), 84 * u), int(38 * u))
	no.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	no.pressed.connect(func(): _close_confirm())
	col.add_child(no)
	add_child(root)
	_confirm = root


func _close_confirm() -> void:
	if is_instance_valid(_confirm):
		_confirm.queue_free()
	_confirm = null
