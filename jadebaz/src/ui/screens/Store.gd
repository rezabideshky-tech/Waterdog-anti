extends Screen
## Store.gd — فروشگاه: بسته‌های سکه، کلید، و گردونهٔ شانس
## خریدها با الماس انجام می‌شود تا بازی کاملاً آفلاین بماند.

var wheel: Wheel
var wheel_panel: Panel
var keys_chip: PanelContainer
var spin_btn: AnimatedButton
var coin_cards := []
var key_cards := []

func build() -> void:
	add_bg("sky_dusk", "city_night", "mtn_dusk")
	top_bar("فروشگاه")
	_build_coins()
	_build_keys()
	_build_wheel()

func _build_coins() -> void:
	var title := UI.label("بسته‌های سکه", 30, UI.COL_GOLD)
	title.position = Vector2(40, 118)
	content.add_child(title)
	var box := VBoxContainer.new()
	box.add_theme_constant_override("separation", 12)
	box.position = Vector2(40, 160)
	content.add_child(box)
	var names := ["یه مشت سکه", "یه پنل سکه", "یه سطل سکه", "یه کیسه سکه", "یه فرغون سکه"]
	for i in Game.SHOP_COINS.size():
		var pack: Dictionary = Game.SHOP_COINS[i]
		var row := UI.panel(Color("#1b2740"), 22)
		row.custom_minimum_size = Vector2(430, 84)
		var h := HBoxContainer.new()
		h.add_theme_constant_override("separation", 10)
		row.add_child(h)
		var ic := TextureRect.new()
		ic.texture = UI.tex("icon_coin")
		ic.custom_minimum_size = Vector2(52, 52)
		ic.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
		ic.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
		h.add_child(ic)
		var lb := UI.label("%s — %s" % [names[i], Game.money(int(pack["coins"]))], 23)
		lb.size_flags_horizontal = Control.SIZE_EXPAND_FILL
		h.add_child(lb)
		var b := UI.button("%s 💎" % Game.fa(int(pack["gems"])), "green", 150, 66, 24)
		b.pressed.connect(func(): _buy_pack(pack))
		h.add_child(b)
		box.add_child(row)
		coin_cards.append(row)

func _build_keys() -> void:
	var title := UI.label("کلید گردونه", 30, UI.COL_GOLD)
	title.position = Vector2(40, 600 - 30)
	content.add_child(title)

func _build_wheel() -> void:
	var panel := UI.frame(Color("#16203a"), 30)
	wheel_panel = panel
	panel.position = Vector2(520, 120)
	panel.size = Vector2(720, 570)
	content.add_child(panel)
	var title := UI.title("گردونهٔ شانس", 40)
	title.position = Vector2(20, 12)
	title.size = Vector2(680, 56)
	title.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	panel.add_child(title)
	wheel = Wheel.new()
	wheel.prizes = Game.WHEEL_PRIZES
	wheel._labels = []
	for p in Game.WHEEL_PRIZES:
		match str(p["kind"]):
			"coins": wheel._labels.append(Game.money(int(p["amount"])))
			"gems": wheel._labels.append("%s جم" % Game.fa(int(p["amount"])))
			"key": wheel._labels.append("کلید")
	wheel.position = Vector2(190, 80)
	wheel.size = Vector2(340, 340)
	wheel.spin_finished.connect(_on_spin_done)
	panel.add_child(wheel)

	spin_btn = UI.button("چرخاندن — ۱ کلید", "orange", 420, 92, 30)
	spin_btn.position = Vector2(150, 450)
	spin_btn.pressed.connect(_do_spin)
	panel.add_child(spin_btn)

	keys_chip = UI.chip("icon_key", Game.fa(Game.keys), 150)
	keys_chip.position = Vector2(40, 460)
	keys_chip.name = "keyschip"
	panel.add_child(keys_chip)

	# بسته‌های کلید زیر گردونه
	var kbox := HBoxContainer.new()
	kbox.add_theme_constant_override("separation", 12)
	kbox.position = Vector2(40, 600)
	content.add_child(kbox)
	for i in Game.SHOP_KEYS.size():
		var kp: Dictionary = Game.SHOP_KEYS[i]
		var kb := UI.button("%s کلید — %s 💎" % [Game.fa(int(kp["keys"])), Game.fa(int(kp["gems"]))], "blue", 290, 76, 22)
		kb.pressed.connect(func():
			if Game.spend_gems(int(kp["gems"])):
				Game.keys += int(kp["keys"])
				Game.save_game()
				Snd.play("buy")
				refresh()
			else:
				Snd.play("denied"))
		kbox.add_child(kb)

func _buy_pack(pack: Dictionary) -> void:
	if Game.spend_gems(int(pack["gems"])):
		Game.add_coins(int(pack["coins"]))
		Snd.play("buy")
		_toast("+%s سکه به حسابت اضافه شد" % Game.money(int(pack["coins"])))
		refresh()
	else:
		Snd.play("denied")
		_toast("الماس کافی نداری! از گردونهٔ شانس و ماموریت‌ها الماس بگیر.")

func _do_spin() -> void:
	if Game.keys <= 0:
		Snd.play("denied")
		_toast("کلید نداری! با الماس کلید بخر.")
		return
	if wheel.spinning:
		return
	Game.keys -= 1
	Game.save_game()
	Snd.play("wheel")
	wheel.spin()
	refresh()

func _on_spin_done(index: int) -> void:
	var p: Dictionary = Game.WHEEL_PRIZES[index % Game.WHEEL_PRIZES.size()]
	match str(p["kind"]):
		"coins": Game.add_coins(int(p["amount"]))
		"gems": Game.add_gems(int(p["amount"]))
		"key": Game.keys += int(p["amount"])
	Game.save_game()
	Snd.play("win")
	_toast("بردی: %s" % _prize_text(p))
	refresh()

func _prize_text(p: Dictionary) -> String:
	match str(p["kind"]):
		"coins": return "%s سکه" % Game.money(int(p["amount"]))
		"gems": return "%s الماس" % Game.fa(int(p["amount"]))
		_: return "%s کلید" % Game.fa(int(p["amount"]))

func _toast(text: String) -> void:
	var l := UI.label(text, 28, Color(1, 0.92, 0.6))
	l.position = Vector2(300, 40)
	l.size = Vector2(700, 46)
	l.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	l.z_index = 20
	content.add_child(l)
	var t := l.create_tween()
	t.tween_property(l, "modulate:a", 0.0, 1.8).set_delay(1.0)
	t.tween_callback(l.queue_free)

func refresh() -> void:
	if spin_btn:
		spin_btn.disabled = Game.keys <= 0 or (wheel != null and wheel.spinning)
		spin_btn.text = "چرخاندن — ۱ کلید" if Game.keys > 0 else "کلید نداری"
	if keys_chip:
		var l: Label = keys_chip.get_meta("label")
		if l: l.text = Game.fa(Game.keys)
