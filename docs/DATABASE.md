# Database Guide

## Overview

Pype PHP provides a powerful database layer with support for **MySQL, PostgreSQL, and SQLite**. It includes a fluent query builder, migrations, seeders, and PDO-based connections with prepared statements for security.

## Configuration

### Environment Setup

Configure your database in the `.env` file:

**SQLite (Recommended for beginners):**
```env
DB_TYPE=sqlite
DB_PATH=database.sqlite
```

**MySQL:**
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=root
DB_PASS=password
DB_PORT=3306
```

**PostgreSQL:**
```env
DB_TYPE=postgresql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=postgres
DB_PASS=password
DB_PORT=5432
```

---

## DB Query Builder

The `DB` class provides a fluent interface for database operations.

### Basic Usage

```php
use Framework\Helper\DB;

// Get all records
$users = DB::table('users')->get();

// Find by ID
$user = DB::table('users')->find(1);

// First record
$user = DB::table('users')->first();
```

### Magic Table Method

```php
// DB::tablename() is a shortcut for DB::table('tablename')
$users = DB::users()->get();
$posts = DB::posts()->get();
```

### Helper Functions

```php
// Using db() helper
$users = db('users')->get();

// Using table() helper
$users = table('users')->get();
```

---

## Query Methods

### Selecting Data

```php
// Get all records
$users = DB::table('users')->get();

// Select specific columns
$users = DB::table('users')->select('name, email')->get();

// Get single column values
$emails = DB::table('users')->pluck('email');

// Get first record
$user = DB::table('users')->first();

// Find by ID
$user = DB::table('users')->find(1);

// Find or fail
$user = DB::table('users')->findOrFail(1);

// Count records
$count = DB::table('users')->count();

// Check if exists
$exists = DB::table('users')->where('email', 'john@example.com')->exists();
```

### Where Clauses

```php
// Basic where
$users = DB::table('users')->where('status', 'active')->get();

// Where with operator
$users = DB::table('users')->where('age', '>', 18)->get();

// Or where
$users = DB::table('users')
    ->where('status', 'active')
    ->orWhere('role', 'admin')
    ->get();

// Where IN
$users = DB::table('users')->whereIn('id', [1, 2, 3])->get();

// Where NOT IN
$users = DB::table('users')->whereNotIn('status', ['banned', 'deleted'])->get();

// Where BETWEEN
$users = DB::table('users')->whereBetween('age', 18, 65)->get();

// Where LIKE
$users = DB::table('users')->whereLike('name', 'John')->get();

// Where Starts With
$users = DB::table('users')->whereStartsWith('email', 'admin')->get();

// Where Ends With
$users = DB::table('users')->whereEndsWith('email', '@gmail.com')->get();

// Where NULL
$users = DB::table('users')->whereNull('deleted_at')->get();

// Where NOT NULL
$users = DB::table('users')->whereNotNull('email')->get();
```

### Ordering & Grouping

```php
// Order by
$users = DB::table('users')->orderBy('name', 'ASC')->get();
$users = DB::table('users')->orderBy('created_at', 'DESC')->get();

// Group by
$results = DB::table('orders')->groupBy('user_id')->get();

// Having
$results = DB::table('orders')
    ->groupBy('user_id')
    ->having('total', '>', 100)
    ->get();
```

### Limiting Results

```php
// Limit
$users = DB::table('users')->limit(10)->get();

// Offset (skip)
$users = DB::table('users')->skip(20)->limit(10)->get();

// Take (alias for limit)
$users = DB::table('users')->take(5)->get();
```

### Distinct Results

```php
$uniqueEmails = DB::table('users')->distinct()->pluck('email');
```

---

## CRUD Operations

### Create (Insert)

```php
// Insert single record
$id = DB::table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Create (alias for insert)
$id = DB::table('users')->create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);
```

### Read (Select)

```php
// Get all
$users = DB::table('users')->get();

// Find one
$user = DB::table('users')->find(1);

// Conditional
$users = DB::table('users')->where('status', 'active')->get();
```

### Update

```php
// Update with conditions
DB::table('users')->update(
    ['name' => 'Jane Smith', 'email' => 'jane@new.com'],
    ['id' => 1]
);

// Update or Create
DB::table('users')->updateOrCreate(
    ['email' => 'john@example.com'],  // Find condition
    ['name' => 'John Updated']        // Values to update/insert
);
```

### Delete

```php
// Delete with conditions
DB::table('users')->delete(['id' => 1]);

// Delete multiple
DB::table('users')->delete(['status' => 'inactive']);
```

---

## Aggregate Functions

```php
// Count
$count = DB::table('users')->count();

// Sum
$total = DB::table('orders')->sum('amount');

// Average
$avg = DB::table('orders')->avg('amount');

// Minimum
$min = DB::table('products')->min('price');

// Maximum
$max = DB::table('products')->max('price');
```

---

## Joins

```php
// Inner Join
$results = DB::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->get();

// Left Join
$results = DB::table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->get();

// Right Join
$results = DB::table('users')
    ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
    ->get();

// Multiple Joins
$results = DB::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
    ->get();

// Cross Join
$results = DB::table('colors')->crossJoin('sizes')->get();
```

---

## Advanced Features

### Pagination

```php
$page = $_GET['page'] ?? 1;
$perPage = 10;

$users = DB::table('users')->paginate($perPage, $page);
```

### Chunking (Large Datasets)

```php
DB::table('users')->chunk(100, function($users) {
    foreach ($users as $user) {
        // Process each user
        echo $user['name'];
    }
});
```

### Upsert (Insert or Update)

```php
DB::table('users')->upsert([
    ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
    ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
], ['id']); // Unique column
```

### Transactions

```php
DB::table('users')->transaction(function($db) {
    $db->table('users')->insert(['name' => 'John', 'email' => 'john@example.com']);
    $db->table('profiles')->insert(['user_id' => 1, 'bio' => 'Hello']);
    // If any query fails, all changes are rolled back
});
```

### Raw Queries

```php
// Instance method
$results = DB::table('users')->raw(
    'SELECT * FROM users WHERE status = ?',
    ['active']
);

// Static method
$stmt = DB::rawQuery('SELECT * FROM users WHERE id = ?', [1]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Debug Mode

```php
// Enable debug to see SQL queries
$users = DB::table('users')->debug()->where('status', 'active')->get();
// Outputs: SQL query and bind values
```

### Column Selection Helpers

```php
// Only specific columns
$users = DB::table('users')->only(['name', 'email'])->get();

// Except specific columns
$users = DB::table('users')->except(['password', 'remember_token'])->get();
```

---

## Migrations

Migrations allow version control for your database schema.

### CLI Commands

```bash
# Create a migration
php pype.php make:migration create_users_table

# Run migrations
php pype.php migrate

# Rollback last migration
php pype.php migrate:rollback
```

### Migration File Example

```php
<?php

return [
    'up' => function($table) {
        $table->id();
        $table->string('name', 255);
        $table->string('email', 255)->unique();
        $table->string('password', 255);
        $table->string('status', 50)->default('active');
        $table->timestamps();
    },
    'down' => function($table) {
        $table->drop();
    }
];
```

### Available Field Types

```php
$table->id();                          // Auto-increment primary key
$table->string('name', 255);           // VARCHAR
$table->text('description');           // TEXT
$table->integer('age');                // INT
$table->float('price');                // FLOAT
$table->boolean('active');             // BOOLEAN
$table->date('birth_date');            // DATE
$table->datetime('created_at');        // DATETIME
$table->timestamps();                  // created_at & updated_at
$table->unique('email');               // Unique constraint
$table->default('active');             // Default value
$table->nullable();                    // Allow NULL
```

---

## Seeders

Seeders populate your database with test data.

### CLI Commands

```bash
# Create a seeder
php pype.php make:seeder UserSeeder

# Run all seeders
php pype.php seed
```

### Seeder Example

```php
<?php

use Framework\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'status' => 'active'
        ]);

        // Insert multiple records
        for ($i = 1; $i <= 10; $i++) {
            DB::table('users')->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'status' => 'active'
            ]);
        }
    }
}
```

---

## Security

- All queries use **PDO prepared statements** (prevents SQL injection)
- Passwords should always be hashed with `password_hash()`
- Use environment variables for database credentials
- Never commit `.env` file to version control

---

## Quick Reference

| Method | Description |
|--------|-------------|
| `DB::table('name')` | Start query on table |
| `->get()` | Fetch all results |
| `->first()` | Fetch first result |
| `->find($id)` | Find by ID |
| `->where()` | Add WHERE clause |
| `->orWhere()` | Add OR WHERE clause |
| `->orderBy()` | Sort results |
| `->limit()` | Limit results |
| `->insert()` | Insert record |
| `->update()` | Update records |
| `->delete()` | Delete records |
| `->count()` | Count records |
| `->sum()` | Sum column |
| `->join()` | Join tables |
| `->paginate()` | Paginate results |
| `->chunk()` | Process in batches |
| `->transaction()` | Run in transaction |
| `->raw()` | Execute raw SQL |
