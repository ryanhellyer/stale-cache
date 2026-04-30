<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache\Tests;

use PHPUnit\Framework\TestCase;
use RyanHellyer\StaleCache\StaleCache;

class StaleCacheTest extends TestCase
{
    private const TEST_KEY = 'test_key';
    private const TEST_DATA = 'test_data';
    private InMemoryCacheStore $store;
    private InMemoryHookManager $hooks;

    protected function setUp(): void
    {
        $this->store = new InMemoryCacheStore();
        $this->hooks = new InMemoryHookManager();
    }

    /** @param array<int> $times */
    private function createCache(array $times): StaleCache
    {
        return new StaleCache(self::TEST_KEY, $times, $this->store, $this->hooks);
    }

    public function testCacheMissCallsCallbackAndCachesResult(): void
    {
        $result = $this->createCache([5, 10])->resolve(
            fn() => self::TEST_DATA
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertEquals(self::TEST_DATA, $this->store->get(self::TEST_KEY));
        $this->assertGreaterThan(time(), $this->store->get(self::TEST_KEY . '_stale_time'));
    }

    public function testFreshCacheReturnsDataWithoutCallingCallback(): void
    {
        $this->store->set(self::TEST_KEY, self::TEST_DATA, 0);
        $this->store->set(self::TEST_KEY . '_stale_time', time() + 100, 0);

        $callbackCalled = false;
        $result = $this->createCache([5, 10])->resolve(
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
        $this->store->set(self::TEST_KEY, self::TEST_DATA, 0);
        $this->store->set(self::TEST_KEY . '_stale_time', time() - 1, 0);
        $this->store->set(self::TEST_KEY . '_refresh_lock', true, 0);

        $result = $this->createCache([5, 10])->resolve(
            fn() => 'should_not_be_called'
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertEmpty($this->hooks->getShutdownCallbacks());
    }

    public function testStaleCacheWithoutLockTriggersBackgroundRefresh(): void
    {
        $this->store->set(self::TEST_KEY, self::TEST_DATA, 0);
        $this->store->set(self::TEST_KEY . '_stale_time', time() - 1, 0);

        $result = $this->createCache([5, 10, 60])->resolve(
            fn() => 'fresh_data'
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertTrue($this->store->get(self::TEST_KEY . '_refresh_lock'));
        $this->assertCount(1, $this->hooks->getShutdownCallbacks());

        ($this->hooks->getShutdownCallbacks()[0])();

        $this->assertEquals('fresh_data', $this->store->get(self::TEST_KEY));
        $this->assertArrayNotHasKey(self::TEST_KEY . '_refresh_lock', $this->store->toArray());
    }

    public function testStaleCacheDoubleReturn(): void
    {
        $this->store->set(self::TEST_KEY, self::TEST_DATA, 0);
        $this->store->set(self::TEST_KEY . '_stale_time', time() - 1, 0);
        $this->store->set(self::TEST_KEY . '_refresh_lock', true, 0);

        for ($i = 0; $i < 2; $i++) {
            $result = $this->createCache([5, 10])->resolve(
                fn() => self::TEST_DATA
            );

            $this->assertEquals(self::TEST_DATA, $result);
        }
    }

    public function testCallbackReturningZeroIsCached(): void
    {
        $result = $this->createCache([5, 10])->resolve(
            fn() => 0
        );

        $this->assertSame(0, $result);
        $this->assertSame(0, $this->store->get(self::TEST_KEY));
    }

    public function testCallbackReturningEmptyStringIsCached(): void
    {
        $result = $this->createCache([5, 10])->resolve(
            fn() => ''
        );

        $this->assertSame('', $result);
        $this->assertSame('', $this->store->get(self::TEST_KEY));
    }

    public function testCallbackReturningFalseIsNotCached(): void
    {
        $result = $this->createCache([5, 10])->resolve(
            fn() => false
        );

        $this->assertFalse($result);
        $this->assertArrayNotHasKey(self::TEST_KEY, $this->store->toArray());
    }

    public function testCallbackExceptionReturnsFalse(): void
    {
        $result = $this->createCache([5, 10])->resolve(
            fn() => throw new \RuntimeException('test error')
        );

        $this->assertFalse($result);
        $this->assertArrayNotHasKey(self::TEST_KEY, $this->store->toArray());
    }
}
