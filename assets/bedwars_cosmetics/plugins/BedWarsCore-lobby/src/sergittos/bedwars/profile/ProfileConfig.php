<?php

declare(strict_types=1);

namespace sergittos\bedwars\profile;

use function array_key_exists;
use function array_map;
use function explode;
use function is_array;
use function is_bool;
use function is_numeric;

/** Small read-only wrapper around profile.yml (dotted paths, typed getters, safe defaults). */
final class ProfileConfig{

    /** @param array<string, mixed> $data */
    public function __construct(private array $data){}

    public function get(string $path, mixed $default = null): mixed{
        $node = $this->data;
        foreach(explode(".", $path) as $key){
            if(!is_array($node) || !array_key_exists($key, $node)){
                return $default;
            }
            $node = $node[$key];
        }
        return $node;
    }

    public function int(string $path, int $default): int{
        $v = $this->get($path, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function float(string $path, float $default): float{
        $v = $this->get($path, $default);
        return is_numeric($v) ? (float) $v : $default;
    }

    public function bool(string $path, bool $default): bool{
        $v = $this->get($path, $default);
        return is_bool($v) ? $v : $default;
    }

    /**
     * @param list<int> $default
     * @return list<int>
     */
    public function intList(string $path, array $default): array{
        $v = $this->get($path, $default);
        if(!is_array($v) || $v === []){
            return $default;
        }
        return array_map(static fn($x) => is_numeric($x) ? (int) $x : 0, array_values($v));
    }
}
