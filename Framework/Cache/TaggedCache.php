<?php

namespace Framework\Cache;

/**
 * Tagged Cache Wrapper
 * Allows grouping cache items by tags for bulk operations.
 */
class TaggedCache
{
    protected CacheInterface $cache;
    protected array $tags;

    public function __construct(CacheInterface $cache, array $tags)
    {
        $this->cache = $cache;
        $this->tags = $tags;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->tagKey($key), $default);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $taggedKey = $this->tagKey($key);
        $result = $this->cache->put($taggedKey, $value, $ttl);

        // Track tag membership for flush operations
        foreach ($this->tags as $tag) {
            $tagIndexKey = "__tag:{$tag}";
            $keys = $this->cache->get($tagIndexKey, []);
            $keys[] = $taggedKey;
            $this->cache->put($tagIndexKey, array_unique($keys), $ttl);
        }

        return $result;
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($this->tagKey($key));
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->tagKey($key));
    }

    public function flush(): bool
    {
        foreach ($this->tags as $tag) {
            $tagIndexKey = "__tag:{$tag}";
            $keys = $this->cache->get($tagIndexKey, []);
            foreach ($keys as $key) {
                $this->cache->forget($key);
            }
            $this->cache->forget($tagIndexKey);
        }
        return true;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->cache->increment($this->tagKey($key), $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->cache->decrement($this->tagKey($key), $value);
    }

    protected function tagKey(string $key): string
    {
        return implode(':', $this->tags) . ':' . $key;
    }
}
