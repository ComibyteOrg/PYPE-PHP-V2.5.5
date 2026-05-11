<?php

namespace Framework\Model;

use Framework\Database\DatabaseQuery;

/**
 * Django-style Base Model Class
 * Models define the database schema directly using fields
 */
class Model extends DatabaseQuery
{
    /**
     * Table name for this model
     * @var string
     */
    protected static $table;

    /**
     * Primary key column
     * @var string
     */
    protected static $primaryKey = 'id';

    /**
     * Fields definition (Django style)
     * @var array
     */
    protected static $fields = [];

    /**
     * Current attributes/data
     * @var array
     */
    protected $data = [];

    /**
     * Query builder properties (static for fluent static chaining)
     */
    protected static $querySelect = '*';
    protected static $queryJoins = [];
    protected static $queryWhere = [];
    protected static $queryHaving = '';
    protected static $queryHavingValues = [];
    protected static $queryOrder = '';
    protected static $queryGroup = '';
    protected static $queryLimit = '';
    protected static $queryOffset = '';
    protected static $queryDebug = false;

    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        parent::__construct();
        $this->data = $data;
    }

    /**
     * Define the table schema (override in child models)
     * This is called by migrations to get the table structure
     * 
     * Example:
     * public static function schema($table) {
     *     $table->id();
     *     $table->string('name', 255);
     *     $table->string('email', 255);
     *     $table->text('description')->nullable();
     *     $table->integer('status')->default(1);
     *     $table->timestamps();
     * }
     */
    public static function schema($table)
    {
        // Base implementation - child models should override this
        $table->id();
    }

    /**
     * Get the table name
     */
    public static function getTable()
    {
        if (static::$table) {
            return static::$table;
        }

        $parts = explode('\\', static::class);
        $className = end($parts);
        return strtolower($className) . 's';
    }

    /**
     * Get all fields definition
     */
    public static function getFields()
    {
        return static::$fields;
    }

    /**
     * Get all records from the table
     * User::all()
     */
    public static function all()
    {
        $instance = new static();
        $result = $instance->select(static::getTable());
        $records = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $records[] = new static($row);
            }
        }

        return $records;
    }

    /**
     * Find a record by primary key
     * User::find(1)
     */
    public static function find($id)
    {
        $instance = new static();
        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        // Use PDO-style query
        $result = static::where($primaryKey, $id)->first();
        
        return $result;
    }

    /**
     * Find by a column value
     * User::findBy('email', 'user@example.com')
     */
    public static function findBy($column, $value)
    {
        // Use query builder
        $result = static::where($column, $value)->first();
        
        return $result;
    }

    /**
     * Filter records with multiple conditions
     * User::filter(['status' => 1, 'is_active' => true])
     */
    public static function filter($conditions = [])
    {
        $instance = new static();
        $table = static::getTable();

        if (empty($conditions)) {
            return static::all();
        }

        $conditionStrings = [];
        $values = [];
        $types = "";

        foreach ($conditions as $column => $value) {
            $conditionStrings[] = "$column = ?";
            $values[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }

        $condition = implode(' AND ', $conditionStrings);
        $result = $instance->select($table, "*", $condition, $types, $values);

        $records = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $records[] = new static($row);
            }
        }

        return $records;
    }

    /**
     * Get the first record
     * User::first()
     */
    public static function first()
    {
        // If query builder has conditions, use getFirst()
        if (!empty(static::$queryWhere) || static::$querySelect !== '*' || static::$queryOrder !== '' || static::$queryLimit !== '') {
            return static::getFirst();
        }
        $records = static::all();
        return !empty($records) ? $records[0] : null;
    }

    /**
     * Get total count of records
     * User::count()
     */
    public static function count()
    {
        // If query builder has conditions, use countRows()
        if (!empty(static::$queryWhere) || static::$querySelect !== '*' || static::$queryOrder !== '' || static::$queryLimit !== '') {
            return static::countRows();
        }
        $instance = new static();
        $result = $instance->select(static::getTable(), "COUNT(*) as count");

        if ($result && $row = $result->fetch_assoc()) {
            return intval($row['count']);
        }

        return 0;
    }

    /**
     * Create a new record in database
     * User::create(['name' => 'John', 'email' => 'john@example.com'])
     */
    public static function create($data = [])
    {
        $instance = new static();
        $table = static::getTable();

        if ($instance->insert($table, $data)) {
            return new static($data);
        }

        return null;
    }

    /**
     * Save current model instance to database
     * $user = new User(['name' => 'John']);
     * $user->save();
     */
    public function save()
    {
        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        if (empty($this->data[$primaryKey])) {
            // Insert new record
            return $this->insert($table, $this->data);
        } else {
            // Update existing record
            $id = $this->data[$primaryKey];
            $dataToUpdate = $this->data;
            unset($dataToUpdate[$primaryKey]);

            return $this->update($table, $dataToUpdate, "$primaryKey = ?", "i", [$id]);
        }
    }

    /**
     * Update a record by primary key
     * User::updateRecord(1, ['name' => 'Jane'])
     */
    public static function updateRecord($id, $data = [])
    {
        $instance = new static();
        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        return $instance->update($table, $data, "$primaryKey = ?", "i", [$id]);
    }

    /**
     * Delete a record by primary key
     * User::destroy(1)
     */
    public static function destroy($id)
    {
        $instance = new static();
        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        return $instance->delete($table, "$primaryKey = ?", "i", [$id]);
    }

    /**
     * Delete this model instance
     * $user->remove()
     */
    public function remove()
    {
        $table = static::getTable();
        $primaryKey = static::$primaryKey;

        if (!empty($this->data[$primaryKey])) {
            return static::destroy($this->data[$primaryKey]);
        }

        return false;
    }

    /**
     * Get all records and delete them
     * User::truncate()
     */
    public static function truncate()
    {
        $instance = new static();
        $table = static::getTable();

        return $instance->rawQuery("TRUNCATE TABLE $table");
    }

    /**
     * Execute a raw SQL query
     * User::raw("SELECT * FROM users WHERE status = ?", [1])
     * 
     * ORM Methods Quick Reference:
     * Static methods:
     *   - all() - Get all records
     *   - find($id) - Find by primary key
     *   - findBy($column, $value) - Find by column value
     *   - filter($conditions) - Filter with AND conditions
     *   - first() - Get first record
     *   - count() - Count records
     *   - create($data) - Create new record
     *   - updateRecord($id, $data) - Update record by ID
     *   - destroy($id) - Delete record by ID
     *   - truncate() - Delete all records
     *   - raw($query, $params) - Execute raw SQL
     * Instance methods:
     *   - save() - Save instance (insert/update)
     *   - remove() - Delete this instance
     *   - toArray() - Convert to array
     *   - toJson() - Convert to JSON
     */
    public static function raw($query, $params = [], $types = "")
    {
        $instance = new static();
        return $instance->rawQuery($query, $params, $types);
    }

    /**
     * Get attribute value
     */
    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Set attribute value
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Check if attribute exists
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Convert model to array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Convert model to JSON
     */
    public function toJson()
    {
        return json_encode($this->data);
    }


    /**
     * Set attribute value
     */
    public function set($name, $value)
    {
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * ============================================================
     * QUERY BUILDER METHODS - Fluent Interface for Advanced Queries
     * ============================================================
     */

    /**
     * Create a new query instance
     */
    public static function query()
    {
        return new static();
    }

    /**
     * Enable debug mode
     */
    public static function debug()
    {
        static::$queryDebug = true;
        return new static();
    }

    /**
     * COLUMNS - Specify columns to select
     */
    public static function columns($columns)
    {
        static::$querySelect = $columns;
        return new static();
    }

    /**
     * WHERE - AND condition
     */
    public static function where($column, $operator, $value = null)
    {
        // Handle both where('column', 'value') and where('column', 'operator', 'value')
        if ($value === null) {
            // where('column', 'value') - default operator is '='
            $value = $operator;
            $operator = '=';
        }
        
        static::$queryWhere[] = ['AND', $column, $operator, $value];
        return new static();
    }

    /**
     * OR WHERE - OR condition
     */
    public static function orWhere($column, $operator, $value = null)
    {
        // Handle both orWhere('column', 'value') and orWhere('column', 'operator', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        static::$queryWhere[] = ['OR', $column, $operator, $value];
        return new static();
    }

    /**
     * WHERE NULL
     */
    public static function whereNull($column)
    {
        static::$queryWhere[] = ['AND', "$column IS NULL"];
        return new static();
    }

    /**
     * WHERE NOT NULL
     */
    public static function whereNotNull($column)
    {
        static::$queryWhere[] = ['AND', "$column IS NOT NULL"];
        return new static();
    }

    /**
     * WHERE IN - Check if column in array
     */
    public static function whereIn($column, array $values)
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        static::$queryWhere[] = ['AND', "$column IN ($placeholders)", 'IN', $values];
        return new static();
    }

    /**
     * WHERE NOT IN
     */
    public static function whereNotIn($column, array $values)
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        static::$queryWhere[] = ['AND', "$column NOT IN ($placeholders)", 'NOT IN', $values];
        return new static();
    }

    /**
     * WHERE BETWEEN
     */
    public static function whereBetween($column, $min, $max)
    {
        static::$queryWhere[] = ['AND', "$column BETWEEN ? AND ?", 'BETWEEN', [$min, $max]];
        return new static();
    }

    /**
     * WHERE NOT BETWEEN
     */
    public static function whereNotBetween($column, $min, $max)
    {
        static::$queryWhere[] = ['AND', "$column NOT BETWEEN ? AND ?", 'NOT BETWEEN', [$min, $max]];
        return new static();
    }

    /**
     * WHERE LIKE - Pattern matching
     */
    public static function whereLike($column, $value)
    {
        static::$queryWhere[] = ['AND', $column, 'LIKE', "%$value%"];
        return new static();
    }

    /**
     * WHERE NOT LIKE
     */
    public static function whereNotLike($column, $value)
    {
        static::$queryWhere[] = ['AND', $column, 'NOT LIKE', "%$value%"];
        return new static();
    }

    /**
     * WHERE STARTS WITH
     */
    public static function whereStartsWith($column, $value)
    {
        static::$queryWhere[] = ['AND', $column, 'LIKE', "$value%"];
        return new static();
    }

    /**
     * WHERE ENDS WITH
     */
    public static function whereEndsWith($column, $value)
    {
        static::$queryWhere[] = ['AND', $column, 'LIKE', "%$value"];
        return new static();
    }

    /**
     * ORDER BY - Sort results
     */
    public static function orderBy($column, $direction = 'ASC')
    {
        static::$queryOrder = " ORDER BY $column " . strtoupper($direction) . " ";
        return new static();
    }

    /**
     * GROUP BY - Group results
     */
    public static function groupBy($column)
    {
        static::$queryGroup = " GROUP BY $column ";
        return new static();
    }

    /**
     * HAVING - Conditions on grouped results
     */
    public static function having($column, $operator, $value)
    {
        $operator = strtoupper($operator);
        static::$queryHaving .= (static::$queryHaving ? ' AND ' : ' HAVING ') . "$column $operator ?";
        static::$queryHavingValues[] = $value;
        return new static();
    }

    /**
     * LIMIT - Limit number of results
     */
    public static function limit($limit)
    {
        static::$queryLimit = " LIMIT $limit ";
        return new static();
    }

    /**
     * OFFSET - Skip number of results
     */
    public static function offset($offset)
    {
        static::$queryOffset = " OFFSET $offset ";
        return new static();
    }

    /**
     * TAKE - Alias for limit
     */
    public static function take($limit)
    {
        return static::limit($limit);
    }

    /**
     * SKIP - Alias for offset
     */
    public static function skip($offset)
    {
        return static::offset($offset);
    }

    /**
     * JOIN - Inner join
     */
    public static function join($table, $first, $operator, $second)
    {
        static::$queryJoins[] = " JOIN $table ON $first $operator $second ";
        return new static();
    }

    /**
     * LEFT JOIN
     */
    public static function leftJoin($table, $first, $operator, $second)
    {
        static::$queryJoins[] = " LEFT JOIN $table ON $first $operator $second ";
        return new static();
    }

    /**
     * RIGHT JOIN
     */
    public static function rightJoin($table, $first, $operator, $second)
    {
        static::$queryJoins[] = " RIGHT JOIN $table ON $first $operator $second ";
        return new static();
    }

    /**
     * INNER JOIN
     */
    public static function innerJoin($table, $first, $operator, $second)
    {
        static::$queryJoins[] = " INNER JOIN $table ON $first $operator $second ";
        return new static();
    }

    /**
     * CROSS JOIN
     */
    public static function crossJoin($table)
    {
        static::$queryJoins[] = " CROSS JOIN $table ";
        return new static();
    }

    /**
     * DISTINCT - Get distinct/unique results
     */
    public static function distinct()
    {
        if (static::$querySelect === '*') {
            static::$querySelect = 'DISTINCT *';
        } else {
            static::$querySelect = 'DISTINCT ' . static::$querySelect;
        }
        return new static();
    }

    /**
     * ONLY - Select specific columns
     */
    public static function only($columns)
    {
        $cols = is_array($columns) ? implode(', ', $columns) : $columns;
        return static::columns($cols);
    }

    /**
     * EXCEPT - Exclude specific columns from result
     */
    public static function except($columns, $data = null)
    {
        $excludedColumns = is_array($columns) ? $columns : [$columns];
        if ($data === null) {
            $data = static::get();
        }

        if (is_array($data) && count($data) > 0 && is_array($data[0])) {
            foreach ($data as &$row) {
                foreach ($excludedColumns as $col) {
                    unset($row[$col]);
                }
            }
        }

        return $data;
    }

    /**
     * Build WHERE clause for query
     */
    protected static function buildWhere(&$bindValues)
    {
        if (empty(static::$queryWhere))
            return '';

        $sql = " WHERE ";
        $conditions = [];

        foreach (static::$queryWhere as $index => $w) {
            $condition = '';
            if (count($w) == 2) { // NULL
                $condition = "{$w[0]} {$w[1]}";
            } elseif (in_array($w[2], ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])) {
                $condition = "{$w[1]}";
                if (is_array($w[3])) {
                    foreach ($w[3] as $v) {
                        $bindValues[] = $v;
                    }
                } else {
                    $bindValues[] = $w[3];
                }
            } else {
                // Format: column operator ?
                // $w = ['AND', 'id', '=', 3]
                $condition = "{$w[1]} {$w[2]} ?";
                $bindValues[] = $w[3];
            }

            // Remove leading AND/OR for the first condition
            if ($index === 0) {
                $condition = preg_replace('/^(AND|OR)\s+/i', '', trim($condition));
            }

            $conditions[] = $condition;
        }

        return $sql . implode(' AND ', $conditions);
    }

    /**
     * Build complete query
     */
    protected static function buildQuery(&$bindValues)
    {
        $table = static::getTable();
        $where = static::buildWhere($bindValues);
        $joins = implode('', static::$queryJoins);

        $having = '';
        if (!empty(static::$queryHaving)) {
            $having = static::$queryHaving;
            $bindValues = array_merge($bindValues, static::$queryHavingValues);
        }

        $sql = "SELECT " . static::$querySelect . " FROM " . $table . " "
            . $joins . " "
            . $where . " "
            . static::$queryGroup
            . $having
            . static::$queryOrder
            . static::$queryLimit
            . static::$queryOffset;

        return $sql;
    }

    /**
     * Execute query
     */
    protected static function executeQuery($sql, $bindValues)
    {
        if (static::$queryDebug) {
            echo "<pre style='background:#000;color:#0f0;padding:20px;'>";
            echo "SQL: $sql\n";
            print_r($bindValues);
            echo "</pre>";
        }

        $stmt = static::getStaticConnection()->prepare($sql);
        $result = $stmt->execute($bindValues);
        return $stmt;
    }

    /**
     * Reset query builder
     */
    protected static function resetQuery()
    {
        static::$querySelect = '*';
        static::$queryJoins = [];
        static::$queryWhere = [];
        static::$queryHaving = '';
        static::$queryHavingValues = [];
        static::$queryOrder = '';
        static::$queryGroup = '';
        static::$queryLimit = '';
        static::$queryOffset = '';
        static::$queryDebug = false;
    }

    /**
     * GET - Fetch all results
     */
    public static function get()
    {
        $bindValues = [];
        $sql = static::buildQuery($bindValues);
        $stmt = static::executeQuery($sql, $bindValues);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        static::resetQuery();
        return $data;
    }

    /**
     * FIRST - Get first result as model instance
     */
    public static function getFirst()
    {
        static::limit(1);
        $data = static::get();
        return !empty($data[0]) ? new static($data[0]) : null;
    }

    /**
     * PLUCK - Get single column as array
     */
    public static function pluck($column)
    {
        static::$querySelect = $column;
        $data = static::get();
        return array_column($data, $column);
    }

    /**
     * GET COLUMNS - Alias for pluck
     */
    public static function getColumns($column)
    {
        return static::pluck($column);
    }

    /**
     * EXISTS - Check if any records match
     */
    public static function exists()
    {
        $data = static::get();
        return count($data) > 0;
    }

    /**
     * SUM - Sum a column
     */
    public static function sum($column)
    {
        static::$querySelect = "SUM($column) as total";
        $result = static::getFirst();
        
        // Handle both array and object results
        if (is_object($result) && isset($result->total)) {
            return $result->total;
        } elseif (is_array($result) && isset($result['total'])) {
            return $result['total'];
        }
        
        return 0;
    }

    /**
     * AVG - Average of a column
     */
    public static function avg($column)
    {
        static::$querySelect = "AVG($column) as avg";
        $result = static::getFirst();
        
        // Handle both array and object results
        if (is_object($result) && isset($result->avg)) {
            return $result->avg;
        } elseif (is_array($result) && isset($result['avg'])) {
            return $result['avg'];
        }
        
        return 0;
    }

    /**
     * MIN - Minimum value
     */
    public static function min($column)
    {
        static::$querySelect = "MIN($column) as min";
        $result = static::getFirst();
        
        // Handle both array and object results
        if (is_object($result) && isset($result->min)) {
            return $result->min;
        } elseif (is_array($result) && isset($result['min'])) {
            return $result['min'];
        }
        
        return 0;
    }

    /**
     * MAX - Maximum value
     */
    public static function max($column)
    {
        static::$querySelect = "MAX($column) as max";
        $result = static::getFirst();
        
        // Handle both array and object results
        if (is_object($result) && isset($result->max)) {
            return $result->max;
        } elseif (is_array($result) && isset($result['max'])) {
            return $result['max'];
        }
        
        return 0;
    }

    /**
     * COUNT - Count records
     */
    public static function countRows()
    {
        static::$querySelect = "COUNT(*) as count";
        $result = static::getFirst();
        
        // Handle both array and object results
        if (is_object($result) && isset($result->count)) {
            return intval($result->count);
        } elseif (is_array($result) && isset($result['count'])) {
            return intval($result['count']);
        }
        
        return 0;
    }

    /**
     * FIND OR FAIL - Find by ID or throw exception
     */
    public static function findOrFail($id)
    {
        $result = static::where(static::$primaryKey, $id)->getFirst();
        if (!$result) {
            throw new \Exception("Record with ID $id not found in " . static::getTable());
        }
        return new static($result);
    }

    /**
     * FIND BY OR FAIL - Find by column or throw exception
     */
    public static function findByOrFail($column, $value)
    {
        $result = static::where($column, $value)->getFirst();
        if (!$result) {
            throw new \Exception("Record with $column = $value not found in " . static::getTable());
        }
        return new static($result);
    }

    /**
     * UPDATE - Update records
     */
    public static function updateRows($data)
    {
        $table = static::getTable();
        $set = implode(', ', array_map(fn($col) => "$col = ?", array_keys($data)));

        $bindValues = array_values($data);
        $whereClause = static::buildWhere($bindValues);

        $sql = "UPDATE " . $table . " SET $set " . $whereClause;

        $stmt = static::executeQuery($sql, $bindValues);
        static::resetQuery();
        return true;
    }

    /**
     * INCREMENT - Increment a column value
     */
    public static function increment($column, $amount = 1)
    {
        $table = static::getTable();
        $bindValues = [$amount];
        $whereClause = static::buildWhere($bindValues);

        $sql = "UPDATE " . $table . " SET $column = $column + ? " . $whereClause;

        $stmt = static::executeQuery($sql, $bindValues);
        static::resetQuery();
        return true;
    }

    /**
     * DECREMENT - Decrement a column value
     */
    public static function decrement($column, $amount = 1)
    {
        $table = static::getTable();
        $bindValues = [$amount];
        $whereClause = static::buildWhere($bindValues);

        $sql = "UPDATE " . $table . " SET $column = $column - ? " . $whereClause;

        $stmt = static::executeQuery($sql, $bindValues);
        static::resetQuery();
        return true;
    }

    /**
     * DELETE - Delete records
     */
    public static function deleteRows()
    {
        $table = static::getTable();
        $bindValues = [];
        $whereClause = static::buildWhere($bindValues);

        $sql = "DELETE FROM " . $table . $whereClause;

        $stmt = static::executeQuery($sql, $bindValues);
        static::resetQuery();
        return true;
    }

    /**
     * UPDATE OR CREATE - Update if exists, create if not
     */
    public static function updateOrCreate($conditions, $values)
    {
        foreach ($conditions as $col => $val) {
            static::where($col, $val);
        }
        $existingRecord = static::getFirst();

        if ($existingRecord) {
            foreach ($conditions as $col => $val) {
                static::where($col, $val);
            }
            return static::updateRows($values);
        } else {
            $dataToInsert = array_merge($conditions, $values);
            return static::create($dataToInsert);
        }
    }

    /**
     * CHUNK - Process large datasets in batches
     */
    public static function chunk($size, callable $callback)
    {
        $page = 1;
        while (true) {
            static::resetQuery();
            $results = static::paginate($size, $page);
            if (empty($results)) {
                break;
            }
            if ($callback($results) === false) {
                break;
            }
            $page++;
        }
        return true;
    }

    /**
     * PAGINATE - Get paginated results
     */
    public static function paginate($perPage, $page)
    {
        $offset = ($page - 1) * $perPage;
        static::limit($perPage)->offset($offset);
        return static::get();
    }

    /**
     * UPSERT - Batch insert or update
     */
    public static function upsert($data, $uniqueColumns = null)
    {
        if ($uniqueColumns === null) {
            $uniqueColumns = [static::$primaryKey];
        }

        $instance = new static();
        $table = static::getTable();

        if (empty($data)) {
            return false;
        }

        // Ensure data is array of arrays
        if (!isset($data[0]) || !is_array($data[0])) {
            $data = [$data];
        }

        $columns = array_keys($data[0]);
        $placeholders = implode(',', array_fill(0, count($data), '(' . implode(',', array_fill(0, count($columns), '?')) . ')'));

        $flatValues = [];
        foreach ($data as $row) {
            foreach ($columns as $col) {
                $flatValues[] = $row[$col] ?? null;
            }
        }

        // Build ON DUPLICATE KEY UPDATE clause
        $updateClause = implode(', ', array_map(
            fn($col) => "$col = VALUES($col)",
            array_diff($columns, $uniqueColumns)
        ));

        $sql = "INSERT INTO " . $table . " (" . implode(',', $columns) . ") VALUES $placeholders";
        if (!empty($updateClause)) {
            $sql .= " ON DUPLICATE KEY UPDATE $updateClause";
        }

        $stmt = $instance->connection->prepare($sql);
        return $stmt->execute($flatValues);
    }

    /**
     * TRANSACTION - Execute query in transaction
     */
    public static function transaction(callable $callback)
    {
        $instance = new static();
        try {
            $instance->connection->beginTransaction();
            $callback($instance);
            $instance->connection->commit();
        } catch (\Exception $e) {
            $instance->connection->rollBack();
            throw $e;
        }
    }
}

