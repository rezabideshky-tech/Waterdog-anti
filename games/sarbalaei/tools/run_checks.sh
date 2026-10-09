#!/usr/bin/env bash
# Local check: builds the class cache, then runs the GUT tests headless.
# Usage: GODOT=/path/to/godot4.7 tools/run_checks.sh
set -euo pipefail
GODOT="${GODOT:-godot}"
cd "$(dirname "$0")/.."

python3 tools/gen_class_cache.py

echo "== import (fonts and class names)"
"$GODOT" --headless --import . || true

echo "== unit and scene tests (GUT)"
"$GODOT" --headless --path . -s addons/gut/gut_cmdln.gd -gdir=res://tests -gexit
