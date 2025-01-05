# stale-cache
Stale Cache for WordPress.
Allows for caching chunks of data, but never requiring end-users to wait for cache refreshes to take place. Instead, once the cache becomes stale, the cached version will be served while the cache is refreshed seamlessly in the background.

This is very loosely based on the new `Cache::flexible()` functionality in Laravel.

## Usage
`StaleCache::get('some-key', [5, 30], function() {return getSomethingExpensive();})`
Where `5` is the cache stale time and 30 is the cache duration (in seconds).
