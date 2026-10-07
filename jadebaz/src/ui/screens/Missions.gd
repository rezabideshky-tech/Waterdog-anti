extends Screen
## Missions.gd — کارنامه و ماموریت‌ها: سه ماموریت فعال با نوار پیشرفت و جایزه
## هر ماموریت که پر شود، جایزه‌اش سکه است و بلافاصله ماموریت تازه جایش می‌آید.

var list: VBoxContainer
var stats_lbl: Label

func build() -> void:
	add_bg("sky_day", "forest_autumn", "mtn_day")
	top_bar("کارنامه و ماموریت‌ها")
	list = VBoxContainer.new()
	list.add_theme_constant_override("separation", 16)
	list.position = Vector2(60, 130)
	content.add_child(list)
	var refresh_btn := UI.button("ماموریت‌های تازه (۲ کلید)", "blue", 380, 80, 24)
	refresh_btn.position = Vector2(60, 612)
	refresh_btn.pressed.connect(func():
		if Game.keys >= 2:
			Game.keys -= 2
			Game.roll_missions()
			Game.save_game()
			Snd.play("buy")
			refresh()
		else:
			Snd.play("denied")
			_toast("کلید کافی نداری"))
	content.add_child(refresh_btn)
	stats_lbl = UI.label("", 23, UI.COL_DIM)
	stats_lbl.position = Vector2(560, 628)
	stats_lbl.size = Vector2(660, 40)
	stats_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_RIGHT
	content.add_child(stats_lbl)

func _make_row(idx: int, m: Dictionary) -> PanelContainer:
	var def := Game.mission_def(str(m["id"]))
	var p := UI.panel(Color("#1b2740"), 24)
	p.custom_minimum_size = Vector2(1140, 118)
	var h := HBoxContainer.new()
	h.add_theme_constant_override("separation", 16)
	p.add_child(h)
	var ic := TextureRect.new()
	ic.texture = UI.tex("icon_mission")
	ic.custom_minimum_size = Vector2(60, 60)
	ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	h.add_child(ic)
	var v := VBoxContainer.new()
	v.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	v.add_theme_constant_override("separation", 4)
	h.add_child(v)
	var t := UI.label(str(def.get("text", "ماموریت")), 26)
	v.add_child(t)
	var bar := ProgressBar.new()
	bar.custom_minimum_size = Vector2(620, 20)
	bar.max_value = 100.0
	bar.show_percentage = false
	bar.add_theme_stylebox_override("background", UI.panel_style(Color("#0e1526"), 8))
	bar.add_theme_stylebox_override("fill", UI.panel_style(Color("#38c06a"), 8))
	v.add_child(bar)
	var prog := UI.label("", 19, UI.COL_DIM)
	v.add_child(prog)
	var reward := UI.chip("icon_coin", Game.money(int(def.get("reward", 0))), 170)
	h.add_child(reward)
	var claim := UI.button("دریافت", "green", 170, 70, 24)
	claim.pressed.connect(func(): _claim(idx))
	h.add_child(claim)
	p.set_meta("bar", bar)
	p.set_meta("prog", prog)
	p.set_meta("claim", claim)
	return p

func _claim(idx: int) -> void:
	var got := Game.claim_mission(idx)
	if got > 0:
		Snd.play("win")
		_toast("جایزه گرفتی: %s سکه" % Game.money(got))
		refresh()
	else:
		Snd.play("denied")

func refresh() -> void:
	if list == null:
		return
	for c in list.get_children():
		c.queue_free()
	await get_tree().process_frame
	for i in Game.missions.size():
		var m: Dictionary = Game.missions[i]
		var def := Game.mission_def(str(m["id"]))
		var row := _make_row(i, m)
		list.add_child(row)
		UI.pop_in(row, 0.25)
		var bar: ProgressBar = row.get_meta("bar")
		var prog: Label = row.get_meta("prog")
		var claim: AnimatedButton = row.get_meta("claim")
		var target := maxf(float(def.get("target", 1)), 1.0)
		bar.value = clampf(float(m["progress"]) / target, 0.0, 1.0) * 100.0
		prog.text = "%s از %s" % [Game.fa(int(m["progress"])), Game.fa(int(def.get("target", 0)))]
		if bool(m.get("done", false)):
			claim.text = "دریافت جایزه"
			claim.disabled = false
			claim.wiggle()
		else:
			claim.text = "در جریان"
			claim.disabled = true
	if stats_lbl:
		stats_lbl.text = "کل مسافت: %s متر  |  تعداد رانندگی: %s  |  ستاره‌ها: %s" % [
			Game.fa(int(Game.stats.get("dist", 0.0))), Game.fa(int(Game.stats.get("runs", 0))), Game.fa(Game.total_stars)]

func _toast(text: String) -> void:
	var l := UI.label(text, 28, Color(1, 0.92, 0.6))
	l.position = Vector2(330, 60)
	l.size = Vector2(620, 46)
	l.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	l.z_index = 20
	content.add_child(l)
	var t := l.create_tween()
	t.tween_property(l, "modulate:a", 0.0, 1.6).set_delay(0.9)
	t.tween_callback(l.queue_free)
