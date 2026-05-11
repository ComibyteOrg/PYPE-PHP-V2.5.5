<?php
namespace Framework\Database;

class MySQLConnection implements DatabaseInterface
{
    private $connection;
    private $host;
    private $username;
    private $password;
    private $database;
    private $port;

    public function __construct(string $host, string $username, string $password, string $database, int $port = 3306)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
    }

    public function connect()
    {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";

        try {
            $this->connection = new \PDO($dsn, $this->username, $this->password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
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