# Sarbalaei (سربالایی)

A free, offline, Persian-first 2D hill-climb driving game in Godot 4.7. Original art, original
vehicles and routes, no ads, no gacha, no pay-to-win.

This directory is the **vertical slice** (roadmap phase 0): one car, one route, real suspension
physics, fuel and coins, a route map, a garage, and an animated result postcard.

## Status

| Area | State |
|---|---|
| Drive physics (suspension, motor, brakes, flips, falls) | Implemented. Tuned by reasoning, not yet by play. Needs on-device tuning. |
| Fuel, coins, air-time bonus, stars, stuck and flip rules | Implemented and unit tested (`scripts/run_rules.gd`). |
| Procedural route (Chalus), pickups, parallax backdrop | Implemented. |
| Route map, garage, result postcard, splash | Implemented, animated. |
| Persian text with Vazirmatn; English kept in sync | Implemented; checked by `tests/test_data_and_text.gd`. |
| Save file (coins, stars, best distance, car colour) | Implemented and tested. |
| GUT tests and scene smoke test | Implemented. |
| Android debug APK in CI | Workflow `.github/workflows/sarbalaei-apk.yml`. |
| Second and third routes, more cars, sound, store build | Not in this slice. See `docs/DESIGN.md`. |

## Run it

Requires Godot 4.7.x (CI uses 4.7.1).

```bash
cd games/sarbalaei
godot --headless --import .           # first time only (fonts are read at runtime)
godot --path .                        # open the game
```

Controls: GAS and BRAKE buttons at the bottom corners. On a desktop, the right and left arrow keys.

## Test

```bash
GODOT=/path/to/godot tools/run_checks.sh
```

The script builds the class cache, then runs GUT. The CI workflow runs the same tests with the
official engine build.

## Build the Android APK

The workflow `Sarbalaei - tests and Android debug APK` builds the APK on push to the session
branch when files under `games/sarbalaei/` change, and publishes it as a prerelease. The
preset exports arm64-v8a only, to keep the APK small. The package id is a placeholder,
`com.example.sarbalaei`, and must be changed before any store submission.

## Layout

```
scripts/           game logic (pure: data, terrain_gen, run_rules, strings, save)
scripts/           engine glue (car, run, hud, background, terrain, ui_button)
scripts/screens/   splash, map, garage, result
scenes/            one .tscn per screen; run.tscn is the drive
tests/             GUT tests (unit, scene smoke, script load)
tools/             run_checks.sh, gen_class_cache.py
docs/              DESIGN.md (scope and decisions), ORIGINALITY.md (clean-room rules)
assets/            Vazirmatn font (OFL) and the app icon; everything else is drawn in code
```

See `LICENSES.md` for asset provenance.
