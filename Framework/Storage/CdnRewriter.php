<?php

namespace Framework\Storage;

class CdnRewriter
{
    private static array $disks = [];
    private static bool $enabled = false;
    private static string $defaultDisk = '';

    public static function configure(array $config): void
    {
        self::$enabled = $config['enabled'] ?? false;
        self::$disks = $config['disks'] ?? [];
        self::$defaultDisk = $config['default'] ?? '';
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function rewrite(string $url, ?string $disk = null): string
    {
        if (!self::$enabled) {
            return $url;
        }

        $disk = $disk ?: self::$defaultDisk;

        if (empty($disk) || !isset(self::$disks[$disk])) {
            return $url;
        }

        $cdnConfig = self::$disks[$disk];

        if (empty($cdnConfig['cdn_url'])) {
            return $url;
        }

        $localUrl = $cdnConfig['url'] ?? '';

        if (empty($localUrl)) {
            return str_replace(
                rtrim($cdnConfig['cdn_url'], '/'),
                rtrim($localUrl, '/'),
                $url
            );
        }

        return str_replace(
            rtrim($localUrl, '/'),
            rtrim($cdnConfig['cdn_url'], '/'),
            $url
        );
    }

    public static function rewritePath(string $path, ?string $disk = null): string
    {
        if (!self::$enabled) {
            return $path;
        }

        $disk = $disk ?: self::$defaultDisk;

        if (empty($disk) || !isset(self::$disks[$disk])) {
            return $path;
        }

        $cdnConfig = self::$disks[$disk];

        if (empty($cdnConfig['cdn_url'])) {
            return $path;
        }

        return rtrim($cdnConfig['cdn_url'], '/') . '/' . ltrim($path, '/');
    }

    public static function rewriteContent(string $content, ?string $disk = null): string
    {
        if (!self::$enabled) {
            return $content;
        }

        $disk = $disk ?: self::$defaultDisk;

        if (empty($disk) || !isset(self::$disks[$disk])) {
            return $content;
        }

        $cdnConfig = self::$disks[$disk];

        if (empty($cdnConfig['cdn_url'])) {
            return $content;
        }

        $localUrl = $cdnConfig['url'] ?? '';

        if (!empty($localUrl)) {
            $content = str_replace(
                rtrim($localUrl, '/'),
                rtrim($cdnConfig['cdn_url'], '/'),
                $content
            );
        }

        $baseUrl = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http' . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        $content = str_replace(
            rtrim($baseUrl, '/') . '/storage',
            rtrim($cdnConfig['cdn_url'], '/'),
            $content
        );

        return $content;
    }

    public static function rewriteHtml(string $html, ?string $disk = null): string
    {
        return self::rewriteContent($html, $disk);
    }
}
