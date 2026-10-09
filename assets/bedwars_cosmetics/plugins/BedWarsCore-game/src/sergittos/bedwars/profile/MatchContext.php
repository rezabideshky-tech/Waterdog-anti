<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

/** Everything the profile module remembers about ONE running game. */
final class MatchContext{

    public bool $firstBlood = false;
    public bool $firstBed = false;

    /** @var array<string, Participant> lowercase username => participant */
    public array $players = [];

    public function __construct(public int $startedAt, public string $mode, public string $map){}
}
