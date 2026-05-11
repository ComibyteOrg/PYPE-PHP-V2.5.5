<?php
namespace Framework\Database;

use Dotenv\Dotenv;

class Connect
{
    public $connection;
    private $database;

    public function __construct()
    {
        // Try to load .env file if Dotenv is available
        if (class_exists('Dotenv\Dotenv')) {
            try {
                $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
                $dotenv->load();
            } catch (\Exception $e) {
                // Dotenv not available, continue with system env vars
            }
        }

        // Check if required database configuration exists
        $dbType = $_ENV['DB_TYPE'] ?? $_SERVER['DB_TYPE'] ?? null;
        $dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? null;
        $dbName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? null;
        $dbUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? null;

        if (!$dbType || (!$dbName && strtolower($dbType) !== 'sqlite')) {
            throw new \Exception(
                "Database configuration not found!\n\n" .
                "Please create a .env file in your project root with database credentials:\n\n" .
                "DB_TYPE=mysql\n" .
                "DB_HOST=localhost\n" .
                "DB_USER=root\n" .
                "DB_PASS=\n" .
                "DB_NAME=your_database_name\n" .
                "DB_PORT=3306\n\n" .
                "Or use SQLite:\n\n" .
                "DB_TYPE=sqlite\n" .
                "DB_PATH=/path/to/database.sqlite"
            );
        }

        try {
            $this->database = DatabaseFactory::createConnection($_ENV);
            $this->connection = $this->database->connect();
        } catch (\Exception $e) {
            throw new \Exception(
                "Database connection failed!\n\n" .
                "Error: " . $e->getMessage() . "\n\n" .
                "Please check your database credentials in the .env file:\n" .
                "- DB_HOST: " . ($dbHost ?? 'NOT SET') . "\n" .
                "- DB_NAME: " . ($dbName ?? 'NOT SET') . "\n" .
                "- DB_USER: " . ($dbUser ?? 'NOT SET') . "\n" .
                "- DB_TYPE: " . ($dbType ?? 'NOT SET')
            );
        }
    }

    public function __destruct()
    {
        if ($this->database) {
            $this->database->close();
        }
    }
}