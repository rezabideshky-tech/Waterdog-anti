extends GutTest
## Builds the drive scene and steps it a few frames with no input. Nothing should end the run.


func test_drive_scene_builds_and_steps() -> void:
	if not ClassDB.class_exists("RigidBody2D"):
		pending("this engine build has no 2D physics; the CI Godot build runs this test")
		return
	App.pending_route_id = "chalus"
	App.pending_car_id = "kuhnavard"
	var packed: PackedScene = load(App.SCENE_RUN)
	var run := packed.instantiate()
	add_child_autofree(run)
	await get_tree().process_frame
	await get_tree().physics_frame
	await get_tree().physics_frame
	assert_true(run.running, "still running after a few frames")
	assert_not_null(run.car, "car exists")
	assert_gt(run.points.size(), 10, "terrain exists")
	assert_gt(run.pickups.size(), 0, "pickups exist")
