<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache\Tests;

use PHPUnit\Framework\TestCase;
use RyanHellyer\StaleCache\StaleCache;

class StaleCacheTest extends TestCase
{
    private const TEST_KEY = 'test_key';
    private const TEST_DATA = 'test_data';
    private const STALE_TIME_KEY = 'test_key_stale_time';
    private const LOCK_KEY = 'test_key_refresh_lock';
    private const STALE_SECONDS = 5;
    private const CACHE_TTL_SECONDS = 10;
    private const LOCK_TTL_SECONDS = 60;

    private InMemoryCacheStore $store;
    private InMemoryHookManager $hooks;

    protected function setUp(): void
    {
        $this->store = new InMemoryCacheStore();
        $this->hooks = new InMemoryHookManager();
    }

    /** @param array<int>|null $times */
    private function createCache(?array $times = null): StaleCache
    {
        return new StaleCache(
            self::TEST_KEY,
            $times ?? [self::STALE_SECONDS, self::CACHE_TTL_SECONDS],
            $this->store,
            $this->hooks
        );
    }

    private function seedFreshCache(): void
    {
        $this->store->setForever(self::TEST_KEY, self::TEST_DATA);
        $this->store->setForever(self::STALE_TIME_KEY, time() + 100);
    }

    private function seedStaleCache(): void
    {
        $this->store->setForever(self::TEST_KEY, self::TEST_DATA);
        $this->store->setForever(self::STALE_TIME_KEY, time() - 1);
    }

    private function seedStaleCacheWithLock(): void
    {
        $this->seedStaleCache();
        $this->store->setForever(self::LOCK_KEY, true);
    }

    public function testCacheMissCallsCallbackAndCachesResult(): void
    {
        $result = $this->createCache()->resolve(
            fn() => self::TEST_DATA
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertEquals(self::TEST_DATA, $this->store->get(self::TEST_KEY));
        $this->assertGreaterThan(time(), $this->store->get(self::STALE_TIME_KEY));
    }

    public function testFreshCacheReturnsDataWithoutCallingCallback(): void
    {
        $this->seedFreshCache();

        $callbackCalled = false;
        $result = $this->createCache()->resolve(
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
        $this->seedStaleCacheWithLock();

        $result = $this->createCache()->resolve(
            fn() => 'should_not_be_called'
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertEmpty($this->hooks->getShutdownCallbacks());
    }

    public function testStaleCacheWithoutLockTriggersBackgroundRefresh(): void
    {
        $this->seedStaleCache();

        $result = $this->createCache([self::STALE_SECONDS, self::CACHE_TTL_SECONDS, self::LOCK_TTL_SECONDS])->resolve(
            fn() => 'fresh_data'
        );

        $this->assertEquals(self::TEST_DATA, $result);
        $this->assertTrue($this->store->get(self::LOCK_KEY));
        $this->assertCount(1, $this->hooks->getShutdownCallbacks());

        ($this->hooks->getShutdownCallbacks()[0])();

        $this->assertEquals('fresh_data', $this->store->get(self::TEST_KEY));
        $this->assertArrayNotHasKey(self::LOCK_KEY, $this->store->toArray());
    }

    public function testStaleCacheDoubleReturn(): void
    {
        $this->seedStaleCacheWithLock();

        for ($i = 0; $i < 2; $i++) {
            $result = $this->createCache()->resolve(
                fn() => self::TEST_DATA
            );

            $this->assertEquals(self::TEST_DATA, $result);
        }
    }

    public function testCallbackReturningZeroIsCached(): void
    {
        $result = $this->createCache()->resolve(fn() => 0);

        $this->assertSame(0, $result);
        $this->assertSame(0, $this->store->get(self::TEST_KEY));
    }

    public function testCallbackReturningEmptyStringIsCached(): void
    {
        $result = $this->createCache()->resolve(fn() => '');

        $this->assertSame('', $result);
        $this->assertSame('', $this->store->get(self::TEST_KEY));
    }

    public function testCallbackReturningFalseIsCached(): void
    {
        $result = $this->createCache()->resolve(fn() => false);

        $this->assertFalse($result);
        $this->assertArrayHasKey(self::TEST_KEY, $this->store->toArray());
        $this->assertFalse($this->store->get(self::TEST_KEY));
    }

    public function testCallbackExceptionReturnsFalse(): void
    {
        $result = $this->createCache()->resolve(
            fn() => throw new \RuntimeException('test error')
        );

        $this->assertFalse($result);
        $this->assertArrayNotHasKey(self::TEST_KEY, $this->store->toArray());
    }
}
