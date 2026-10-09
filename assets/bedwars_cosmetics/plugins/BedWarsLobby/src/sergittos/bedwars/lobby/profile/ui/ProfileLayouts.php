<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\profile\ui;

/**
 * GENERATED together with the BedWars Profile UI resource pack (ui/bwp/profile.json).
 * Every button text is marker(i) . value, where i is the position of the button in the form.
 * DATA slots come first, followed by ACTIONS (same order in every layout).
 */
final class ProfileLayouts{

	public const TABS = ['home', 'stats', 'rank', 'medals', 'ach', 'history'];

	/** @var array<string, string> hidden marker placed in the form title, one per layout */
	public const TITLES = [
		'home' => "§b§w§p0§q",
		'stats' => "§b§w§p1§q",
		'rank' => "§b§w§p2§q",
		'medals' => "§b§w§p3§q",
		'ach' => "§b§w§p4§q",
		'history' => "§b§w§p5§q",
		'board' => "§b§w§p6§q",
	];

	/** @var list<string> clickable buttons that follow DATA in every layout */
	public const ACTIONS = ['tab_home', 'tab_stats', 'tab_rank', 'tab_medals', 'tab_ach', 'tab_history', 'top', 'search', 'prev', 'next'];

	/** @var array<string, list<string>> */
	public const DATA = [
		'home' => ['name', 'lvl', 'uid', 'xpbar', 'xp', 'rank_icon', 'rank_line', 'season', 'season_sub', 'footer', 'page', 'hero_icon', 'hero_name', 'hero_rp', 'hero_bar', 'hero_next', 'hero_peak', 'hero_pos', 'q_wins', 'q_wr', 'q_kd', 'q_final', 'q_beds', 'q_streak', 'g0_icon', 'g0_txt', 'g0_bar', 'g1_icon', 'g1_txt', 'g1_bar', 'g2_icon', 'g2_txt', 'g2_bar'],
		'stats' => ['name', 'lvl', 'uid', 'xpbar', 'xp', 'rank_icon', 'rank_line', 'season', 'season_sub', 'footer', 'page', 's_wins', 's_losses', 's_games', 's_wr', 's_kills', 's_deaths', 's_kd', 's_finals', 's_beds', 's_streak', 's_time', 's_mvp'],
		'rank' => ['name', 'lvl', 'uid', 'xpbar', 'xp', 'rank_icon', 'rank_line', 'season', 'season_sub', 'footer', 'page', 'r0', 'r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'rk_title', 'rk_rp', 'rk_bar', 'rk_hint', 'h0_s', 'h0_r', 'h0_p', 'h1_s', 'h1_r', 'h1_p', 'h2_s', 'h2_r', 'h2_p', 'h3_s', 'h3_r', 'h3_p'],
		'medals' => ['name', 'lvl', 'uid', 'xpbar', 'xp', 'rank_icon', 'rank_line', 'season', 'season_sub', 'footer', 'page', 'm0_icon', 'm0_txt', 'm1_icon', 'm1_txt', 'm2_icon', 'm2_txt', 'm3_icon', 'm3_txt', 'm4_icon', 'm4_txt', 'm5_icon', 'm5_txt', 'm6_icon', 'm6_txt', 'm7_icon', 'm7_txt'],
		'ach' => ['name', 'lvl', 'uid', 'xpbar', 'xp', 'rank_icon', 'rank_line', 'season', 'season_sub', 'footer', 'page', 'a0_icon', 'a0_txt', 'a0_bar', 'a1_icon', 'a1_txt', 'a1_bar', 'a2_icon', 'a2_txt', 'a2_bar', 'a3_icon', 'a3_txt', 'a3_bar', 'a4_icon', 'a4_txt', 'a4_bar', 'a5_icon', 'a5_txt', 'a5_bar', 'a6_icon', 'a6_txt', 'a6_bar', 'a7_icon', 'a7_txt', 'a7_bar'],
		'history' => ['name', 'lvl', 'uid', 'xpbar', 'xp', 'rank_icon', 'rank_line', 'season', 'season_sub', 'footer', 'page', 'x0_chip', 'x0_main', 'x0_stats', 'x0_rp', 'x1_chip', 'x1_main', 'x1_stats', 'x1_rp', 'x2_chip', 'x2_main', 'x2_stats', 'x2_rp', 'x3_chip', 'x3_main', 'x3_stats', 'x3_rp', 'x4_chip', 'x4_main', 'x4_stats', 'x4_rp', 'x5_chip', 'x5_main', 'x5_stats', 'x5_rp', 'x_empty'],
		'board' => ['name', 'lvl', 'uid', 'xpbar', 'xp', 'rank_icon', 'rank_line', 'season', 'season_sub', 'footer', 'page', 'b0_icon', 'b0_name', 'b0_rp', 'b1_icon', 'b1_name', 'b1_rp', 'b2_icon', 'b2_name', 'b2_rp', 'b3_icon', 'b3_name', 'b3_rp', 'b4_icon', 'b4_name', 'b4_rp', 'b5_icon', 'b5_name', 'b5_rp', 'b6_icon', 'b6_name', 'b6_rp', 'b7_icon', 'b7_name', 'b7_rp', 'b8_icon', 'b8_name', 'b8_rp', 'b9_icon', 'b9_name', 'b9_rp', 'b_empty'],
	];

	/** @var array<string, list<string>> slots that carry a picture (button image) instead of text */
	public const IMAGES = [
		'home' => ['xpbar', 'rank_icon', 'hero_icon', 'hero_bar', 'g0_icon', 'g0_bar', 'g1_icon', 'g1_bar', 'g2_icon', 'g2_bar'],
		'stats' => ['xpbar', 'rank_icon'],
		'rank' => ['xpbar', 'rank_icon', 'r0', 'r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'rk_bar'],
		'medals' => ['xpbar', 'rank_icon', 'm0_icon', 'm1_icon', 'm2_icon', 'm3_icon', 'm4_icon', 'm5_icon', 'm6_icon', 'm7_icon'],
		'ach' => ['xpbar', 'rank_icon', 'a0_icon', 'a0_bar', 'a1_icon', 'a1_bar', 'a2_icon', 'a2_bar', 'a3_icon', 'a3_bar', 'a4_icon', 'a4_bar', 'a5_icon', 'a5_bar', 'a6_icon', 'a6_bar', 'a7_icon', 'a7_bar'],
		'history' => ['xpbar', 'rank_icon', 'x0_chip', 'x1_chip', 'x2_chip', 'x3_chip', 'x4_chip', 'x5_chip'],
		'board' => ['xpbar', 'rank_icon', 'b0_icon', 'b1_icon', 'b2_icon', 'b3_icon', 'b4_icon', 'b5_icon', 'b6_icon', 'b7_icon', 'b8_icon', 'b9_icon'],
	];

	public static function marker(int $index) : string{
		return "\u{00AB}" . str_pad((string) $index, 2, "0", STR_PAD_LEFT) . "\u{00BB}";
	}

}
