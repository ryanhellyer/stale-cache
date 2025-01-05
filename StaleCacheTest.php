<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class StaleCacheTest extends TestCase
{
    private $transients = [];
    private $scheduledActions = [];

    protected function setUp(): void
    {
        $this->transients = [];
        $this->scheduledActions = [];
    }

    public function testFreshCacheReturn()
    {
        $key = 'test_key';
        $data = 'test_data';
        $times = [5, 10]; // 5 s stale, 10 s cache

        // Set up fresh cache.
        $this->transients[$key] = $data;
        $this->transients[$key . '_stale_time'] = time() + $times[0];

        $result = StaleCache::get($key, $times, function() {sleep(2)});

        $this->assertEquals($data . '<br>Served via cache', $result);
    }
}

// wp-mock-functions.php
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

function add_action($hook, $callback) {
    global $test;
    $test->scheduledActions[$hook] = $callback;
}

function fastcgi_finish_request() {
    return true;
}
