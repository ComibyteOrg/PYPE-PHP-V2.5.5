<?php

namespace Framework\Storage;

interface StorageDriverInterface
{
    public function put(string $path, $contents, array $options = []): bool;
    public function putFile(string $path, string $localPath, array $options = []): bool;
    public function get(string $path): string|false;
    public function stream(string $path): mixed;
    public function exists(string $path): bool;
    public function missing(string $path): bool;
    public function size(string $path): int|false;
    public function lastModified(string $path): int|false;
    public function url(string $path): string;
    public function download(string $path, ?string $name = null): void;
    public function delete(string|array $paths): bool;
    public function deleteDirectory(string $path): bool;
    public function copy(string $from, string $to): bool;
    public function move(string $from, string $to): bool;
    public function files(string $directory = '', bool $recursive = false): array;
    public function allFiles(string $directory = ''): array;
    public function directories(string $directory = ''): array;
    public function makeDirectory(string $path): bool;
    public function temporaryUrl(string $path, int $expiration): string;
    public function metadata(string $path): array;
    public function driver(): string;
}
