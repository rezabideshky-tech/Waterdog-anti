<?php

declare(strict_types=1);

namespace sergittos\bedwars\clan\request;

use sergittos\bedwars\clan\ClanCrestRegistry;

final class ClanCreationRequest{

    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $name,
        public readonly string $tag,
        public readonly string $color,
        public readonly string $description,
        public readonly string $crestId,
        public readonly string $visibility,
        public readonly int $requiredLevel,
        public readonly int $cost,
        public readonly string $status,
        public readonly string $reason,
        public readonly int $createdAt
    ){}

    public static function fromRow(array $row): self{
        return new self(
            (string) $row["id"],
            (string) $row["username"],
            (string) $row["name"],
            (string) $row["tag"],
            (string) ($row["color"] ?? "WHITE"),
            (string) ($row["description"] ?? ""),
            (string) ($row["crest_id"] ?? ClanCrestRegistry::getDefault()),
            (string) ($row["visibility"] ?? "PUBLIC"),
            (int) ($row["required_level"] ?? 1),
            (int) ($row["cost"] ?? 0),
            (string) ($row["status"] ?? "pending"),
            (string) ($row["reason"] ?? ""),
            (int) ($row["created_at"] ?? time())
        );
    }
}
