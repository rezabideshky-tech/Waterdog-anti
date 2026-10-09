#!/usr/bin/env python3
"""Writes .godot/global_script_class_cache.cfg from every `class_name` in the project.

The Godot editor builds this cache when a project is opened. Headless runs (tests, CI,
exports done without an editor) need it too, or any script that uses a global
`class_name` fails to compile. Run this before headless Godot. The .godot/ folder
is git-ignored, so the file is regenerated, never committed.
"""
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
CLASS_RX = re.compile(r"^class_name\s+(\w+)", re.M)
EXTENDS_RX = re.compile(r"^extends\s+([\w\"./:]+)", re.M)
NATIVE_BASES = {"Node", "Control", "RefCounted", "Object", "Resource", "Node2D", "Node3D",
                "Button", "Label", "Panel", "PanelContainer", "CenterContainer", "VBoxContainer",
                "HBoxContainer", "TextureRect", "ColorRect", "Area2D", "Sprite2D", "Camera2D",
                "CanvasLayer", "Timer", "SceneTree", "AudioStreamPlayer", "Tween"}


def main() -> int:
    entries = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if not d.startswith(".")]
        for name in filenames:
            if not name.endswith(".gd"):
                continue
            full = os.path.join(dirpath, name)
            with open(full, encoding="utf-8") as f:
                src = f.read()
            m = CLASS_RX.search(src)
            if not m:
                continue
            base = ""
            e = EXTENDS_RX.search(src)
            if e:
                target = e.group(1).strip('"')
                if target.startswith("res://"):
                    base = ""  # path bases are resolved by the engine at load time
                else:
                    base = target
            rel = "res://" + os.path.relpath(full, ROOT).replace(os.sep, "/")
            entries.append({"base": base, "class": m.group(1), "path": rel})
    entries.sort(key=lambda d: d["class"])
    lines = ["list=["]
    for d in entries:
        lines.append(
            '{"base": &"%s", "class": &"%s", "icon": "", "is_abstract": false, "is_tool": false, '
            '"language": &"GDScript", "path": "%s"},' % (d["base"], d["class"], d["path"]))
    lines.append("]")
    out_dir = os.path.join(ROOT, ".godot")
    os.makedirs(out_dir, exist_ok=True)
    with open(os.path.join(out_dir, "global_script_class_cache.cfg"), "w", encoding="utf-8") as f:
        f.write("\n".join(lines) + "\n")
    print("class cache: %d classes" % len(entries))
    return 0


if __name__ == "__main__":
    sys.exit(main())
