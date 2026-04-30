<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache;

interface CacheStore
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl): bool;

    public function delete(string $key): bool;
}
