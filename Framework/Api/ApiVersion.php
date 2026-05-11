<?php

namespace Framework\Api;

class ApiVersion
{
    private static string $currentVersion = 'v1';
    private static array $availableVersions = ['v1'];
    private static string $versionHeader = 'X-API-Version';
    private static string $urlPrefix = 'api';

    public static function configure(array $config): void
    {
        self::$currentVersion = $config['current'] ?? self::$currentVersion;
        self::$availableVersions = $config['available'] ?? self::$availableVersions;
        self::$versionHeader = $config['header'] ?? self::$versionHeader;
        self::$urlPrefix = $config['url_prefix'] ?? self::$urlPrefix;
    }

    public static function getCurrent(): string
    {
        return self::$currentVersion;
    }

    public static function getFromRequest(): string
    {
        $version = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper(self::$versionHeader))] ?? '';

        if (empty($version)) {
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
            $prefix = '/' . self::$urlPrefix . '/';
            if (str_starts_with($path, $prefix)) {
                $afterPrefix = substr($path, strlen($prefix));
                $parts = explode('/', $afterPrefix);
                if (!empty($parts) && in_array($parts[0], self::$availableVersions)) {
                    $version = $parts[0];
                }
            }
        }

        if (empty($version)) {
            $version = $_GET['api_version'] ?? '';
        }

        return $version ?: self::$currentVersion;
    }

    public static function isDeprecated(string $version): bool
    {
        $deprecated = [
            'v1' => true,
        ];

        return $deprecated[$version] ?? false;
    }

    public static function isSupported(string $version): bool
    {
        return in_array($version, self::$availableVersions);
    }

    public static function enforce(string $version): bool
    {
        if (!self::isSupported($version)) {
            ApiProblem::make(400)
                ->type('https://api.example.com/errors/unsupported-version')
                ->title('Unsupported API Version')
                ->detail("API version '{$version}' is not supported. Available: " . implode(', ', self::$availableVersions))
                ->extension('available_versions', self::$availableVersions)
                ->send();
        }

        if (self::isDeprecated($version)) {
            header('Warning: 299 - "API version ' . $version . ' is deprecated"');
            header('Sunset: ' . gmdate('D, d M Y H:i:s T', strtotime('+6 months')));
        }

        return true;
    }

    public static function group(string $version, callable $routes): void
    {
        $prefix = '/' . self::$urlPrefix . '/' . $version;

        \Framework\Router\Route::group(['prefix' => $prefix], function() use ($routes) {
            $routes();
        });
    }

    public static function versionedGroup(callable $routes): void
    {
        $version = self::getFromRequest();
        self::enforce($version);
        self::group($version, $routes);
    }
}
