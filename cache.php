<?php

declare(strict_types=1);

final class StaleCache {
    private const LOCK_SUFFIX = '_refresh_lock';
    private const STALE_SUFFIX = '_stale_time';
    private const LOCK_DURATION = HOUR_IN_SECONDS;
    private static $staleTime;
    private static $cacheDuration;

    public static function get(string $key, array $times, callable $callback): string|false {
        [static::$staleTime, static::$cacheDuration] = $times;

        $data = get_transient($key);
        if ($data === false) {
            return self::update($key, $callback);
        }

        $staleTime = get_transient($key . self::STALE_SUFFIX);
        if ($staleTime >= time()) {
            return $data . '<br>Served via cache';
        }

        return self::handleStaleCache($key, $data, $callback);
    }

    private static function handleStaleCache(string $key, string $data, callable $callback): string {
        $lockKey = $key . self::LOCK_SUFFIX;

        if (!get_transient($lockKey)) {
            set_transient($lockKey, true, self::LOCK_DURATION);
            self::scheduleRefresh($key, $callback, $lockKey);
        }

        return $data . '<br>Served as stale cache';
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
    }
}