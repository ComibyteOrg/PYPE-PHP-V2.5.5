<?php

namespace Framework\Database;

/**
 * Migration Base Class - Define database schemas
 */
abstract class Migration
{
    protected $connection;
    protected $dbType;

    public function __construct()
    {
        $connect = new Connect();
        $this->connection = $connect->connection;
        $this->dbType = $this->detectDatabaseType();
    }

    /**
     * Detect the database type being used
     */
    private function detectDatabaseType()
    {
        if ($this->connection instanceof \mysqli) {
            return 'mysql';
        } elseif ($this->connection instanceof \SQLite3) {
            return 'sqlite';
        } elseif ($this->connection instanceof \PDO) {
            // Get the actual database driver name from PDO
            return $this->connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
        }
        return 'unknown';
    }

    /**
     * Define the schema changes for the up migration
     */
    abstract public function up();

    /**
     * Define the schema changes for the down migration
     */
    abstract public function down();

    /**
     * Create a new table
     */
    protected function createTable($tableName, callable $callback)
    {
        $schema = new Schema($tableName, $this->dbType, $this->connection);
        $callback($schema);
        $schema->execute();
    }

    /**
     * Drop a table
     */
    protected function dropTable($tableName)
    {
        $query = "DROP TABLE IF EXISTS `$tableName`";

        if ($this->dbType === 'sqlite') {
            $query = "DROP TABLE IF EXISTS $tableName";
        }

        if ($this->connection instanceof \mysqli) {
            $this->connection->query($query);
        } elseif ($this->connection instanceof \SQLite3) {
            $this->connection->exec($query);
        } elseif ($this->connection instanceof \PDO) {
            $this->connection->exec($query);
        }
    }

    /**
     * Modify an existing table
     */
    protected function modifyTable($tableName, callable $callback)
    {
        $schema = new Schema($tableName, $this->dbType, $this->connection, 'modify');
        $callback($schema);
        $schema->execute();
    }

    /**
     * Execute raw SQL
     */
    protected function raw($sql)
    {
        if ($this->connection instanceof \mysqli) {
            return $this->connection->query($sql);
        } elseif ($this->connection instanceof \SQLite3) {
            return $this->connection->exec($sql);
        } elseif ($this->connection instanceof \PDO) {
            return $this->connection->exec($sql);
        }
    }
}

/**
 * Column Class - Represents a database column with modifiers
 */
class Column
{
    private $name;
    private $type;
    private $dbType;
    private $modifiers = [];

    public function __construct($name, $type, $dbType)
    {
        $this->name = $name;
        $this->type = $type;
        $this->dbType = $dbType;
    }

    public function nullable()
    {
        $this->modifiers[] = 'nullable';
        return $this;
    }

    public function default($value)
    {
        // Check if the value is a SQL function or constant that shouldn't be quoted
        if (is_string($value)) {
            // Check for common SQL functions/constants that should not be quoted
            $unquotedValues = ['CURRENT_TIMESTAMP', 'NOW()', 'NULL'];
            $upperValue = strtoupper($value);
            
            if (in_array($upperValue, array_map('strtoupper', $unquotedValues))) {
                $this->modifiers[] = "DEFAULT $value";
            } else {
                $this->modifiers[] = "DEFAULT '$value'";
            }
        } elseif (is_bool($value)) {
            // Handle boolean values properly (1 for true, 0 for false)
            $boolValue = $value ? 1 : 0;
            $this->modifiers[] = "DEFAULT $boolValue";
        } else {
            $this->modifiers[] = "DEFAULT " . (is_string($value) ? "'$value'" : $value);
        }
        return $this;
    }

    public function unique()
    {
        $this->modifiers[] = 'UNIQUE';
        return $this;
    }

    public function toString()
    {
        $nullable = in_array('nullable', $this->modifiers) ? '' : 'NOT NULL';
        $modifiers = implode(' ', array_diff($this->modifiers, ['nullable']));

        $parts = array_filter([$this->type, $nullable, $modifiers]);
        return implode(' ', $parts);
    }

    public function toSQL()
    {
        $sql = '';
        if ($this->dbType === 'pgsql') {
            $sql = "\"{$this->name}\" " . $this->toString();
        } else {
            $sql = "`{$this->name}` " . $this->toString();
        }
        return trim($sql);
    }
}

/**
 * Schema Builder Class
 */
class Schema
{
    private $tableName;
    private $dbType;
    private $connection;
    private $columns = [];
    private $indexes = [];
    private $mode = 'create';

    public function __construct($tableName, $dbType, $connection, $mode = 'create')
    {
        $this->tableName = $tableName;
        $this->dbType = $dbType;
        $this->connection = $connection;
        $this->mode = $mode;
    }

    /**
     * Add an ID column (primary key)
     */
    public function id()
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "INTEGER PRIMARY KEY AUTOINCREMENT";
        } else {
            $type = "INT AUTO_INCREMENT PRIMARY KEY";
        }

        $column = new Column('id', $type, $this->dbType);
        $this->columns['id'] = $column;
        return $this;
    }

    /**
     * Add a string column
     */
    public function string($columnName, $length = 255)
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "TEXT";
        } elseif ($this->dbType === 'pgsql') {
            $type = "VARCHAR($length)";
        } else {
            $type = "VARCHAR($length)";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add an integer column
     */
    public function integer($columnName)
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "INTEGER";
        } elseif ($this->dbType === 'pgsql') {
            $type = "INTEGER";
        } else {
            $type = "INT";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a text column
     */
    public function text($columnName)
    {
        $type = "TEXT";

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a timestamp column
     */
    public function timestamp($columnName)
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "TIMESTAMP";
        } elseif ($this->dbType === 'pgsql') {
            $type = "TIMESTAMP";
        } else {
            $type = "TIMESTAMP";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        // Apply default CURRENT_TIMESTAMP
        $column->default('CURRENT_TIMESTAMP');
        return $column;
    }

    /**
     * Add a boolean column
     */
    public function boolean($columnName)
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "INTEGER";
        } elseif ($this->dbType === 'pgsql') {
            $type = "BOOLEAN";
        } else {
            $type = "BOOLEAN";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a double/float column
     */
    public function double($columnName, $total = 8, $places = 2)
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "REAL";
        } elseif ($this->dbType === 'pgsql') {
            $type = "DOUBLE PRECISION";
        } else {
            $type = "DOUBLE($total,$places)";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a date column
     */
    public function date($columnName)
    {
        $type = "DATE";

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a datetime column
     */
    public function datetime($columnName)
    {
        $type = "DATETIME";

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a JSON column
     */
    public function json($columnName)
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "TEXT";
        } elseif ($this->dbType === 'pgsql') {
            $type = "JSON";
        } else {
            $type = "JSON";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a time column
     */
    public function time($columnName)
    {
        $type = "TIME";

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add a binary column
     */
    public function binary($columnName)
    {
        $type = '';
        if ($this->dbType === 'sqlite') {
            $type = "BLOB";
        } elseif ($this->dbType === 'pgsql') {
            $type = "BYTEA";
        } else {
            $type = "LONGBLOB";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Add an enum column
     */
    public function enum($columnName, $allowedValues)
    {
        $type = '';
        if ($this->dbType === 'mysql') {
            $type = "ENUM('" . implode("','", $allowedValues) . "')";
        } else {
            // For other databases, use VARCHAR with validation
            $type = "VARCHAR(255)";
        }

        $column = new Column($columnName, $type, $this->dbType);
        $this->columns[$columnName] = $column;
        return $column;
    }

    /**
     * Make a column nullable
     */
    public function nullable()
    {
        // This method is called on Schema, but columns now have their own nullable()
        // This is kept for backwards compatibility
        return $this;
    }

    /**
     * Add a default value
     */
    public function default($value)
    {
        // This method is called on Schema, but columns now have their own default()
        // This is kept for backwards compatibility
        return $this;
    }

    /**
     * Add timestamps (created_at, updated_at)
     */
    public function timestamps()
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
        return $this;
    }

    /**
     * Add soft deletes (deleted_at)
     */
    public function softDeletes()
    {
        $column = $this->timestamp('deleted_at');
        $column->nullable();
        return $this;
    }

    /**
     * Add a raw SQL column definition
     * Useful for database-specific column types not covered by other methods
     * 
     * @param string $rawSql The raw SQL column definition
     * @return $this
     */
    public function raw($rawSql)
    {
        $this->columns[] = $rawSql;
        return $this;
    }

    public function index(string|array $columns, ?string $name = null): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $indexName = $name ?: 'idx_' . $this->tableName . '_' . implode('_', $cols);
        $this->indexes[] = ['type' => 'index', 'columns' => $cols, 'name' => $indexName];
        return $this;
    }

    public function unique(string|array $columns, ?string $name = null): self
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $indexName = $name ?: 'uniq_' . $this->tableName . '_' . implode('_', $cols);
        $this->indexes[] = ['type' => 'unique', 'columns' => $cols, 'name' => $indexName];
        return $this;
    }

    public function foreign(string $column, string $referencesTable, string $referencesColumn = 'id', string $onDelete = 'CASCADE', string $onUpdate = 'CASCADE'): self
    {
        $this->indexes[] = [
            'type' => 'foreign',
            'columns' => [$column],
            'name' => 'fk_' . $this->tableName . '_' . $column,
            'references_table' => $referencesTable,
            'references_column' => $referencesColumn,
            'on_delete' => $onDelete,
            'on_update' => $onUpdate,
        ];
        return $this;
    }

    /**
     * Execute the schema changes
     */
    public function execute()
    {
        if ($this->mode === 'create') {
            $this->createTableSQL();
            $this->createIndexes();
        } else {
            $this->modifyTableSQL();
        }
    }

    /**
     * Generate and execute CREATE TABLE query
     */
    private function createTableSQL()
    {
        $columnsSQLParts = [];
        foreach ($this->columns as $column) {
            if ($column instanceof Column) {
                $columnSQL = $column->toSQL();
                // Only add non-empty column definitions
                if (!empty(trim($columnSQL))) {
                    $columnsSQLParts[] = $columnSQL;
                }
            } else {
                // Handle legacy string entries
                $columnSQL = trim($column);
                if (!empty($columnSQL)) {
                    $columnsSQLParts[] = $columnSQL;
                }
            }
        }
        $columnsSQL = implode(", ", $columnsSQLParts);

        if ($this->dbType === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} ($columnsSQL)";
        } elseif ($this->dbType === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} ($columnsSQL)";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` ($columnsSQL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        // Debug: Print the SQL being executed
        // echo "DEBUG SQL: $sql\n";

        $this->executeSQL($sql);
    }

    /**
     * Generate and execute ALTER TABLE query
     */
    private function modifyTableSQL()
    {
        // Modify operations would go here
        // For now, just execute ALTER TABLE commands
        foreach ($this->columns as $column) {
            $columnSQL = '';
            if ($column instanceof Column) {
                $columnSQL = $column->toSQL();
            } else {
                $columnSQL = $column;
            }

            if ($this->dbType === 'pgsql') {
                $sql = "ALTER TABLE {$this->tableName} ADD COLUMN $columnSQL";
            } else {
                $sql = "ALTER TABLE `{$this->tableName}` ADD COLUMN $columnSQL";
            }
            $this->executeSQL($sql);
        }
    }

    /**
     * Create indexes after table creation
     */
    private function createIndexes()
    {
        foreach ($this->indexes as $index) {
            $cols = implode(', ', array_map(fn($c) => $this->dbType === 'pgsql' ? "\"{$c}\"" : "`{$c}`", $index['columns']));

            if ($index['type'] === 'index') {
                $sql = "CREATE INDEX {$index['name']} ON {$this->tableName} ({$cols})";
            } elseif ($index['type'] === 'unique') {
                $sql = "CREATE UNIQUE INDEX {$index['name']} ON {$this->tableName} ({$cols})";
            } elseif ($index['type'] === 'foreign') {
                $refCol = $this->dbType === 'pgsql' ? "\"{$index['references_column']}\"" : "`{$index['references_column']}`";
                $refTable = $this->dbType === 'pgsql' ? "\"{$index['references_table']}\"" : "`{$index['references_table']}`";
                $sql = "ALTER TABLE {$this->tableName} ADD CONSTRAINT {$index['name']} FOREIGN KEY ({$cols}) REFERENCES {$refTable}({$refCol}) ON DELETE {$index['on_delete']} ON UPDATE {$index['on_update']}";
            }

            if (isset($sql)) {
                $this->executeSQL($sql);
            }
        }
    }

    /**
     * Execute SQL statement
     */
    private function executeSQL($sql)
    {
        try {
            if ($this->connection instanceof \mysqli) {
                if (!$this->connection->query($sql)) {
                    throw new \Exception("MySQL Error: " . $this->connection->error);
                }
            } elseif ($this->connection instanceof \SQLite3) {
                if (!$this->connection->exec($sql)) {
                    throw new \Exception("SQLite Error: " . $this->connection->lastErrorMsg());
                }
            } elseif ($this->connection instanceof \PDO) {
                $this->connection->exec($sql);
            }
        } catch (\Exception $e) {
            throw new \Exception("Migration Error: " . $e->getMessage());
        }
    }
}
