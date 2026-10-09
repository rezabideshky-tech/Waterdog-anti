<?php

declare(strict_types=1);

namespace sergittos\bedwars\lobby\profile;

use sergittos\bedwars\profile\PlayerProfile;

/** Everything one profile screen needs, already loaded from the database. */
final class ProfileView{

    /**
     * @param array<string, mixed> $extra tab specific data: history/historyTotal, seasons, top
     */
    public function __construct(
        public PlayerProfile $profile,
        public string $tab,
        public int $page,
        public int $pages,
        public int $entityId,
        public bool $self,
        public string $viewerName,
        public array $extra = []
    ){}
}
