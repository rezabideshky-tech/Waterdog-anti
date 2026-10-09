<?php

declare(strict_types=1);

namespace sergittos\bedwars\network;

class ServerInfo {

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly int    $online,
        public readonly int    $maxPlayers,
        public readonly string $status = "online"
    ) {}

    public function isFull(): bool   { return $this->online >= $this->maxPlayers; }
    public function isOnline(): bool { return $this->status === "online"; }

    public function getStatusIcon(): string {
        if (!$this->isOnline()) return "§c●";
        if ($this->isFull())    return "§e●";
        return "§a●";
    }

    public function getDisplayName(): string {
        return match($this->type) {
            "lobby"  => "§b§lLobby",
            "solo"   => "§e§lSolo",
            "double" => "§a§lDoubles",
            "triple" => "§d§lTriples",
            "squad"  => "§6§lSquads",
            default  => "§f" . ucfirst($this->type),
        };
    }
}
