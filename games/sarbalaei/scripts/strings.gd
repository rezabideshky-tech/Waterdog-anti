extends Node
## All player-facing text. Persian is the default; English is kept in sync.
## Every key must have both "fa" and "en" (checked by tests/test_strings.gd).

const TABLE := {
	"app_name": {"fa": "سربالایی", "en": "Sarbalaei"},
	"splash_tap": {"fa": "برای ادامه لمس کنید", "en": "Tap to continue"},
	"map_title": {"fa": "نقشه‌ی سفر", "en": "Trip map"},
	"menu_garage": {"fa": "گاراژ", "en": "Garage"},
	"menu_start": {"fa": "شروع", "en": "Start"},
	"map_locked": {"fa": "قفل است؛ مسیر قبلی را با یک ستاره تمام کن", "en": "Locked: finish the previous route with one star"},
	"map_best": {"fa": "بهترین", "en": "Best"},
	"map_par": {"fa": "هدف زمان", "en": "Target time"},
	"route_chalus": {"fa": "جاده‌ی چالوس", "en": "Chalus road"},
	"route_gilan": {"fa": "جاده‌ی گیلان", "en": "Gilan road"},
	"route_damavand": {"fa": "یال دماوند", "en": "Damavand ridge"},
	"car_kuhnavard": {"fa": "کوهنورد", "en": "Kuhnavard"},
	"garage_title": {"fa": "کوهنورد", "en": "Kuhnavard"},
	"garage_power": {"fa": "قدرت", "en": "Power"},
	"garage_grip": {"fa": "چسبندگی", "en": "Grip"},
	"garage_tank": {"fa": "باک", "en": "Tank"},
	"hud_gas": {"fa": "گاز", "en": "Gas"},
	"hud_brake": {"fa": "ترمز", "en": "Brake"},
	"hud_fuel": {"fa": "بنزین", "en": "Fuel"},
	"hud_pause": {"fa": "مکث", "en": "Pause"},
	"toast_fuel": {"fa": "بنزین گرفتی!", "en": "Fuel up!"},
	"toast_air": {"fa": "پرش در هوا", "en": "Air time"},
	"pause_title": {"fa": "یه دقیقه توقف", "en": "Quick pause"},
	"pause_resume": {"fa": "ادامه", "en": "Resume"},
	"pause_restart": {"fa": "دوباره", "en": "Restart"},
	"pause_exit": {"fa": "خروج به نقشه", "en": "Back to map"},
	"unit_meter": {"fa": "متر", "en": "m"},
	"unit_kmh": {"fa": "کیلومتر/ساعت", "en": "km/h"},
	"unit_sec": {"fa": "ثانیه", "en": "s"},
	"result_finished": {"fa": "به مقصد رسیدی!", "en": "You made it!"},
	"result_flipped": {"fa": "ماشین واژگون شد", "en": "The car flipped"},
	"result_no_fuel": {"fa": "بنزین تمام شد", "en": "Out of fuel"},
	"result_stuck": {"fa": "گیر کردی", "en": "Stuck"},
	"result_fell": {"fa": "از جاده افتادی", "en": "You fell off the road"},
	"result_distance": {"fa": "مسافت", "en": "Distance"},
	"result_coins": {"fa": "سکه", "en": "Coins"},
	"result_time": {"fa": "زمان", "en": "Time"},
	"result_best": {"fa": "بهترین", "en": "Best"},
	"result_again": {"fa": "دوباره", "en": "Again"},
	"result_map": {"fa": "نقشه", "en": "Map"},
	"stamp_done": {"fa": "ثبت شد", "en": "Logged"},
	"stamp_try": {"fa": "دوباره امتحان کن", "en": "Try again"},
	"coins_label": {"fa": "سکه", "en": "Coins"},
}

var lang := "fa"


## Returns the text for the current language, falling back to Persian.
func t(key: String) -> String:
	var entry: Dictionary = TABLE.get(key, {})
	if entry.is_empty():
		return key
	var current := App.language if App != null else "fa"
	return String(entry.get(current, entry.get("fa", key)))


## Formats a number. In Persian it uses Persian digits.
func num(value: Variant) -> String:
	var s := str(int(round(float(value))))
	if App != null and App.language == "fa":
		var persian := ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"]
		for i in 10:
			s = s.replace(str(i), persian[i])
	return s
