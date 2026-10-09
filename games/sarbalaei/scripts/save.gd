extends Node
## Local save file in user://. Plain JSON, so it is easy to inspect and migrate.
## Tests point `path` at a temporary file.

const Data = preload("res://scripts/data.gd")
const DEFAULT_PATH := "user://sarbalaei_save.json"

var path := DEFAULT_PATH
var data: Dictionary = {}


func _ready() -> void:
	load_game()


func defaults() -> Dictionary:
	return {
		"version": 1,
		"coins": 0,
		"stars": {},
		"best_m": {},
		"car_color": 0,
		"language": "fa",
	}


func load_game() -> void:
	data = defaults()
	if not FileAccess.file_exists(path):
		return
	var f := FileAccess.open(path, FileAccess.READ)
	if f == null:
		return
	var parsed: Variant = JSON.parse_string(f.get_as_text())
	f.close()
	if typeof(parsed) != TYPE_DICTIONARY:
		return
	for k in data.keys():
		if parsed.has(k):
			data[k] = parsed[k]


func save_game() -> void:
	var f := FileAccess.open(path, FileAccess.WRITE)
	if f == null:
		push_warning("Sarbalaei: cannot write save file %s" % path)
		return
	f.store_string(JSON.stringify(data, "\t"))
	f.close()


func total_coins() -> int:
	return int(data.coins)


func add_coins(amount: int) -> void:
	data.coins = int(data.coins) + amount
	save_game()


func stars_of(route_id: String) -> int:
	return int(data.stars.get(route_id, 0))


func best_of(route_id: String) -> float:
	return float(data.best_m.get(route_id, 0.0))


func is_unlocked(route_id: String) -> bool:
	var route: Dictionary = Data.route_by_id(route_id)
	var needs := String(route.requires)
	if needs == "":
		return true
	return stars_of(needs) >= 1


## Stores the best distance and the best star count for a route. Returns the new best.
func record_run(route_id: String, distance_m: float, stars: int) -> float:
	var best := best_of(route_id)
	if distance_m > best:
		best = distance_m
		data.best_m[route_id] = distance_m
	if stars > stars_of(route_id):
		data.stars[route_id] = stars
	save_game()
	return best
