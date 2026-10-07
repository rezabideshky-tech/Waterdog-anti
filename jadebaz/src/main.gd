extends Node2D
## main.gd — مدیر صفحه‌ها و نبرد
## منو ↔ نقشه ↔ گاراژ ↔ فروشگاه ↔ ماموریت‌ها ↔ تنظیمات ↔ بازی ↔ نتیجه

const SCREENS := {
	"menu": preload("res://src/ui/screens/MainMenu.gd"),
	"map": preload("res://src/ui/screens/WorldMap.gd"),
	"garage": preload("res://src/ui/screens/Garage.gd"),
	"store": preload("res://src/ui/screens/Store.gd"),
	"missions": preload("res://src/ui/screens/Missions.gd"),
	"settings": preload("res://src/ui/screens/Settings.gd"),
	"tutorial": preload("res://src/ui/screens/Tutorial.gd"),
	"results": preload("res://src/ui/screens/Results.gd"),
}

var holder: Control
var current: Screen
var current_name := ""
var level: Level
var hud: GameHUD
var pause_menu: PauseMenu

func _ready() -> void:
	randomize()
	holder = Control.new()
	holder.set_anchors_preset(Control.PRESET_FULL_RECT)
	holder.mouse_filter = Control.MOUSE_FILTER_PASS
	add_child(holder)
	pause_menu = PauseMenu.new()
	pause_menu.resume_pressed.connect(_resume)
	pause_menu.retry_pressed.connect(func():
		_resume()
		start_level(_level_id))
	pause_menu.quit_pressed.connect(func():
		_resume()
		quit_level()
		go("menu"))
	holder.add_child(pause_menu)
	Snd.start_music()
	go("menu", {}, false)
	# هدیهٔ روزانه
	_game_ready()

func _game_ready() -> void:
	if Game.missions.is_empty():
		Game.roll_missions()

# ------------------------------------------------------------------ ناوبری
func go(name: String, data := {}, animate := true) -> void:
	if name == "game":
		start_level(str(data.get("level", Game.level_data("tehran")["id"])))
		return
	# اگر از بازی بیرون می‌آییم، دنیای مسیر آزاد شود
	if level != null and name != "results":
		quit_level()
	if current_name == name and current:
		current.show_screen(data)
		return
	var old := current
	if old:
		var t := old.create_tween()
		t.tween_property(old, "modulate:a", 0.0, 0.16 if animate else 0.0)
		t.tween_callback(old.hide_screen)
	var scr: Screen = SCREENS[name].new()
	scr.app = self
	scr.visible = false
	scr.nav.connect(_on_nav)
	holder.add_child(scr)
	current = scr
	current_name = name
	scr.show_screen(data)

func _on_nav(to: String, data: Dictionary) -> void:
	go(to, data)

# ------------------------------------------------------------------ بازی
var _level_id := "tehran"

func start_level(id: String) -> void:
	_level_id = id
	if current:
		current.hide_screen()
	if level:
		level.queue_free()
		level = null
	if hud:
		hud.queue_free()
		hud = null
	pause_menu.visible = false
	Snd.play("engine_start")
	level = Level.new()
	level.name = "Level"
	add_child(level)
	level.setup(id, randi())
	var lv := Game.level_data(id)
	level.add_bg_layer(UI.tex_at("res://assets/bg/%s.png" % str(lv["sky"])), 0.02, -60, 1.6, -30)
	level.add_bg_layer(UI.tex_at("res://assets/bg/%s.png" % str(lv["bg2"])), 0.08, 90, 1.4, -20)
	level.add_bg_layer(UI.tex_at("res://assets/bg/%s.png" % str(lv["bg1"])), 0.18, 230, 1.3, -10)
	hud = GameHUD.new()
	add_child(hud)
	hud.setup(level)
	hud.pause_pressed.connect(_pause)
	level.finished.connect(_on_finished)
	level.failed.connect(_on_failed)
	level.start()
	# شمارش نیترو برای ماموریت‌ها
	level.car.nitro_used.connect(func(): _nitro_count += 1)

var _nitro_count := 0

func _pause() -> void:
	if level == null or level.ended:
		return
	level.set_process(false)
	if level.car:
		level.car.set_physics_process(false)
		Snd.engine_rpm(0.0)
	pause_menu.open()

func _resume() -> void:
	pause_menu.close()
	if level:
		level.set_process(true)
		if level.car:
			level.car.set_physics_process(true)

func _on_finished(dist: float, coins: int, gems: int, stunts: int, air_max: float) -> void:
	show_results(dist, coins, gems, stunts, false, "")

func _on_failed(reason: String) -> void:
	if level == null:
		return
	show_results(level.distance_m(), level.coins, level.gems, level.stunts, true, reason)

func show_results(dist: float, coins: int, gems: int, stunts: int, failed: bool, reason: String) -> void:
	if hud:
		hud.visible = false
	var res := Game.record_run(_level_id, dist, coins, gems, stunts,
		_nitro_count, level.car.max_speed_kmh if level and level.car else 0.0,
		level.car.air_max if level and level.car else 0.0, not failed)
	var mission_bonus := 0
	for m in Game.missions:
		if bool(m.get("done", false)) and not bool(m.get("claimed", false)):
			mission_bonus += int(Game.mission_def(str(m["id"])).get("reward", 0))
	_nitro_count = 0
	# صفحهٔ نتیجه روی بازی
	var scr: Screen = SCREENS["results"].new()
	scr.app = self
	scr.nav.connect(_on_nav)
	holder.add_child(scr)
	current = scr
	current_name = "results"
	scr.visible = true
	scr.show_result({
		"level": _level_id, "dist": dist, "coins": coins, "gems": gems,
		"stunts": stunts, "stars": int(res["stars"]), "reward": int(res["reward"]),
		"gems": int(res["gems"]), "best": float(res["best"]),
		"failed": failed, "reason": reason, "mission": mission_bonus,
	})

func quit_level() -> void:
	if level:
		level.queue_free()
		level = null
	if hud:
		hud.queue_free()
		hud = null
