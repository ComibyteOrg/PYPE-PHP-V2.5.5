<?php

namespace Framework\Cache;

/**
 * Memcached Cache Driver
 * Uses php-memcached extension for caching.
 */
class MemcachedDriver implements CacheInterface
{
    protected \Memcached $memcached;

    public function __construct(?string $host = null, ?int $port = null)
    {
        if (!extension_loaded('memcached')) {
            throw new \RuntimeException('Memcached extension is not installed.');
        }

        $this->memcached = new \Memcached();
        $host = $host ?? env('MEMCACHED_HOST', '127.0.0.1');
        $port = $port ?? (int) env('MEMCACHED_PORT', 11211);

        if (!$this->memcached->addServer($host, $port)) {
            throw new \RuntimeException("Failed to connect to Memcached at {$host}:{$port}");
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return $default;
        }
        return $value;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->memcached->set($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->memcached->delete($key);
    }

    public function has(string $key): bool
    {
        $this->memcached->get($key);
        return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $result = $this->memcached->increment($key, $value);
        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            $this->put($key, $value);
            return $value;
        }
        return $result !== false ? $result : false;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->memcached->decrement($key, $value);
    }

    public function flush(): bool
    {
        return $this->memcached->flush();
    }

    public function many(array $keys): array
    {
        $values = $this->memcached->getMulti($keys);
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $values[$key] ?? null;
        }
        return $result;
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        return $this->memcached->setMulti($values, $ttl);
    }

    public function memcached(): \Memcached
    {
        return $this->memcached;
    }
}
