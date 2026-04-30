<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache;

class StaleCache
{
    private const LOCK_SUFFIX = '_refresh_lock';
    private const STALE_SUFFIX = '_stale_time';
    private const DEFAULT_LOCK_DURATION = 3600;
    private string $key;
    private int $staleTime;
    private int $cacheDuration;
    private int $lockDuration;
    private CacheStore $store;
    private HookManager $hooks;

    /**
     * @param array<int> $times
     */
    public static function get(string $key, array $times, callable $callback): mixed
    {
        return (new self(
            $key,
            $times,
            new WordPressTransientStore(),
            new WordPressHookManager(),
        ))->resolve($callback);
    }

    /**
     * @param array<int> $times
     */
    public function __construct(string $key, array $times, CacheStore $store, ?HookManager $hooks = null)
    {
        $this->store = $store;
        $this->hooks = $hooks ?? new WordPressHookManager();
        $times = array_map('abs', $times);
        $settings = $times + [2 => self::DEFAULT_LOCK_DURATION];
        [$this->staleTime, $this->cacheDuration, $this->lockDuration] = $settings;
        $this->key = $key;
    }

    public function resolve(callable $callback): mixed
    {
        try {
            $data = $this->store->get($this->key);

            if ($data === null) {
                return $this->update($callback);
            }

            $staleAt = $this->store->get($this->key . self::STALE_SUFFIX);
            if ($staleAt >= time()) {
                return $data;
            }

            return $this->handleStaleCache($data, $callback);
        } catch (\Throwable $e) {
            error_log("StaleCache resolve failed for key {$this->key}: " . $e->getMessage());
            return false;
        }
    }

    private function handleStaleCache(mixed $data, callable $callback): mixed
    {
        $lockKey = $this->key . self::LOCK_SUFFIX;

        if (!$this->store->get($lockKey)) {
            $this->store->set($lockKey, true, $this->lockDuration);
            $this->scheduleRefresh($callback, $lockKey);
        }

        return $data;
    }

    private function scheduleRefresh(callable $callback, string $lockKey): void
    {
        $this->hooks->onShutdown(function () use ($callback, $lockKey): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            try {
                $this->update($callback);
            } catch (\Throwable $e) {
                error_log("StaleCache background refresh failed for key {$this->key}: " . $e->getMessage());
            }

            $this->store->delete($lockKey);
        });
    }

    private function update(callable $callback): mixed
    {
        $data = $callback();

        $this->store->set($this->key, $data, $this->cacheDuration);
        $this->store->set(
            $this->key . self::STALE_SUFFIX,
            time() + $this->staleTime,
            $this->cacheDuration
        );

        return $data;
    }
}
