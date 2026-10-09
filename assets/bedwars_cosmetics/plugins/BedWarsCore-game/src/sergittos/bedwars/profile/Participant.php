<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use sergittos\bedwars\game\team\Team;
use sergittos\bedwars\session\Session;
use function strtolower;

/** Live per-match numbers of one player (kept only while the match runs). */
final class Participant{

    public string $key;
    public string $name;
    public ?Team $team = null;
    public string $teamName = "";

    public int $kills = 0;
    public int $finals = 0;
    public int $beds = 0;
    public int $deaths = 0;
    public bool $eliminated = false;

    /** @var list<float> timestamps of recent kills (multi-kill window) */
    public array $stamps = [];

    /** @var array<string, int> */
    public array $medals = [];

    public function __construct(public Session $session){
        $this->name = $session->getUsername();
        $this->key = strtolower($this->name);
        $this->team = $session->getTeam();
        $this->teamName = $this->team !== null ? $this->team->getName() : "";
    }

    public function points() : int{
        return $this->kills * 2 + $this->finals * 4 + $this->beds * 6;
    }
}
