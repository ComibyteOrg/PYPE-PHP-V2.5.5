<?php

namespace Framework\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $ttl = 3600): bool;
    public function forget(string $key): bool;
    public function has(string $key): bool;
    public function increment(string $key, int $value = 1): int|false;
    public function decrement(string $key, int $value = 1): int|false;
    public function flush(): bool;
    public function many(array $keys): array;
    public function putMany(array $values, int $ttl = 3600): bool;
}
