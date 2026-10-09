extends GutTest
## Every script under scripts/, tests/ and tools/ must parse and be instantiable.
## Catches parse errors that only show up at runtime (factory gate: test_scripts_load).

const ROOTS := ["res://scripts", "res://tests", "res://tools"]


func _collect(dir: String, out: Array) -> void:
	for sub in DirAccess.get_directories_at(dir):
		_collect(dir + "/" + sub, out)
	for file in DirAccess.get_files_at(dir):
		if file.ends_with(".gd"):
			out.append(dir + "/" + file)


func test_all_scripts_load_and_instantiate() -> void:
	var files: Array = []
	for root_dir in ROOTS:
		_collect(root_dir, files)
	assert_gt(files.size(), 10, "should find the project scripts")
	for path in files:
		var script = load(path)
		assert_not_null(script, "failed to load/parse: " + path)
		if script != null:
			assert_true(script.can_instantiate(), "cannot instantiate: " + path)
