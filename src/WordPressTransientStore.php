<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache;

class WordPressTransientStore implements CacheStore
{
    public function get(string $key): mixed
    {
        $value = get_transient($key); // @phpstan-ignore-line
        return $value === false ? null : $value;
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        return set_transient($key, $value, $ttl); // @phpstan-ignore-line
    }

    public function delete(string $key): bool
    {
        return delete_transient($key); // @phpstan-ignore-line
    }
}
