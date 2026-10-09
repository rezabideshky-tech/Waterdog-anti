extends Node

## Every user-visible string lives here (autoload "I18n"). Persian is a first-class
## language: RTL text, Persian digits and a font with full Arabic-script coverage.

signal language_changed

const STRINGS := {
	"en": {
		"app_name": "Aegis Orbit",
		"menu_play": "Play",
		"menu_daily": "Daily Sky",
		"menu_records": "Records",
		"menu_settings": "Settings",
		"menu_classic_rules": "Endless sky · 3 lives · Nova at every 10 combo",
		"menu_daily_rules": "Same sky for everyone · 2 lives · no Nova",
		"menu_daily_done": "Today's sky: %d points. Come back tomorrow.",
		"menu_daily_quit": "Today's attempt was left unfinished. Come back tomorrow.",
		"hud_score": "Score",
		"hud_wave": "Wave %d",
		"hud_best": "Best %d",
		"hud_combo": "Combo ×%d",
		"hud_nova": "NOVA",
		"nova_fired": "NOVA!",
		"nova_ready": "Nova charged",
		"pause_title": "Paused",
		"resume": "Resume",
		"restart": "Restart",
		"to_menu": "Menu",
		"over_title": "Planet Lost",
		"over_score": "Score",
		"over_best": "Best %d",
		"over_new_record": "New record!",
		"over_again": "Play again",
		"over_daily_locked": "Daily attempt used. Come back tomorrow.",
		"unlocked": "Achievement: %s",
		"tut_1": "Drag anywhere to turn the shield. It always follows your finger.",
		"tut_2": "Block rocks on the ring. A rock that gets past costs one life.",
		"tut_3": "Every 10 blocks in a row charges a Nova that clears the sky.",
		"tut_next": "Next",
		"tut_done": "Start",
		"tut_skip": "Skip",
		"set_title": "Settings",
		"set_sfx": "Sound effects",
		"set_music": "Music",
		"set_vibration": "Vibration",
		"set_language": "Language",
		"set_on": "On",
		"set_off": "Off",
		"set_reset": "Reset progress",
		"set_reset_ask": "Erase every record and achievement? This cannot be undone.",
		"set_reset_yes": "Erase",
		"set_reset_no": "Keep",
		"set_reset_done": "Progress erased",
		"set_about": "Protect the planet. Built with Godot 4.",
		"back": "Back",
		"rec_title": "Records",
		"rec_empty": "No runs yet. Play one!",
		"rec_best_score": "Best score",
		"rec_best_combo": "Best combo",
		"rec_best_wave": "Best wave",
		"rec_games": "Games played",
		"rec_blocks": "Rocks blocked",
		"rec_daily": "Daily sky runs",
		"rec_top": "Top 10",
		"rec_mode_classic": "Classic",
		"rec_mode_daily": "Daily",
		"ach_title": "Achievements",
		"ach_locked": "Locked",
		"ach_first_block": "First Block",
		"ach_first_block_d": "Block your first rock.",
		"ach_blocks_100": "Century",
		"ach_blocks_100_d": "Block 100 rocks in total.",
		"ach_combo_10": "On Fire",
		"ach_combo_10_d": "Reach a 10 combo.",
		"ach_combo_25": "Unstoppable",
		"ach_combo_25_d": "Reach a 25 combo.",
		"ach_armor_5": "Iron Wall",
		"ach_armor_5_d": "Break 5 armored rocks.",
		"ach_nova_1": "Supernova",
		"ach_nova_1_d": "Fire your first Nova.",
		"ach_score_1000": "Rising Star",
		"ach_score_1000_d": "Score 1,000 in one run.",
		"ach_score_5000": "Guardian",
		"ach_score_5000_d": "Score 5,000 in one run.",
		"ach_wave_6": "Storm Rider",
		"ach_wave_6_d": "Survive to wave 6.",
		"ach_daily_1": "Ritual",
		"ach_daily_1_d": "Finish a Daily Sky.",
		"ach_games_20": "Veteran",
		"ach_games_20_d": "Play 20 runs.",
	},
	"fa": {
		"app_name": "سپر مدار",
		"menu_play": "بازی",
		"menu_daily": "آسمان امروز",
		"menu_records": "رکوردها",
		"menu_settings": "تنظیمات",
		"menu_classic_rules": "آسمان بی‌پایان · ۳ جان · نوا در هر ۱۰ ترکیب",
		"menu_daily_rules": "آسمان یکسان برای همه · ۲ جان · بدون نوا",
		"menu_daily_done": "امتیاز آسمان امروز: %d. فردا دوباره بیا.",
		"menu_daily_quit": "تلاش امروز نیمه‌کاره ماند. فردا برگرد.",
		"hud_score": "امتیاز",
		"hud_wave": "موج %d",
		"hud_best": "بهترین %d",
		"hud_combo": "ترکیب ×%d",
		"hud_nova": "نوا",
		"nova_fired": "نوا!",
		"nova_ready": "نوا آماده شد",
		"pause_title": "توقف",
		"resume": "ادامه",
		"restart": "از نو",
		"to_menu": "منو",
		"over_title": "سیاره از دست رفت",
		"over_score": "امتیاز",
		"over_best": "بهترین %d",
		"over_new_record": "رکورد تازه!",
		"over_again": "دوباره بازی",
		"over_daily_locked": "تلاش امروز تمام شد. فردا برگرد.",
		"unlocked": "دستاورد: %s",
		"tut_1": "با انگشت هر جای صفحه بکش تا سپر بچرخد. سپر همیشه دنبال انگشت تو می‌آید.",
		"tut_2": "سنگ‌ها را روی حلقه متوقف کن. سنگی که رد شود یک جان می‌گیرد.",
		"tut_3": "هر ۱۰ توقف پیاپی یک نوا شارژ می‌کند که آسمان را پاک می‌کند.",
		"tut_next": "بعدی",
		"tut_done": "شروع",
		"tut_skip": "رد کردن",
		"set_title": "تنظیمات",
		"set_sfx": "افکت صوتی",
		"set_music": "موسیقی",
		"set_vibration": "لرزش",
		"set_language": "زبان",
		"set_on": "روشن",
		"set_off": "خاموش",
		"set_reset": "پاک کردن پیشرفت",
		"set_reset_ask": "همه رکوردها و دستاوردها پاک شود؟ این کار برگشت ندارد.",
		"set_reset_yes": "پاک کن",
		"set_reset_no": "نگه دار",
		"set_reset_done": "پیشرفت پاک شد",
		"set_about": "از سیاره محافظت کن. ساخته‌شده با Godot 4.",
		"back": "بازگشت",
		"rec_title": "رکوردها",
		"rec_empty": "هنوز بازی‌ای ثبت نشده. یکی بازی کن!",
		"rec_best_score": "بهترین امتیاز",
		"rec_best_combo": "بهترین ترکیب",
		"rec_best_wave": "بهترین موج",
		"rec_games": "تعداد بازی",
		"rec_blocks": "سنگ‌های متوقف‌شده",
		"rec_daily": "بازی‌های آسمان امروز",
		"rec_top": "ده رکورد برتر",
		"rec_mode_classic": "کلاسیک",
		"rec_mode_daily": "روزانه",
		"ach_title": "دستاوردها",
		"ach_locked": "قفل",
		"ach_first_block": "اولین توقف",
		"ach_first_block_d": "اولین سنگ را متوقف کن.",
		"ach_blocks_100": "صد تایی",
		"ach_blocks_100_d": "در مجموع ۱۰۰ سنگ را متوقف کن.",
		"ach_combo_10": "شعله‌ور",
		"ach_combo_10_d": "به ترکیب ۱۰ برس.",
		"ach_combo_25": "بی‌توقف",
		"ach_combo_25_d": "به ترکیب ۲۵ برس.",
		"ach_armor_5": "دیوار آهنین",
		"ach_armor_5_d": "۵ سنگ زرهی را بشکن.",
		"ach_nova_1": "ابرنواختر",
		"ach_nova_1_d": "اولین نوا را شلیک کن.",
		"ach_score_1000": "ستاره در حال طلوع",
		"ach_score_1000_d": "در یک بازی ۱٬۰۰۰ امتیاز بگیر.",
		"ach_score_5000": "نگهبان",
		"ach_score_5000_d": "در یک بازی ۵٬۰۰۰ امتیاز بگیر.",
		"ach_wave_6": "سوارِ طوفان",
		"ach_wave_6_d": "تا موج ۶ زنده بمان.",
		"ach_daily_1": "آیین",
		"ach_daily_1_d": "یک آسمان روزانه را تمام کن.",
		"ach_games_20": "کهنه‌کار",
		"ach_games_20_d": "۲۰ بازی انجام بده.",
	},
}

var language: String = "en"


func _ready() -> void:
	language = str(Save.setting("language"))
	if not STRINGS.has(language):
		language = "en"


func set_language(lang: String) -> void:
	if not STRINGS.has(lang) or lang == language:
		return
	language = lang
	Save.set_setting("language", lang)
	language_changed.emit()


func is_rtl() -> bool:
	return language == "fa"


func t(key: String) -> String:
	var table: Dictionary = STRINGS[language]
	if table.has(key):
		return table[key]
	return STRINGS["en"].get(key, "??" + key)


## Formats a translated string with % arguments and converts digits for Persian.
func fmt(key: String, args: Array = []) -> String:
	var s := t(key)
	if not args.is_empty():
		s = s % args
	return digits(s)


func digits(s: String) -> String:
	if language != "fa":
		return s
	var out := ""
	for c in s:
		var i := "0123456789".find(c)
		out += "۰۱۲۳۴۵۶۷۸۹".substr(i, 1) if i >= 0 else c
	return out


func keys_for(lang: String) -> Array:
	return STRINGS[lang].keys()
