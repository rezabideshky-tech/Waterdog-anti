extends "res://scripts/screen_base.gd"
const Achievements = preload("res://scripts/achievements.gd")
const UIKit = preload("res://scripts/ui_kit.gd")
const IconDraw = preload("res://scripts/icon_draw.gd")

## Records: lifetime stats, top-10 runs and the achievement list with progress.


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
	col.add_theme_constant_override("separation", int(20 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	root.add_child(col)

	var header := HBoxContainer.new()
	header.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_child(header)
	var back := UIKit.button(I18n.t("back"), false, Vector2(200 * u, 72 * u), int(34 * u))
	back.pressed.connect(func(): shell.show_menu())
	header.add_child(back)
	var title := UIKit.label(I18n.t("rec_title"), int(50 * u), UIKit.C_GOLD, true)
	title.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	header.add_child(title)
	var spacer := Control.new()
	spacer.custom_minimum_size = Vector2(200 * u, 72 * u)
	spacer.mouse_filter = Control.MOUSE_FILTER_IGNORE
	header.add_child(spacer)

	var scroll := ScrollContainer.new()
	scroll.size_flags_vertical = Control.SIZE_EXPAND_FILL
	scroll.horizontal_scroll_mode = ScrollContainer.SCROLL_MODE_DISABLED
	col.add_child(scroll)

	var list := VBoxContainer.new()
	list.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	list.add_theme_constant_override("separation", int(18 * u))
	list.mouse_filter = Control.MOUSE_FILTER_IGNORE
	scroll.add_child(list)

	_add_stats(list, u)
	_add_top_runs(list, u)
	_add_achievements(list, u)


func _stat_row(parent: Control, caption: String, value: String, u: float) -> void:
	var row := HBoxContainer.new()
	row.mouse_filter = Control.MOUSE_FILTER_IGNORE
	row.custom_minimum_size = Vector2(0, 58 * u)
	var cap := UIKit.label(caption, int(32 * u), UIKit.C_MUTED, false, HORIZONTAL_ALIGNMENT_LEFT)
	cap.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	row.add_child(cap)
	var val := UIKit.label(value, int(36 * u), UIKit.C_TEXT, true, HORIZONTAL_ALIGNMENT_RIGHT)
	row.add_child(val)
	parent.add_child(row)


func _add_stats(list: Control, u: float) -> void:
	var p := UIKit.panel(UIKit.C_PANEL, int(28 * u))
	list.add_child(p)
	var col := VBoxContainer.new()
	col.add_theme_constant_override("separation", int(8 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	p.add_child(col)
	var s: Dictionary = Save.data.stats
	_stat_row(col, I18n.t("rec_best_score"), I18n.digits(str(s.best_score)), u)
	_stat_row(col, I18n.t("rec_best_combo"), I18n.digits(str(s.best_combo)), u)
	_stat_row(col, I18n.t("rec_best_wave"), I18n.digits(str(s.best_wave)), u)
	_stat_row(col, I18n.t("rec_games"), I18n.digits(str(s.games)), u)
	_stat_row(col, I18n.t("rec_blocks"), I18n.digits(str(s.total_blocks)), u)
	_stat_row(col, I18n.t("rec_daily"), I18n.digits(str(s.daily_done)), u)


func _add_top_runs(list: Control, u: float) -> void:
	list.add_child(UIKit.label(I18n.t("rec_top"), int(38 * u), UIKit.C_TEAL, true))
	var recs: Array = Save.data.records
	if recs.is_empty():
		var empty := UIKit.label(I18n.t("rec_empty"), int(32 * u), UIKit.C_MUTED)
		empty.custom_minimum_size = Vector2(0, 80 * u)
		list.add_child(empty)
		return
	var p := UIKit.panel(UIKit.C_PANEL, int(28 * u))
	list.add_child(p)
	var col := VBoxContainer.new()
	col.add_theme_constant_override("separation", int(6 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	p.add_child(col)
	var rank := 1
	for r in recs:
		var mode_name := I18n.t("rec_mode_daily") if r.mode == "daily" else I18n.t("rec_mode_classic")
		var line := "%d.  %s  ·  %s  ·  %s" % [rank, str(r.score), mode_name, str(r.date)]
		var l := UIKit.label(I18n.digits(line), int(30 * u), UIKit.C_TEXT, rank == 1, HORIZONTAL_ALIGNMENT_LEFT)
		l.custom_minimum_size = Vector2(0, 46 * u)
		col.add_child(l)
		rank += 1


func _add_achievements(list: Control, u: float) -> void:
	var unlocked: Array = Save.data.achievements
	list.add_child(UIKit.label("%s  %s" % [I18n.t("ach_title"), I18n.digits("%d/%d" % [unlocked.size(), Achievements.DEFS.size()])], int(38 * u), UIKit.C_TEAL, true))
	var p := UIKit.panel(UIKit.C_PANEL, int(28 * u))
	list.add_child(p)
	var col := VBoxContainer.new()
	col.add_theme_constant_override("separation", int(14 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	p.add_child(col)
	for d in Achievements.DEFS:
		var id: String = d.id
		var done := unlocked.has(id)
		var row := HBoxContainer.new()
		row.add_theme_constant_override("separation", int(18 * u))
		row.mouse_filter = Control.MOUSE_FILTER_IGNORE
		col.add_child(row)
		var badge := IconDraw.new("star" if done else "lock", UIKit.C_GOLD if done else UIKit.C_MUTED, true)
		badge.custom_minimum_size = Vector2(72 * u, 72 * u)
		row.add_child(badge)
		var texts := VBoxContainer.new()
		texts.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		texts.mouse_filter = Control.MOUSE_FILTER_IGNORE
		row.add_child(texts)
		var name_l := UIKit.label(I18n.t("ach_" + id), int(32 * u), UIKit.C_GOLD if done else UIKit.C_TEXT, true, HORIZONTAL_ALIGNMENT_LEFT)
		texts.add_child(name_l)
		var desc := I18n.t("ach_%s_d" % id)
		var pr := Achievements.progress(Save.data.stats, id)
		if not done and pr.y > 1:
			desc += "   " + I18n.digits("%d/%d" % [pr.x, pr.y])
		var desc_l := UIKit.label(desc, int(26 * u), UIKit.C_MUTED, false, HORIZONTAL_ALIGNMENT_LEFT)
		desc_l.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
		texts.add_child(desc_l)
