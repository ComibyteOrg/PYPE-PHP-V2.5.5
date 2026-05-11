<?php
namespace Framework\Database;

use Framework\Database\MySQLConnection;
use Framework\Database\PostgreSQLConnection;
use Framework\Database\SQLiteConnection;

class DatabaseFactory
{
    public static function createConnection(array $config): DatabaseInterface
    {
        // Validate database configuration
        $type = $config['DB_TYPE'] ?? null;
        
        // If not in config array, try to get from superglobals
        if (!$type) {
            $type = $_ENV['DB_TYPE'] ?? $_SERVER['DB_TYPE'] ?? null;
        }

        if (!$type) {
            throw new \Exception(
                "Database type (DB_TYPE) not configured.\n\n" .
                "Supported types: mysql, postgresql (pgsql), sqlite\n" .
                "Please set DB_TYPE in your .env file or environment variables."
            );
        }

        switch (strtolower($type)) {
            case 'mysql':
                // Validate MySQL config
                $host = $config['DB_HOST'] ?? null;
                $user = $config['DB_USER'] ?? null;
                $pass = $config['DB_PASS'] ?? '';
                $name = $config['DB_NAME'] ?? null;
                $port = (int) ($config['DB_PORT'] ?? 3306);

                if (!$host || !$user || !$name) {
                    throw new \Exception(
                        "MySQL configuration incomplete.\n" .
                        "Required settings: DB_HOST, DB_USER, DB_NAME\n" .
                        "Optional: DB_PASS, DB_PORT (default: 3306)\n\n" .
                        "Current values:\n" .
                        "- DB_HOST: " . ($host ?? 'NOT SET') . "\n" .
                        "- DB_USER: " . ($user ?? 'NOT SET') . "\n" .
                        "- DB_NAME: " . ($name ?? 'NOT SET')
                    );
                }

                return new MySQLConnection($host, $user, $pass, $name, $port);

            case 'sqlite':
                $path = $config['DB_PATH'] ?? __DIR__ . '/../../db.sqlite';
                return new SQLiteConnection($path);

            case 'pgsql':
            case 'postgresql':
                // Validate PostgreSQL config
                $host = $config['DB_HOST'] ?? null;
                $user = $config['DB_USER'] ?? null;
                $pass = $config['DB_PASS'] ?? '';
                $name = $config['DB_NAME'] ?? null;
                $port = (int) ($config['DB_PORT'] ?? 5432);

                if (!$host || !$user || !$name) {
                    throw new \Exception(
                        "PostgreSQL configuration incomplete.\n" .
                        "Required settings: DB_HOST, DB_USER, DB_NAME\n" .
                        "Optional: DB_PASS, DB_PORT (default: 5432)\n\n" .
                        "Current values:\n" .
                        "- DB_HOST: " . ($host ?? 'NOT SET') . "\n" .
                        "- DB_USER: " . ($user ?? 'NOT SET') . "\n" .
                        "- DB_NAME: " . ($name ?? 'NOT SET')
                    );
                }

                return new PostgreSQLConnection($host, $user, $pass, $name, $port);

            default:
                throw new \Exception(
                    "Unsupported database type: {$type}\n\n" .
                    "Supported types:\n" .
                    "- mysql (MySQL/MariaDB)\n" .
                    "- postgresql or pgsql (PostgreSQL)\n" .
                    "- sqlite (SQLite)\n\n" .
                    "Set DB_TYPE in your .env file to one of these values."
                );
        }
    }
}