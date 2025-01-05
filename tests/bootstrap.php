<?php

declare(strict_types=1);

namespace {
    // Mock WP functionality.
    function get_transient(string $key): mixed
    {
        global $test;
        return $test->transients[$key] ?? false;
    }

    function set_transient(string $key, mixed $value, int $expiration): bool
    {
        global $test;
        $test->transients[$key] = $value;
        return true;
    }

    function delete_transient(string $key): void
    {
        global $test;
        unset($test->transients[$key]);
    }

    function absint(int $maybeint): int
    {
        return abs((int) $maybeint);
    }

    define('HOUR_IN_SECONDS', 60 * 60);
}
