<?php
declare(strict_types=1);

namespace {
    // Mock WP functionality.
    function get_transient($key) {
        global $test;
        return $test->transients[$key] ?? false;
    }

    function set_transient($key, $value, $expiration) {
        global $test;
        $test->transients[$key] = $value;
        return true;
    }

    function delete_transient($key) {
        global $test;
        unset($test->transients[$key]);
    }

    function absint($maybeint) {
        return abs((int) $maybeint);
    }

    define('HOUR_IN_SECONDS', 60 * 60);
}
