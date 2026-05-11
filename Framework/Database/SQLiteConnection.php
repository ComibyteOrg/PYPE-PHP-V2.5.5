<?php
namespace Framework\Database;

use PDO;
use PDOException;

class SQLiteConnection implements DatabaseInterface
{
    private $connection;
    private $database;

    public function __construct(string $database)
    {
        $this->database = $database;
    }

    public function connect()
    {
        $dsn = "sqlite:" . $this->database;

        try {
            $this->connection = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Connection failed: " . $e->getMessage());
        }

        return $this->connection;
    }

    public function close()
    {
        $this->connection = null;
    }

    public function query($query, $params = [])
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt;
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
        return $this->connection->rollBack();
    }
}