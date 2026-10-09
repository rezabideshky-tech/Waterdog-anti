<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

/** GENERATED from the same catalog that drives the resource pack artwork (do not edit by hand). */
final class ProfileCatalog{

	/**
	 * @var list<array{id: string, name: string, color: string}> ordered low -> high
	 *
	 * `id` intentionally still matches the original tier keys (rookie, veteran,
	 * elite, pro, master, grandmaster, legendary) because the resource pack's
	 * rank artwork is keyed off these ids (textures/bwp/rank/<id>.png etc.) -
	 * only the display `name` was rebranded, so every icon/texture lookup
	 * (RankedTiers::tierId(), ProfileValues' rank_icon/hero_icon) keeps
	 * resolving to the correct existing textures with zero resource pack
	 * changes needed.
	 */
	public const TIERS = [
		['id' => 'rookie', 'name' => 'Bronze', 'color' => '§6'],
		['id' => 'veteran', 'name' => 'Silver', 'color' => '§7'],
		['id' => 'elite', 'name' => 'Gold', 'color' => '§e'],
		['id' => 'pro', 'name' => 'Platinum', 'color' => '§b'],
		['id' => 'master', 'name' => 'Diamond', 'color' => '§3'],
		['id' => 'grandmaster', 'name' => 'Master', 'color' => '§5'],
		['id' => 'legendary', 'name' => 'Grandmaster', 'color' => '§4'],
	];

	/** @var array<string, array{name: string, desc: string, cat: string}> */
	public const MEDALS = [
		'first_blood' => ['name' => 'First Blood', 'desc' => 'Score the first kill of a match', 'cat' => 'combat'],
		'double_kill' => ['name' => 'Double Kill', 'desc' => '2 kills within 10 seconds', 'cat' => 'combat'],
		'triple_kill' => ['name' => 'Triple Kill', 'desc' => '3 kills within 10 seconds', 'cat' => 'combat'],
		'fury_kill' => ['name' => 'Fury Kill', 'desc' => '4 kills within 10 seconds', 'cat' => 'combat'],
		'rampage' => ['name' => 'Rampage', 'desc' => '5 kills within 10 seconds', 'cat' => 'combat'],
		'slayer' => ['name' => 'Slayer', 'desc' => '8 or more kills in one match', 'cat' => 'combat'],
		'final_blow' => ['name' => 'Final Blow', 'desc' => 'Land a final kill', 'cat' => 'combat'],
		'executioner' => ['name' => 'Executioner', 'desc' => '3 final kills in one match', 'cat' => 'combat'],
		'bed_breaker' => ['name' => 'Bed Breaker', 'desc' => 'Destroy an enemy bed', 'cat' => 'beds'],
		'bed_wrecker' => ['name' => 'Bed Wrecker', 'desc' => 'Destroy 2 beds in one match', 'cat' => 'beds'],
		'bed_hunter' => ['name' => 'Bed Hunter', 'desc' => 'Destroy 3 beds in one match', 'cat' => 'beds'],
		'trailblazer' => ['name' => 'Trailblazer', 'desc' => 'Destroy the first bed of a match', 'cat' => 'beds'],
		'victor' => ['name' => 'Victor', 'desc' => 'Win a match', 'cat' => 'victory'],
		'flawless' => ['name' => 'Flawless', 'desc' => 'Win with your bed intact', 'cat' => 'victory'],
		'untouchable' => ['name' => 'Untouchable', 'desc' => 'Win without dying once', 'cat' => 'victory'],
		'comeback' => ['name' => 'Comeback', 'desc' => 'Win after your bed was destroyed', 'cat' => 'victory'],
		'hot_streak' => ['name' => 'Hot Streak', 'desc' => 'Reach a 3 win streak', 'cat' => 'victory'],
		'unstoppable' => ['name' => 'Unstoppable', 'desc' => 'Reach a 5 win streak', 'cat' => 'victory'],
		'dominator' => ['name' => 'Dominator', 'desc' => 'Win with 10+ kills, finals and beds', 'cat' => 'victory'],
		'clutch' => ['name' => 'Clutch', 'desc' => 'Win as your team\'s last player alive', 'cat' => 'victory'],
		'match_mvp' => ['name' => 'Match MVP', 'desc' => 'Best player of the winning team', 'cat' => 'special'],
		'marathon' => ['name' => 'Marathon', 'desc' => 'Play a match lasting 20+ minutes', 'cat' => 'special'],
		'blitz' => ['name' => 'Blitz', 'desc' => 'Win a match in under 10 minutes', 'cat' => 'special'],
		'perfectionist' => ['name' => 'Perfectionist', 'desc' => 'Win as MVP, bed intact, zero deaths', 'cat' => 'special'],
	];

	/** @var array<string, array{name: string, desc: string, stat: string, goal: int, coins: int, xp: int}> */
	public const ACHIEVEMENTS = [
		'first_win' => ['name' => 'Opening Victory', 'desc' => 'Win your first match', 'stat' => 'wins', 'goal' => 1, 'coins' => 50, 'xp' => 100],
		'wins_10' => ['name' => 'Hot Hands', 'desc' => 'Win 10 matches', 'stat' => 'wins', 'goal' => 10, 'coins' => 100, 'xp' => 150],
		'wins_50' => ['name' => 'Battle Hardened', 'desc' => 'Win 50 matches', 'stat' => 'wins', 'goal' => 50, 'coins' => 250, 'xp' => 300],
		'wins_100' => ['name' => 'Centurion', 'desc' => 'Win 100 matches', 'stat' => 'wins', 'goal' => 100, 'coins' => 500, 'xp' => 500],
		'wins_500' => ['name' => 'Sovereign', 'desc' => 'Win 500 matches', 'stat' => 'wins', 'goal' => 500, 'coins' => 2500, 'xp' => 2500],
		'games_10' => ['name' => 'Getting Started', 'desc' => 'Play 10 matches', 'stat' => 'games', 'goal' => 10, 'coins' => 50, 'xp' => 100],
		'games_100' => ['name' => 'Regular', 'desc' => 'Play 100 matches', 'stat' => 'games', 'goal' => 100, 'coins' => 300, 'xp' => 400],
		'games_500' => ['name' => 'Lifer', 'desc' => 'Play 500 matches', 'stat' => 'games', 'goal' => 500, 'coins' => 1500, 'xp' => 1500],
		'kills_100' => ['name' => 'Hunter', 'desc' => 'Get 100 kills', 'stat' => 'kills', 'goal' => 100, 'coins' => 200, 'xp' => 250],
		'kills_1000' => ['name' => 'Reaper', 'desc' => 'Get 1,000 kills', 'stat' => 'kills', 'goal' => 1000, 'coins' => 1500, 'xp' => 1500],
		'finals_50' => ['name' => 'Closer', 'desc' => 'Get 50 final kills', 'stat' => 'finals', 'goal' => 50, 'coins' => 250, 'xp' => 300],
		'finals_500' => ['name' => 'Executioner', 'desc' => 'Get 500 final kills', 'stat' => 'finals', 'goal' => 500, 'coins' => 2000, 'xp' => 2000],
		'beds_25' => ['name' => 'Demolisher', 'desc' => 'Break 25 beds', 'stat' => 'beds', 'goal' => 25, 'coins' => 200, 'xp' => 250],
		'beds_250' => ['name' => 'Bed Bane', 'desc' => 'Break 250 beds', 'stat' => 'beds', 'goal' => 250, 'coins' => 1500, 'xp' => 1500],
		'streak_5' => ['name' => 'Unbroken', 'desc' => 'Reach a 5 win streak', 'stat' => 'streak', 'goal' => 5, 'coins' => 300, 'xp' => 300],
		'streak_10' => ['name' => 'Relentless', 'desc' => 'Reach a 10 win streak', 'stat' => 'streak', 'goal' => 10, 'coins' => 1000, 'xp' => 1000],
		'medals_10' => ['name' => 'Collector', 'desc' => 'Earn 10 medals', 'stat' => 'medals', 'goal' => 10, 'coins' => 150, 'xp' => 200],
		'medals_100' => ['name' => 'Decorated', 'desc' => 'Earn 100 medals', 'stat' => 'medals', 'goal' => 100, 'coins' => 1000, 'xp' => 1000],
		'playtime_24' => ['name' => 'Dedicated', 'desc' => 'Play for 24 hours', 'stat' => 'playtime', 'goal' => 24, 'coins' => 500, 'xp' => 500],
		'rank_master' => ['name' => 'Ascendant', 'desc' => 'Reach the Diamond rank', 'stat' => 'peak_tier', 'goal' => 4, 'coins' => 1000, 'xp' => 1000],
	];

}
