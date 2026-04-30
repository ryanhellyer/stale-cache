<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache\Tests;

use RyanHellyer\StaleCache\CacheStore;

class InMemoryCacheStore implements CacheStore
{
    /** @var array<string, mixed> */
    private array $storage = [];

    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        $this->storage[$key] = $value;
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->storage;
    }
}
