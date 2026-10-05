extends CanvasLayer
class_name GameHUD
## GameHUD.gd — رابط داخل بازی: غربال‌های گاز/ترمز، سنج‌ها، نیترو، سکه، نوار پیشرفت

signal pause_pressed()

var level: Level
var gas: TouchWheel
var brake_wheel: TouchWheel
var speed_g: Gauge
var rpm_g: Gauge
var fuel_bar: ProgressBar
var nitro_bar: ProgressBar
var coin_chip: Control
var coin_label: Label
var gem_label: Label
var prog: ProgressBar
var prog_car: TextureRect
var nitro_btn: AnimatedButton
var top_box: Control
var root: Control
var msg: Label
var _shake_t := 0.0
var _shake_p := 0.0
var _nitro_hold := 0.0

func setup(lv: Level) -> void:
	level = lv
	layer = 5
	root = Control.new()
	root.set_anchors_preset(Control.PRESET_FULL_RECT)
	root.mouse_filter = Control.MOUSE_FILTER_PASS
	add_child(root)
	_build_top()
	_build_controls()
	_build_gauges()
	msg = UI.label("", 34, Color(1, 0.9, 0.4))
	msg.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	msg.position = Vector2(340, 250)
	msg.size = Vector2(600, 60)
	msg.modulate.a = 0.0
	root.add_child(msg)
	# بستن HUD به ماشین
	lv.car.throttle = 0.0
	set_process(true)

func _build_top() -> void:
	top_box = Control.new()
	top_box.set_anchors_preset(Control.PRESET_TOP_WIDE)
	top_box.size = Vector2(1280, 90)
	top_box.mouse_filter = Control.MOUSE_FILTER_IGNORE
	root.add_child(top_box)
	var pause_btn := UI.icon_button("icon_pause", 84)
	pause_btn.pressed.connect(func(): pause_pressed.emit())
	pause_btn.position = Vector2(20, 16)
	top_box.add_child(pause_btn)
	# سکه‌ها
	coin_chip = Control.new()
	coin_chip.position = Vector2(120, 18)
	coin_chip.size = Vector2(210, 68)
	coin_chip.mouse_filter = Control.MOUSE_FILTER_IGNORE
	var bg := UI.chip("icon_coin", "۰", 200)
	coin_chip.add_child(bg)
	coin_label = bg.get_meta("label")
	top_box.add_child(coin_chip)
	var gem_bg := UI.chip("icon_gem", "۰", 140)
	gem_bg.position = Vector2(230, 0)
	gem_bg.scale = Vector2(0.85, 0.85)
	gem_label = gem_bg.get_meta("label")
	coin_chip.add_child(gem_bg)
	# نوار پیشرفت
	prog = ProgressBar.new()
	prog.position = Vector2(700, 30)
	prog.size = Vector2(540, 30)
	prog.max_value = 100.0
	prog.show_percentage = false
	prog.add_theme_stylebox_override("background", UI.panel_style(Color(0.08, 0.09, 0.14, 0.75), 14))
	prog.add_theme_stylebox_override("fill", UI.panel_style(Color(0.35, 0.75, 0.45), 14))
	top_box.add_child(prog)
	prog_car = TextureRect.new()
	prog_car.texture = UI.tex("icon_car_head")
	prog_car.position = Vector2(700, 20)
	prog_car.size = Vector2(40, 40)
	top_box.add_child(prog_car)
	var flag := TextureRect.new()
	flag.texture = UI.tex("icon_flag")
	flag.position = Vector2(1200, 18)
	flag.size = Vector2(48, 48)
	top_box.add_child(flag)

func _build_controls() -> void:
	gas = TouchWheel.new()
	gas.label_text = "گاز"
	gas.accent = Color(0.36, 0.8, 0.4)
	gas.size = Vector2(250, 250)
	gas.position = Vector2(1280 - 290, 720 - 300)
	root.add_child(gas)

	brake_wheel = TouchWheel.new()
	brake_wheel.label_text = "ترمز"
	brake_wheel.accent = Color(0.88, 0.35, 0.32)
	brake_wheel.size = Vector2(220, 220)
	brake_wheel.position = Vector2(60, 720 - 280)
	root.add_child(brake_wheel)

	nitro_btn = UI.icon_button("icon_nitro", 104)
	nitro_btn.pressed.connect(_toggle_nitro)
	nitro_btn.position = Vector2(1280 - 400, 720 - 170)
	root.add_child(nitro_btn)

func _build_gauges() -> void:
	var box := Control.new()
	box.position = Vector2(470, 720 - 210)
	box.size = Vector2(360, 190)
	box.mouse_filter = Control.MOUSE_FILTER_IGNORE
	root.add_child(box)

	speed_g = Gauge.new()
	speed_g.kind = Gauge.Kind.SPEED
	speed_g.title = "km/h"
	speed_g.accent = Color(0.35, 0.7, 1.0)
	speed_g.size = Vector2(180, 180)
	box.add_child(speed_g)

	rpm_g = Gauge.new()
	rpm_g.kind = Gauge.Kind.RPM
	rpm_g.title = "RPM"
	rpm_g.accent = Color(1.0, 0.55, 0.2)
	rpm_g.size = Vector2(150, 150)
	rpm_g.position = Vector2(185, 20)
	box.add_child(rpm_g)

	fuel_bar = _bar(UI.tex("icon_fuel"), Color(1.0, 0.78, 0.25))
	fuel_bar.position = Vector2(360 - 60, 0)
	box.add_child(fuel_bar)
	nitro_bar = _bar(UI.tex("icon_nitro"), Color(0.4, 0.8, 1.0))
	nitro_bar.position = Vector2(360 + 30, 0)
	box.add_child(nitro_bar)

	var fl := UI.label("بنزین", 18, Color(1, 1, 1, 0.6))
	fl.position = Vector2(300, 92)
	box.add_child(fl)
	var nl := UI.label("نیترو", 18, Color(1, 1, 1, 0.6))
	nl.position = Vector2(392, 92)
	box.add_child(nl)

func _bar(icon: Texture2D, col: Color) -> ProgressBar:
	var b := ProgressBar.new()
	b.size = Vector2(34, 130)
	b.max_value = 100.0
	b.value = 100.0
	b.fill_mode = ProgressBar.FILL_BOTTOM_TO_TOP
	b.show_percentage = false
	b.add_theme_stylebox_override("background", UI.panel_style(Color(0.08, 0.09, 0.14, 0.8), 12))
	b.add_theme_stylebox_override("fill", UI.panel_style(col, 12))
	return b

func _toggle_nitro() -> void:
	if level and level.car:
		level.car.nitro = true
		_nitro_hold = 1.1
		nitro_btn.wiggle()
		Snd.play("nitro")

func add_coin(n: int) -> void:
	if coin_label:
		coin_label.text = Game.fa(level.coins)
		var tw := coin_label.create_tween()
		coin_label.scale = Vector2(1.35, 1.35)
		tw.tween_property(coin_label, "scale", Vector2.ONE, 0.25)

func toast(text: String, dur := 1.6) -> void:
	if msg == null:
		return
	msg.text = text
	msg.modulate.a = 1.0
	var tw := msg.create_tween()
	tw.tween_interval(dur)
	tw.tween_property(msg, "modulate:a", 0.0, 0.4)

func camera_shake(power: float) -> void:
	cam_shake(power)

func cam_shake(power: float) -> void:
	_shake_p = power
	_shake_t = 0.25

func _process(dt: float) -> void:
	if level == null or level.car == null:
		return
	var car: CarRig = level.car
	car.throttle = maxf(gas.value, 0.0 if not Input.is_action_pressed("ui_up") else 1.0)
	car.brake = maxf(brake_wheel.value, 1.0 if Input.is_action_pressed("ui_down") else 0.0)
	# فرمان (کیبورد در دسکتاپ) — در هوا هم برای چرخش کار می‌کند
	var st := 0.0
	if Input.is_action_pressed("ui_left"):
		st -= 1.0
	if Input.is_action_pressed("ui_right"):
		st += 1.0
	car.steer = st
	# نیترو: دکمه یک بازهٔ کوتاه نیترو می‌دهد، نگه‌داشتن هم پشتیبانی می‌شود
	if _nitro_hold > 0.0:
		_nitro_hold -= dt
	elif not Input.is_action_pressed("ui_accept"):
		car.nitro = false
	# سنج‌ها
	speed_g.ratio = clampf(car.speed_kmh() / 160.0, 0.0, 1.0)
	speed_g.set_text("%d" % int(car.speed_kmh()))
	rpm_g.ratio = car.rpm_ratio()
	fuel_bar.value = car.fuel / maxf(1.0, car.fuel_max) * 100.0
	nitro_bar.value = car.nitro_charge / maxf(0.01, car.nitro_max) * 100.0
	if prog:
		prog.value = level.progress() * 100.0
		if prog_car:
			prog_car.position.x = prog.position.x + prog.size.x * level.progress() - 20.0
	if gem_label:
		gem_label.text = Game.fa(level.gems)
	if _shake_t > 0.0:
		_shake_t -= dt
		var cam := level.camera
		if cam:
			cam.offset = Vector2(randf_range(-_shake_p, _shake_p), randf_range(-_shake_p, _shake_p))
	elif level.camera and level.camera.offset != Vector2.ZERO:
		level.camera.offset = level.camera.offset.lerp(Vector2.ZERO, clampf(dt * 8.0, 0.0, 1.0))
