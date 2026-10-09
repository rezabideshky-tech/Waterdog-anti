extends GutTest
## Every string exists in both languages, Persian text is fully covered by the bundled
## font (LESSONS L22), and buttons fit their boxes (LESSONS L21, L59, L64).

const I18nScript = preload("res://scripts/i18n.gd")
const UIKit = preload("res://scripts/ui_kit.gd")
const Achievements = preload("res://scripts/achievements.gd")
const AssetsScript = preload("res://scripts/assets.gd")

var i18n


func before_each() -> void:
	i18n = I18nScript.new()


func after_each() -> void:
	i18n.free()


func _all_literal_keys() -> Array:
	## Scans the source for I18n.t("key") / I18n.fmt("key") literals (plain string search,
	## so it works in every build, including ones without the RegEx module).
	var keys: Array = []
	var markers: Array[String] = ['I18n.t("', 'I18n.fmt("', 'i18n.t("']
	for dir in ["res://scripts", "res://scenes"]:
		for file in DirAccess.get_files_at(dir):
			if not (file.ends_with(".gd") or file.ends_with(".tscn")):
				continue
			var text := FileAccess.get_file_as_string(dir + "/" + file)
			for marker: String in markers:
				var pos := text.find(marker)
				while pos != -1:
					var start := pos + marker.length()
					var end := text.find('"', start)
					var k := text.substr(start, end - start)
					## only complete literals: "key")  or  "key",  (dynamic "ach_" + id is skipped)
					var closer := text.substr(end + 1, 1)
					if (closer == ")" or closer == ",") and k.is_valid_identifier() and not keys.has(k):
						keys.append(k)
					pos = text.find(marker, end)
	return keys


func test_english_and_persian_have_the_same_keys() -> void:
	var en: Array = i18n.keys_for("en")
	var fa: Array = i18n.keys_for("fa")
	for k in en:
		assert_true(fa.has(k), "missing Persian string: " + k)
	for k in fa:
		assert_true(en.has(k), "extra Persian string: " + k)


func test_every_used_key_exists_in_both_languages() -> void:
	var keys := _all_literal_keys()
	assert_gt(keys.size(), 20, "the scan should find the UI strings")
	for k in keys:
		assert_true(i18n.keys_for("en").has(k), "unknown key in code: " + k)
		assert_true(i18n.keys_for("fa").has(k), "no Persian text for key: " + k)


func test_every_achievement_has_a_name_and_description() -> void:
	for id in Achievements.ids():
		assert_true(i18n.keys_for("en").has("ach_" + id), "missing name " + id)
		assert_true(i18n.keys_for("en").has("ach_%s_d" % id), "missing description " + id)
		assert_true(i18n.keys_for("fa").has("ach_" + id), "missing Persian name " + id)
		assert_true(i18n.keys_for("fa").has("ach_%s_d" % id), "missing Persian description " + id)


func test_persian_digits_convert() -> void:
	i18n.language = "fa"
	assert_eq(i18n.digits("2026-10-09"), "۲۰۲۶-۱۰-۰۹")
	i18n.language = "en"
	assert_eq(i18n.digits("2026"), "2026")


func test_persian_text_is_fully_covered_by_the_font() -> void:
	var font: Font = AssetsScript.new().font(false)
	assert_not_null(font, "bundled Vazirmatn font must load")
	for k in i18n.keys_for("fa"):
		var text: String = i18n.STRINGS["fa"][k]
		for i in text.length():
			var c := text.unicode_at(i)
			if c == 32 or c == 45 or c == 46 or c == 58 or c == 47 or c == 40 or c == 41 or c == 44 or c == 37 or c == 215 or c == 183:
				continue  # space, punctuation, math glyphs
			assert_true(font.has_char(c), "font lacks U+%04X in key %s" % [c, k])


func test_long_persian_button_labels_shrink_to_fit() -> void:
	var font: Font = AssetsScript.new().font(true)
	var box_w := 320.0
	for k in i18n.keys_for("fa"):
		if not k.begins_with("menu_") and not k.begins_with("over_") and not k.begins_with("set_"):
			continue
		var text: String = i18n.STRINGS["fa"][k]
		var size := UIKit.fit_font_size(text, font, box_w, 44, 18)
		var w := font.get_string_size(text, HORIZONTAL_ALIGNMENT_CENTER, -1, size).x
		assert_true(size >= 18, "font must not go below the minimum for " + k)
		assert_true(w <= box_w or size == 18, "label overflows its box: " + k)
