<?php

namespace Framework\Storage;

class Disk
{
    private static array $disks = [];
    private static string $defaultDisk = 'local';
    private static array $config = [];
    private static ?Disk $instance = null;
    private string $name;
    private StorageDriverInterface $driver;

    public static function configure(array $disks, string $default = 'local'): void
    {
        self::$disks = $disks;
        self::$defaultDisk = $default;
        self::$config = $disks;
    }

    public static function make(?string $name = null): self
    {
        $name = $name ?? self::$defaultDisk;

        if (!isset(self::$disks[$name])) {
            throw new \InvalidArgumentException("Storage disk '{$name}' is not configured.");
        }

        $disk = new self();
        $disk->name = $name;
        $disk->driver = self::resolveDriver($name, self::$disks[$name]);

        return $disk;
    }

    public static function local(): self
    {
        return self::make('local');
    }

    public static function s3(): self
    {
        return self::make('s3');
    }

    public static function cloudinary(): self
    {
        return self::make('cloudinary');
    }

    public static function gcs(): self
    {
        return self::make('gcs');
    }

    public static function azure(): self
    {
        return self::make('azure');
    }

    public static function get(string $name): self
    {
        return self::make($name);
    }

    public function put(string $path, $contents, array $options = []): bool
    {
        return $this->driver->put($path, $contents, $options);
    }

    public function putFile(string $path, string $localPath, array $options = []): bool
    {
        return $this->driver->putFile($path, $localPath, $options);
    }

    public function putFileAs(string $path, $file, ?string $name = null, array $options = []): string|false
    {
        if (is_array($file) && isset($file['tmp_name'])) {
            $extension = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
            $name = $name ?: uniqid() . ($extension ? '.' . $extension : '');
            $destination = rtrim($path, '/') . '/' . $name;
            $success = $this->driver->putFile($destination, $file['tmp_name'], $options);
            return $success ? $destination : false;
        }

        if (is_string($file)) {
            $name = $name ?: basename($file);
            $destination = rtrim($path, '/') . '/' . $name;
            $success = $this->driver->putFile($destination, $file, $options);
            return $success ? $destination : false;
        }

        return false;
    }

    public function get(string $path): string|false
    {
        return $this->driver->get($path);
    }

    public function stream(string $path, $output): bool|int
    {
        $input = $this->driver->stream($path);
        if ($input === false) {
            return false;
        }
        return stream_copy_to_stream($input, $output);
    }

    public function exists(string $path): bool
    {
        return $this->driver->exists($path);
    }

    public function missing(string $path): bool
    {
        return $this->driver->missing($path);
    }

    public function size(string $path): int|false
    {
        return $this->driver->size($path);
    }

    public function lastModified(string $path): int|false
    {
        return $this->driver->lastModified($path);
    }

    public function url(string $path): string
    {
        return $this->driver->url($path);
    }

    public function download(string $path, ?string $name = null): void
    {
        $this->driver->download($path, $name);
    }

    public function delete(string|array $paths): bool
    {
        return $this->driver->delete($paths);
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->driver->deleteDirectory($path);
    }

    public function copy(string $from, string $to): bool
    {
        return $this->driver->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->driver->move($from, $to);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        return $this->driver->files($directory, $recursive);
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->driver->allFiles($directory);
    }

    public function directories(string $directory = ''): array
    {
        return $this->driver->directories($directory);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->driver->makeDirectory($path);
    }

    public function temporaryUrl(string $path, int $expiration = 3600): string
    {
        return $this->driver->temporaryUrl($path, time() + $expiration);
    }

    public function temporaryUrlAt(string $path, \DateTime|int $expiration): string
    {
        $timestamp = $expiration instanceof \DateTime ? $expiration->getTimestamp() : $expiration;
        return $this->driver->temporaryUrl($path, $timestamp);
    }

    public function metadata(string $path): array
    {
        return $this->driver->metadata($path);
    }

    public function driver(): string
    {
        return $this->driver->driver();
    }

    public function driverInstance(): StorageDriverInterface
    {
        return $this->driver;
    }

    public function name(): string
    {
        return $this->name;
    }

    private static function resolveDriver(string $name, array $config): StorageDriverInterface
    {
        return match ($config['driver']) {
            'local' => new Drivers\LocalDriver($config),
            's3', 'minio' => new Drivers\S3Driver($config),
            'cloudinary' => new Drivers\CloudinaryDriver($config),
            'gcs', 'google' => new Drivers\GcsDriver($config),
            'azure' => new Drivers\AzureDriver($config),
            default => throw new \InvalidArgumentException("Unsupported storage driver: {$config['driver']}"),
        };
    }
}
