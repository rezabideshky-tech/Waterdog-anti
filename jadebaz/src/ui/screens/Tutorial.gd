extends Screen
## Tutorial.gd — «یادگیری رانندگی»: آموزش قدم‌به‌قدم با تصویر و انیمیشن
## چهار صفحه: گاز و ترمز، نیترو، بنزین، پرش و سکه.

var step := 0
var icon: TextureRect
var title_lbl: Label
var body_lbl: Label
var page_lbl: Label
var demo: CarActor
var arrow: Sprite2D

const STEPS := [
	{
		"t": "گاز و ترمز",
		"b": "غربالِ سمت راست = گاز، غربالِ سمت چپ = ترمز. در هوا گاز بده تا ماشین به عقب بچرخد و با ترمز جلو بیفتد — همین رازِ فرود نرم است!",
		"i": "icon_touch",
	},
	{
		"t": "نیترو",
		"b": "دکمهٔ نیترو کنار غربال گاز است. شارژ نیترو را در مسیر از بطری‌های آبی پر کن؛ هنگام گاز دادن، موتور می‌غرد و ماشین می‌پرد.",
		"i": "icon_nitro",
	},
	{
		"t": "بنزین",
		"b": "باک را زیر نظر داشته باش؛ با تمام شدن بنزین، ماشین می‌ایستد. قوطی‌های بنزین در مسیر، باک را تا نیمه پر می‌کنند.",
		"i": "icon_fuel",
	},
	{
		"t": "پرش، سکه و چرخش",
		"b": "سکه‌ها را بگیر، از تپه‌ها بپر و در هوا بچرخ تا پاداش بگیری. با سکه‌ها ماشین بخواه و در گاراژ تقویت کن.",
		"i": "icon_coin",
	},
]

func build() -> void:
	add_bg("sky_day", "mtn_day", "forest_green")
	var panel := UI.panel(Color("#16203a"), 32)
	panel.position = Vector2(280, 100)
	panel.size = Vector2(720, 520)
	content.add_child(panel)
	var v := VBoxContainer.new()
	v.add_theme_constant_override("separation", 14)
	panel.add_child(v)
	icon = TextureRect.new()
	icon.custom_minimum_size = Vector2(120, 120)
	icon.expand_mode = TextureRect.EXPAND_IGNORE_SIZE
	icon.stretch_mode = TextureRect.STRETCH_KEEP_ASPECT_CENTERED
	v.add_child(icon)
	title_lbl = UI.title("", 42)
	title_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(title_lbl)
	body_lbl = UI.label("", 23, UI.COL_DIM)
	body_lbl.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	body_lbl.custom_minimum_size = Vector2(640, 150)
	body_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(body_lbl)
	demo = CarActor.new()
	demo.setup("peykan", Color("#38b32a"), 3, 0.34)
	demo.position = Vector2(360, 400)
	demo.speed = 5.0
	panel.add_child(demo)
	page_lbl = UI.label("", 22, UI.COL_GOLD)
	page_lbl.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	v.add_child(page_lbl)

	var nav := HBoxContainer.new()
	nav.add_theme_constant_override("separation", 18)
	nav.position = Vector2(340, 636)
	nav.alignment = BoxContainer.ALIGNMENT_CENTER
	content.add_child(nav)
	var prev := UI.button("‹  قبلی", "gray", 190, 78, 26)
	prev.pressed.connect(func(): _step(-1))
	nav.add_child(prev)
	var next := UI.button("بعدی  ›", "green", 220, 78, 26)
	next.pressed.connect(func(): _step(1))
	nav.add_child(next)
	var start := UI.button("بزن بریم!", "orange", 240, 78, 28)
	start.pressed.connect(func():
		Snd.play("engine_start")
		go("map"))
	nav.add_child(start)
	_apply()

func _step(dir: int) -> void:
	Snd.play("click")
	step = clampi(step + dir, 0, STEPS.size() - 1)
	_apply()
	UI.pop_in(demo, 0.25)

func _apply() -> void:
	var s: Dictionary = STEPS[step]
	icon.texture = UI.tex(str(s["i"]))
	title_lbl.text = str(s["t"])
	body_lbl.text = str(s["b"])
	page_lbl.text = "صفحهٔ %s از %s" % [Game.fa(step + 1), Game.fa(STEPS.size())]

func refresh() -> void:
	step = 0
	_apply()
