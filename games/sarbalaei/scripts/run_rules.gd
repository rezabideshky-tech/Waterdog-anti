extends RefCounted
## Pure rules for one drive: units, stars, fuel, stuck and flip checks, and air bonuses.

const PX_PER_M := 10.0
const IDLE_BURN := 0.6
const COIN_VALUE := 5
const FUEL_CAN := 25.0


static func distance_m(travelled_px: float) -> float:
	return maxf(0.0, travelled_px) / PX_PER_M


## 1 px is 10 cm, so px/s * 0.1 m/s * 3.6 gives km/h.
static func kmh(speed_px_s: float) -> float:
	return speed_px_s * 0.36


## 0 stars unless the route was finished. Then: 1 for finishing, +1 for half the coins, +1 under the target time.
static func stars(finished: bool, coin_ratio: float, time_s: float, par_s: float) -> int:
	if not finished:
		return 0
	var s := 1
	if coin_ratio >= 0.5:
		s += 1
	if time_s <= par_s:
		s += 1
	return s


## Fuel used in one step: idle burn plus throttle, which costs more at higher speed.
static func fuel_used(gas: float, speed_px_s: float, delta: float, rate: float) -> float:
	var g := clampf(gas, 0.0, 1.0)
	return (IDLE_BURN + g * rate * (1.0 + speed_px_s / 1200.0)) * delta


static func is_stuck(speed_px_s: float, gas: float, stuck_time: float) -> bool:
	return gas > 0.5 and speed_px_s < 6.0 and stuck_time >= 5.0


## True when the chassis is more than 110 degrees from upright.
static func is_flipped(angle_rad: float) -> bool:
	return absf(wrapf(angle_rad, -PI, PI)) > deg_to_rad(110.0)


## Coins for air time, plus 50 per full turn in the air. Short hops pay nothing.
static func air_bonus(air_s: float, spin_rad: float) -> int:
	if air_s < 0.5:
		return 0
	var turns := int(absf(spin_rad) / TAU)
	return int(air_s * 20.0) + turns * 50


static func coin_ratio(collected: int, total: int) -> float:
	if total <= 0:
		return 0.0
	return clampf(float(collected) / float(total), 0.0, 1.0)
