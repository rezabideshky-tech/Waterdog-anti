extends Screen
## Garage.gd — گاراژ: انتخاب ماشین، ارتقاها، رنگ (صافکاری) و رینگ
## تب‌های ارتقا: موتور، لاستیک، کمک‌فنر، نیترو، باک — هر کدام با قیمت و سطح.

var car_actor: CarActor
var name_lbl: Label
var desc_lbl: Label
var stat_bars := {}
var tab_buttons := {}
var tab_icon: TextureRect
var tab_title: Label
var tab_desc: Label
var tab_level: Label
var tab_pips: HBoxContainer
var up_btn: AnimatedButton
var buy_btn: AnimatedButton
var select_btn: AnimatedButton
var cur_tab := "engine"
var index := 0
var overlay: Control

const ORDER := ["engine", "tires", "suspension", "turbo", "tank"]

func build() -> void:
	add_bg("sky_day", "forest_green", "mtn_day")
	top_bar("گاراژ — تعمیرگاه مهندس")
	_build_podium()
	_build_tabs()
	_build_actions()

func _build_podium() -> void:
	var panel := UI.frame(Color("#16203a"), 30)
	panel.position = Vector2(30, 120)
	panel.size = Vector2(470, 500)
	content.add_child(panel)
	var floor := ColorRect.new()
	floor.color = Color("#243a55")
	floor.position = Vector2(20, 350)
	floor.size = Vector2(430, 60)
	panel.add_child(floor)

	car_actor = CarActor.new()
	car_actor.position = Vector2(235, 290)
	panel.add_child(car_actor)

	var left := UI.button("‹", "blue", 76, 76, 34)
	left.position = Vector2(16, 120)
	left.pressed.connect(func(): _step(1))
	panel.add_child(left)
	var right := UI.button("›", "blue", 76, 76, 34)
	right.position = Vector2(378, 120)
	right.pressed.connect(func(): _step(-1))
	panel.add_child(right)

	name_lbl = UI.title("پیکان", 44)
	name_lbl.position = Vector2(10, 20)
	name_lbl.size = Vector2(450, 60)
	name_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	panel.add_child(name_lbl)

	desc_lbl = UI.label("", 20, UI.COL_DIM)
	desc_lbl.position = Vector2(20, 78)
	desc_lbl.size = Vector2(430, 60)
	desc_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	desc_lbl.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	panel.add_child(desc_lbl)

	# نوارهای آمار
	var names := [["power", "قدرت"], ["grip", "چسبندگی"], ["susp", "کمکفنر"], ["fuel", "باک"]]
	for i in names.size():
		var key := str(names[i][0])
		var row := HBoxContainer.new()
		row.position = Vector2(24, 400 + i * 24)
		row.size = Vector2(420, 22)
		var l := UI.label(str(names[i][1]), 19, UI.COL_DIM)
		l.custom_minimum_size = Vector2(90, 20)
		row.add_child(l)
		var bar := ProgressBar.new()
		bar.custom_minimum_size = Vector2(300, 18)
		bar.max_value = 100.0
		bar.show_percentage = false
		bar.add_theme_stylebox_override("background", UI.panel_style(Color("#0e1526"), 8))
		bar.add_theme_stylebox_override("fill", UI.panel_style(Color("#5ff5c8"), 8))
		bar.name = key
		row.add_child(bar)
		panel.add_child(row)
		stat_bars[key] = bar

func _build_tabs() -> void:
	var panel := UI.frame(Color("#1b2740"), 30)
	panel.position = Vector2(520, 120)
	panel.size = Vector2(730, 500)
	content.add_child(panel)

	var tabs := HBoxContainer.new()
	tabs.add_theme_constant_override("separation", 10)
	tabs.position = Vector2(20, 18)
	tabs.layout_direction = Control.LAYOUT_DIRECTION_RTL
	panel.add_child(tabs)
	for key in ORDER:
		var def: Dictionary = Game.UPGRADES[key]
		var b := UI.button(str(def["name"]), "orange", 132, 72, 22)
		b.pressed.connect(func(): _select_tab(key))
		tabs.add_child(b)
		tab_buttons[key] = b

	tab_icon = TextureRect.new()
	tab_icon.position = Vector2(30, 120)
	tab_icon.size = Vector2(120, 120)
	tab_icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	tab_icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	panel.add_child(tab_icon)

	tab_title = UI.title("موتور", 40)
	tab_title.position = Vector2(170, 118)
	tab_title.size = Vector2(520, 54)
	panel.add_child(tab_title)

	tab_desc = UI.label("", 21, UI.COL_DIM)
	tab_desc.position = Vector2(60, 190)
	tab_desc.size = Vector2(620, 80)
	tab_desc.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	panel.add_child(tab_desc)

	tab_pips = HBoxContainer.new()
	tab_pips.add_theme_constant_override("separation", 6)
	tab_pips.position = Vector2(60, 290)
	panel.add_child(tab_pips)

	tab_level = UI.label("", 26, UI.COL_GOLD)
	tab_level.position = Vector2(60, 340)
	tab_level.size = Vector2(620, 40)
	panel.add_child(tab_level)

	up_btn = UI.button("ارتقا", "green", 420, 90, 32)
	up_btn.position = Vector2(150, 390)
	up_btn.pressed.connect(_do_upgrade)
	panel.add_child(up_btn)

func _build_actions() -> void:
	buy_btn = UI.button("خرید ماشین", "orange", 300, 84, 28)
	buy_btn.position = Vector2(60, 634)
	buy_btn.pressed.connect(_do_buy)
	content.add_child(buy_btn)

	select_btn = UI.button("انتخاب این ماشین", "green", 300, 84, 26)
	select_btn.position = Vector2(380, 634)
	select_btn.pressed.connect(func():
		Game.select_car(Game.CAR_ORDER[index])
		Snd.play("click")
		refresh())
	content.add_child(select_btn)

	var paint_btn := UI.button("صافکاری و رنگ", "blue", 250, 84, 26)
	paint_btn.position = Vector2(760, 634)
	paint_btn.pressed.connect(func(): _open_paints())
	content.add_child(paint_btn)

	var rim_btn := UI.button("رینگ اسپرت", "blue", 230, 84, 26)
	rim_btn.position = Vector2(1024, 634)
	rim_btn.pressed.connect(func(): _open_rims())
	content.add_child(rim_btn)

func refresh() -> void:
	index = maxi(0, Game.CAR_ORDER.find(Game.car))
	_show_car()

func _step(dir: int) -> void:
	index = wrapi(index + dir, 0, Game.CAR_ORDER.size())
	Snd.play("click")
	_show_car()
	UI.pop_in(car_actor, 0.25)

func _show_car() -> void:
	var id := str(Game.CAR_ORDER[index])
	var d := Game.car_data(id)
	var owns := Game.has_car(id)
	var color := Game.paint_color() if owns else Color(0.72, 0.74, 0.8)
	car_actor.setup(id, color, Game.rim if owns else 1, 0.48)
	car_actor.position = Vector2(235, 290)
	car_actor.speed = 2.4
	name_lbl.text = str(d["name"])
	desc_lbl.text = str(d["desc"])
	var s := Game.car_stats(id)
	stat_bars["power"].value = clampf(float(s["power"]) / 2.2 * 100.0, 6.0, 100.0)
	stat_bars["grip"].value = clampf(float(s["grip"]) / 1.4 * 100.0, 6.0, 100.0)
	stat_bars["susp"].value = clampf(float(s["susp"]) / 1.5 * 100.0, 6.0, 100.0)
	stat_bars["fuel"].value = clampf(float(s["fuel"]) / 130.0 * 100.0, 6.0, 100.0)
	buy_btn.visible = not owns
	select_btn.visible = owns
	if not owns:
		var need_stars := int(d["stars"])
		if need_stars > Game.total_stars:
			buy_btn.text = "قفل — %s ستاره لازم است" % Game.fa(need_stars)
			buy_btn.disabled = true
		else:
			buy_btn.text = "خرید — %s سکه" % Game.money(int(d["price"]))
			buy_btn.disabled = Game.coins < int(d["price"])
	else:
		select_btn.disabled = (Game.car == id)
		select_btn.text = "همین ماشین انتخاب است" if Game.car == id else "انتخاب این ماشین"
	_select_tab(cur_tab)

func _select_tab(key: String) -> void:
	cur_tab = key
	Snd.play("click")
	var def: Dictionary = Game.UPGRADES[key]
	var id := str(Game.CAR_ORDER[index])
	var lv := Game.upg_level(id, key)
	var mx := int(def["max"])
	tab_icon.texture = UI.tex("icon_%s" % str(def["icon"]))
	tab_title.text = "ارتقای %s" % str(def["name"])
	tab_desc.text = str(def["desc"])
	tab_level.text = "سطح %s از %s" % [Game.fa(lv), Game.fa(mx)]
	for c in tab_pips.get_children():
		c.queue_free()
	for i in mx:
		var tr := TextureRect.new()
		tr.texture = UI.tex("icon_star")
		tr.custom_minimum_size = Vector2(24, 24)
		tr.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		tr.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		tr.modulate = Color(1, 1, 1, 1) if i < lv else Color(0.25, 0.3, 0.4, 0.7)
		tab_pips.add_child(tr)
	for k in tab_buttons.keys():
		var b: AnimatedButton = tab_buttons[k]
		b.disabled = (k == key)
		var l: int = Game.upg_level(id, k)
		b.text = "%s\n%s/%s" % [str(Game.UPGRADES[k]["name"]), Game.fa(l), Game.fa(int(Game.UPGRADES[k]["max"]))]
	if Game.upg_maxed(id, key):
		up_btn.text = "کامل ارتقا یافته ✔"
		up_btn.disabled = true
	else:
		var cost := Game.upg_cost(id, key)
		up_btn.text = "ارتقا — %s سکه" % Game.money(cost)
		up_btn.disabled = Game.coins < cost

func _do_upgrade() -> void:
	var id := str(Game.CAR_ORDER[index])
	if Game.buy_upgrade(id, cur_tab):
		Snd.play("buy")
		UI.pop_in(car_actor, 0.25)
		_show_car()
	else:
		Snd.play("denied")
		_flash(up_btn)

func _do_buy() -> void:
	var id := str(Game.CAR_ORDER[index])
	if Game.buy_car(id):
		Snd.play("win")
		UI.pop_in(car_actor, 0.4)
		_show_car()
	else:
		Snd.play("denied")
		_flash(buy_btn)

func _flash(node: CanvasItem) -> void:
	var t := node.create_tween()
	t.tween_property(node, "modulate", Color(1, 0.5, 0.5), 0.08)
	t.tween_property(node, "modulate", Color.WHITE, 0.08)

# -------------------------------------------------------------- پنجره‌ها
func _open_paints() -> void:
	_close_overlay()
	var p := UI.panel(Color("#16203a"), 30)
	p.position = Vector2(280, 150)
	p.size = Vector2(720, 420)
	var title := UI.title("صافکاری و رنگ", 36)
	title.position = Vector2(20, 10)
	title.size = Vector2(680, 50)
	title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	p.add_child(title)
	var grid := GridContainer.new()
	grid.columns = 5
	grid.add_theme_constant_override("h_separation", 16)
	grid.add_theme_constant_override("v_separation", 16)
	grid.position = Vector2(40, 80)
	p.add_child(grid)
	for pid in Game.PAINT_ORDER:
		var def: Dictionary = Game.PAINTS[pid]
		var owned := Game.owned_paints.has(pid)
		var b := AnimatedButton.new()
		b.kind = "blue" if owned else "gray"
		b.custom_minimum_size = Vector2(120, 130)
		b.text = str(def["name"]) if owned else "%s\n%s" % [str(def["name"]), Game.money(int(def["price"]))]
		b.add_theme_font_size_override("font_size", 17)
		var sb := ColorRect.new()
		sb.color = Color(str(def["color"]))
		sb.position = Vector2(30, 8)
		sb.size = Vector2(60, 44)
		sb.mouse_filter = Control.MOUSE_FILTER_IGNORE
		b.add_child(sb)
		b.pressed.connect(func():
			if Game.buy_paint(pid):
				Snd.play("buy")
				_close_overlay()
				_show_car()
			else:
				Snd.play("denied"))
		grid.add_child(b)
	var close := UI.button("بستن", "red", 200, 70, 26)
	close.position = Vector2(260, 330)
	close.pressed.connect(_close_overlay)
	p.add_child(close)
	_show_overlay(p)

func _open_rims() -> void:
	_close_overlay()
	var p := UI.panel(Color("#16203a"), 30)
	p.position = Vector2(280, 170)
	p.size = Vector2(720, 380)
	var title := UI.title("رینگ اسپرت", 36)
	title.position = Vector2(20, 10)
	title.size = Vector2(680, 50)
	title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	p.add_child(title)
	var row := HBoxContainer.new()
	row.add_theme_constant_override("separation", 14)
	row.position = Vector2(40, 80)
	row.layout_direction = Control.LAYOUT_DIRECTION_RTL
	p.add_child(row)
	for i in range(1, Game.RIM_COUNT + 1):
		var owned := Game.rims.has(i)
		var b := AnimatedButton.new()
		b.kind = "green" if Game.rim == i else ("blue" if owned else "gray")
		b.custom_minimum_size = Vector2(90, 150)
		b.text = "" if owned else Game.money(Game.RIM_PRICE)
		b.add_theme_font_size_override("font_size", 15)
		var ic := TextureRect.new()
		ic.texture = UI.tex_at("res://assets/wheels/rim_%d.png" % i)
		ic.position = Vector2(15, 10)
		ic.size = Vector2(60, 60)
		ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		ic.mouse_filter = Control.MOUSE_FILTER_IGNORE
		b.add_child(ic)
		b.pressed.connect(func():
			if Game.buy_rim(i):
				Snd.play("buy")
				_close_overlay()
				_show_car()
			else:
				Snd.play("denied"))
		row.add_child(b)
	var close := UI.button("بستن", "red", 200, 70, 26)
	close.position = Vector2(260, 290)
	close.pressed.connect(_close_overlay)
	p.add_child(close)
	_show_overlay(p)

func _show_overlay(p: Control) -> void:
	overlay = Control.new()
	overlay.set_anchors_preset(Control.PRESET_FULL_RECT)
	var dim := ColorRect.new()
	dim.color = Color(0, 0, 0, 0.55)
	dim.set_anchors_preset(Control.PRESET_FULL_RECT)
	overlay.add_child(dim)
	overlay.add_child(p)
	overlay.z_index = 50
	add_child(overlay)
	UI.pop_in(p, 0.28)

func _close_overlay() -> void:
	if overlay:
		overlay.queue_free()
		overlay = null
