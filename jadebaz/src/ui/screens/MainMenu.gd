extends Screen
## MainMenu.gd — صفحهٔ اصلی: تابلوی چوبی «جاده‌باز»، کاروان ماشین‌ها، دکمه‌های انیمیشنی
## الهام‌گرفته از صفحهٔ اول بازی شوفر: تابلو، شعار، و ردیف ماشین‌های ایرانی.

var board: TextureRect
var title_lbl: Label
var slogan: Label
var convoy: Array[CarActor] = []
var hero: CarActor
var _cars := ["pride", "samand", "nissan", "peykan"]
var _paints := ["purple", "white", "blue", "red"]

func build() -> void:
	add_bg("sky_day", "city_day", "mtn_day")
	_build_convoy()
	_build_board()
	_build_buttons()
	_build_top()
	# ورود اولیه
	UI.pop_in(board, 0.5)

func _build_convoy() -> void:
	var y := 620.0
	for i in _cars.size():
		var a := CarActor.new()
		a.setup(_cars[i], Color(Game.PAINTS[_paints[i]]["color"]), 1, 0.34)
		a.position = Vector2(240.0 + i * 300.0, y - i * 6.0)
		a.speed = 6.0 + i * 1.4
		content.add_child(a)
		convoy.append(a)
	# ماشین قهرمان روی سکو، بزرگ‌تر
	hero = CarActor.new()
	hero.setup(Game.car, Game.paint_color(), Game.rim, 0.62)
	hero.position = Vector2(640, 570)
	hero.speed = 2.0
	content.add_child(hero)

func _build_board() -> void:
	board = TextureRect.new()
	board.texture = UI.tex("logo_board")
	board.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	board.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	board.size = Vector2(700, 330)
	board.position = Vector2(290, 40)
	board.pivot_offset = board.size / 2.0
	board.mouse_filter = Control.MOUSE_FILTER_IGNORE
	content.add_child(board)
	# چرخش خیلی ملایم تابلو
	var t := board.create_tween().set_loops()
	t.tween_property(board, "rotation", 0.022, 2.2).set_trans(Tween.TRANS_SINE).set_ease(Tween.EASE_IN_OUT)
	t.tween_property(board, "rotation", -0.022, 2.2).set_trans(Tween.TRANS_SINE).set_ease(Tween.EASE_IN_OUT)

	title_lbl = UI.title("جادهباز", 74)
	title_lbl.position = Vector2(300, 110)
	title_lbl.size = Vector2(680, 110)
	title_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	content.add_child(title_lbl)

	slogan = UI.label("سوار شو، گاز بده و ماشین ایرانیت رو تقویت کن!", 26, Color("#4b2d12"))
	slogan.position = Vector2(300, 240)
	slogan.size = Vector2(680, 40)
	slogan.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	content.add_child(slogan)

func _build_buttons() -> void:
	var start := UI.button("شروع رانندگی", "green", 380, 104, 40)
	start.position = Vector2(450, 380)
	start.pressed.connect(func():
		Snd.play("engine_start")
		go("map"))
	content.add_child(start)

	var row := HBoxContainer.new()
	row.add_theme_constant_override("separation", 16)
	row.position = Vector2(340, 500)
	content.add_child(row)
	var items := [
		["گاراژ", "icon_wheel", "garage"],
		["فروشگاه", "icon_coin", "store"],
		["ماموریتها", "icon_mission", "missions"],
		["راهنما", "icon_map", "tutorial"],
		["تنظیمات", "icon_settings", "settings"],
	]
	for it in items:
		var b := UI.button(str(it[0]), "blue", 190, 84, 26)
		b.pressed.connect(func(): go(str(it[2])))
		row.add_child(b)

func _build_top() -> void:
	var coins := UI.chip("icon_coin", Game.money(Game.coins))
	coins.position = Vector2(24, 20)
	content.add_child(coins)
	var gems := UI.chip("icon_gem", Game.fa(Game.gems), 140)
	gems.position = Vector2(232, 20)
	content.add_child(gems)
	Game.coins_changed.connect(func():
		var l: Label = coins.get_meta("label")
		if l: l.text = Game.money(Game.coins))
	Game.gems_changed.connect(func():
		var l: Label = gems.get_meta("label")
		if l: l.text = Game.fa(Game.gems))
	var name_lbl := UI.label("راننده: %s" % Game.player_name, 24, UI.COL_DIM)
	name_lbl.position = Vector2(1000, 30)
	name_lbl.size = Vector2(260, 40)
	name_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_RIGHT
	content.add_child(name_lbl)
	# کلید و ستاره
	var st := UI.chip("icon_star", Game.fa(Game.total_stars), 150)
	st.position = Vector2(1050, 78)
	content.add_child(st)
	var key := UI.chip("icon_key", Game.fa(Game.keys), 130)
	key.position = Vector2(880, 78)
	content.add_child(key)

func refresh() -> void:
	if hero:
		hero.setup(Game.car, Game.paint_color(), Game.rim, 0.62)
		hero.position = Vector2(640, 570)
	# کاروان: رنگ‌ها را به‌روز کن
	for i in convoy.size():
		convoy[i].speed = 6.0 + i * 1.4
	# انیمیشن ورود دکمه‌ها
	for c in content.get_children():
		if c is AnimatedButton:
			c.wiggle()

func _process(dt: float) -> void:
	# حرکت ملایم کاروان (لوپ تختِ بی‌پایان)
	for a in convoy:
		a.position.x -= a.speed * 6.0 * dt
		if a.position.x < -200.0:
			a.position.x = 1500.0
