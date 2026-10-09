extends Node2D
## A drivable vehicle built in code: a chassis body, two wheel bodies, and damped springs
## as suspension. Drive torque goes to the wheels; the chassis carries the cargo weight.

const CarBody = preload("res://scripts/car_body.gd")
const CarWheel = preload("res://scripts/car_wheel.gd")
const CarDraw = preload("res://scripts/car_draw.gd")

## Emitted when the wheels touch the ground again after being airborne.
signal landed(air_s: float, spin_rad: float)

var chassis: RigidBody2D
var wheels: Array = []
var spec: Dictionary = {}
var gas := 0.0
var brake := 0.0

var _airborne := false
var _air_s := 0.0
var _spin := 0.0


func build(car_spec: Dictionary, body_color: Color) -> void:
	spec = car_spec
	chassis = RigidBody2D.new()
	chassis.name = "Chassis"
	chassis.mass = float(spec.chassis_mass)
	chassis.linear_damp = 0.05
	chassis.angular_damp = 0.6
	chassis.can_sleep = false
	chassis.contact_monitor = true
	chassis.max_contacts_reported = 6
	var cs := CollisionShape2D.new()
	var rect := RectangleShape2D.new()
	rect.size = Vector2(230, 58)
	cs.shape = rect
	cs.position = Vector2(0, -8)
	chassis.add_child(cs)
	var vis := CarBody.new()
	vis.body_color = body_color
	chassis.add_child(vis)
	add_child(chassis)

	var grip := PhysicsMaterial.new()
	grip.friction = float(spec.grip)
	grip.bounce = 0.0
	for off in CarDraw.WHEEL_OFFSETS:
		var w := RigidBody2D.new()
		w.mass = float(spec.wheel_mass)
		w.can_sleep = false
		w.contact_monitor = true
		w.max_contacts_reported = 4
		w.physics_material_override = grip
		var wc := CollisionShape2D.new()
		var circle := CircleShape2D.new()
		circle.radius = float(spec.wheel_radius)
		wc.shape = circle
		w.add_child(wc)
		var wv := CarWheel.new()
		wv.radius = float(spec.wheel_radius)
		w.add_child(wv)
		w.position = off
		add_child(w)
		chassis.add_collision_exception_with(w)
		w.add_collision_exception_with(chassis)

		var spring := DampedSpringJoint2D.new()
		add_child(spring)
		spring.node_a = spring.get_path_to(chassis)
		spring.node_b = spring.get_path_to(w)
		# Godot: rest_length is the resting distance, length is the maximum stretch.
		spring.rest_length = off.length()
		spring.length = off.length() * 1.5
		spring.stiffness = float(spec.spring_k)
		spring.damping = float(spec.spring_d)
		wheels.append(w)


func set_inputs(gas_in: float, brake_in: float) -> void:
	gas = clampf(gas_in, 0.0, 1.0)
	brake = clampf(brake_in, 0.0, 1.0)


func _physics_process(delta: float) -> void:
	if chassis == null:
		return
	var max_w := float(spec.max_wheel_speed)
	var torque_cap := float(spec.torque)
	for i in wheels.size():
		var w: RigidBody2D = wheels[i]
		var driven: bool = spec.drive == "all" or (spec.drive == "rear" and i == 0) or (spec.drive == "front" and i == 1)
		if not driven:
			continue
		var torque: float
		if brake > 0.0:
			torque = -signf(w.angular_velocity) * brake * float(spec.brake_torque)
		else:
			var err := gas * max_w - w.angular_velocity
			torque = clampf(err * float(spec.motor_gain), -torque_cap, torque_cap)
		w.apply_torque(torque)

	var touching := false
	for w in wheels:
		if w.get_contact_count() > 0:
			touching = true
	if touching:
		if _airborne:
			landed.emit(_air_s, _spin)
		_airborne = false
		_air_s = 0.0
		_spin = 0.0
	else:
		_airborne = true
		_air_s += delta
		_spin += chassis.angular_velocity * delta


func body_position() -> Vector2:
	return chassis.global_position


func body_angle() -> float:
	return chassis.rotation


func speed_px() -> float:
	return chassis.linear_velocity.length()


## True when the chassis itself (not the wheels) is touching the ground.
func chassis_on_ground() -> bool:
	return chassis.get_contact_count() > 0
