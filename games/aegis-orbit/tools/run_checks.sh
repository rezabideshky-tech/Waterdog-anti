#!/usr/bin/env bash
# Full local check, in the same order as the factory gate:
#   1. global class cache (headless runs need it; the editor builds it on open)
#   2. GUT unit tests (includes test_scripts_load.gd, which loads every script)
#   3. autoplay bot through the real UI (writes an isolated save file)
# Usage: GODOT=/path/to/godot4.7 tools/run_checks.sh
set -euo pipefail
GODOT="${GODOT:-godot}"
cd "$(dirname "$0")/.."

python3 tools/gen_class_cache.py

echo "== unit tests (GUT)"
"$GODOT" --headless --path . -s addons/gut/gut_cmdln.gd -gdir=res://tests -gexit

echo "== autoplay (real UI, bot aim through feed_input)"
"$GODOT" --headless --path . -s res://tools/autoplay.gd -- 400
