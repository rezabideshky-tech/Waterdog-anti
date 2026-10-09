<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use function max;
use function md5;
use function strtolower;
use function strtoupper;
use function substr;

/** Plain data holder: everything the profile screens need about one player. */
final class PlayerProfile{

    public bool $exists = false;
    public string $displayName;

    // bw_players (lifetime stats owned by the core session)
    public int $kills = 0;
    public int $wins = 0;
    public int $finals = 0;
    public int $beds = 0;
    public int $deaths = 0;
    public int $xp = 0;
    public int $level = 1;
    public int $winStreak = 0;
    public int $bestStreak = 0;

    // bw_profile (tracked by the profile module)
    public int $games = 0;
    public int $losses = 0;
    public int $playtime = 0;
    public int $mvps = 0;
    public int $rp = 0;
    public int $peakRp = 0;
    public int $peakTier = 0;

    public ?int $rankPosition = null;

    /** @var array<string, int> medal id => times earned */
    public array $medals = [];

    public function __construct(public readonly string $username){
        $this->displayName = $username;
    }

    public function uid() : string{
        return strtoupper(substr(md5("bw-profile:" . strtolower($this->username)), 0, 10));
    }

    public function medalTotal() : int{
        $t = 0;
        foreach($this->medals as $c){
            $t += $c;
        }
        return $t;
    }

    public function kd() : float{
        return ($this->kills + $this->finals) / max(1, $this->deaths);
    }

    public function winRate() : float{
        $played = $this->wins + $this->losses;
        return $played > 0 ? ($this->wins / $played) * 100.0 : 0.0;
    }

    /** Progress value for an achievement stat key. */
    public function statValue(string $stat) : float{
        return match($stat){
            "wins" => (float) $this->wins,
            "games" => (float) $this->games,
            "kills" => (float) $this->kills,
            "finals" => (float) $this->finals,
            "beds" => (float) $this->beds,
            "streak" => (float) $this->bestStreak,
            "medals" => (float) $this->medalTotal(),
            "playtime" => $this->playtime / 3600.0,
            "peak_tier" => (float) $this->peakTier,
            default => 0.0,
        };
    }
}
