# Sarbalaei: design notes for the vertical slice

The full concept is in the chat design doc ("سربالایی"). This file records what this build
commits to, and what is deliberately left out.

## Pillars

1. **Physics that reads.** A heavier load changes how the car sits and climbs. Springs, not
   sliders, carry the car. Wheels, chassis, and joints are separate bodies.
2. **Fair economy.** Coins and stars only. No gacha, no paid progress, no ads in v1.
3. **Persian first.** Persian text, Persian numerals on the HUD, Persian route and car names.
   English is kept in sync.
4. **Animated, not busy.** Each screen has one signature motion: the car rolling across the
   splash, the car driving to the chosen town, the postcard's stars and stamp.
5. **Original.** Everything is drawn in code, or made for this project. See `ORIGINALITY.md`.

## Slice scope

- Route: Chalus road, 5200 px long. 1 px = 10 cm, so the route is 520 m.
- Car: Kuhnavard, all-wheel drive, four colours. Its name is original.
- Cargo and passengers, city deliveries, and multiple routes are **not** in this slice.
- Stars: finish (1), half the coins (2), finish under the target time (3). The second and third
  routes unlock when the previous route has one star.

## Physics parameters (first pass)

| Parameter | Value | Why |
|---|---|---|
| Chassis mass | 1.4 | Heavy enough to sit on the springs. |
| Wheel mass, radius | 0.6, 34 px | Light wheels so the motor controls the spin. |
| Spring stiffness | 300 | About 2 px of static sag under the car's weight. |
| Spring damping | 0.4 (ratio) | Under-damped enough to bounce on landing, not endlessly. |
| Spring max stretch | 1.5 × rest length | Room for suspension travel. |
| Motor | target wheel speed, torque capped | Stops the car from spinning out on grip. |
| Fuel | 0.6 idle + throttle × rate × (1 + speed / 1200) | Fuel matters on hills. |

These are reasoned, not measured. The first play session should tune spring_k, spring_d,
torque, and grip in `scripts/data.gd`.

## Next phases

1. Playtest and tune the physics. Record a replay for regression.
2. Two more routes (Gilan, Damavand), each with its own sky, soil, and grass palette.
3. Cargo and passengers with the health and mood meters.
4. Sound: engine tone from RPM, original Persian-instrument music.
5. Store build: Bazaar and Myket listings, an arm64 release signing setup, and a real package id.
