<?php

namespace Framework\Cache;

/**
 * Cache Facade
 * Unified caching interface with multiple drivers.
 *
 * Usage:
 * Cache::put('key', 'value', 3600);
 * $value = Cache::get('key', 'default');
 * Cache::forget('key');
 * Cache::remember('key', 3600, fn() => expensiveQuery());
 */
class Cache
{
    protected static ?CacheInterface $driver = null;

    public static function driver(?string $name = null): CacheInterface
    {
        if (self::$driver !== null && $name === null) {
            return self::$driver;
        }

        $driver = $name ?? env('CACHE_DRIVER', 'file');

        return match ($driver) {
            'redis' => new RedisDriver(),
            'memcached' => new MemcachedDriver(),
            'array' => new ArrayDriver(),
            default => new FileDriver(),
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::driver()->get($key, $default);
    }

    public static function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return self::driver()->put($key, $value, $ttl);
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return self::put($key, $value, $ttl);
    }

    public static function forget(string $key): bool
    {
        return self::driver()->forget($key);
    }

    public static function delete(string $key): bool
    {
        return self::forget($key);
    }

    public static function has(string $key): bool
    {
        return self::driver()->has($key);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        self::put($key, $value, $ttl);
        return $value;
    }

    public static function rememberForever(string $key, callable $callback): mixed
    {
        return self::remember($key, 0, $callback);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    public static function increment(string $key, int $value = 1): int|false
    {
        return self::driver()->increment($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int|false
    {
        return self::driver()->decrement($key, $value);
    }

    public static function flush(): bool
    {
        return self::driver()->flush();
    }

    public static function clear(): bool
    {
        return self::flush();
    }

    public static function many(array $keys): array
    {
        return self::driver()->many($keys);
    }

    public static function putMany(array $values, int $ttl = 3600): bool
    {
        return self::driver()->putMany($values, $ttl);
    }

    public static function add(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (self::has($key)) {
            return false;
        }
        return self::put($key, $value, $ttl);
    }

    public static function forever(string $key, mixed $value): bool
    {
        return self::put($key, $value, 0);
    }

    public static function tags(array $tags): TaggedCache
    {
        return new TaggedCache(self::driver(), $tags);
    }
}
