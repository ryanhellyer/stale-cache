<?php

declare(strict_types=1);

class StaleCache {
    private const LOCK_SUFFIX = '_refresh_lock';
    private const STALE_SUFFIX = '_stale_time';
    private static $lockDuration;
    private static $staleTime;
    private static $cacheDuration;

    public static function get(string $key, array $times, callable $callback): string|false {
        $times = array_map(fn($value) => absint($value), $times);
        [static::$staleTime, static::$cacheDuration, static::$lockDuration] = $times + [2 => HOUR_IN_SECONDS];

        $data = get_transient($key);

        if ($data === false) {
            return self::update($key, $callback);
        }

        $staleTime = get_transient($key . self::STALE_SUFFIX);
        if ($staleTime >= time()) {
            return $data;
        }

        return self::handleStaleCache($key, $data, $callback);
    }

    private static function handleStaleCache(string $key, string $data, callable $callback): string {
        $lockKey = $key . self::LOCK_SUFFIX;

        if (!get_transient($lockKey)) {
            set_transient($lockKey, true, static::$lockDuration);
            self::scheduleRefresh($key, $callback, $lockKey);
        }

        return $data;
    }

    private static function scheduleRefresh(string $key, callable $callback, string $lockKey): void {
        add_action('shutdown', function() use ($key, $callback, $lockKey) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            self::update($key, $callback);
            delete_transient($lockKey);
        });
    }

    private static function update(string $key, callable $callback): string|false {
        try {
            $data = $callback ? $callback() : false;

            if (!$data) {
                return false;
            }

            set_transient($key, $data, static::$cacheDuration);
            set_transient(
                $key . self::STALE_SUFFIX,
                time() + static::$staleTime,
                static::$cacheDuration
            );

            return $data;
        } catch (\Throwable $e) {
            error_log("StaleCache update failed for key {$key}: " . $e->getMessage());
            return false;
        }
    }
}
