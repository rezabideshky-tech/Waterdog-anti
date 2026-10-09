<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan\war;

/**
 * Skeleton only, intentionally. Clan Wars is announced in the UI as
 * "Coming Soon" (see ClanMainGui's War tab) and ships with zero gameplay
 * logic on purpose, exactly as specced, so it can't introduce bugs or
 * conflicts with the current game modes. Wire actual war logic here in a
 * future phase.
 */
final class ClanWarManager{

    public function isEnabled(): bool{
        return false;
    }
}
