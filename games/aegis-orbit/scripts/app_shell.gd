extends Control
const UIKit = preload("res://scripts/ui_kit.gd")
const MenuView = preload("res://scripts/menu_view.gd")
const GameView = preload("res://scripts/game_view.gd")
const RecordsView = preload("res://scripts/records_view.gd")
const SettingsView = preload("res://scripts/settings_view.gd")

## Root of the app: owns the background, routes between screens and shows toasts.
## Screens are rebuilt from scratch on navigation and on language change, so every
## string is always fresh and nothing carries stale state between screens.

var _screen_root: Control
var _current: Control
var _current_kind: String = ""
var _toast_host: VBoxContainer


func _ready() -> void:
	UIKit.fill_parent(self)
	mouse_filter = Control.MOUSE_FILTER_IGNORE
	var bg := TextureRect.new()
	bg.texture = Assets.texture("bg_space")
	bg.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_COVERED
	bg.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	bg.mouse_filter = Control.MOUSE_FILTER_IGNORE
	UIKit.fill_parent(bg)
	add_child(bg)
	_screen_root = Control.new()
	UIKit.fill_parent(_screen_root)
	_screen_root.mouse_filter = Control.MOUSE_FILTER_IGNORE
	add_child(_screen_root)
	var margin := MarginContainer.new()
	UIKit.fill_parent(margin)
	margin.mouse_filter = Control.MOUSE_FILTER_IGNORE
	margin.add_theme_constant_override("margin_top", 150)
	add_child(margin)
	_toast_host = VBoxContainer.new()
	_toast_host.alignment = BoxContainer.ALIGNMENT_BEGIN
	_toast_host.mouse_filter = Control.MOUSE_FILTER_IGNORE
	_toast_host.add_theme_constant_override("separation", 12)
	margin.add_child(_toast_host)
	I18n.language_changed.connect(_rebuild)
	show_menu()
	Audio.start_music()


func show_menu() -> void:
	_swap("menu", MenuView.new(self))


func show_game(mode: String) -> void:
	_swap("game", GameView.new(self, mode))


func show_records() -> void:
	_swap("records", RecordsView.new(self))


func show_settings() -> void:
	_swap("settings", SettingsView.new(self))


func current_game():
	return _current


func _rebuild() -> void:
	match _current_kind:
		"menu":
			show_menu()
		"records":
			show_records()
		"settings":
			show_settings()
		# a running game is not rebuilt on language change; it keeps its state


func _swap(kind: String, view: Control) -> void:
	if _current != null:
		_current.queue_free()
	_current = view
	_current_kind = kind
	_screen_root.add_child(view)
	UIKit.fill_parent(view)


## Hardware back / Escape. Returns true when the press was handled.
func handle_back() -> bool:
	if _current_kind == "game":
		var g = current_game()
		return g.handle_back()
	if _current_kind == "menu":
		return false
	show_menu()
	return true


func toast(text: String) -> void:
	var p := UIKit.panel(Color(0.04, 0.07, 0.18, 0.95), 22)
	p.size_flags_horizontal = Control.SIZE_SHRINK_CENTER
	var l := UIKit.label(text, 34, UIKit.C_GOLD, true)
	l.custom_minimum_size = Vector2(0, 60)
	p.add_child(l)
	p.modulate.a = 0.0
	_toast_host.add_child(p)
	var tw := p.create_tween().bind_node(p)
	tw.tween_property(p, "modulate:a", 1.0, 0.25)
	tw.tween_interval(2.2)
	tw.tween_property(p, "modulate:a", 0.0, 0.5)
	tw.tween_callback(p.queue_free)


func _notification(what: int) -> void:
	match what:
		NOTIFICATION_WM_GO_BACK_REQUEST:
			handle_back()
		NOTIFICATION_APPLICATION_PAUSED, NOTIFICATION_APPLICATION_FOCUS_OUT:
			# pause only, never quit (LESSONS L40)
			if _current_kind == "game":
				current_game().pause_for_system()
