extends "res://scripts/screen_base.gd"
const OrbitLogic = preload("res://scripts/orbit_logic.gd")
const UIKit = preload("res://scripts/ui_kit.gd")
const IconDraw = preload("res://scripts/icon_draw.gd")
const GameField = preload("res://scripts/game_field.gd")

## One run of the game: input, presentation, HUD and the run-end flow.
## All rules are in OrbitLogic; this view only translates touches into aim angles and
## turns rule events into sound, particles, text and haptics.

const PLANET_SHADER := """
shader_type canvas_item;
uniform sampler2D surface : source_color, filter_linear_mipmap, repeat_enable;
uniform float spin = 0.0;
uniform vec3 atmos : source_color = vec3(0.45, 0.92, 1.0);
void fragment() {
	vec2 p = UV * 2.0 - 1.0;
	float d2 = dot(p, p);
	if (d2 > 1.0) {
		COLOR = vec4(0.0);
	} else {
		float z = sqrt(1.0 - d2);
		vec3 n = vec3(p.x, p.y, z);
		float lon = atan(n.x, n.z) + spin;
		float lat = asin(clamp(n.y, -1.0, 1.0));
		vec3 base = texture(surface, vec2(lon / 6.2831853 + 0.5, lat / 3.1415926 + 0.5)).rgb;
		float light = clamp(dot(n, normalize(vec3(-0.45, -0.55, 0.7))), 0.0, 1.0);
		vec3 col = base * (0.22 + 0.95 * light);
		col += atmos * pow(1.0 - z, 2.2) * 0.85;
		float edge = smoothstep(1.0, 0.96, sqrt(d2));
		COLOR = vec4(col, edge);
	}
}
"""

var mode: String = "classic"
var logic := OrbitLogic.new()
var paused := false
var overlay: Control = null
var _finished := false
var _visual_t := 0.0
var _shake := 0.0
var _flash := 0.0
var _planet_hit := 0.0
var _active_pointer := -99
var _tut_step := 1
var _particles: Array = []
var _planet: TextureRect
var _planet_material: ShaderMaterial
var _field: GameField
var _fx: Control
var _score_lbl: Label
var _wave_lbl: Label
var _best_lbl: Label
var _combo_lbl: Label
var _hearts: Array = []
var _nova_btn: Button
var _nova_count: Label
var _nova_box: Control
var _pause_btn: Button
var _pending_center: CenterContainer
var _score_big: Label = null


func _init(p_shell, p_mode: String) -> void:
	super(p_shell)
	mode = p_mode
	var seed_value := randi()
	if mode == "daily":
		Save.start_daily_attempt()
		seed_value = OrbitLogic.daily_seed(Save.today_key())
	logic.setup(mode, seed_value)


func _ready() -> void:
	Audio.set_intensity(0.0)
	super._ready()


func _on_resized() -> void:
	# A game is never rebuilt on resize (that would lose the overlay state); it only re-lays out.
	if size.x < 2.0 or size.y < 2.0:
		return
	if _planet == null:
		_build()
		if not bool(Save.data.tutorial_done):
			# overlays need a real size, so the first-run tutorial opens after the first layout
			paused = true
			_show_tutorial(1)
	_layout_planet()


# ----------------------------------------------------------- geometry ----

func center() -> Vector2:
	return Vector2(size.x * 0.5, size.y * 0.53)


func field_scale() -> float:
	return minf(size.x * 0.92, size.y * 0.78) * 0.5 / OrbitLogic.SPAWN_R


func polar(a: float, r: float) -> Vector2:
	return center() + Vector2(cos(a), sin(a)) * r * field_scale()


# ------------------------------------------------------------- build ----

func _build() -> void:
	var u := unit()
	_planet = TextureRect.new()
	_planet.texture = Assets.texture("planet")
	_planet.stretch_mode = TextureRect.STRETCH_SCALE
	_planet.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	_planet.mouse_filter = Control.MOUSE_FILTER_IGNORE
	var shader := Shader.new()
	shader.code = PLANET_SHADER
	_planet_material = ShaderMaterial.new()
	_planet_material.shader = shader
	_planet_material.set_shader_parameter("surface", Assets.texture("planet"))
	_planet.material = _planet_material
	add_child(_planet)

	_field = GameField.new(self)
	UIKit.fill_parent(_field)
	add_child(_field)

	_fx = Control.new()
	UIKit.fill_parent(_fx)
	_fx.mouse_filter = Control.MOUSE_FILTER_IGNORE
	add_child(_fx)

	_build_hud(u)


func _build_hud(u: float) -> void:
	var hud := MarginContainer.new()
	UIKit.fill_parent(hud)
	hud.mouse_filter = Control.MOUSE_FILTER_IGNORE
	hud.add_theme_constant_override("margin_left", int(28 * u))
	hud.add_theme_constant_override("margin_right", int(28 * u))
	hud.add_theme_constant_override("margin_top", int(34 * u))
	hud.add_theme_constant_override("margin_bottom", int(40 * u))
	add_child(hud)

	var col := VBoxContainer.new()
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_theme_constant_override("separation", int(12 * u))
	hud.add_child(col)

	# top row: score and hearts | wave and best | pause
	var top := HBoxContainer.new()
	top.mouse_filter = Control.MOUSE_FILTER_IGNORE
	top.add_theme_constant_override("separation", int(16 * u))
	col.add_child(top)

	var left := VBoxContainer.new()
	left.mouse_filter = Control.MOUSE_FILTER_IGNORE
	top.add_child(left)
	var score_cap := UIKit.label(I18n.t("hud_score"), int(26 * u), UIKit.C_MUTED, false, HORIZONTAL_ALIGNMENT_LEFT)
	left.add_child(score_cap)
	_score_lbl = UIKit.label("0", int(68 * u), UIKit.C_TEXT, true, HORIZONTAL_ALIGNMENT_LEFT)
	_score_lbl.custom_minimum_size = Vector2(0, 80 * u)
	left.add_child(_score_lbl)
	var heart_row := HBoxContainer.new()
	heart_row.mouse_filter = Control.MOUSE_FILTER_IGNORE
	heart_row.add_theme_constant_override("separation", int(8 * u))
	left.add_child(heart_row)
	_hearts.clear()
	for i in OrbitLogic.mode_lives(mode):
		var h := IconDraw.new("heart", UIKit.C_RED)
		h.custom_minimum_size = Vector2(44 * u, 40 * u)
		heart_row.add_child(h)
		_hearts.append(h)

	var spacer := Control.new()
	spacer.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	spacer.mouse_filter = Control.MOUSE_FILTER_IGNORE
	top.add_child(spacer)

	var mid := VBoxContainer.new()
	mid.alignment = BoxContainer.ALIGNMENT_CENTER
	mid.mouse_filter = Control.MOUSE_FILTER_IGNORE
	top.add_child(mid)
	_wave_lbl = UIKit.label("", int(34 * u), UIKit.C_TEAL, true)
	_wave_lbl.custom_minimum_size = Vector2(0, 46 * u)
	mid.add_child(_wave_lbl)
	_best_lbl = UIKit.label("", int(26 * u), UIKit.C_MUTED)
	_best_lbl.custom_minimum_size = Vector2(0, 36 * u)
	mid.add_child(_best_lbl)

	var spacer2 := Control.new()
	spacer2.size_flags_horizontal = Control.SIZE_EXPAND_FILL
	spacer2.mouse_filter = Control.MOUSE_FILTER_IGNORE
	top.add_child(spacer2)

	_pause_btn = _round_button(96.0 * u, "pause")
	_pause_btn.pressed.connect(func(): pause())
	top.add_child(_pause_btn)

	# combo line
	_combo_lbl = UIKit.label("", int(58 * u), UIKit.C_GOLD, true)
	_combo_lbl.custom_minimum_size = Vector2(0, 80 * u)
	_combo_lbl.modulate.a = 0.0
	col.add_child(_combo_lbl)

	var flex := Control.new()
	flex.size_flags_vertical = Control.SIZE_EXPAND_FILL
	flex.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_child(flex)

	# bottom row: nova (right-aligned)
	var bottom := HBoxContainer.new()
	bottom.alignment = BoxContainer.ALIGNMENT_END
	bottom.mouse_filter = Control.MOUSE_FILTER_IGNORE
	col.add_child(bottom)
	_nova_box = VBoxContainer.new()
	_nova_box.alignment = BoxContainer.ALIGNMENT_CENTER
	_nova_box.mouse_filter = Control.MOUSE_FILTER_IGNORE
	bottom.add_child(_nova_box)
	_nova_btn = _round_button(156.0 * u, "nova")
	_nova_btn.pressed.connect(func(): use_nova())
	_nova_box.add_child(_nova_btn)
	_nova_count = UIKit.label("", int(30 * u), UIKit.C_GOLD, true)
	_nova_count.custom_minimum_size = Vector2(156 * u, 44 * u)
	_nova_box.add_child(_nova_count)
	_nova_box.visible = OrbitLogic.mode_nova_enabled(mode)


func _round_button(d: float, icon: String) -> Button:
	var b := Button.new()
	b.custom_minimum_size = Vector2(d, d)
	b.size = Vector2(d, d)
	b.focus_mode = Control.FOCUS_NONE
	b.text = ""
	var r := int(d * 0.5)
	b.add_theme_stylebox_override("normal", UIKit.style(UIKit.C_PANEL, r, UIKit.C_BORDER, 3))
	b.add_theme_stylebox_override("hover", UIKit.style(UIKit.C_PANEL.lightened(0.15), r, UIKit.C_TEAL, 3))
	b.add_theme_stylebox_override("pressed", UIKit.style(UIKit.C_TEAL_DEEP, r, UIKit.C_TEAL, 3))
	b.add_theme_stylebox_override("disabled", UIKit.style(Color(0.06, 0.07, 0.14, 0.7), r, UIKit.C_BORDER, 2))
	b.add_theme_stylebox_override("focus", StyleBoxEmpty.new())
	var glyph := IconDraw.new(icon, UIKit.C_GOLD if icon == "nova" else UIKit.C_TEXT)
	glyph.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	glyph.offset_left = d * 0.22
	glyph.offset_top = d * 0.22
	glyph.offset_right = -d * 0.22
	glyph.offset_bottom = -d * 0.22
	b.add_child(glyph)
	return b


func _layout_planet() -> void:
	var k := field_scale()
	var d := 2.0 * OrbitLogic.PLANET_R * k
	_planet.size = Vector2(d, d)
	_planet.pivot_offset = Vector2(d, d) * 0.5
	_planet.position = center() - Vector2(d, d) * 0.5
	_planet_material.set_shader_parameter("atmos", Color(0.45, 0.92, 1.0))


# -------------------------------------------------------------- input ----

func _gui_input(event: InputEvent) -> void:
	feed_input(event)


## Public entry point used by the real touch layer AND by autoplay, so both drive the
## same code path (LESSONS L3).
func feed_input(event: InputEvent) -> void:
	if not _accepts_input():
		return
	if event is InputEventScreenTouch:
		if event.pressed:
			_active_pointer = event.index
			aim_at(event.position)
		elif event.index == _active_pointer:
			_active_pointer = -99
	elif event is InputEventScreenDrag:
		if event.index == _active_pointer:
			aim_at(event.position)
	elif event is InputEventMouseButton and event.button_index == MOUSE_BUTTON_LEFT:
		if event.pressed:
			_active_pointer = -2
			aim_at(event.position)
		elif _active_pointer == -2:
			_active_pointer = -99
	elif event is InputEventMouseMotion and _active_pointer == -2:
		aim_at(event.position)
	if get_viewport() != null:
		accept_event()


func aim_at(p: Vector2) -> void:
	logic.set_target(atan2(p.y - center().y, p.x - center().x))


func _accepts_input() -> bool:
	return not paused and overlay == null and not logic.over


# --------------------------------------------------------------- loop ----

func _process(delta: float) -> void:
	tick(delta)


func tick(dt: float) -> void:
	if _planet == null:
		return
	_visual_t += dt
	_planet_material.set_shader_parameter("spin", _visual_t * 0.05)
	if not paused and overlay == null and not logic.over:
		logic.step(dt)
		for ev in logic.drain_events():
			_handle(ev)
	_update_fx(dt)
	_update_hud()
	_field.queue_redraw()
	if logic.over and not _finished:
		_finish()


func _handle(ev: Dictionary) -> void:
	match ev.type:
		"block":
			var pos: Vector2 = polar(ev.a, OrbitLogic.RING_R)
			var col := UIKit.C_GOLD if ev.kind != "comet" else UIKit.C_TEAL
			_burst(pos, col, 16)
			_float_text("+%d" % ev.pts, pos, UIKit.C_GOLD)
			Audio.play("sfx_block", 0.9 + 0.025 * float(mini(ev.combo, 20)))
			_flash = 0.18
		"armor_hit":
			_burst(polar(ev.a, OrbitLogic.RING_R), UIKit.C_TEAL, 18)
			Audio.play("sfx_armor")
			Audio.haptic(14)
			_shake = maxf(_shake, 0.08)
		"leak":
			var hit: Vector2 = polar(ev.a, OrbitLogic.PLANET_R)
			_burst(hit, UIKit.C_RED, 28)
			_float_text("-1", center() + Vector2(0, -150.0 * field_scale()), UIKit.C_RED)
			Audio.play("sfx_leak")
			Audio.haptic(45)
			_shake = 0.55
			_planet_hit = 1.0
		"wave":
			Audio.set_intensity(float(ev.wave - 1) / 8.0)
			Audio.play("sfx_combo", 1.0 + 0.04 * float(ev.wave))
			_banner(I18n.fmt("hud_wave", [ev.wave]))
		"nova_ready":
			Audio.play("sfx_combo", 1.45)
			_banner(I18n.t("nova_ready"), UIKit.C_TEAL)
		"nova":
			Audio.play("sfx_nova")
			_shake = 0.45
			_flash = 0.6
			_banner(I18n.t("nova_fired"), UIKit.C_TEAL)
		"destroy":
			_burst(polar(ev.a, ev.r), UIKit.C_TEAL, 10)


func use_nova() -> bool:
	if not _accepts_input():
		return false
	return logic.try_nova()


func pause() -> void:
	if logic.over or overlay != null and overlay.name == "gameover":
		return
	paused = true
	_set_overlay(_build_pause(), "pause")


func resume() -> void:
	if logic.over:
		return
	paused = false
	_clear_overlay()


func pause_for_system() -> void:
	# called when the OS backgrounds the app: pause only, never quit (LESSONS L40)
	if overlay == null and not logic.over:
		pause()


func restart() -> void:
	if mode == "daily":
		return
	logic.setup("classic", randi())
	_finished = false
	paused = false
	_clear_overlay()


func to_menu() -> void:
	shell.show_menu()


func handle_back() -> bool:
	if overlay != null and overlay.name == "tutorial":
		return true
	if logic.over:
		to_menu()
		return true
	if paused and overlay != null and overlay.name == "pause":
		resume()
		return true
	pause()
	return true


# ----------------------------------------------------------- overlays ----

func _set_overlay(c: Control, id: String) -> void:
	_clear_overlay()
	c.name = id
	overlay = c
	add_child(c)
	UIKit.fill_parent(c)


func _clear_overlay() -> void:
	if overlay != null:
		overlay.queue_free()
		overlay = null


func _panel_column(u: float, width_frac: float = 0.86) -> VBoxContainer:
	var center_box := CenterContainer.new()
	UIKit.fill_parent(center_box)
	center_box.mouse_filter = Control.MOUSE_FILTER_PASS
	var p := UIKit.panel(UIKit.C_PANEL, int(34 * u))
	p.custom_minimum_size = Vector2(minf(size.x * width_frac, 640.0 * u), 0)
	p.mouse_filter = Control.MOUSE_FILTER_STOP
	center_box.add_child(p)
	var col := VBoxContainer.new()
	col.add_theme_constant_override("separation", int(16 * u))
	col.mouse_filter = Control.MOUSE_FILTER_IGNORE
	p.add_child(col)
	_pending_center = center_box
	return col


func _overlay_root() -> Control:
	var root := Control.new()
	UIKit.fill_parent(root)
	root.mouse_filter = Control.MOUSE_FILTER_STOP
	var dim := UIKit.dim_backdrop(0.62)
	root.add_child(dim)
	return root


func _build_pause() -> Control:
	var u := unit()
	var root := _overlay_root()
	var col := _panel_column(u)
	root.add_child(_pending_center)
	col.add_child(UIKit.label(I18n.t("pause_title"), int(56 * u), UIKit.C_GOLD, true))
	col.add_child(_btn_row(I18n.t("resume"), true, u, func(): resume()))
	if mode != "daily":
		col.add_child(_btn_row(I18n.t("restart"), false, u, func(): restart()))
	col.add_child(_btn_row(I18n.t("to_menu"), false, u, func(): to_menu()))
	return root


func _show_tutorial(step: int) -> void:
	_tut_step = step
	var u := unit()
	var root := _overlay_root()
	var col := _panel_column(u)
	root.add_child(_pending_center)
	root.name = "tutorial"
	col.add_child(UIKit.label(I18n.digits("%d / 3" % step), int(30 * u), UIKit.C_TEAL, true))
	var body_key := "tut_%d" % step
	var body := UIKit.label(I18n.t(body_key), int(36 * u), UIKit.C_TEXT)
	body.custom_minimum_size = Vector2(0, 150 * u)
	body.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	col.add_child(body)
	var last := step >= 3
	col.add_child(_btn_row(I18n.t("tut_done") if last else I18n.t("tut_next"), true, u, func():
		if last:
			_finish_tutorial()
		else:
			_show_tutorial(step + 1)))
	if not last:
		col.add_child(_btn_row(I18n.t("tut_skip"), false, u, func(): _finish_tutorial()))
	_set_overlay(root, "tutorial")


func _finish_tutorial() -> void:
	Save.data.tutorial_done = true
	Save.save_data()
	paused = false
	_clear_overlay()


func _btn_row(text: String, primary: bool, u: float, action: Callable) -> Button:
	var w := minf(size.x * 0.7, 520.0 * u)
	var b := UIKit.button(text, primary, Vector2(w, clampf(88.0 * u, 60.0, 110.0)), int(40 * u))
	b.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	b.pressed.connect(action)
	return b


func _finish() -> void:
	_finished = true
	var previous_best := int(Save.data.stats.best_score)
	var fresh: Array = Save.commit_run(logic)
	if mode == "daily":
		Save.record_daily_score(logic.score)
	Audio.play("sfx_gameover")
	Audio.set_intensity(0.0)
	Audio.haptic(120)
	_set_overlay(_build_gameover(previous_best, fresh), "gameover")
	_count_up_score()
	var delay := 1.2
	for id in fresh:
		var title: String = I18n.t("ach_%s" % id)
		get_tree().create_timer(delay).timeout.connect(func():
			Audio.play("sfx_achievement")
			shell.toast(I18n.fmt("unlocked", [title])))
		delay += 2.6


## The count-up tween needs the label inside the tree, so it starts after _set_overlay().
func _count_up_score() -> void:
	if _score_big == null or not _score_big.is_inside_tree():
		return
	var label := _score_big
	var tw := label.create_tween().bind_node(label)
	tw.tween_method(func(v: float): label.text = I18n.digits(str(int(v))), 0.0, float(logic.score), 1.1)
	_score_big = null


func _build_gameover(previous_best: int, fresh: Array) -> Control:
	var u := unit()
	var root := _overlay_root()
	var col := _panel_column(u)
	root.add_child(_pending_center)
	col.add_child(UIKit.label(I18n.t("over_title"), int(52 * u), UIKit.C_RED, true))
	var score_big := UIKit.label(I18n.digits("0"), int(96 * u), UIKit.C_GOLD, true)
	score_big.custom_minimum_size = Vector2(0, 120 * u)
	col.add_child(score_big)
	_score_big = score_big
	col.add_child(UIKit.label(I18n.fmt("hud_wave", [logic.wave()]) + "   ·   " + I18n.fmt("hud_combo", [logic.best_combo]), int(30 * u), UIKit.C_MUTED))
	var best_now := maxi(previous_best, logic.score)
	var new_record := logic.score > previous_best and logic.score > 0
	var best_line := I18n.fmt("over_best", [best_now])
	if new_record:
		best_line = I18n.t("over_new_record") + "  " + best_line
	var best_lbl := UIKit.label(best_line, int(36 * u), UIKit.C_TEAL, true)
	best_lbl.custom_minimum_size = Vector2(0, 50 * u)
	col.add_child(best_lbl)
	if fresh.size() > 0:
		col.add_child(UIKit.label(I18n.fmt("unlocked", [I18n.t("ach_" + fresh[0])]), int(28 * u), UIKit.C_GOLD))
	if mode == "daily":
		col.add_child(UIKit.label(I18n.t("over_daily_locked"), int(28 * u), UIKit.C_MUTED))
		col.add_child(_btn_row(I18n.t("menu_records"), false, u, func(): shell.show_records()))
	else:
		col.add_child(_btn_row(I18n.t("over_again"), true, u, func(): _again()))
	col.add_child(_btn_row(I18n.t("to_menu"), false, u, func(): to_menu()))
	return root


func _again() -> void:
	restart()


# ------------------------------------------------------------- visuals ----

func _update_hud() -> void:
	if _score_lbl == null:
		return
	_score_lbl.text = I18n.digits(str(logic.score))
	_wave_lbl.text = I18n.fmt("hud_wave", [logic.wave()])
	_best_lbl.text = I18n.fmt("hud_best", [maxi(int(Save.data.stats.best_score), logic.score)])
	for i in _hearts.size():
		_hearts[i].modulate.a = 1.0 if i < logic.lives else 0.18
	if logic.combo >= 2:
		_combo_lbl.text = I18n.fmt("hud_combo", [logic.combo])
		var m: int = logic.multiplier()
		_combo_lbl.add_theme_color_override("font_color", UIKit.C_GOLD if m < 3 else UIKit.C_TEAL)
		_combo_lbl.modulate.a = lerpf(_combo_lbl.modulate.a, 1.0, 0.25)
	else:
		_combo_lbl.modulate.a = lerpf(_combo_lbl.modulate.a, 0.0, 0.2)
	if _nova_btn != null:
		_nova_btn.disabled = logic.nova_charges <= 0 or not logic.nova_enabled()
		_nova_btn.modulate.a = 1.0 if logic.nova_charges > 0 else 0.4
		_nova_count.text = I18n.digits("× %d" % logic.nova_charges) if logic.nova_charges > 0 else I18n.t("hud_nova")
	_planet_material.set_shader_parameter("spin", _visual_t * 0.05)
	_planet.modulate = Color(1.0, 1.0 - 0.55 * _planet_hit, 1.0 - 0.55 * _planet_hit, 1.0)


func _update_fx(dt: float) -> void:
	_shake = maxf(0.0, _shake - dt * 1.9)
	_flash = maxf(0.0, _flash - dt * 2.6)
	_planet_hit = maxf(0.0, _planet_hit - dt * 3.0)
	var u := unit()
	var off := Vector2.ZERO
	if _shake > 0.0:
		off = Vector2(randf_range(-1.0, 1.0), randf_range(-1.0, 1.0)) * _shake * 22.0 * u
	_field.position = off
	_planet.position = center() - Vector2(_planet.size.x, _planet.size.y) * 0.5 + off
	var keep: Array = []
	for p in _particles:
		p.life -= dt
		if p.life > 0.0:
			p.pos += p.vel * dt
			p.vel *= pow(0.02, dt)
			keep.append(p)
	_particles = keep
	# particles and floaters are positioned relative to the field, so they follow the shake
	_fx.position = off


func _burst(pos: Vector2, col: Color, count: int) -> void:
	var u := unit()
	for i in count:
		var ang := randf() * TAU
		var spd := randf_range(120.0, 420.0) * u
		_particles.append({
			"pos": pos, "vel": Vector2(cos(ang), sin(ang)) * spd,
			"life": randf_range(0.35, 0.7), "max": 0.7, "size": randf_range(3.0, 7.0), "color": col,
		})


func _float_text(text: String, pos: Vector2, col: Color) -> void:
	var u := unit()
	var l := UIKit.label(I18n.digits(text), int(40 * u), col, true)
	l.custom_minimum_size = Vector2(140 * u, 56 * u)
	l.size = Vector2(140 * u, 56 * u)
	l.position = pos - Vector2(70 * u, 28 * u)
	_fx.add_child(l)
	var tw := l.create_tween().bind_node(l)
	tw.set_parallel(true)
	tw.tween_property(l, "position:y", l.position.y - 70.0 * u, 0.8)
	tw.tween_property(l, "modulate:a", 0.0, 0.8)
	tw.chain().tween_callback(l.queue_free)


func _banner(text: String, col: Color = UIKit.C_GOLD) -> void:
	var u := unit()
	var l := UIKit.label(text, int(60 * u), col, true)
	var w := minf(size.x * 0.9, 760.0 * u)
	l.custom_minimum_size = Vector2(w, 80 * u)
	l.size = Vector2(w, 80 * u)
	l.position = Vector2(size.x * 0.5 - w * 0.5, size.y * 0.3)
	l.pivot_offset = Vector2(w, 80 * u) * 0.5
	l.scale = Vector2(1.4, 1.4)
	_fx.add_child(l)
	var tw := l.create_tween().bind_node(l)
	tw.tween_property(l, "scale", Vector2.ONE, 0.18).set_trans(Tween.TRANS_BACK)
	tw.tween_interval(0.9)
	tw.tween_property(l, "modulate:a", 0.0, 0.5)
	tw.tween_callback(l.queue_free)


## Called by GameField every frame. Draws ring, shield, rocks and particles.
func paint(c: CanvasItem) -> void:
	if logic == null or size.x < 2.0:
		return
	var u := unit()
	var k := field_scale()
	var ctr := center()
	var ring_px := OrbitLogic.RING_R * k
	c.draw_arc(ctr, ring_px, 0.0, TAU, 160, Color(0.55, 0.85, 1.0, 0.10), 2.0 * u, true)
	c.draw_arc(ctr, ring_px * 1.05, 0.0, TAU, 160, Color(0.55, 0.85, 1.0, 0.05), 1.0 * u, true)

	var a0 := logic.shield_a - OrbitLogic.SHIELD_HALF
	var a1 := logic.shield_a + OrbitLogic.SHIELD_HALF
	c.draw_arc(ctr, ring_px, a0, a1, 56, Color(1.0, 0.8, 0.34, 0.22 + 0.55 * _flash), 40.0 * u, true)
	c.draw_arc(ctr, ring_px, a0, a1, 56, Color(1.0, 0.86, 0.5, 0.9), 14.0 * u, true)
	c.draw_arc(ctr, ring_px, a0, a1, 56, Color(1.0, 1.0, 1.0, 0.85), 3.0 * u, true)

	var rock_tex := Assets.texture("rock")
	for rk in logic.rocks:
		var pos: Vector2 = ctr + Vector2(cos(rk.a), sin(rk.a)) * rk.r * k
		if rk.kind == "comet":
			var dir: Vector2 = (ctr - pos).normalized()
			c.draw_line(pos, pos - dir * 110.0 * u, Color(0.4, 0.95, 0.9, 0.45), 16.0 * u, true)
			c.draw_line(pos, pos - dir * 60.0 * u, Color(0.8, 1.0, 1.0, 0.7), 6.0 * u, true)
			c.draw_circle(pos, 16.0 * u, Color(0.5, 1.0, 0.95))
			continue
		var armored: bool = rk.kind == "armor"
		var half := (54.0 if armored else 42.0) * u
		var rot: float = float(rk.id) * 1.3 + logic.elapsed * (rk.omega * 1.5 + 0.6)
		var tint := Color(0.72, 0.9, 1.0) if armored else Color.WHITE
		if armored and rk.hp == 1:
			tint = Color(1.0, 0.6, 0.55)
		if rock_tex != null:
			c.draw_set_transform(pos, rot, Vector2.ONE)
			c.draw_texture_rect(rock_tex, Rect2(-half, -half, half * 2.0, half * 2.0), false, tint)
			c.draw_set_transform(Vector2.ZERO, 0.0, Vector2.ONE)
		if armored:
			c.draw_arc(pos, half * 0.82, 0.0, TAU, 32, Color(0.4, 0.9, 1.0, 0.55), 3.0 * u, true)

	for p in _particles:
		var t: float = clampf(p.life / p.max, 0.0, 1.0)
		var col: Color = p.color
		col.a = t
		c.draw_circle(p.pos, p.size * u * (0.4 + 0.6 * t), col)
