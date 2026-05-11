<?php

namespace Framework\Storage\Drivers;

use Framework\Storage\StorageDriverInterface;

class LocalDriver implements StorageDriverInterface
{
    private string $root;
    private ?string $url;

    public function __construct(array $config)
    {
        $this->root = rtrim($config['root'] ?? storage_path('uploads'), '/\\') . DIRECTORY_SEPARATOR;
        $this->url = $config['url'] ?? null;

        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    public function put(string $path, $contents, array $options = []): bool
    {
        $fullPath = $this->resolvePath($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $visibility = $options['visibility'] ?? 0644;

        if (is_resource($contents)) {
            $dest = fopen($fullPath, 'wb');
            stream_copy_to_stream($contents, $dest);
            fclose($dest);
        } else {
            file_put_contents($fullPath, $contents);
        }

        chmod($fullPath, $visibility);
        return true;
    }

    public function putFile(string $path, string $localPath, array $options = []): bool
    {
        if (!file_exists($localPath)) {
            return false;
        }

        $fullPath = $this->resolvePath($path);
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $visibility = $options['visibility'] ?? 0644;
        copy($localPath, $fullPath);
        chmod($fullPath, $visibility);

        return true;
    }

    public function get(string $path): string|false
    {
        $fullPath = $this->resolvePath($path);
        return file_exists($fullPath) ? file_get_contents($fullPath) : false;
    }

    public function stream(string $path): mixed
    {
        $fullPath = $this->resolvePath($path);
        return file_exists($fullPath) ? fopen($fullPath, 'rb') : false;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function size(string $path): int|false
    {
        $fullPath = $this->resolvePath($path);
        return file_exists($fullPath) ? filesize($fullPath) : false;
    }

    public function lastModified(string $path): int|false
    {
        $fullPath = $this->resolvePath($path);
        return file_exists($fullPath) ? filemtime($fullPath) : false;
    }

    public function url(string $path): string
    {
        if ($this->url !== null) {
            return rtrim($this->url, '/') . '/' . ltrim($path, '/');
        }

        $relativePath = str_replace($this->root, '', $this->resolvePath($path));
        $relativePath = str_replace('\\', '/', $relativePath);

        $baseUrl = rtrim($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http' . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/');

        if (str_starts_with($this->root, storage_path())) {
            return $baseUrl . '/storage/' . ltrim($relativePath, '/');
        }

        return $baseUrl . '/' . ltrim($relativePath, '/');
    }

    public function download(string $path, ?string $name = null): void
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            http_response_code(404);
            exit('File not found');
        }

        $name = $name ?: basename($path);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . mime_content_type($fullPath));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($fullPath);
        exit;
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_string($paths) ? [$paths] : $paths;

        foreach ($paths as $path) {
            $fullPath = $this->resolvePath($path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        return true;
    }

    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->resolvePath($path);
        return $this->removeDirectory($fullPath);
    }

    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->resolvePath($from);
        $toPath = $this->resolvePath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return copy($fromPath, $toPath);
    }

    public function move(string $from, string $to): bool
    {
        $fromPath = $this->resolvePath($from);
        $toPath = $this->resolvePath($to);

        if (!file_exists($fromPath)) {
            return false;
        }

        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return rename($fromPath, $toPath);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->resolvePath($directory);
        if (!is_dir($fullPath)) {
            return [];
        }

        return $this->getFiles($fullPath, $recursive, $directory);
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->files($directory, true);
    }

    public function directories(string $directory = ''): array
    {
        $fullPath = $this->resolvePath($directory);
        if (!is_dir($fullPath)) {
            return [];
        }

        $dirs = [];
        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $dirPath = $directory ? $directory . '/' . $item : $item;
                $dirs[] = $dirPath;
            }
        }

        return $dirs;
    }

    public function makeDirectory(string $path): bool
    {
        return mkdir($this->resolvePath($path), 0755, true);
    }

    public function temporaryUrl(string $path, int $expiration): string
    {
        $token = hash_hmac('sha256', $path . $expiration, env('APP_KEY', 'default-key'));
        return $this->url($path) . '?token=' . $token . '&expires=' . $expiration;
    }

    public function metadata(string $path): array
    {
        $fullPath = $this->resolvePath($path);
        if (!file_exists($fullPath)) {
            return [];
        }

        return [
            'path' => $path,
            'size' => filesize($fullPath),
            'mime' => mime_content_type($fullPath),
            'last_modified' => filemtime($fullPath),
            'extension' => pathinfo($fullPath, PATHINFO_EXTENSION),
            'basename' => basename($fullPath),
        ];
    }

    public function driver(): string
    {
        return 'local';
    }

    private function resolvePath(string $path): string
    {
        $path = ltrim($path, '/');
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        return $this->root . $path;
    }

    private function getFiles(string $directory, bool $recursive, string $prefix = ''): array
    {
        $files = [];
        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $directory . DIRECTORY_SEPARATOR . $item;
            $relativePath = $prefix ? $prefix . '/' . $item : $item;

            if (is_dir($itemPath) && $recursive) {
                $files = array_merge($files, $this->getFiles($itemPath, true, $relativePath));
            } elseif (is_file($itemPath)) {
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    private function removeDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        return rmdir($path);
    }
}
