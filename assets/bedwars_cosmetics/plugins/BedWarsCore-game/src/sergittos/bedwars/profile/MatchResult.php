<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

/** One participant's finished match, handed to ProfileService::recordMatch(). */
final class MatchResult{

    /**
     * @param string $result WIN | LOSS | TIE | LEFT
     * @param array<string, int> $medals medal id => times earned this match
     */
    public function __construct(
        public string $username,
        public string $displayName,
        public string $mode,
        public string $map,
        public string $result,
        public int $kills,
        public int $finals,
        public int $beds,
        public int $deaths,
        public int $duration,
        public bool $mvp,
        public array $medals
    ){}
}
