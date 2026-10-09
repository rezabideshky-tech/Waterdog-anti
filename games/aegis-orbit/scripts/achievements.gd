extends RefCounted
## Achievement definitions and the pure unlock check. Text lives in I18n ("ach_<id>",
## "ach_<id>_d"); tests assert every id has both strings in every language.

const DEFS := [
	{"id": "first_block", "stat": "total_blocks", "goal": 1},
	{"id": "blocks_100", "stat": "total_blocks", "goal": 100},
	{"id": "combo_10", "stat": "best_combo", "goal": 10},
	{"id": "combo_25", "stat": "best_combo", "goal": 25},
	{"id": "armor_5", "stat": "total_armor", "goal": 5},
	{"id": "nova_1", "stat": "total_novas", "goal": 1},
	{"id": "score_1000", "stat": "best_score", "goal": 1000},
	{"id": "score_5000", "stat": "best_score", "goal": 5000},
	{"id": "wave_6", "stat": "best_wave", "goal": 6},
	{"id": "daily_1", "stat": "daily_done", "goal": 1},
	{"id": "games_20", "stat": "games", "goal": 20},
]


static func ids() -> Array:
	var out: Array = []
	for d in DEFS:
		out.append(d.id)
	return out


## Returns the ids that are now satisfied but were not in `unlocked`.
static func newly_unlocked(stats: Dictionary, unlocked: Array) -> Array:
	var out: Array = []
	for d in DEFS:
		if unlocked.has(d.id):
			continue
		if int(stats.get(d.stat, 0)) >= int(d.goal):
			out.append(d.id)
	return out


static func progress(stats: Dictionary, id: String) -> Vector2i:
	for d in DEFS:
		if d.id == id:
			return Vector2i(mini(int(stats.get(d.stat, 0)), int(d.goal)), int(d.goal))
	return Vector2i.ZERO
