# Licences and asset provenance

Factory rule (LESSONS L28): every asset's licence is documented.

| Asset | Source | Licence |
|---|---|---|
| `assets/art/*.png` (planet, rock, background, icons) | Generated with an AI image model, then cropped, resized and keyed by `tools/process_art.py`. No third-party artwork. | Owned by the project. Review the image-model provider's terms before commercial release. |
| `assets/audio/*.wav` | Synthesised by `tools/gen_audio.py` (numpy). No samples. | Owned by the project. |
| `assets/fonts/Vazirmatn-*.ttf` | Vazirmatn by Saber Rastikerdar. | SIL Open Font License 1.1 (see the upstream project for the licence text). |
| `addons/gut` (GUT 9.6.1) | Butch Wesley / bitwes | MIT (see `addons/gut/LICENSE.md`). Not shipped in release builds. |
| Godot Engine | godotengine.org | MIT. |

No keystore, token or signing secret is stored in this project.
