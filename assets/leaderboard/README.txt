BedWars Honor Leaderboard — Blockbench prototype

Files
- bedwars_honor_leaderboard.bbmodel: editable Blockbench project (Bedrock model format).
- bedwars_honor_leaderboard.png: 128x128 texture atlas; the texture is also embedded in the .bbmodel.
- bedwars_honor_leaderboard.geo.json: Bedrock geometry export for a later resource-pack integration.
- preview_bedwars_honor_leaderboard.png: rendered concept preview.

Open the .bbmodel directly in Blockbench. The gold-framed board is a decorative
backdrop for a PocketMine hologram: player names and honor values are not baked
into the model so the server can render those dynamically. No plugin or resource
pack installation is included in this first visual draft.

The supplied geometry is unscaled. Adjust the custom entity's scale and its
hologram/text position to fit your lobby before deploying it on a live server.
