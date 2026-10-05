extends Screen
## WorldMap.gd — نقشهٔ ایران: کارت مسیرها با رکورد، ستاره و قفل
## کارت‌ها از راست به چپ چیده می‌شوند (RTL) و با انیمیشن وارد می‌شوند.

var scroll: ScrollContainer
var row: HBoxContainer
var cards := {}

func build() -> void:
	add_bg("sky_dusk", "mtn_desert", "city_dusk")
	top_bar("ماجراجویی — انتخاب مسیر")
	scroll = ScrollContainer.new()
	scroll.position = Vector2(30, 120)
	scroll.size = Vector2(1220, 560)
	scroll.horizontal_scroll_mode = ScrollContainer.SCROLL_MODE_AUTO
	scroll.vertical_scroll_mode = ScrollContainer.SCROLL_MODE_DISABLED
	content.add_child(scroll)
	row = HBoxContainer.new()
	row.add_theme_constant_override("separation", 22)
	row.layout_direction = Control.LAYOUT_DIRECTION_RTL
	scroll.add_child(row)
	for lv in Game.LEVELS:
		_make_card(lv)
	# دکمهٔ برگشت در پایین
	var back := UI.button("‹  بازگشت به خانه", "gray", 300, 76, 26)
	back.position = Vector2(40, 620)
	back.pressed.connect(func(): go("menu"))
	content.add_child(back)

func _make_card(lv: Dictionary) -> void:
	var id := str(lv["id"])
	var p := UI.panel(Color("#182238"), 28)
	p.custom_minimum_size = Vector2(340, 500)
	p.clip_contents = true
	var v := VBoxContainer.new()
	v.add_theme_constant_override("separation", 10)
	p.add_child(v)
	# تصویر مسیر
	var thumb := TextureRect.new()
	thumb.texture = UI.tex_at("res://assets/bg/%s.png" % str(lv["sky"]))
	thumb.custom_minimum_size = Vector2(300, 150)
	thumb.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	thumb.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_COVERED
	thumb.clip_contents = true
	v.add_child(thumb)
	var name_lbl := UI.label(str(lv["name"]), 34)
	name_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(name_lbl)
	var teaser := UI.label(str(lv["teaser"]), 19, UI.COL_DIM)
	teaser.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	teaser.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	teaser.custom_minimum_size = Vector2(300, 46)
	v.add_child(teaser)

	var best := UI.label("", 24, UI.COL_GOLD)
	best.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	best.name = "best"
	v.add_child(best)
	var stars := UI.stars_row(0, 40)
	stars.alignment = BoxContainer.ALIGNMENT_CENTER
	stars.name = "stars"
	v.add_child(stars)
	# دکمهٔ بازی/باز کردن
	var unlocked := Game.level_unlocked(id)
	var btn := UI.button("رانندگی" if unlocked else "باز کردن مسیر", "green" if unlocked else "gray", 270, 82, 30)
	btn.name = "play"
	btn.pressed.connect(func(): _on_play(id))
	v.add_child(btn)
	var lock_ic := TextureRect.new()
	lock_ic.texture = UI.tex("icon_lock")
	lock_ic.custom_minimum_size = Vector2(40, 40)
	lock_ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	lock_ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	lock_ic.name = "lock"
	v.add_child(lock_ic)
	content.add_child(p)
	cards[id] = p

func _on_play(id: String) -> void:
	var lv := Game.level_data(id)
	if not Game.level_unlocked(id):
		if Game.total_stars < int(lv["unlock_stars"]):
			Snd.play("denied")
			_toast("برای باز کردن این مسیر به %s ستاره نیاز داری" % Game.fa(int(lv["unlock_stars"])))
			return
		if not Game.unlock_level(id):
			Snd.play("denied")
			_toast("سکه کافی نداری! (%s سکه لازم است)" % Game.money(int(lv["unlock_coins"])))
			return
		Snd.play("buy")
		_toast("مسیر باز شد!")
		refresh()
		return
	Snd.play("click")
	go("game", { "level": id })

func _toast(text: String) -> void:
	var l := UI.label(text, 28, Color(1, 0.92, 0.6))
	l.position = Vector2(300, 640)
	l.size = Vector2(700, 46)
	l.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	content.add_child(l)
	var t := l.create_tween()
	t.tween_property(l, "modulate:a", 0.0, 1.6).set_delay(0.9)
	t.tween_callback(l.queue_free)

func refresh() -> void:
	for id in cards.keys():
		var p: PanelContainer = cards[id]
		var lv := Game.level_data(id)
		var rec: Dictionary = Game.levels.get(id, {})
		var best: Label = null
		var stars_node: HBoxContainer = null
		var btn: Button = null
		var lock: TextureRect = null
		for c in p.get_child(0).get_children():
			if c.name == "best": best = c
			elif c.name == "stars": stars_node = c
			elif c.name == "play": btn = c
			elif c.name == "lock": lock = c
		var unlocked := Game.level_unlocked(id)
		var m := float(rec.get("best", 0.0))
		if best:
			if m > 0.0:
				best.text = "رکورد: %s متر" % Game.fa(int(m))
			else:
				best.text = "هنوز رانندگی نکرده‌ای"
		if stars_node:
			var want := int(rec.get("stars", 0))
			for i in stars_node.get_child_count():
				var tr: TextureRect = stars_node.get_child(i)
				tr.modulate = Color(1, 1, 1, 1) if i < want else Color(0.25, 0.3, 0.4, 0.85)
		if btn:
			btn.text = "رانندگی" if unlocked else "باز کردن مسیر"
		if lock:
			lock.visible = not unlocked
		p.modulate = Color(1, 1, 1, 1) if unlocked else Color(0.75, 0.78, 0.86, 1)
		UI.pop_in(p, 0.3)
