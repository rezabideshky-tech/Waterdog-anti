<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\clan\ui;

/**
 * GENERATED together with the BedWars Clan UI resource pack additions (ui/bwp/clan.json, namespace "bwc").
 * Same convention as ProfileLayouts: every button text is marker(i) . value, i = position in the form.
 * All 6 dashboard screens (home/members/war/bank/apps/info) share ONE fixed 40-slot layout so the resource
 * pack only has to define each row/action control once; form_dialog is a separate, smaller 10-slot layout
 * used for every confirm/prompt/choice screen (leave, disband, invite, mute, reject reason, etc).
 */
final class ClanLayouts{

	public const SCREENS = ['home', 'members', 'war', 'bank', 'apps', 'info', 'dialog', 'search'];

	/** @var array<string,string> hidden marker placed in the form title, one per screen */
	public const TITLES = [
		'home' => "\u{00A7}b\u{00A7}w\u{00A7}c0\u{00A7}q",
		'members' => "\u{00A7}b\u{00A7}w\u{00A7}c1\u{00A7}q",
		'war' => "\u{00A7}b\u{00A7}w\u{00A7}c2\u{00A7}q",
		'bank' => "\u{00A7}b\u{00A7}w\u{00A7}c3\u{00A7}q",
		'apps' => "\u{00A7}b\u{00A7}w\u{00A7}c4\u{00A7}q",
		'info' => "\u{00A7}b\u{00A7}w\u{00A7}c5\u{00A7}q",
		'dialog' => "\u{00A7}b\u{00A7}w\u{00A7}c6\u{00A7}q",
		// Reuses the exact same 40-slot DASH_DATA/DASH_ACTIONS layout as home/members/etc.
		// (see ClanForm::data()/actions() - only 'dialog' branches differently), so the
		// clan-search results list gets the same reskinned rows/pagination for free.
		'search' => "\u{00A7}b\u{00A7}w\u{00A7}c7\u{00A7}q",
	];

	public const DASH_DATA = ['hero', 'line1', 'line2', 'row0_icon', 'row0_text', 'row1_icon', 'row1_text', 'row2_icon', 'row2_text', 'row3_icon', 'row3_text', 'row4_icon', 'row4_text', 'row5_icon', 'row5_text', 'row6_icon', 'row6_text', 'row7_icon', 'row7_text', 'page'];
	public const DASH_ACTIONS = ['tab_home', 'tab_members', 'tab_war', 'tab_bank', 'tab_apps', 'tab_settings', 'close', 'prev', 'next', 'primary', 'secondary', 'tertiary', 'row0_action', 'row1_action', 'row2_action', 'row3_action', 'row4_action', 'row5_action', 'row6_action', 'row7_action'];
	public const DASH_IMAGES = ['hero', 'row0_icon', 'row1_icon', 'row2_icon', 'row3_icon', 'row4_icon', 'row5_icon', 'row6_icon', 'row7_icon'];

	public const DIALOG_DATA = ['icon', 'title', 'message'];
	public const DIALOG_ACTIONS = ['opt0', 'opt1', 'opt2', 'opt3', 'opt4', 'opt5', 'close'];
	public const DIALOG_IMAGES = ['icon'];

	public static function marker(int $index) : string{
		return "\u{00AB}" . str_pad((string) $index, 2, "0", STR_PAD_LEFT) . "\u{00BB}";
	}

}
