extends GutTest
## Every script and scene must parse and load. This catches broken references early.


func _collect(dir_path: String, ext: String, out: Array) -> void:
	var dir := DirAccess.open(dir_path)
	if dir == null:
		return
	dir.list_dir_begin()
	var name := dir.get_next()
	while name != "":
		var full := dir_path + "/" + name
		if dir.current_is_dir():
			if not name.begins_with("."):
				_collect(full, ext, out)
		elif name.ends_with(ext):
			out.append(full)
		name = dir.get_next()
	dir.list_dir_end()


func test_all_scripts_load() -> void:
	var files: Array = []
	_collect("res://scripts", ".gd", files)
	assert_gt(files.size(), 20, "found the scripts")
	for path in files:
		var s = load(path)
		assert_not_null(s, "failed to load %s" % path)


func test_all_scenes_load_and_instantiate() -> void:
	var files: Array = []
	_collect("res://scenes", ".tscn", files)
	assert_eq(files.size(), 5)
	for path in files:
		var packed: PackedScene = load(path)
		assert_not_null(packed, "failed to load %s" % path)
		var node := packed.instantiate()
		assert_not_null(node, "failed to instantiate %s" % path)
		node.free()
