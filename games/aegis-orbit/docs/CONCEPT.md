# Aegis Orbit — concept (for approval)

**Status:** drafted by the build agent. **Human approval: pending.** The build was started on the
owner's direct request, before the factory's concept gate; this document is the record to approve or change.

## One line
A planet sits in the middle of the screen. A shield orbits it. Block the rocks before they reach the surface.

## Core loop (about 60 s per run in the early game)
1. Drag a finger anywhere; the shield follows the finger's angle around the planet.
2. Rocks spawn at the edge and fall toward the planet.
3. A rock that crosses the shield ring while under the shield is blocked: points and combo.
4. A rock that gets past the ring costs a life. Three lives in Classic, two in Daily.
5. Every 10 blocks in a row charges a **Nova** (max 2) that clears all outer rocks. Classic only.

## Signature mechanic (factory §3)
The **shield arc with a predictive aim ring**: the rock's arrival angle is the thing you must
anticipate, so the skill is reading motion, not reacting to a flash. Armoured rocks need two hits
(knocked back out and back in); comets are fast and worth more.

## Modes
- **Classic:** endless. Waves every 18 s. Spawn rate and speed rise each wave; armour from wave 3,
  comets from wave 5, spinning rocks from wave 4, a burst every fourth wave. Undo/Nova is classic-only.
- **Daily Sky:** same seed for every player on the same local date. Two lives, no Nova, one attempt
  per day (consumed at the start, so quitting cannot be used to retry). Score goes to the daily board.

## Progression and retention
- Eleven achievements (first block, 100 blocks, combo 10 and 25, 5 armour breaks, first Nova,
  1000 and 5000 points, wave 6, first daily, 20 games).
- Records screen: lifetime stats, top ten runs, achievement progress.
- First-run tutorial (3 steps, skippable, shown once).
- No gacha, no pay-to-win, no ads, no purchases, no account (factory §4).

## Look and feel (factory: flat UI and bare mechanics are rejected)
- Space backdrop, a shaded planet with a rotating shader, a glowing gold shield arc, cratered rock sprites.
- Rounded panels with borders, glow-ringed icon chips (dark plate, bright glyph), label shrink-to-fit
  on every button.
- Layered audio: a pad loop always on, a pulse loop that fades in with intensity, synthesised SFX.
- Persian and English text. Persian text is rendered with Vazirmatn; art has no AI-generated text.

## Platforms and market
- Android first (portrait, 1080×1920 reference, `canvas_items` + `expand`), Linux desktop as a second target.
- International, English first, with full Persian (factory default; `factory.json` lists only Iran, so
  confirm the market before publishing).

## Build standards applied
Pure logic class with GUT tests (37 tests), Godot 4.7 `gl_compatibility`, ETC2/ASTC textures,
autoload services (Save, I18n, Assets, Audio), autoplay bot through the real UI handlers, and no
dev tools in the export (`addons/gut`, `tests`, `tools` excluded).

## Out of scope for v1
Online leaderboards, accounts, cloud save, ads, IAP, multiple planets, sharing images.

## Open decisions for the approver
1. Market: international (English first) or Iran first?
2. Package name and publisher (currently a placeholder, `com.example.aegisorbit`).
3. Icon, art and music: accept the generated set, or request a new art pass with human artists.
