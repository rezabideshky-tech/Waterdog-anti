<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan\application;

final class ClanApplication{

    public function __construct(
        public readonly string $id,
        public readonly string $clanId,
        public readonly string $username,
        public readonly string $message,
        public readonly string $status,
        public readonly string $reason,
        public readonly int $createdAt
    ){}

    public static function fromRow(array $row): self{
        return new self(
            (string) $row["id"],
            (string) $row["clan_id"],
            (string) $row["username"],
            (string) ($row["message"] ?? ""),
            (string) ($row["status"] ?? "pending"),
            (string) ($row["reason"] ?? ""),
            (int) ($row["created_at"] ?? time())
        );
    }
}
