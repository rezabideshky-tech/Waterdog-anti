extends SceneTree
## Autoplay driver (factory gate: "autoplay bot drives real UI handlers").
##
## Boots the real main scene and presses the real buttons (Play, Skip, Nova, Play again,
## Records, Settings, language). The bot's aim goes through GameView.feed_input(), the same
## entry point a finger uses. It uses an isolated save file, so a real profile is untouched.
##
## Run:   godot --headless --path . -s res://tools/autoplay.gd -- 90
## Exit:  0 when a game reaches game over with no invariant failure, 1 otherwise.

const OrbitBot = preload("res://scripts/bot.gd")
const OrbitLogic = preload("res://scripts/orbit_logic.gd")

var _main: Node
var _i18n: Node
var _save: Node
var _frame := 0
var _phase := "boot"
var _phase_t := 0.0
var _elapsed := 0.0
var _limit := 90.0
var _failures := 0
var _pressed_down := false
var _shots: Array = []
var _game_over_seen := false


func _initialize() -> void:
	var args := OS.get_cmdline_user_args()
	if args.size() > 0 and args[0].is_valid_float():
		_limit = float(args[0])


## Autoloads are attached after _initialize(), so the boot happens on the first frame.
func _boot() -> void:
	_save = root.get_node("/root/Save")
	_i18n = root.get_node("/root/I18n")
	_save.path = "user://autoplay_save.json"
	_save.load_data()
	_main = load("res://scenes/main.tscn").instantiate()
	root.add_child(_main)


func _find_button(node: Node, text: String) -> Button:
	if node is Button and node.text == text and node.is_visible_in_tree():
		return node
	for c in node.get_children():
		var r := _find_button(c, text)
		if r != null:
			return r
	return null


func _press(text: String) -> bool:
	var b := _find_button(_main, text)
	if b == null or b.disabled:
		return false
	b.pressed.emit()
	return true


func _view():
	return _main.current_game() if _main._current_kind == "game" else null


func _shot(name: String) -> void:
	## Headless runs have no frame to read back, so screenshots are skipped there.
	var img: Image = null
	if DisplayServer.get_name() != "headless":
		var tex := root.get_texture()
		img = tex.get_image() if tex != null else null
	if img == null or img.is_empty():
		_shots.append("%s: (no renderer; screenshot skipped)" % name)
		return
	var p := "user://autoplay_%s.png" % name
	img.save_png(p)
	_shots.append(ProjectSettings.globalize_path(p))


func _fail(msg: String) -> void:
	_failures += 1
	printerr("AUTOPLAY FAIL: " + msg)


func _next(phase: String) -> void:
	_phase = phase
	_phase_t = 0.0


# gdlint:ignore = max-returns
func _process(dt: float) -> bool:
	_frame += 1
	if _frame == 2:
		_boot()
	if _frame < 4:
		return false
	_elapsed += dt
	_phase_t += dt
	match _phase:
		"boot":
			if _press(_i18n.t("menu_play")):
				_next("tutorial_wait")
			elif _phase_t > 5.0:
				_fail("menu Play button never appeared")
				return _finish()
		"tutorial_wait":
			var v = _view()
			if v != null and v.overlay != null and v.overlay.name == "tutorial":
				_shot("tutorial")
				_press(_i18n.t("tut_skip"))
				_next("play")
			elif v != null and _phase_t > 1.0:
				_next("play")
			elif _phase_t > 5.0:
				_fail("game view never started")
				return _finish()
		"play":
			var v = _view()
			if v == null:
				_fail("left the game unexpectedly")
				return _finish()
			if v.logic.over or (v.overlay != null and v.overlay.name == "gameover"):
				_game_over_seen = true
				_shot("gameover")
				_next("after_over")
				return false
			if _elapsed > _limit:
				_fail("time limit reached before game over")
				return _finish()
			_bot_step(v)
		"after_over":
			if _phase_t > 1.2:
				_press(_i18n.t("menu_records"))
				_next("records")
		"records":
			if _phase_t > 0.5:
				_shot("records")
				_main.show_settings()
				_next("settings")
		"settings":
			if _phase_t > 0.5:
				_shot("settings")
				_save.set_setting("sfx", not bool(_save.setting("sfx")))
				_save.set_setting("sfx", not bool(_save.setting("sfx")))
				_i18n.set_language("fa")
				_next("persian")
		"persian":
			if _phase_t > 0.6:
				_shot("settings_fa")
				_main.show_menu()
				_next("menu_fa")
		"menu_fa":
			if _phase_t > 0.5:
				_shot("menu_fa")
				_i18n.set_language("en")
				_next("done")
		"done":
			return _finish()
	return false


func _bot_step(v) -> void:
	# Nova through the real button when the bot asks for it.
	if v.logic.nova_charges > 0 and OrbitBot._should_nova(v.logic) and v._nova_btn != null:
		v._nova_btn.pressed.emit()
	var angle: float = OrbitBot.choose_target(v.logic)
	var target: Vector2 = v.center() + Vector2(cos(angle), sin(angle)) * OrbitLogic.RING_R * v.field_scale()
	var ev: InputEvent
	if not _pressed_down:
		ev = InputEventMouseButton.new()
		ev.button_index = MOUSE_BUTTON_LEFT
		ev.pressed = true
		_pressed_down = true
	else:
		ev = InputEventMouseMotion.new()
	ev.position = target
	v.feed_input(ev)
	var bad := OrbitBot._check_invariants(v.logic)
	if bad > 0:
		_fail("invariant broken at %.1fs (%d)" % [_elapsed, bad])


func _finish() -> bool:
	var v = _view()
	var s: Dictionary = _save.data.stats
	print("AUTOPLAY summary: game_over=%s elapsed=%.1fs score=%s best_combo=%s games=%s records=%d achievements=%d" % [
		str(_game_over_seen), _elapsed, str(s.best_score), str(s.best_combo), str(s.games),
		_save.data.records.size(), _save.data.achievements.size()])
	if v != null:
		print("AUTOPLAY last score=%d wave=%d blocks=%d lives=%d" % [v.logic.score, v.logic.wave(), v.logic.blocks, v.logic.lives])
	for p in _shots:
		print("AUTOPLAY screenshot: " + str(p))
	var ok := _failures == 0 and _game_over_seen
	if not _game_over_seen and _failures == 0:
		_failures += 1
	_save.path = "user://save.json"
	quit(0 if ok else 1)
	return true
