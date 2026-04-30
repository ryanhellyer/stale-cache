# StaleCache

A PHP implementation of the **stale-while-revalidate** caching pattern for WordPress.

Serve stale content instantly while asynchronously refreshing the cache — zero wait time for your users.

## Overview

StaleCache brings the **stale-while-revalidate** caching strategy to WordPress, heavily inspired by Laravel's `Cache::flexible()`. When cached data expires, the library serves the existing (stale) content immediately while triggering a background refresh — eliminating the performance penalty of synchronous cache regeneration.

This pattern is especially valuable for expensive operations like API calls, complex database queries, or rendered template fragments where you cannot afford to block a request.

---

## Features

- **Stale-While-Revalidate** — Serve stale content instantly; refresh the cache asynchronously in the background.
- **Cache Stampede Prevention** — Atomic locking ensures only one process regenerates the cache at a time.
- **Async Refresh** — Leverages `fastcgi_finish_request()` to flush the response to the client before the cache update runs (PHP-FPM required).
- **Pluggable Architecture** — `CacheStore` and `HookManager` interfaces let you swap out the storage backend or hook system.
- **WordPress-Native** — Ships with `WordPressTransientStore` and `WordPressHookManager` for drop-in WordPress compatibility.
- **Type-Safe** — Written in strict PHP 8.2+ with full type declarations.
- **Tested** — Comprehensive test suite with PHPUnit.

---

## Installation

```bash
composer require ryanhellyer/stale-cache
```

Requires **PHP 8.2+**.

---

## Quick Start

```php
use RyanHellyer\StaleCache\StaleCache;

$data = StaleCache::get(
    'my_cache_key',
    [5, 3600],      // [stale_time, cache_duration, lock_duration (optional)]
    function () {
        return get_expensive_data();
    }
);
```

That's it. The first request populates the cache; subsequent requests within the stale window serve the cached value; once expired, stale content is served while the callback runs off the critical path.

---

## How It Works

```
 Request
    │
    ├── Cache hit & fresh?   ───→ Return cached data (instant)
    │
    ├── Cache hit & stale?   ───→ Return stale data + trigger
    │                              background refresh
    │
    └── Cache miss?          ───→ Run callback, store result,
                                  return new data (synchronous)
```

The cache transitions through three lifecycle states:

| State | Behaviour |
|---|---|
| **Fresh** | Content is served directly from the cache. No overhead. |
| **Stale** | Content is served from the cache. A shutdown hook acquires a lock and asynchronously re-executes the callback. |
| **Missing** | No cached value exists. The callback runs synchronously, the result is stored, and the stale timestamp is set. |

### Locking

When the cache enters the **stale** state, the first process to encounter it acquires a **refresh lock** (stored alongside the cache). Subsequent concurrent requests see the lock and serve stale content without attempting to regenerate — preventing the classic **cache stampede**.

---

## API Reference

### `StaleCache::get(string $key, array $times, callable $callback): mixed`

Static facade for the simplest use case. Internally instantiates the class with WordPress defaults.

| Parameter | Type | Description |
|---|---|---|
| `$key` | `string` | Unique cache key |
| `$times` | `array<int>` | `[stale_time, cache_duration, lock_duration?]` (in seconds) |
| `$callback` | `callable` | The expensive operation to execute and cache |

### `StaleCache::__construct(string $key, array $times, CacheStore $store, ?HookManager $hooks = null)`

Dependency-injectable constructor for custom backends.

### `StaleCache::resolve(callable $callback): mixed`

Orchestrates the full resolve cycle. Called automatically by `::get()`.

### Interfaces

#### `CacheStore`

```php
interface CacheStore
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl): bool;
    public function delete(string $key): bool;
}
```

#### `HookManager`

```php
interface HookManager
{
    public function onShutdown(callable $callback): void;
}
```

### Included Implementations

| Class | Implements | Description |
|---|---|---|
| `WordPressTransientStore` | `CacheStore` | Stores values via `set_transient()` / `get_transient()` |
| `WordPressHookManager` | `HookManager` | Registers shutdown callbacks via `add_action('shutdown', ...)` |

---

## Configuration

The `$times` array accepts up to three integers:

```php
[stale_time, cache_duration, lock_duration]
```

| Parameter | Default | Description |
|---|---|---|
| `stale_time` | **required** | Seconds the cache is considered fresh |
| `cache_duration` | **required** | Total TTL for the cached value |
| `lock_duration` | `3600` (1 hour) | How long the refresh lock is held |

> **Tip:** Set `lock_duration` high enough to cover the worst-case execution time of your callback. The lock is deleted automatically after the refresh completes.

---

## Performance

- **Zero-blocking reads** — Stale responses are served instantly while the refresh runs asynchronously after the response is flushed.
- **No cache stampede** — The distributed lock mechanism guarantees at most one concurrent regeneration.
- **Shutdown-based refresh** — By hooking into PHP's shutdown sequence (and calling `fastcgi_finish_request()` where available), the client receives the response before the expensive callback executes.

---

## Development

### Requirements

- PHP 8.2+
- Composer

### Setup

```bash
composer install
```

### Scripts

| Command | Description |
|---|---|
| `composer test` | Run the PHPUnit test suite |
| `composer phpcs` | Check PSR-12 coding standards |
| `composer phpcs-fix` | Auto-fix coding standards violations |
| `composer phpstan` | Run static analysis (Level 8) |

### Static Analysis

This project enforces **PHPStan Level 8** — the strictest level — ensuring complete type safety across the entire codebase.

---

## Contributing

Contributions are welcome. Please ensure your changes:

1. Pass all existing tests (`composer test`)
2. Meet PSR-12 coding standards (`composer phpcs`)
3. Pass PHPStan Level 8 (`composer phpstan`)

Submit a pull request and include a clear description of the change and any relevant issue numbers.

---

## License

This project is open-sourced software licensed under the [GPL v2](LICENSE) license.
