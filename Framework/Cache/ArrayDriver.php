<?php

namespace Framework\Cache;

/**
 * Array Cache Driver
 * In-memory cache, useful for testing. Does not persist between requests.
 */
class ArrayDriver implements CacheInterface
{
    protected array $store = [];
    protected array $expires = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->store[$key];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->store[$key] = $value;
        if ($ttl > 0) {
            $this->expires[$key] = time() + $ttl;
        }
        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->store[$key], $this->expires[$key]);
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }
        if (isset($this->expires[$key]) && time() > $this->expires[$key]) {
            $this->forget($key);
            return false;
        }
        return true;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        if (!isset($this->store[$key]) || !is_numeric($this->store[$key])) {
            return false;
        }
        $this->store[$key] = (int) $this->store[$key] + $value;
        return (int) $this->store[$key];
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    public function flush(): bool
    {
        $this->store = [];
        $this->expires = [];
        return true;
    }

    public function many(array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }
        return $values;
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $ttl);
        }
        return true;
    }

    public function all(): array
    {
        return $this->store;
    }
}
