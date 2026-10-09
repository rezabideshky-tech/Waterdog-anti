extends Node
## Asset loader (autoload "Assets"). Art, fonts and sounds ship as plain files and are
## read at runtime. That works in the editor, in headless tests and in exported builds
## without a separate import step, and every path is checked so a missing file is a
## logged error with a safe fallback, never a crash.

var _textures := {}
var _fonts := {}
var _streams := {}


func texture(asset: String) -> Texture2D:
	if _textures.has(asset):
		return _textures[asset]
	var path := "res://assets/art/%s.png" % asset
	var img := Image.load_from_file(path)
	if img == null or img.is_empty():
		push_error("missing texture: " + path)
		return null
	var tex := ImageTexture.create_from_image(img)
	_textures[asset] = tex
	return tex


func font(bold: bool = false) -> Font:
	var key := "bold" if bold else "regular"
	if _fonts.has(key):
		return _fonts[key]
	var path := "res://assets/fonts/Vazirmatn-%s.ttf" % ("Bold" if bold else "Regular")
	var bytes := FileAccess.get_file_as_bytes(path)
	if bytes.is_empty():
		push_error("missing font: " + path)
		return ThemeDB.fallback_font
	var f := FontFile.new()
	f.data = bytes
	f.antialiasing = TextServer.FONT_ANTIALIASING_LCD
	_fonts[key] = f
	return f


func sound(asset: String) -> AudioStreamWAV:
	if _streams.has(asset):
		return _streams[asset]
	var path := "res://assets/audio/%s.wav" % asset
	var s := AudioStreamWAV.load_from_file(path)
	if s == null:
		push_error("missing sound: " + path)
		return null
	_streams[asset] = s
	return s


func asset_paths() -> PackedStringArray:
	## Used by tests: every asset the game refers to, to prove none is missing.
	return PackedStringArray([
		"res://assets/art/bg_space.png", "res://assets/art/planet.png", "res://assets/art/rock.png",
		"res://assets/art/icon.png", "res://assets/art/store_icon_1024.png",
		"res://assets/fonts/Vazirmatn-Regular.ttf", "res://assets/fonts/Vazirmatn-Bold.ttf",
		"res://assets/audio/music_pad.wav", "res://assets/audio/music_pulse.wav",
		"res://assets/audio/sfx_block.wav", "res://assets/audio/sfx_armor.wav",
		"res://assets/audio/sfx_leak.wav", "res://assets/audio/sfx_nova.wav",
		"res://assets/audio/sfx_click.wav", "res://assets/audio/sfx_combo.wav",
		"res://assets/audio/sfx_achievement.wav", "res://assets/audio/sfx_gameover.wav",
	])
