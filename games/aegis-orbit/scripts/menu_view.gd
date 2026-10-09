extends "res://scripts/screen_base.gd"
const UIKit = preload("res://scripts/ui_kit.gd")

## Main menu. Built from containers, so every size follows the real viewport.


func _build() -> void:
	var u := unit()
	var root := MarginContainer.new()
	UIKit.fill_parent(root)
	root.mouse_filter = Control.MOUSE_FILTER_IGNORE
	root.add_theme_constant_override("margin_left", int(40 * u))
	root.add_theme_constant_override("margin_right", int(40 * u))
	root.add_theme_constant_override("margin_top", int(80 * u))
	root.add_theme_constant_override("margin_bottom", int(60 * u))
	add_child(root)

	var col := VBoxContainer.new()
	col.alignment = BoxContainer.ALIGNMENT_CENTER
	col.add_theme_constant_override("separation", int(18 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	root.add_child(col)

	col.add_child(_language_row(u))

	var top_space := Control.new()
	top_space.size_flags_vertical = Control.SIZE_EXPAND_FILL
	top_space.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_child(top_space)

	var logo := TextureRect.new()
	logo.texture = Assets.texture("icon")
	logo.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	logo.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	logo.custom_minimum_size = Vector2(300, 300) * u
	logo.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	logo.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_child(logo)

	var title := UIKit.label(I18n.t("app_name"), int(84 * u), UIKit.C_GOLD, true)
	title.custom_minimum_size = Vector2(0, 110 * u)
	col.add_child(title)

	var tag := UIKit.label(I18n.t("set_about"), int(30 * u), UIKit.C_MUTED)
	tag.custom_minimum_size = Vector2(0, 60 * u)
	col.add_child(tag)

	var btn_h := clampf(96.0 * u, 64.0, 120.0)
	var play := _button(I18n.t("menu_play"), true, btn_h, u)
	play.pressed.connect(func(): shell.show_game("classic"))
	col.add_child(play)
	col.add_child(_rule_label(I18n.t("menu_classic_rules"), u))

	var daily_done := Save.daily_attempted_today()
	var daily := _button(I18n.t("menu_daily"), false, btn_h, u)
	daily.disabled = daily_done
	daily.pressed.connect(func(): shell.show_game("daily"))
	col.add_child(daily)
	if daily_done and Save.daily_score_today() < 0:
		col.add_child(_rule_label(I18n.t("menu_daily_quit"), u))
	elif daily_done:
		col.add_child(_rule_label(I18n.fmt("menu_daily_done", [Save.daily_score_today()]), u))
	else:
		col.add_child(_rule_label(I18n.t("menu_daily_rules"), u))

	var rec := _button(I18n.t("menu_records"), false, btn_h, u)
	rec.pressed.connect(func(): shell.show_records())
	col.add_child(rec)
	var set_btn := _button(I18n.t("menu_settings"), false, btn_h, u)
	set_btn.pressed.connect(func(): shell.show_settings())
	col.add_child(set_btn)

	var bottom_space := Control.new()
	bottom_space.size_flags_vertical = Control.SIZE_EXPAND_FILL
	bottom_space.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_child(bottom_space)

	var best := UIKit.label(I18n.fmt("hud_best", [int(Save.data.stats.best_score)]), int(34 * u), UIKit.C_TEAL, true)
	best.custom_minimum_size = Vector2(0, 50 * u)
	col.add_child(best)


func _button(text: String, primary: bool, h: float, u: float) -> Button:
	var w := clampf(_width() * 0.72, 260.0, 620.0 * u)
	return UIKit.button(text, primary, Vector2(w, h), int(44 * u))


func _width() -> float:
	return size.x if size.x > 2.0 else get_viewport_rect().size.x


func _rule_label(text: String, u: float) -> Label:
	var l := UIKit.label(text, int(26 * u), UIKit.C_MUTED)
	l.custom_minimum_size = Vector2(0, 40 * u)
	l.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	return l


func _language_row(u: float) -> Control:
	var row := HBoxContainer.new()
	row.alignment = BoxContainer.ALIGNMENT_END
	row.mouse_filter = Control.MOUSE_FILTER_IGNORE
	var other := "فارسی" if I18n.language == "en" else "English"
	var lang := UIKit.button(other, false, Vector2(180 * u, 64 * u), int(32 * u))
	lang.pressed.connect(func(): I18n.set_language("fa" if I18n.language == "en" else "en"))
	row.add_child(lang)
	return row
