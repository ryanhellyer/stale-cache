<?php

declare(strict_types=1);

namespace RyanHellyer\StaleCache;

class StaleCache
{
    private const LOCK_SUFFIX = '_refresh_lock';
    private const STALE_SUFFIX = '_stale_time';
    private string $key;
    private int $staleTime;
    private int $cacheDuration;
    private int $lockDuration;
    private CacheStore $store;

    /**
     * @param array<int> $times
     */
    public static function get(string $key, array $times, callable $callback): mixed
    {
        return (new self($key, $times, new WordPressTransientStore()))->resolve($callback);
    }

    /**
     * @param array<int> $times
     */
    public function __construct(string $key, array $times, CacheStore $store)
    {
        $this->store = $store;
        $times = array_map('absint', $times);
        $settings = $times + [2 => HOUR_IN_SECONDS];
        [$this->staleTime, $this->cacheDuration, $this->lockDuration] = $settings;
        $this->key = $key;
    }

    private function resolve(callable $callback): mixed
    {
        $data = $this->store->get($this->key);

        if ($data === false) {
            return $this->update($callback);
        }

        $staleAt = $this->store->get($this->key . self::STALE_SUFFIX);
        if ($staleAt >= time()) {
            return $data;
        }

        return $this->handleStaleCache($data, $callback);
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
        add_action('shutdown', function () use ($callback, $lockKey): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $this->update($callback);
            $this->store->delete($lockKey);
        });
    }

    private function update(callable $callback): mixed
    {
        try {
            $data = $callback();

            if ($data === false) {
                return false;
            }

            $this->store->set($this->key, $data, $this->cacheDuration);
            $this->store->set(
                $this->key . self::STALE_SUFFIX,
                time() + $this->staleTime,
                $this->cacheDuration
            );

            return $data;
        } catch (\Throwable $e) {
            error_log("StaleCache update failed for key {$this->key}: " . $e->getMessage());
            return false;
        }
    }
}
