<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache\Tests;

use PHPUnit\Framework\TestCase;
use RyanHellyer\StaleCache\StaleCache;

class StaleCacheTest extends TestCase
{
    private const TEST_KEY = 'test_key';
    private const TEST_DATA = 'test_data';

    protected function setUp(): void
    {
        global $test;
        $test = new \stdClass();
        $test->transients = [];
    }

    public function testFreshCacheReturn(): void
    {
        global $test;

        $test->transients = [];

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

    public function testStaleCacheReturn(): void
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() - 1;
        $test->transients[self::TEST_KEY . '_refresh_lock'] = time() + HOUR_IN_SECONDS;

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

    public function testStaleCacheDoubleReturn(): void
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

    public function testExpiredCacheReturn(): void
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() - 56;
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
