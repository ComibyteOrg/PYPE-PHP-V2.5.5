<?php 
    namespace Framework\Database;
    use mysqli;
    use InvalidArgumentException;
    use Framework\Database\Connect;

    class DatabaseQuery extends Connect{

        /**
         * Simple result wrapper to provide num_rows and fetch_assoc() for non-mysqli drivers
         */
        private function wrapArrayResult(array $rows)
        {
            return new class($rows) {
                private $rows;
                private $index = 0;
                public $num_rows;

                public function __construct(array $rows)
                {
                    $this->rows = $rows;
                    $this->num_rows = count($rows);
                }

                public function fetch_assoc()
                {
                    if ($this->index < $this->num_rows) {
                        return $this->rows[$this->index++];
                    }
                    return null;
                }
            };
        }

        // Select Method
        public function select($table, $selectors = "*", $conditions = "", $types = "", $params = array()){
            $columns = is_array($selectors) ? implode(', ', $selectors) : $selectors;

            // Prevent misuse with full queries
            if (str_contains(strtolower($table), 'select')) {
                throw new InvalidArgumentException('The "select" method\'s first argument should be a table name, not a full query. Use "rawQuery" for full queries.');
            }

            $query = "SELECT $columns FROM $table";
            if (!empty($conditions)) {
                $query .= " WHERE $conditions";
            }

            // mysqli
            if ($this->connection instanceof mysqli) {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) {
                    throw new \RuntimeException("Query error: " . $this->connection->error);
                }

                if (!empty($params)) {
                    if (!is_array($params)) {
                        throw new InvalidArgumentException('Params must be an array.');
                    }
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                return $result;
            }

            // SQLite3
            if ($this->connection instanceof \SQLite3) {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) {
                    throw new \RuntimeException("SQLite prepare failed: " . $this->connection->lastErrorMsg());
                }

                // bind params (1-based)
                foreach ($params as $i => $val) {
                    $idx = $i + 1;
                    if (is_int($val)) {
                        $stmt->bindValue($idx, $val, \SQLITE3_INTEGER);
                    } elseif (is_float($val)) {
                        $stmt->bindValue($idx, $val, \SQLITE3_FLOAT);
                    } elseif (is_null($val)) {
                        $stmt->bindValue($idx, null, \SQLITE3_NULL);
                    } else {
                        $stmt->bindValue($idx, $val, \SQLITE3_TEXT);
                    }
                }

                $res = $stmt->execute();
                if ($res === false) {
                    throw new \RuntimeException("SQLite execute failed: " . $this->connection->lastErrorMsg());
                }

                $rows = [];
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $rows[] = $row;
                }

                return $this->wrapArrayResult($rows);
            }

            // PDO (PostgreSQL or others)
            if ($this->connection instanceof \PDO) {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) {
                    $err = $this->connection->errorInfo();
                    die('PDO prepare error: ' . json_encode($err));
                }
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                return $this->wrapArrayResult($rows);
            }

            throw new \RuntimeException('Unsupported database connection type.');
        }

        /**
         * Executes a raw SQL query.
         *
         * @param string $query The raw SQL query string.
         * @param array $params The parameters to bind to the query.
         * @param string $types The types string for mysqli bind_param.
         * @return mixed The result of the query.
         */
        public function rawQuery(string $query, array $params = [], string $types = "")
        {
            // mysqli
            if ($this->connection instanceof mysqli) {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) {
                    throw new \RuntimeException("Query error: " . $this->connection->error);
                }

                if (!empty($params)) {
                    if (empty($types)) {
                        $types = str_repeat('s', count($params));
                    }
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                return $result;
            }

            // SQLite3
            if ($this->connection instanceof \SQLite3) {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) {
                    throw new \RuntimeException("SQLite prepare failed: " . $this->connection->lastErrorMsg());
                }

                foreach ($params as $i => $val) {
                    $idx = $i + 1;
                    if (is_int($val)) $stmt->bindValue($idx, $val, \SQLITE3_INTEGER);
                    elseif (is_float($val)) $stmt->bindValue($idx, $val, \SQLITE3_FLOAT);
                    elseif (is_null($val)) $stmt->bindValue($idx, null, \SQLITE3_NULL);
                    else $stmt->bindValue($idx, $val, \SQLITE3_TEXT);
                }

                $res = $stmt->execute();
                if ($res === false) {
                    throw new \RuntimeException("SQLite execute failed: " . $this->connection->lastErrorMsg());
                }

                $rows = [];
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $rows[] = $row;
                }
                return $this->wrapArrayResult($rows);
            }

            // PDO
            if ($this->connection instanceof \PDO) {
                $stmt = $this->connection->prepare($query);
                $stmt->execute($params);
                return $this->wrapArrayResult($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            throw new \RuntimeException('Unsupported database connection type.');
        }


        // Update method
        public function update($table, $data, $where, $types = "", $params = []) {
            $setParts = [];
            $setValues = [];

            foreach ($data as $column => $value) {
                $setParts[] = "`$column` = ?";
                $setValues[] = $value;
            }

            $setQuery = implode(', ', $setParts);
            $sql = "UPDATE `$table` SET $setQuery WHERE $where";

            $allValues = array_merge($setValues, $params);

            // mysqli
            if ($this->connection instanceof mysqli) {
                if ($stmt = $this->connection->prepare($sql)) {
                    $types = $types ?: str_repeat('s', count($allValues));
                    $stmt->bind_param($types, ...$allValues);
                    $ok = $stmt->execute();
                    $stmt->close();
                    return $ok;
                }
                return false;
            }

            // SQLite3
            if ($this->connection instanceof \SQLite3) {
                $stmt = $this->connection->prepare($sql);
                if (!$stmt) return false;
                foreach ($allValues as $i => $val) {
                    $idx = $i + 1;
                    if (is_int($val)) $stmt->bindValue($idx, $val, \SQLITE3_INTEGER);
                    elseif (is_float($val)) $stmt->bindValue($idx, $val, \SQLITE3_FLOAT);
                    else $stmt->bindValue($idx, $val, \SQLITE3_TEXT);
                }
                $res = $stmt->execute();
                return $res !== false;
            }

            // PDO
            if ($this->connection instanceof \PDO) {
                $stmt = $this->connection->prepare($sql);
                return $stmt->execute($allValues);
            }

            return false;
        }


        // Delete Method
        public function delete($table, $condition = "", $datatypes = "", $values = []){
            $query = "DELETE FROM $table WHERE $condition";

            if ($this->connection instanceof mysqli) {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) return false;
                if (!empty($values)) {
                    $types = $datatypes ?: str_repeat('s', count($values));
                    $stmt->bind_param($types, ...$values);
                }
                return $stmt->execute();
            }

            if ($this->connection instanceof \SQLite3) {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) return false;
                foreach ($values as $i => $val) {
                    $idx = $i + 1;
                    if (is_int($val)) $stmt->bindValue($idx, $val, \SQLITE3_INTEGER);
                    elseif (is_float($val)) $stmt->bindValue($idx, $val, \SQLITE3_FLOAT);
                    else $stmt->bindValue($idx, $val, \SQLITE3_TEXT);
                }
                $res = $stmt->execute();
                return $res !== false;
            }

            if ($this->connection instanceof \PDO) {
                $stmt = $this->connection->prepare($query);
                return $stmt->execute($values);
            }

            return false;
        }

        

        // Insert Method
       public function insert($table, $datas){
            $columns = implode(', ', array_keys($datas));
            $placeholders = implode(',', array_fill(0, count($datas), "?"));

            $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

            $values = array_values($datas);

            if ($this->connection instanceof mysqli) {
                error_log("Executing SQL: $sql");
                error_log("Values: " . print_r($values, true));
                
                $stmt = $this->connection->prepare($sql);
                if ($stmt == false) {
                    $error = $this->connection->error;
                    error_log("Prepare failed: $error");
                    throw new \RuntimeException("Failed to prepare statement: ($error)");
                }

                $types = '';
                foreach ($values as $data) {
                    if (is_int($data)) {
                        $types .= 'i';
                    } elseif (is_double($data) || is_float($data)) {
                        $types .= 'd';
                    } else{
                        $types .= 's';
                    }
                }
                error_log("Param types: $types");
                
                try {
                    $stmt->bind_param($types, ...$values);
                    $result = $stmt->execute();
                    if (!$result) {
                        error_log("Execute failed: " . $stmt->error);
                    }
                    return $result;
                } catch (\Exception $e) {
                    error_log("Database error: " . $e->getMessage());
                    throw $e;
                }
            }

            if ($this->connection instanceof \SQLite3) {
                $stmt = $this->connection->prepare($sql);
                if (!$stmt) throw new \RuntimeException("Failed to prepare statement: ({$this->connection->lastErrorMsg()})");
                foreach ($values as $i => $val) {
                    $idx = $i + 1;
                    if (is_int($val)) $stmt->bindValue($idx, $val, \SQLITE3_INTEGER);
                    elseif (is_float($val)) $stmt->bindValue($idx, $val, \SQLITE3_FLOAT);
                    else $stmt->bindValue($idx, $val, \SQLITE3_TEXT);
                }
                $res = $stmt->execute();
                return $res !== false;
            }

            if ($this->connection instanceof \PDO) {
                $stmt = $this->connection->prepare($sql);
                return $stmt->execute($values);
            }

            return false;
        }
    }