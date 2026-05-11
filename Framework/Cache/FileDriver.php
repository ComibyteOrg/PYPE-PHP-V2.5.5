<?php

namespace Framework\Cache;

/**
 * File Cache Driver
 * Stores cached items as serialized files on disk.
 */
class FileDriver implements CacheInterface
{
    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (defined('STORAGE_PATH') ? STORAGE_PATH . '/cache' : __DIR__ . '/../../Storage/cache');
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->filePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);
        if ($data === false) {
            return $default;
        }

        if (isset($data['expires']) && $data['expires'] > 0 && time() > $data['expires']) {
            $this->forget($key);
            return $default;
        }

        return $data['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $data = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];

        return file_put_contents($this->filePath($key), serialize($data), LOCK_EX) !== false;
    }

    public function forget(string $key): bool
    {
        $file = $this->filePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $current = $this->get($key, 0);
        if (!is_numeric($current)) {
            return false;
        }
        $new = (int) $current + $value;
        $this->put($key, $new);
        return $new;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    public function flush(): bool
    {
        $files = glob($this->path . '/cache_*');
        if ($files === false) {
            return true;
        }
        foreach ($files as $file) {
            unlink($file);
        }
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
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->put($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function garbageCollect(): void
    {
        $files = glob($this->path . '/cache_*');
        if ($files === false) return;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) continue;
            $data = unserialize($content);
            if (isset($data['expires']) && $data['expires'] > 0 && time() > $data['expires']) {
                unlink($file);
            }
        }
    }

    protected function filePath(string $key): string
    {
        return $this->path . '/cache_' . md5($key);
    }
}
