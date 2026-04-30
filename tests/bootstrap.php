<?php

declare(strict_types=1);

namespace {
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
