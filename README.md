# StaleCache

A PHP implementation of the stale-while-revalidate caching pattern for WordPress, designed to improve performance and reduce load on expensive operations.

## Features

- **Stale-While-Revalidate Pattern**: Serves stale content while asynchronously refreshing cache in the background
- **Race Condition Prevention**: Uses locking mechanism to prevent multiple simultaneous cache updates
- **WordPress Integration**: Built on WordPress transients for reliable cache storage
- **Async Updates**: Leverages FastCGI finish request for non-blocking cache updates (for when using PHP-FPM)
- **Type Safety**: Written in strict PHP with full type declarations

## Installation

```bash
composer require ryanhellyer/stale-cache
```

## Usage

Basic usage example:

\```php
$result = StaleCache::get(
    'my_cache_key',
    [
        5,      // Stale time in seconds
        3600,   // Cache duration in seconds
        60      // Lock duration in seconds (optional)
    ],
    function() {
        // Your expensive operation here
        return getExpensiveData();
    }
);
\```

### Configuration Parameters

- **Stale Time**: How long the cache is considered fresh (in seconds)
- **Cache Duration**: Total time to keep the cache (in seconds)
- **Lock Duration**: How long to hold the refresh lock (defaults to 1 hour)

### Cache States

The cache can be in one of three states:

1. **Fresh**: Content is served directly from cache
2. **Stale**: Content is served from cache while a background refresh is triggered
3. **Missing**: Content is generated synchronously and cached

### Performance Considerations

- Uses \`fastcgi_finish_request()\` when available for non-blocking updates
- Implements locking to prevent cache stampede
- Serves stale content rather than blocking on regeneration

## Development

### Code Style & Quality
This project follows PSR-12 coding standards and uses several tools to maintain code quality:

#### PHP CodeSniffer
Check coding standards:
```bash
composer phpcs
```

Automatically fix coding standards violations:
```bash
composer phpcs-fix
```

#### PHPStan
Run static analysis:
```bash
composer phpstan
```

The project uses PHPStan Level 8 (maximum) for strict type checking and analysis.

### Testing

Run the test suite:

```bash
composer test
```

## License

This project is licensed under the GPL v2 license.
