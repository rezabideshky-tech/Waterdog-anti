<?php

declare(strict_types=1);

namespace sergittos\bedwars\session;

use pocketmine\player\Player;

class SessionManager {

    /** @var array<string, Session> */
    private array $sessions = [];

    public function create(Player $player): Session {
        $session = new Session($player);
        $this->sessions[strtolower($player->getName())] = $session;
        return $session;
    }

    public function get(Player $player): ?Session {
        return $this->sessions[strtolower($player->getName())] ?? null;
    }

    public function getByName(string $name): ?Session {
        return $this->sessions[strtolower($name)] ?? null;
    }

    public function remove(Player $player): void {
        unset($this->sessions[strtolower($player->getName())]);
    }

    /** @return Session[] */
    public function getAll(): array {
        return $this->sessions;
    }

    public function saveAll(): void {
        foreach ($this->sessions as $session) {
            $session->save();
        }
    }
}
