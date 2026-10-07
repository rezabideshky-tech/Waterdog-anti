extends Node
class_name CarGeometry
## CarGeometry.gd — هندسهٔ اندازه‌گیری‌شدهٔ هر ماشین (خروجی خودکار tools/specs.mjs)
## همهٔ اعداد در «پیکسل تصویر» هستند و در بازی در مقیاس جهانی ضرب می‌شوند.
## wb فاصلهٔ محور چرخ‌ها، wr شعاع چرخ، wy مرکز چرخ نسبت به مرکز تصویر،
## left/right/top/bottom حدود بدنه نسبت به مرکز تصویر (برای برخورد بدنه).

const SPECS := {
	"peykan": { "wb": 300, "wr": 36, "wy": 75, "left": -226, "right": 213, "top": -46, "bottom": 37 },
	"pride": { "wb": 276, "wr": 34, "wy": 75, "left": -214, "right": 207, "top": -44, "bottom": 37 },
	"samand": { "wb": 296, "wr": 35, "wy": 75, "left": -222, "right": 219, "top": -50, "bottom": 37 },
	"van": { "wb": 260, "wr": 33, "wy": 75, "left": -210, "right": 189, "top": -64, "bottom": 35 },
	"nissan": { "wb": 280, "wr": 32, "wy": 75, "left": -210, "right": 213, "top": -46, "bottom": 35 },
	"khavar": { "wb": 290, "wr": 40, "wy": 75, "left": -210, "right": 243, "top": -68, "bottom": 35 },
	"benz": { "wb": 280, "wr": 40, "wy": 75, "left": -216, "right": 239, "top": -76, "bottom": 35 },
}

static func of(id: String) -> Dictionary:
	return SPECS.get(id, SPECS["peykan"])

## مقیاس تصویرِ چرخ نسبت به فایل چرخ‌ها (شعاع آن ۹۲ پیکسل است)
static func wheel_scale(id: String, world_scale: float) -> float:
	return world_scale * float(of(id)["wr"]) / 92.0
