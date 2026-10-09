extends RefCounted
## Static game data. Pure: no nodes and no engine state, so it is easy to test.

const ROUTES := [
	{
		"id": "chalus", "name_key": "route_chalus", "length": 5200.0, "seed": 11,
		"amp": 60.0, "slope": 0.20, "par_s": 70.0, "fuel_every": 1400.0,
		"requires": "", "map_x": 470.0, "map_y": 640.0,
		"far": "#8fb6c9", "near": "#6fae5c", "soil": "#8b5e3c", "grass": "#5fa45a",
	},
	{
		"id": "gilan", "name_key": "route_gilan", "length": 6400.0, "seed": 23,
		"amp": 70.0, "slope": 0.26, "par_s": 95.0, "fuel_every": 1300.0,
		"requires": "chalus", "map_x": 960.0, "map_y": 430.0,
		"far": "#7fa8a0", "near": "#4f9a58", "soil": "#6d4a2f", "grass": "#4d8f4a",
	},
	{
		"id": "damavand", "name_key": "route_damavand", "length": 7600.0, "seed": 37,
		"amp": 80.0, "slope": 0.31, "par_s": 120.0, "fuel_every": 1200.0,
		"requires": "gilan", "map_x": 1450.0, "map_y": 640.0,
		"far": "#a9b8c8", "near": "#86a977", "soil": "#7a5a3c", "grass": "#7aa86a",
	},
]

const CARS := [
	{
		"id": "kuhnavard", "name_key": "car_kuhnavard", "drive": "all",
		"torque": 16000.0, "motor_gain": 70.0, "brake_torque": 9000.0, "max_wheel_speed": 30.0,
		"chassis_mass": 1.4, "wheel_mass": 0.6, "wheel_radius": 34.0, "grip": 1.3,
		"spring_k": 300.0, "spring_d": 0.4, "tank": 100.0, "fuel_rate": 2.0,
		"colors": ["#2e7d4f", "#c8553d", "#2f6fa3", "#e0b13a"],
	},
]


static func route_by_id(id: String) -> Dictionary:
	for r in ROUTES:
		if r.id == id:
			return r
	return ROUTES[0]


static func car_by_id(id: String) -> Dictionary:
	for c in CARS:
		if c.id == id:
			return c
	return CARS[0]


static func color_for(car: Dictionary, index: int) -> Color:
	var cols: Array = car.colors
	return Color(String(cols[clampi(index, 0, cols.size() - 1)]))
