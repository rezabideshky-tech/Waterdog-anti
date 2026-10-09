# Aegis Orbit

A Godot 4.7 arcade game in English and Persian. Built to the standards of the Agentic Game Factory
(codedpro/agentic-game-factory): pure logic with GUT tests, autoplay through the real UI, store rules.

- Concept (for approval): [docs/CONCEPT.md](docs/CONCEPT.md)
- Tutorial (Persian, how this was built and how to run it): [docs/TUTORIAL_FA.md](docs/TUTORIAL_FA.md)
- Store listing draft: [store/store_listing.md](store/store_listing.md) (`python3 store/check_lengths.py`)
- Licences: [LICENSES.md](LICENSES.md)

## Run
Open the folder in Godot 4.7.x (gl_compatibility). Press Play.

## Check (headless)
```bash
GODOT=/path/to/godot-4.7 tools/run_checks.sh
```
This writes the global class cache, runs all GUT tests (`test_scripts_load.gd` loads every script under
`scripts/`, `tests/` and `tools/`), then runs the autoplay bot for a full game.

## Layout
- `scripts/orbit_logic.gd` — pure rules (no nodes, no clock). Everything advances through `step(dt)`.
- `scripts/bot.gd` — reference policy used by tests and autoplay.
- `scripts/save.gd`, `achievements.gd`, `i18n.gd`, `assets.gd`, `audio.gd` — autoloads and data.
- `scripts/screen_base.gd` and the `*_view.gd` screens — rebuilt from the live viewport size.
- `tests/` — GUT suites. `tools/` — autoplay driver, class-cache generator, art and audio generators.

## Export
`export_presets.cfg` excludes `addons/gut/*`, `tests/*` and `tools/*`. Android needs the Android SDK,
JDK 17 and Godot export templates; the keystore lives outside the project. Release signing and store
accounts are human steps.

## Not verified here
The build environment could not render frames or export an APK, so layout on real phones, the APK, and
the look of the screens are unverified. See the tutorial's checklist.
