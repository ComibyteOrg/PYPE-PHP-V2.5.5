<?php

namespace Framework\Cache;

/**
 * Redis Cache Driver
 * Uses phpredis extension for caching.
 */
class RedisDriver implements CacheInterface
{
    protected \Redis $redis;
    protected string $prefix;

    public function __construct(?string $host = null, ?int $port = null, ?string $prefix = null)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not installed.');
        }

        $this->redis = new \Redis();
        $host = $host ?? env('REDIS_HOST', '127.0.0.1');
        $port = $port ?? (int) env('REDIS_PORT', 6379);

        if (!$this->redis->connect($host, $port)) {
            throw new \RuntimeException("Failed to connect to Redis at {$host}:{$port}");
        }

        $password = env('REDIS_PASSWORD', null);
        if ($password) {
            $this->redis->auth($password);
        }

        $this->prefix = $prefix ?? env('REDIS_PREFIX', 'pype:');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        if ($value === false) {
            return $default;
        }
        return unserialize($value);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $serialized = serialize($value);
        if ($ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }
        return $this->redis->set($this->prefix . $key, $serialized);
    }

    public function forget(string $key): bool
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $result = $this->redis->incrBy($this->prefix . $key, $value);
        return $result !== false ? $result : false;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $result = $this->redis->decrBy($this->prefix . $key, $value);
        return $result !== false ? $result : false;
    }

    public function flush(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
        return true;
    }

    public function many(array $keys): array
    {
        $prefixed = array_map(fn($k) => $this->prefix . $k, $keys);
        $values = $this->redis->mGet($prefixed);
        $result = [];
        foreach ($keys as $i => $key) {
            $result[$key] = $values[$i] !== false ? unserialize($values[$i]) : null;
        }
        return $result;
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->put($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function redis(): \Redis
    {
        return $this->redis;
    }
}
