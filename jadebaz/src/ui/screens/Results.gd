extends Screen
## Results.gd — صفحهٔ پایان مسیر: ستاره‌ها، مسافت، سکه‌ها و پاداش‌ها
## دکمه‌ها: خانه / دوباره / بعدی — شبیه صفحهٔ نتیجهٔ شوفر.

var panel: PanelContainer
var bg_added := false
var stars_row: HBoxContainer
var title_lbl: Label
var dist_lbl: Label
var reward_box: VBoxContainer
var level_id := "tehran"
var failed := false
var last_data := {}

func build() -> void:
	panel = UI.panel(Color("#141d33"), 34)
	panel.position = Vector2(300, 70)
	panel.size = Vector2(680, 580)
	content.add_child(panel)
	var v := VBoxContainer.new()
	v.add_theme_constant_override("separation", 14)
	panel.add_child(v)
	title_lbl = UI.title("ماموریت انجام شد!", 44)
	title_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(title_lbl)
	stars_row = UI.stars_row(0, 62)
	stars_row.alignment = BoxContainer.ALIGNMENT_CENTER
	v.add_child(stars_row)
	dist_lbl = UI.label("", 30, UI.COL_INK)
	dist_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(dist_lbl)
	reward_box = VBoxContainer.new()
	reward_box.add_theme_constant_override("separation", 8)
	v.add_child(reward_box)
	var row := HBoxContainer.new()
	row.add_theme_constant_override("separation", 16)
	row.alignment = BoxContainer.ALIGNMENT_CENTER
	var home := UI.button("خانه", "blue", 190, 86, 28)
	home.pressed.connect(func():
		Snd.play("back")
		go("menu"))
	var retry := UI.button("دوباره", "orange", 190, 86, 28)
	retry.pressed.connect(func():
		Snd.play("click")
		go("game", { "level": level_id }))
	var next := UI.button("بعدی", "green", 190, 86, 28)
	next.pressed.connect(func():
		Snd.play("click")
		go("map"))
	row.add_child(home)
	row.add_child(retry)
	row.add_child(next)
	v.add_child(row)

func show_result(data: Dictionary) -> void:
	last_data = data
	level_id = str(data.get("level", "tehran"))
	failed = bool(data.get("failed", false))
	var lv := Game.level_data(level_id)
	if not bg_added:
		add_bg(str(lv["sky"]), str(lv["bg1"]), str(lv["bg2"]))
		bg_added = true
	_apply()
	if panel:
		UI.pop_in(panel, 0.35)

func refresh() -> void:
	if not last_data.is_empty():
		_apply()

func _apply() -> void:
	var d := last_data
	if d.is_empty():
		return
	var lv := Game.level_data(level_id)
	var stars := int(d.get("stars", 0))
	if failed:
		title_lbl.text = "این بار نشد!"
		title_lbl.add_theme_color_override("font_color", Color("#ffb0a0"))
	else:
		title_lbl.text = "ماموریت انجام شد!"
		title_lbl.add_theme_color_override("font_color", Color("#fff3c4"))
	# ستاره‌ها یکی‌یکی روشن شوند
	for i in stars_row.get_child_count():
		var tr: TextureRect = stars_row.get_child(i)
		var on := i < stars
		tr.modulate = Color(1, 1, 1, 1) if on else Color(0.25, 0.3, 0.4, 0.85)
		if on:
			tr.scale = Vector2(0.4, 0.4)
			tr.pivot_offset = tr.size / 2.0
			var t := tr.create_tween()
			t.tween_interval(0.18 * float(i))
			t.tween_property(tr, "scale", Vector2(1.25, 1.25), 0.22).set_trans(Tween.TRANS_BACK).set_ease(Tween.EASE_OUT)
			t.tween_property(tr, "scale", Vector2.ONE, 0.12)
	dist_lbl.text = "مسافت: %s متر از %s متر" % [Game.fa(int(d.get("dist", 0.0))), Game.fa(int(lv["length"]))]
	for c in reward_box.get_children():
		c.queue_free()
	var coins := int(d.get("coins", 0))
	var reward := int(d.get("reward", 0))
	var mission := int(d.get("mission", 0))
	var gems := int(d.get("gems", 0))
	_add_row("سکه‌های مسیر", coins, "icon_coin")
	_add_row("جایزهٔ مسیر", reward, "icon_trophy")
	if mission > 0:
		_add_row("پاداش ماموریت", mission, "icon_mission")
	if gems > 0:
		_add_row("الماس", gems, "icon_gem")
	var stunts := int(d.get("stunts", 0))
	if stunts > 0:
		_add_row("پرش و چرخش", stunts, "icon_star")

func _add_row(title: String, value: int, icon: String) -> void:
	var h := HBoxContainer.new()
	h.add_theme_constant_override("separation", 12)
	h.alignment = BoxContainer.ALIGNMENT_CENTER
	var ic := TextureRect.new()
	ic.texture = UI.tex(icon)
	ic.custom_minimum_size = Vector2(40, 40)
	ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	h.add_child(ic)
	var l := UI.label(title, 24, UI.COL_DIM)
	l.custom_minimum_size = Vector2(260, 36)
	l.horizontal_alignment = HORIZONTAL_ALIGNMENT_RIGHT
	h.add_child(l)
	var val := UI.label("+%s" % Game.money(value), 26, UI.COL_GOLD)
	h.add_child(val)
	reward_box.add_child(h)
