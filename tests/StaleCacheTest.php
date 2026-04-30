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
        $test->actions = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        global $test;
        $test = null;
    }

    public function testCacheMissCallsCallbackAndCachesResult(): void
    {
        global $test;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            fn() => self::TEST_DATA
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertEquals(self::TEST_DATA, $test->transients[self::TEST_KEY]);
        $this->assertGreaterThan(time(), $test->transients[self::TEST_KEY . '_stale_time']);
    }

    public function testFreshCacheReturnsDataWithoutCallingCallback(): void
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() + 100;

        $callbackCalled = false;
        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            function () use (&$callbackCalled) {
                $callbackCalled = true;
                return 'should_not_be_called';
            }
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertFalse($callbackCalled);
    }

    public function testStaleCacheWithLockReturnsStaleDataWithoutRefresh(): void
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() - 1;
        $test->transients[self::TEST_KEY . '_refresh_lock'] = true;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            fn() => 'should_not_be_called'
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertEmpty($test->actions);
    }

    public function testStaleCacheWithoutLockTriggersBackgroundRefresh(): void
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() - 1;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10, 60],
            fn() => 'fresh_data'
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertTrue($test->transients[self::TEST_KEY . '_refresh_lock']);
        $this->assertCount(1, $test->actions['shutdown']);

        ($test->actions['shutdown'][0])();

        $this->assertEquals('fresh_data', $test->transients[self::TEST_KEY]);
        $this->assertArrayNotHasKey(self::TEST_KEY . '_refresh_lock', $test->transients);
    }

    public function testStaleCacheDoubleReturn(): void
    {
        global $test;

        $test->transients[self::TEST_KEY] = self::TEST_DATA;
        $test->transients[self::TEST_KEY . '_stale_time'] = time() - 1;
        $test->transients[self::TEST_KEY . '_refresh_lock'] = true;

        for ($i = 0; $i < 2; $i++) {
            $result = StaleCache::get(
                self::TEST_KEY,
                [5, 10],
                fn() => self::TEST_DATA
            );

            $this->assertEquals(self::TEST_DATA, $result);
        }
    }

    public function testCallbackReturningZeroIsCached(): void
    {
        global $test;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            fn() => 0
        );

        $this->assertSame(0, $result);
        $this->assertSame(0, $test->transients[self::TEST_KEY]);
    }

    public function testCallbackReturningEmptyStringIsCached(): void
    {
        global $test;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            fn() => ''
        );

        $this->assertSame('', $result);
        $this->assertSame('', $test->transients[self::TEST_KEY]);
    }

    public function testCallbackReturningFalseIsNotCached(): void
    {
        global $test;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            fn() => false
        );

        $this->assertFalse($result);
        $this->assertArrayNotHasKey(self::TEST_KEY, $test->transients);
    }

    public function testCallbackExceptionReturnsFalse(): void
    {
        global $test;

        $result = StaleCache::get(
            self::TEST_KEY,
            [5, 10],
            fn() => throw new \RuntimeException('test error')
        );

        $this->assertFalse($result);
        $this->assertArrayNotHasKey(self::TEST_KEY, $test->transients);
    }
}