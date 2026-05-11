<?php

namespace Framework\Health;

/**
 * Health Check — system health monitoring.
 *
 * Usage:
 * Health::check();
 * Health::checkDatabase();
 * Health::checkDisk();
 *
 * GET /health → { status: "ok", checks: { ... } }
 */
class Health
{
    protected static array $checks = [];

    public static function check(): array
    {
        self::$checks = [];
        self::checkPhpVersion();
        self::checkExtensions();
        self::checkDiskSpace();
        self::checkDatabase();
        self::checkCache();
        self::checkEnvironment();
        self::checkPermissions();

        $allOk = array_reduce(self::$checks, fn($carry, $check) => $carry && $check['status'] === 'ok', true);

        return [
            'status' => $allOk ? 'ok' : 'degraded',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '2.5.5',
            'checks' => self::$checks,
        ];
    }

    public static function toJson(): string
    {
        return json_encode(self::check(), JSON_PRETTY_PRINT);
    }

    public static function output(): void
    {
        $result = self::check();
        $statusCode = $result['status'] === 'ok' ? 200 : 503;
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo self::toJson();
        exit;
    }

    public static function checkPhpVersion(): void
    {
        $version = PHP_VERSION;
        $required = '8.2.0';
        self::$checks['php_version'] = [
            'status' => version_compare($version, $required, '>=') ? 'ok' : 'fail',
            'message' => "PHP {$version} (required: {$required}+)",
        ];
    }

    public static function checkExtensions(): void
    {
        $required = ['pdo', 'json', 'mbstring', 'curl', 'openssl'];
        $missing = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        self::$checks['extensions'] = [
            'status' => empty($missing) ? 'ok' : 'fail',
            'message' => empty($missing) ? 'All required extensions loaded' : 'Missing: ' . implode(', ', $missing),
        ];
    }

    public static function checkDiskSpace(?string $path = null, int $minMb = 100): void
    {
        $path = $path ?? __DIR__;
        $free = disk_free_space($path);
        $freeMb = $free ? round($free / 1024 / 1024, 2) : 0;

        self::$checks['disk_space'] = [
            'status' => $freeMb >= $minMb ? 'ok' : 'warning',
            'message' => "{$freeMb}MB free (minimum: {$minMb}MB)",
        ];
    }

    public static function checkDatabase(): void
    {
        try {
            $db = \Framework\Database\DatabaseQuery::pdo();
            if (!$db) {
                self::$checks['database'] = ['status' => 'fail', 'message' => 'No database connection'];
                return;
            }

            $db->query('SELECT 1');
            $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
            self::$checks['database'] = [
                'status' => 'ok',
                'message' => "Connected to {$driver}",
            ];
        } catch (\Throwable $e) {
            self::$checks['database'] = [
                'status' => 'fail',
                'message' => $e->getMessage(),
            ];
        }
    }

    public static function checkCache(?string $driver = null): void
    {
        try {
            $driver = $driver ?? env('CACHE_DRIVER', 'file');
            if ($driver === 'redis' && !extension_loaded('redis')) {
                self::$checks['cache'] = ['status' => 'warning', 'message' => 'Redis driver configured but extension not loaded'];
                return;
            }
            if ($driver === 'memcached' && !extension_loaded('memcached')) {
                self::$checks['cache'] = ['status' => 'warning', 'message' => 'Memcached driver configured but extension not loaded'];
                return;
            }
            self::$checks['cache'] = ['status' => 'ok', 'message' => "Cache driver: {$driver}"];
        } catch (\Throwable $e) {
            self::$checks['cache'] = ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    public static function checkEnvironment(): void
    {
        $env = env('APP_ENV', 'production');
        $debug = env('APP_DEBUG', false);

        $status = 'ok';
        $messages = ["Environment: {$env}"];

        if ($env === 'production' && $debug) {
            $status = 'warning';
            $messages[] = 'APP_DEBUG is true in production';
        }

        self::$checks['environment'] = [
            'status' => $status,
            'message' => implode(', ', $messages),
        ];
    }

    public static function checkPermissions(?string $path = null): void
    {
        $path = $path ?? (defined('STORAGE_PATH') ? STORAGE_PATH : __DIR__ . '/../../Storage');
        $writable = is_writable($path);

        self::$checks['permissions'] = [
            'status' => $writable ? 'ok' : 'fail',
            'message' => $writable ? "Storage directory writable" : "Storage directory not writable: {$path}",
        ];
    }

    public static function addCheck(string $name, string $status, string $message): void
    {
        self::$checks[$name] = [
            'status' => $status,
            'message' => $message,
        ];
    }
}
