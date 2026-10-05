extends Node
## Game.gd — داده‌ها، پیشرفت بازیکن و ذخیره‌سازی «جاده‌باز»
## همهٔ ماشین‌ها، ارتقاها، نقشه‌ها، مأموریت‌ها، فروشگاه و گردونهٔ شانس اینجاست.

signal coins_changed
signal gems_changed
signal car_changed
signal progress_changed

# ------------------------------------------------------------------ ماشین‌ها
## power: ضریب قدرت موتور — grip: چسبندگی — susp: نرمی کمک‌فنر
## fuel: ظرفیت باک (لیتر) — mass: وزن — stars: ستارهٔ لازم برای آزادسازی
const CARS := {
	"peykan": {
		"name": "پیکان", "price": 0, "gems": 0, "stars": 0,
		"power": 1.00, "mass": 1.00, "grip": 0.86, "susp": 0.88, "fuel": 42.0, "turbo": 1.0,
		"wheelbase": 208.0, "wheel_r": 30.0, "scale": 1.0, "top": 132,
		"desc": "افتخار ایران؛ سبک و سرحال، اما ترمزهایش تعارف دارد!",
	},
	"pride": {
		"name": "پراید", "price": 2400, "gems": 0, "stars": 0,
		"power": 0.92, "mass": 0.86, "grip": 0.84, "susp": 0.92, "fuel": 38.0, "turbo": 1.05,
		"wheelbase": 190.0, "wheel_r": 27.0, "scale": 0.98, "top": 126,
		"desc": "چابک و کم‌خرج؛ روی دست‌اندازها سبک است و جابه‌جا نمی‌شود.",
	},
	"samand": {
		"name": "سمند", "price": 5200, "gems": 0, "stars": 4,
		"power": 1.16, "mass": 1.10, "grip": 0.94, "susp": 0.96, "fuel": 55.0, "turbo": 1.1,
		"wheelbase": 232.0, "wheel_r": 31.0, "scale": 1.05, "top": 158,
		"desc": "پایدار و مطمئن؛ برای جاده‌های آسفالت و سرعت‌های بالا.",
	},
	"van": {
		"name": "ون دلیکا", "price": 7600, "gems": 0, "stars": 8,
		"power": 1.05, "mass": 1.34, "grip": 0.90, "susp": 1.05, "fuel": 70.0, "turbo": 0.9,
		"wheelbase": 244.0, "wheel_r": 30.0, "scale": 1.12, "top": 140,
		"desc": "بار و خانواده؛ باک بزرگ و کمک‌فنر نرم برای مسیرهای خاکی.",
	},
	"nissan": {
		"name": "نیسان زامیاد", "price": 10400, "gems": 0, "stars": 14,
		"power": 1.30, "mass": 1.28, "grip": 0.96, "susp": 1.0, "fuel": 62.0, "turbo": 1.15,
		"wheelbase": 252.0, "wheel_r": 32.0, "scale": 1.1, "top": 165,
		"desc": "وانت کار؛ قوی و پرطاقت، با کفی که روی دست‌اندازها تکان نمی‌خورد.",
	},
	"khavar": {
		"name": "خاور", "price": 15800, "gems": 0, "stars": 20,
		"power": 1.42, "mass": 1.85, "grip": 1.02, "susp": 1.08, "fuel": 86.0, "turbo": 1.0,
		"wheelbase": 280.0, "wheel_r": 40.0, "scale": 1.22, "top": 152,
		"desc": "کامیون باری؛ سنگین اما شکست‌ناپذیر. گردنه‌ها را می‌خورد!",
	},
	"benz": {
		"name": "بنز ۶۰۸", "price": 26000, "gems": 0, "stars": 30,
		"power": 1.65, "mass": 2.25, "grip": 1.06, "susp": 1.15, "fuel": 110.0, "turbo": 1.0,
		"wheelbase": 296.0, "wheel_r": 42.0, "scale": 1.3, "top": 145,
		"desc": "سلطان کمپرسی؛ هر بار که گاز بدهی، خاک را می‌کَنی.",
	},
}

const CAR_ORDER := ["peykan", "pride", "samand", "van", "nissan", "khavar", "benz"]

# ------------------------------------------------------------------ رنگ‌ها
const PAINTS := {
	"white": { "name": "سفید", "color": "#f6f8fa", "price": 0 },
	"red": { "name": "قرمز", "color": "#e8402a", "price": 400 },
	"blue": { "name": "آبی", "color": "#2f8fe0", "price": 400 },
	"green": { "name": "سبز", "color": "#38b32a", "price": 400 },
	"yellow": { "name": "زرد", "color": "#ffcf4a", "price": 600 },
	"black": { "name": "مشکی", "color": "#3a3f46", "price": 700 },
	"silver": { "name": "نقره‌ای", "color": "#c9ced6", "price": 700 },
	"purple": { "name": "بنفش", "color": "#7a5bd6", "price": 900 },
	"orange": { "name": "نارنجی", "color": "#f08a1e", "price": 900 },
	"chameleon": { "name": "سفید مروارید", "color": "#e8f6ff", "price": 1600 },
}
const PAINT_ORDER := ["white", "red", "blue", "green", "yellow", "black", "silver", "purple", "orange", "chameleon"]

# ------------------------------------------------------------------ رینگ‌ها
const RIM_COUNT := 7
const RIM_PRICE := 350

# ---------------------------------------------------------------- ارتقاها
const UPGRADES := {
	"engine": { "name": "موتور", "icon": "wrench", "max": 8, "base": 300, "mul": 1.55,
		"desc": "قدرت و شتاب بیشتر؛ سربالایی‌ها راحت‌تر فتح می‌شوند." },
	"tires": { "name": "لاستیک", "icon": "wheel", "max": 8, "base": 260, "mul": 1.5,
		"desc": "چسبندگی بیشتر روی خاک و باران؛ کمتر سُر می‌خوری." },
	"suspension": { "name": "کمک‌فنر", "icon": "gear", "max": 8, "base": 280, "mul": 1.5,
		"desc": "فرود نرم‌تر و کنترل بهتر روی دست‌اندازها." },
	"turbo": { "name": "نیترو", "icon": "nitro", "max": 6, "base": 420, "mul": 1.6,
		"desc": "قدرت نیترو بیشتر و شارژ سریع‌تر." },
	"tank": { "name": "باک بنزین", "icon": "fuel", "max": 6, "base": 240, "mul": 1.45,
		"desc": "ظرفیت بنزین بیشتر؛ مسیرهای بلندتر." },
}
const UPGRADE_ORDER := ["engine", "tires", "suspension", "turbo", "tank"]

# ------------------------------------------------------------------ نقشه‌ها
const LEVELS := [
	{
		"id": "tehran", "name": "کوچه‌های تهران", "teaser": "ترافیک، دست‌انداز و صدای بوق!",
		"terrain": "asphalt", "surface": "#4a4f57", "fill": "#6b4a2f",
		"sky": "sky_day", "bg1": "city_day", "bg2": "mtn_day",
		"deco": ["house_city", "streetlight", "barrier", "tree"],
		"length": 1500.0, "hills": 0.55, "rough": 0.5, "fuel_drain": 1.0,
		"coins": [250, 400, 550], "reward_coins": 300, "reward_gems": 1, "unlock_coins": 0, "unlock_stars": 0,
		"desc": "شروع ماجرا؛ آسفالت شهری با دست‌انداز و چاله.",
	},
	{
		"id": "shomal", "name": "جادهٔ شمال", "teaser": "باران، پیچ‌های جنگلی و مه",
		"terrain": "grass", "surface": "#4f8f3f", "fill": "#6b4a2f",
		"sky": "sky_rain", "bg1": "forest_green", "bg2": "mtn_day",
		"deco": ["pine", "tree", "bush", "rock"],
		"length": 2100.0, "hills": 1.0, "rough": 0.8, "fuel_drain": 1.05,
		"coins": [400, 650, 900], "reward_coins": 550, "reward_gems": 1, "unlock_coins": 1200, "unlock_stars": 2,
		"desc": "جادهٔ سبز شمال با تپه‌های نرم و باران ملایم.",
	},
	{
		"id": "mazraee", "name": "مزرعهٔ سبز", "teaser": "شالیزار، تراکتور و گِل",
		"terrain": "grass", "surface": "#57a03f", "fill": "#6b4a2f",
		"sky": "sky_day", "bg1": "forest_autumn", "bg2": "mtn_day",
		"deco": ["house_village", "rice_field", "tree", "tent"],
		"length": 1800.0, "hills": 0.8, "rough": 1.0, "fuel_drain": 1.1,
		"coins": [350, 550, 750], "reward_coins": 450, "reward_gems": 1, "unlock_coins": 3000, "unlock_stars": 5,
		"desc": "روستای ایرانی؛ زمین گِلی و پرش‌های بلند.",
	},
	{
		"id": "kavir", "name": "کویر لوت", "teaser": "تپه‌های شنی و کاکتوس‌ها",
		"terrain": "sand", "surface": "#d9b271", "fill": "#b8874a",
		"sky": "sky_desert", "bg1": "mtn_desert", "bg2": "mtn_desert",
		"deco": ["cactus", "rock", "tent", "palm"],
		"length": 2400.0, "hills": 1.25, "rough": 0.7, "fuel_drain": 1.15,
		"coins": [500, 800, 1150], "reward_coins": 700, "reward_gems": 2, "unlock_coins": 6000, "unlock_stars": 9,
		"desc": "کویر گرم؛ تپه‌های شنی بلند و پرش‌های نفس‌گیر.",
	},
	{
		"id": "shab", "name": "دور دور شهر", "teaser": "شب، نئون و سرعت بالا",
		"terrain": "asphalt", "surface": "#4a4f57", "fill": "#3a3f46",
		"sky": "sky_night", "bg1": "city_night", "bg2": "city_dusk",
		"deco": ["house_city", "streetlight", "barrier"],
		"length": 2600.0, "hills": 0.75, "rough": 0.45, "fuel_drain": 1.2,
		"coins": [600, 950, 1300], "reward_coins": 900, "reward_gems": 2, "unlock_coins": 11000, "unlock_stars": 14,
		"desc": "مسیر شبانهٔ شهر با چراغ‌های نئون و سرعت‌های دیوانه‌وار.",
	},
	{
		"id": "damavand", "name": "کوهستان دماوند", "teaser": "برف، یخ و گردنهٔ نفس‌گیر",
		"terrain": "snow", "surface": "#e8f0f8", "fill": "#b9cbdd",
		"sky": "sky_day", "bg1": "mtn_snow", "bg2": "mtn_day",
		"deco": ["pine", "rock", "bush"],
		"length": 3000.0, "hills": 1.5, "rough": 0.6, "fuel_drain": 1.25,
		"coins": [750, 1150, 1600], "reward_coins": 1300, "reward_gems": 3, "unlock_coins": 18000, "unlock_stars": 20,
		"desc": "بالاترین گردنهٔ ایران؛ یخ، برف و پرش از قله.",
	},
]

# ------------------------------------------------------------- مأموریت‌ها
const MISSION_POOL := [
	{ "id": "coins300", "text": "۳۰۰ سکه در یک مسیر جمع کن", "type": "coins_run", "target": 300, "reward": 350 },
	{ "id": "dist500", "text": "به ۵۰۰ متر برس", "type": "dist_run", "target": 500, "reward": 300 },
	{ "id": "jump5", "text": "۵ بار در هوا شنا کن", "type": "airtime_run", "target": 5, "reward": 400 },
	{ "id": "speed110", "text": "به سرعت ۱۱۰ کیلومتر برس", "type": "speed_run", "target": 110, "reward": 450 },
	{ "id": "noflip", "text": "یک مسیر را بدون واژگون شدن تمام کن", "type": "clean_run", "target": 1, "reward": 600 },
	{ "id": "dist1200", "text": "به ۱۲۰۰ متر برس", "type": "dist_run", "target": 1200, "reward": 550 },
	{ "id": "coins800", "text": "۸۰۰ سکه در یک مسیر جمع کن", "type": "coins_run", "target": 800, "reward": 700 },
	{ "id": "nitro3", "text": "۳ بار نیترو بزن", "type": "nitro_run", "target": 3, "reward": 350 },
	{ "id": "backflip", "text": "یک چرخش کامل در هوا بزن", "type": "flip_run", "target": 1, "reward": 800 },
	{ "id": "stars6", "text": "۶ ستاره بگیر", "type": "stars_total", "target": 6, "reward": 900 },
	{ "id": "car2", "text": "دو ماشین بخری", "type": "cars_total", "target": 2, "reward": 700 },
	{ "id": "dist2500", "text": "به ۲۵۰۰ متر برس", "type": "dist_run", "target": 2500, "reward": 1000 },
]

# --------------------------------------------------------------- فروشگاه
const SHOP_COINS := [
	{ "coins": 1000, "gems": 10 }, { "coins": 3000, "gems": 25 },
	{ "coins": 8000, "gems": 60 }, { "coins": 20000, "gems": 140 }, { "coins": 50000, "gems": 320 },
]
const SHOP_KEYS := [
	{ "keys": 1, "gems": 6 }, { "keys": 5, "gems": 25 }, { "keys": 15, "gems": 60 },
]
const WHEEL_PRIZES := [
	{ "kind": "coins", "amount": 200 }, { "kind": "coins", "amount": 500 },
	{ "kind": "gems", "amount": 3 }, { "kind": "coins", "amount": 1200 },
	{ "kind": "gems", "amount": 8 }, { "kind": "key", "amount": 1 },
	{ "kind": "coins", "amount": 3000 }, { "kind": "gems", "amount": 20 },
]

# ------------------------------------------------------------------ وضعیت
var coins := 800
var gems := 5
var keys := 1
var player_name := "راننده"
var owned_cars := ["peykan"]
var car := "peykan"
var paint := "white"
var owned_paints := ["white"]
var rims := [1]
var rim := 1
var upgrades := {}          # { car_id: { engine:1, ... } }
var levels := {}            # { level_id: { best:0.0, stars:0, runs:0 } }
var missions := []          # [ { id, progress, done, claimed } ]
var stats := { "dist": 0.0, "runs": 0, "flips": 0, "coins": 0 }
var settings := { "sound": true, "music": true, "shake": true, "quality": 1 }
var last_free_key := 0
var last_gift := 0
var total_stars := 0

const SAVE_PATH := "user://jadebaz.save"

func _ready() -> void:
	load_game()
	randomize()
	roll_missions()

# --------------------------------------------------------------- ارقام فارسی
const FA_DIGITS := ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"]

func fa(v) -> String:
	var s := str(v)
	var out := ""
	for i in s.length():
		var c := s.substr(i, 1)
		var d := "0123456789".find(c)
		out += FA_DIGITS[d] if d >= 0 else c
	return out

## عدد با جداکنندهٔ هزارگان و رقم فارسی: ۱۲٬۳۴۵
func money(v) -> String:
	var n := int(round(float(v)))
	var s := str(absi(n))
	var out := ""
	var c := 0
	for i in range(s.length() - 1, -1, -1):
		out = s.substr(i, 1) + out
		c += 1
		if c % 3 == 0 and i > 0:
			out = "٬" + out
	if n < 0:
		out = "−" + out
	return fa(out)

func fa_time(sec: float) -> String:
	var t := int(max(0.0, sec))
	return fa("%d:%02d" % [t / 60, t % 60])

# ------------------------------------------------------------------ پول‌ها
func add_coins(n: int) -> void:
	coins = max(0, coins + n)
	coins_changed.emit()
	save_game()

func add_gems(n: int) -> void:
	gems = max(0, gems + n)
	gems_changed.emit()
	save_game()

func add_keys(n: int) -> void:
	keys = max(0, keys + n)
	save_game()

func spend_coins(n: int) -> bool:
	if coins < n:
		return false
	coins -= n
	coins_changed.emit()
	save_game()
	return true

func spend_gems(n: int) -> bool:
	if gems < n:
		return false
	gems -= n
	gems_changed.emit()
	save_game()
	return true

# ------------------------------------------------------------------ ماشین‌ها
func has_car(id: String) -> bool:
	return owned_cars.has(id)

func car_data(id: String) -> Dictionary:
	return CARS.get(id, CARS["peykan"])

func buy_car(id: String) -> bool:
	if has_car(id):
		return false
	var d := car_data(id)
	if d["stars"] > total_stars:
		return false
	if coins < int(d["price"]):
		return false
	coins -= int(d["price"])
	owned_cars.append(id)
	car = id
	check_mission("cars_total", owned_cars.size())
	coins_changed.emit()
	car_changed.emit()
	save_game()
	return true

func select_car(id: String) -> void:
	if has_car(id):
		car = id
		car_changed.emit()
		save_game()

# ------------------------------------------------------------------ ارتقاها
func upg_level(car_id: String, key: String) -> int:
	var u: Dictionary = upgrades.get(car_id, {})
	return int(u.get(key, 0))

func upg_cost(car_id: String, key: String) -> int:
	var def: Dictionary = UPGRADES[key]
	var lv := upg_level(car_id, key)
	var price_per_car := 1.0 + 0.15 * float(CAR_ORDER.find(car_id))
	return int(round(float(def["base"]) * pow(float(def["mul"]), lv) * price_per_car))

func upg_maxed(car_id: String, key: String) -> bool:
	return upg_level(car_id, key) >= int(UPGRADES[key]["max"])

func buy_upgrade(car_id: String, key: String) -> bool:
	if upg_maxed(car_id, key):
		return false
	var c := upg_cost(car_id, key)
	if not spend_coins(c):
		return false
	var u: Dictionary = upgrades.get(car_id, {})
	u[key] = int(u.get(key, 0)) + 1
	upgrades[car_id] = u
	car_changed.emit()
	save_game()
	return true

## آمار نهایی ماشین با احتساب ارتقاها و لوازم
func car_stats(car_id: String) -> Dictionary:
	var d := car_data(car_id)
	var e := upg_level(car_id, "engine")
	var t := upg_level(car_id, "tires")
	var s := upg_level(car_id, "suspension")
	var n := upg_level(car_id, "turbo")
	var f := upg_level(car_id, "tank")
	return {
		"power": float(d["power"]) * (1.0 + 0.10 * e),
		"mass": float(d["mass"]),
		"grip": clampf(float(d["grip"]) * (1.0 + 0.055 * t), 0.4, 1.9),
		"susp": float(d["susp"]) * (1.0 + 0.05 * s),
		"turbo": float(d["turbo"]) * (1.0 + 0.14 * n),
		"fuel": float(d["fuel"]) * (1.0 + 0.12 * f),
		"wheelbase": float(d["wheelbase"]),
		"wheel_r": float(d["wheel_r"]),
		"scale": float(d["scale"]),
		"top": int(round(float(d["top"]) * (1.0 + 0.06 * e))),
	}

# ------------------------------------------------------------------ رنگ‌ها
func buy_paint(id: String) -> bool:
	if owned_paints.has(id):
		paint = id
		save_game()
		return true
	var p: Dictionary = PAINTS[id]
	if not spend_coins(int(p["price"])):
		return false
	owned_paints.append(id)
	paint = id
	save_game()
	return true

func paint_color() -> Color:
	return Color(PAINTS.get(paint, PAINTS["white"])["color"])

func buy_rim(i: int) -> bool:
	if rims.has(i):
		rim = i
		save_game()
		return true
	if not spend_coins(RIM_PRICE):
		return false
	rims.append(i)
	rim = i
	save_game()
	return true

# ------------------------------------------------------------------ نقشه‌ها
func level_data(id: String) -> Dictionary:
	for l in LEVELS:
		if l["id"] == id:
			return l
	return LEVELS[0]

func level_unlocked(id: String) -> bool:
	var l := level_data(id)
	if int(l["unlock_coins"]) <= 0 and int(l["unlock_stars"]) <= 0:
		return true
	return coins >= int(l["unlock_coins"]) and total_stars >= int(l["unlock_stars"])

func unlock_level(id: String) -> bool:
	var l := level_data(id)
	if level_unlocked(id):
		return true
	if total_stars < int(l["unlock_stars"]):
		return false
	if not spend_coins(int(l["unlock_coins"])):
		return false
	var lv: Dictionary = levels.get(id, {})
	lv["unlocked"] = true
	levels[id] = lv
	save_game()
	return true

func level_progress(id: String) -> Dictionary:
	return levels.get(id, { "best": 0.0, "stars": 0, "runs": 0, "unlocked": level_unlocked(id) })

func stars_for(level_id: String, dist: float) -> int:
	var l := level_data(level_id)
	var c: Array = l["coins"]
	var s := 0
	if dist >= float(c[0]):
		s = 1
	if dist >= float(c[1]):
		s = 2
	if dist >= float(c[2]):
		s = 3
	return s

func record_run(level_id: String, dist: float, got_coins: int, got_gems: int, flips: int, nitro: int, max_speed: float, airtime: float, clean: bool) -> Dictionary:
	var l := level_data(level_id)
	var stars := stars_for(level_id, dist)
	var lp: Dictionary = levels.get(level_id, {})
	var prev_best := float(lp.get("best", 0.0))
	var prev_stars := int(lp.get("stars", 0))
	lp["best"] = maxf(prev_best, dist)
	lp["stars"] = maxi(prev_stars, stars)
	lp["runs"] = int(lp.get("runs", 0)) + 1
	levels[level_id] = lp
	total_stars = 0
	for k in levels.keys():
		total_stars += int(levels[k].get("stars", 0))
	var reward := int(l["reward_coins"])
	if dist >= float(l["length"]):
		reward += 400
	var gems_got := got_gems
	if stars > prev_stars:
		reward += 250 * (stars - prev_stars)
		gems_got += 1
	coins += got_coins + reward
	gems += gems_got
	stats["dist"] = float(stats.get("dist", 0.0)) + dist
	stats["runs"] = int(stats.get("runs", 0)) + 1
	stats["flips"] = int(stats.get("flips", 0)) + flips
	stats["coins"] = int(stats.get("coins", 0)) + got_coins
	# مأموریت‌ها
	check_mission("coins_run", got_coins)
	check_mission("dist_run", int(round(dist)))
	check_mission("airtime_run", int(airtime))
	check_mission("speed_run", int(round(max_speed)))
	check_mission("nitro_run", nitro)
	check_mission("flip_run", flips)
	check_mission("clean_run", 1 if clean else 0)
	check_mission("stars_total", total_stars)
	check_mission("cars_total", owned_cars.size())
	coins_changed.emit()
	gems_changed.emit()
	progress_changed.emit()
	save_game()
	return { "stars": stars, "reward": reward, "gems": gems_got, "best": lp["best"] }

# --------------------------------------------------------------- مأموریت‌ها
func roll_missions() -> void:
	var pool := MISSION_POOL.duplicate()
	pool.shuffle()
	missions = []
	for i in mini(3, pool.size()):
		missions.append({ "id": pool[i]["id"], "progress": 0, "done": false, "claimed": false })
	save_game()

func mission_def(id: String) -> Dictionary:
	for m in MISSION_POOL:
		if m["id"] == id:
			return m
	return {}

func check_mission(type: String, value: int) -> void:
	var changed := false
	for m in missions:
		var def := mission_def(m["id"])
		if def.is_empty() or m["done"] or def["type"] != type:
			continue
		m["progress"] = maxi(int(m["progress"]), value)
		if int(m["progress"]) >= int(def["target"]):
			m["done"] = true
			changed = true
	if changed:
		save_game()

func claim_mission(i: int) -> int:
	if i < 0 or i >= missions.size():
		return 0
	var m: Dictionary = missions[i]
	if not m["done"] or m["claimed"]:
		return 0
	var def := mission_def(m["id"])
	m["claimed"] = true
	missions[i] = m
	coins += int(def["reward"])
	coins_changed.emit()
	# مأموریت تازه جای آن
	var pool := MISSION_POOL.duplicate()
	pool.shuffle()
	for cand in pool:
		var used := false
		for mm in missions:
			if mm["id"] == cand["id"]:
				used = true
		if not used:
			missions[i] = { "id": cand["id"], "progress": 0, "done": false, "claimed": false }
			break
	save_game()
	return int(def["reward"])

# --------------------------------------------------------------- بارگذاری
func to_dict() -> Dictionary:
	return {
		"v": 1, "coins": coins, "gems": gems, "keys": keys, "name": player_name,
		"cars": owned_cars, "car": car, "paint": paint, "paints": owned_paints,
		"rims": rims, "rim": rim, "upgrades": upgrades, "levels": levels,
		"missions": missions, "stats": stats, "settings": settings,
		"free_key": last_free_key, "gift": last_gift,
	}

func from_dict(d: Dictionary) -> void:
	coins = int(d.get("coins", coins))
	gems = int(d.get("gems", gems))
	keys = int(d.get("keys", keys))
	player_name = str(d.get("name", player_name))
	owned_cars = d.get("cars", owned_cars)
	car = str(d.get("car", car))
	paint = str(d.get("paint", paint))
	owned_paints = d.get("paints", owned_paints)
	rims = d.get("rims", rims)
	rim = int(d.get("rim", rim))
	upgrades = d.get("upgrades", {})
	levels = d.get("levels", {})
	missions = d.get("missions", [])
	stats = d.get("stats", stats)
	settings = d.get("settings", settings)
	last_free_key = int(d.get("free_key", 0))
	last_gift = int(d.get("gift", 0))
	total_stars = 0
	for k in levels.keys():
		total_stars += int(levels[k].get("stars", 0))
	# پاک‌سازی داده‌های خراب
	if not owned_cars.has(car):
		car = "peykan"
	if owned_cars.is_empty():
		owned_cars = ["peykan"]
	if not owned_paints.has(paint):
		paint = "white"

func save_game() -> void:
	var f := FileAccess.open(SAVE_PATH, FileAccess.WRITE)
	if f == null:
		return
	f.store_string(JSON.stringify(to_dict()))
	f.close()

func load_game() -> void:
	if not FileAccess.file_exists(SAVE_PATH):
		return
	var txt := FileAccess.get_file_as_string(SAVE_PATH)
	var parsed = JSON.parse_string(txt)
	if parsed is Dictionary:
		from_dict(parsed)

func reset_all() -> void:
	coins = 800
	gems = 5
	keys = 1
	owned_cars = ["peykan"]
	car = "peykan"
	paint = "white"
	owned_paints = ["white"]
	rims = [1]
	rim = 1
	upgrades = {}
	levels = {}
	missions = []
	stats = { "dist": 0.0, "runs": 0, "flips": 0, "coins": 0 }
	total_stars = 0
	roll_missions()
	save_game()
	coins_changed.emit()
	car_changed.emit()
