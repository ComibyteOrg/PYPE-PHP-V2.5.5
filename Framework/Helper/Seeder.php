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
     * @param array $data
     * @return void
     */
    protected function insert(string $table, array $data)
    {
        DB::table($table)->insert($data);
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
