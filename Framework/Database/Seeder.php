<?php

namespace Framework\Database;

use Framework\Helper\DB;

/**
 * Base Seeder Class
 * 
 * Example:
 * class UserSeeder extends Seeder {
 *     public function run() {
 *         $this->table('users')->insert([
 *             ['name' => 'John', 'email' => 'john@example.com'],
 *             ['name' => 'Jane', 'email' => 'jane@example.com'],
 *         ]);
 *     }
 * }
 */
class Seeder
{
    /**
     * Run the seeder
     * Override this method in your seeder classes
     */
    public function run()
    {
        // Override this method
    }

    /**
     * Get a database table instance
     * @param string $table
     * @return DB
     */
    protected function table(string $table)
    {
        return DB::table($table);
    }

    /**
     * Insert data into a table
     * @param string $table
     * @param array $data Single record or array of records
     * @return void
     */
    protected function insert(string $table, array $data)
    {
        // Check if it's a single record (associative array) or multiple records (array of arrays)
        if (!empty($data) && array_keys($data) === range(0, count($data) - 1)) {
            // Multiple records - insert each one
            foreach ($data as $record) {
                try {
                    $result = DB::table($table)->insert($record);
                    echo "  Inserted record into {$table}, ID: {$result}\n";
                } catch (\Exception $e) {
                    echo "  Error inserting: " . $e->getMessage() . "\n";
                }
            }
        } else {
            // Single record
            try {
                $result = DB::table($table)->insert($data);
                echo "  Inserted single record into {$table}, ID: {$result}\n";
            } catch (\Exception $e) {
                echo "  Error inserting: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Insert data with factory (for large datasets)
     * @param string $table
     * @param int $count
     * @param callable $factory
     * @return void
     */
    protected function factory(string $table, int $count, callable $factory)
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table($table)->insert($factory($i));
        }
    }

    /**
     * Truncate a table before seeding
     * @param string $table
     * @return void
     */
    protected function truncate(string $table)
    {
        DB::rawQuery("TRUNCATE TABLE {$table}");
    }
}
