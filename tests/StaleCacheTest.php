<?php

declare(strict_types=1);

require(dirname(__DIR__) . '/StaleCache.php');

use PHPUnit\Framework\TestCase;

class StaleCacheTest extends TestCase
{
    const TEST_KEY = 'test_key';
    const TEST_DATA = 'test_data';

    protected function setUp(): void
    {
        global $test;
        $test = new stdClass();
        $test->transients = [];
    }

    public function testFreshCacheReturn()
    {
        global $test;

        $test->transients = [];

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            function() {
                sleep(1);
                return self::TEST_DATA;
            }
        );

        $this->assertEquals(self::TEST_DATA, $result);
    }

    public function testStaleCacheReturn()
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() - 1;
        $test->transients[self::TEST_KEY . '_refresh_lock'] = time() + HOUR_IN_SECONDS;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            function() {
                sleep(1);
                return self::TEST_DATA;
            }
        );

        $this->assertEquals(self::TEST_DATA, $result);
    }

    public function testStaleCacheDoubleReturn()
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() - 1;
        $test->transients[self::TEST_KEY . '_refresh_lock'] = time() + HOUR_IN_SECONDS;

        for ($i = 0; $i < 2; $i++) {
            $result = StaleCache::get(
                self::TEST_KEY,
                [5, 10],
                function () {
                    sleep(1);
                    return self::TEST_DATA;
                }
            );

            $this->assertEquals(self::TEST_DATA, $result);
        }
    }

    public function testExpiredCacheReturn()
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() -56;
        $test->transients[self::TEST_KEY . '_refresh_lock'] = time() - 1;

        for ($i = 0; $i < 2; $i++) {
            $result = StaleCache::get(
                self::TEST_KEY,
                [5, 10, 60],
                function () {
                    sleep(1);
                    return self::TEST_DATA;
                }
            );

            $this->assertEquals(self::TEST_DATA, $result);
        }
    }
}


// Mock WP and PHP FPM functionality.
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

define('HOUR_IN_SECONDS', 60 * 60);
