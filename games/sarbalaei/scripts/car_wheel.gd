extends Node2D
## Draws one wheel. The parent RigidBody2D rotates it, so the spokes turn with the wheel.

const CarDraw = preload("res://scripts/car_draw.gd")

var radius := 34.0


func _draw() -> void:
	CarDraw.draw_wheel(self, radius)
