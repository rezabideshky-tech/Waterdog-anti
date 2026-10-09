extends RefCounted
## Shared look and widget factories. Every button is sized by the layout AND its label is
## shrunk to fit the box (LESSONS L21, L59), so Persian text can never overflow or overlap.

const C_TEXT := Color(0.95, 0.97, 1.0)
const C_MUTED := Color(0.67, 0.73, 0.88)
const C_GOLD := Color(1.0, 0.8, 0.34)
const C_GOLD_DEEP := Color(0.85, 0.48, 0.12)
const C_TEAL := Color(0.38, 0.95, 0.92)
const C_TEAL_DEEP := Color(0.10, 0.45, 0.62)
const C_RED := Color(1.0, 0.43, 0.46)
const C_PANEL := Color(0.05, 0.08, 0.21, 0.88)
const C_PLATE := Color(0.02, 0.03, 0.10, 0.62)
const C_BORDER := Color(0.55, 0.74, 1.0, 0.35)
const BUTTON_PAD := 14
const RADIUS := 26


static func style(bg: Color, radius: int = RADIUS, border: Color = Color(0, 0, 0, 0), border_w: int = 0) -> StyleBoxFlat:
	var sb := StyleBoxFlat.new()
	sb.bg_color = bg
	sb.set_corner_radius_all(radius)
	sb.set_border_width_all(border_w)
	sb.border_color = border
	sb.content_margin_left = BUTTON_PAD
	sb.content_margin_right = BUTTON_PAD
	sb.content_margin_top = 6
	sb.content_margin_bottom = 6
	sb.anti_aliasing = true
	return sb


static func fit_font_size(text: String, font: Font, max_w: float, max_size: int, min_size: int) -> int:
	var s := max_size
	while s > min_size and font.get_string_size(text, HORIZONTAL_ALIGNMENT_CENTER, -1, s).x > max_w:
		s -= 1
	return s


static func button(text: String, primary: bool, min_size: Vector2, max_font: int = 40) -> Button:
	var b := Button.new()
	b.text = text
	b.custom_minimum_size = min_size
	b.size = min_size
	b.clip_text = true
	b.focus_mode = Control.FOCUS_NONE
	b.alignment = HORIZONTAL_ALIGNMENT_CENTER
	var font := Assets.font(true)
	b.add_theme_font_override("font", font)
	var fs := fit_font_size(text, font, min_size.x - BUTTON_PAD * 2.0, max_font, 14)
	b.add_theme_font_size_override("font_size", fs)
	var r := int(min_size.y * 0.5)
	if primary:
		b.add_theme_stylebox_override("normal", style(C_GOLD, r, Color(1, 0.95, 0.7, 0.9), 2))
		b.add_theme_stylebox_override("hover", style(C_GOLD.lightened(0.12), r, Color(1, 1, 1, 0.9), 2))
		b.add_theme_stylebox_override("pressed", style(C_GOLD_DEEP, r, Color(1, 0.9, 0.6, 0.8), 2))
		b.add_theme_stylebox_override("disabled", style(Color(0.3, 0.32, 0.4, 0.8), r))
		b.add_theme_color_override("font_color", Color(0.14, 0.07, 0.02))
		b.add_theme_color_override("font_hover_color", Color(0.14, 0.07, 0.02))
		b.add_theme_color_override("font_pressed_color", Color(0.1, 0.05, 0.02))
	else:
		b.add_theme_stylebox_override("normal", style(C_PANEL, r, C_BORDER, 2))
		b.add_theme_stylebox_override("hover", style(C_PANEL.lightened(0.1), r, C_TEAL, 2))
		b.add_theme_stylebox_override("pressed", style(C_TEAL_DEEP, r, C_TEAL, 2))
		b.add_theme_stylebox_override("disabled", style(Color(0.1, 0.11, 0.18, 0.7), r, C_BORDER, 1))
		b.add_theme_color_override("font_color", C_TEXT)
		b.add_theme_color_override("font_hover_color", C_TEAL)
		b.add_theme_color_override("font_pressed_color", C_TEXT)
		b.add_theme_color_override("font_disabled_color", Color(0.55, 0.58, 0.68))
	b.add_theme_stylebox_override("focus", StyleBoxEmpty.new())
	return b


static func label(text: String, font_size: int, color: Color = C_TEXT, bold: bool = false, align: HorizontalAlignment = HORIZONTAL_ALIGNMENT_CENTER) -> Label:
	var l := Label.new()
	l.text = text
	l.horizontal_alignment = align
	l.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	l.add_theme_font_override("font", Assets.font(bold))
	l.add_theme_font_size_override("font_size", font_size)
	l.add_theme_color_override("font_color", color)
	l.mouse_filter = Control.MOUSE_FILTER_IGNORE
	l.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	return l


static func panel(bg: Color = C_PANEL, radius: int = RADIUS) -> PanelContainer:
	var p := PanelContainer.new()
	p.add_theme_stylebox_override("panel", style(bg, radius, C_BORDER, 2))
	p.mouse_filter = Control.MOUSE_FILTER_IGNORE
	return p


static func dim_backdrop(alpha: float = 0.6) -> ColorRect:
	var r := ColorRect.new()
	r.color = Color(0.01, 0.02, 0.08, alpha)
	r.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	r.mouse_filter = Control.MOUSE_FILTER_STOP
	return r


static func fill_parent(c: Control) -> void:
	c.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)


## Ask a Button for its text width for layout tests.
static func text_width(text: String, font: Font, size: int) -> float:
	return font.get_string_size(text, HORIZONTAL_ALIGNMENT_CENTER, -1, size).x
