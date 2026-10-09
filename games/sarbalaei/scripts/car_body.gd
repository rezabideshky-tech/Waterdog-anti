extends Node2D
## Draws the chassis of the physics car.

const CarDraw = preload("res://scripts/car_draw.gd")

var body_color := Color("#2e7d4f")


func _draw() -> void:
	CarDraw.draw_body(self, body_color)
