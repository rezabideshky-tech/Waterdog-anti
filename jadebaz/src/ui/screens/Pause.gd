extends Control
class_name PauseMenu
## PauseMenu.gd — پنجرهٔ توقف بازی: ادامه / دوباره / صدا / خروج

signal resume_pressed()
signal retry_pressed()
signal quit_pressed()

var sound_btn: AnimatedButton
var panel: PanelContainer

func _ready() -> void:
	set_anchors_preset(Control.PRESET_FULL_RECT)
	visible = false
	var dim := ColorRect.new()
	dim.color = Color(0, 0, 0, 0.62)
	dim.set_anchors_preset(Control.PRESET_FULL_RECT)
	add_child(dim)
	panel = UI.panel(Color("#16203a"), 32)
	panel.position = Vector2(430, 150)
	panel.size = Vector2(420, 430)
	add_child(panel)
	var v := VBoxContainer.new()
	v.add_theme_constant_override("separation", 16)
	panel.add_child(v)
	var t := UI.title("یک دقیقه توقف", 40)
	t.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(t)
	var resume := UI.button("ادامه", "green", 340, 84, 30)
	resume.pressed.connect(func():
		Snd.play("click")
		resume_pressed.emit())
	v.add_child(resume)
	var retry := UI.button("دوباره", "orange", 340, 84, 30)
	retry.pressed.connect(func():
		Snd.play("click")
		retry_pressed.emit())
	v.add_child(retry)
	sound_btn = UI.button("صدا: روشن", "blue", 340, 84, 26)
	sound_btn.pressed.connect(func():
		Snd.set_sfx(not Snd.sfx_on)
		Snd.set_music(not Snd.music_on)
		Game.settings["sound"] = Snd.sfx_on
		Game.settings["music"] = Snd.music_on
		Game.save_game()
		Snd.play("click")
		_update_sound())
	v.add_child(sound_btn)
	var quit := UI.button("خروج به خانه", "red", 340, 84, 28)
	quit.pressed.connect(func():
		Snd.play("back")
		quit_pressed.emit())
	v.add_child(quit)

func _update_sound() -> void:
	if sound_btn:
		sound_btn.text = "صدا: روشن" if Snd.sfx_on else "صدا: خاموش"

func open() -> void:
	_update_sound()
	visible = true
	UI.pop_in(panel, 0.26)

func close() -> void:
	visible = false
