<?php
namespace Framework\Database;

interface DatabaseInterface
{
    /**
     * Establish a database connection
     * @return mixed
     */
    public function connect();

    /**
     * Close the database connection
     * @return void
     */
    public function close();

    /**
     * Execute a query
     * @param string $query
     * @param array $params
     * @return mixed
     */
    public function query($query, $params = []);

    /**
     * Get the last inserted ID
     * @return mixed
     */
    public function lastInsertId();

    /**
     * Begin a transaction
     * @return bool
     */
    public function beginTransaction();

    /**
     * Commit a transaction
     * @return bool
     */
    public function commit();

    /**
     * Rollback a transaction
     * @return bool
     */
    public function rollback();
}