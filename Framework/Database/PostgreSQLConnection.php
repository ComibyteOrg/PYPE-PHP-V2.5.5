<?php
namespace Framework\Database;

class PostgreSQLConnection implements DatabaseInterface
{
    private $connection;
    private $host;
    private $port;
    private $database;
    private $username;
    private $password;

    public function __construct(string $host, string $username, string $password, string $database, int $port = 5432)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
    }

    public function connect()
    {
        $dsn = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
            $this->host,
            $this->port,
            $this->database,
            $this->username,
            $this->password
        );

        try {
            $this->connection = new \PDO($dsn);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $this->connection;
        } catch (\PDOException $e) {
            throw new \Exception("Connection failed: " . $e->getMessage());
        }
    }

    public function close()
    {
        $this->connection = null;
    }

    public function query($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage());
        }
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollback();
    }
}