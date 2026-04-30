<?php

declare(strict_types=1);

namespace {
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

    function delete_transient(string $key): bool
    {
        global $test;
        unset($test->transients[$key]);
        return true;
    }

    function absint(int $maybeint): int
    {
        return abs((int) $maybeint);
    }

    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        global $test;
        if (!isset($test->actions)) {
            $test->actions = [];
        }
        $test->actions[$hook][] = $callback;
        return true;
    }

    define('HOUR_IN_SECONDS', 60 * 60);
}
