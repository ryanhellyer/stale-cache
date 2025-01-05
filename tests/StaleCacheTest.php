<?php

declare(strict_types=1);

require(dirname(__DIR__) . '/StaleCache.php');

use PHPUnit\Framework\TestCase;

class StaleCacheTest extends TestCase
{
    protected function setUp(): void
    {
        global $test;
        $test = new stdClass();
        $test->transients = [];
    }

    public function testFreshCacheReturn()
    {
        global $test;

        $key = 'test_key';
        $data = 'test_data';
        $times = [5, 10]; // 5 s stale, 10 s cache

        // Set up fresh cache.
        $test->transients[$key] = $data;
        $test->transients[$key . '_stale_time'] = time() + $times[0];

        $result = StaleCache::get(
            $key,
            $times,
            function() use ($data) {
                sleep(2);
                return $data;
            }
        );

        $this->assertEquals($data, $result);
    }
}


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

function add_action($hook, $callback) {
    global $test;
    $test->scheduledActions[$hook] = $callback;
}

function fastcgi_finish_request() {
    return true;
}

define('HOUR_IN_SECONDS', 60 * 60);
