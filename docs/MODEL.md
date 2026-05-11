# Model System - Pype PHP v2.5.5

## Overview

The Pype PHP Model system provides a Laravel-like ORM experience with automatic file attachment capabilities, cascade deletes, and an intuitive fluent query builder.

## Table of Contents

- [Base Model](#base-model)
- [Query Builder](#query-builder)
- [HasFiles Trait](#hasfiles-trait)
- [Cascade Deletes](#cascade-deletes)
- [Migration Helper](#migration-helper)
- [Helper Functions](#helper-functions)

## Base Model

All models extend `Framework\Model\Model`. The base class provides:

- Automatic table name inference (e.g., `User` -> `users`)
- Primary key management (`id` by default)
- Instance creation, updates, and deletion
- Fluent query builder for complex queries

### Basic Model Definition

```php
<?php

namespace App\Models;

use Framework\Model\Model;
use Framework\Model\HasFiles;

class User extends Model
{
    use HasFiles;

    protected static $table = 'users';
    protected static $primaryKey = 'id';
    
    public static function schema($table)
    {
        $table->id();
        $table->string('name', 255);
        $table->string('email', 255);
        $table->timestamps();
    }
}
```

## Query Builder

The Model class includes a comprehensive fluent query builder:

### Retrieving Records

```php
// Get all records
$users = User::all();

// Find by primary key
$user = User::find(1);

// Find by column
$user = User::findBy('email', 'user@example.com');

// Get first record
$user = User::first();

// Filter with conditions
$activeUsers = User::filter(['status' => 1, 'is_active' => true]);

// Count records
$count = User::count();
```

### Advanced Queries

```php
// WHERE clauses
$users = User::where('status', 1)
    ->where('is_active', true)
    ->orWhere('role', 'admin')
    ->get();

// WHERE IN
$users = User::whereIn('id', [1, 2, 3])->get();

// WHERE BETWEEN
$users = User::whereBetween('age', 18, 65)->get();

// WHERE LIKE
$users = User::whereLike('name', 'John')->get();

// WHERE NULL / NOT NULL
$users = User::whereNull('deleted_at')->get();

// Ordering
$users = User::orderBy('created_at', 'DESC')->get();

// Grouping
$results = User::groupBy('status')->get();

// Limit and Offset
$users = User::limit(10)->offset(20)->get();

// Joins
$posts = Post::join('users', 'posts.user_id', '=', 'users.id')
    ->where('users.status', 1)
    ->get();

// Distinct results
$roles = User::distinct()->pluck('role');
```

### Aggregations

```php
$count = User::count();
$sum = User::sum('balance');
$avg = User::avg('age');
$min = User::min('age');
$max = User::max('age');
```

### Creating & Updating Records

```php
// Create new record
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Update by ID
User::updateRecord(1, ['name' => 'Jane Doe']);

// Save instance
$user = new User(['name' => 'John']);
$user->save();

// Update or Create
User::updateOrCreate(
    ['email' => 'john@example.com'],
    ['name' => 'John Doe']
);

// Bulk update
User::where('status', 0)->updateRows(['status' => 1]);

// Increment/Decrement
User::where('id', 1)->increment('login_count');
User::where('id', 1)->decrement('credits', 5);
```

### Deleting Records

```php
// Delete by ID
User::destroy(1);

// Delete instance
$user = User::find(1);
$user->remove();

// Delete with conditions
User::where('status', 0)->deleteRows();

// Truncate table
User::truncate();
```

### Pagination & Chunking

```php
// Paginate
$users = User::paginate(15, 1); // 15 per page, page 1

// Process in chunks
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});
```

### Transactions

```php
User::transaction(function ($db) {
    User::create(['name' => 'John']);
    User::create(['name' => 'Jane']);
});
```

## HasFiles Trait

The `HasFiles` trait provides Laravel-style file attachment capabilities for models. Files are automatically tracked in the database and cleaned up on model deletion.

### Setup

1. Run the migration to create the `model_files` table:

```php
use Framework\Model\ModelFilesMigration;

ModelFilesMigration::create();
```

2. Add the trait to your model:

```php
class User extends Model
{
    use HasFiles;
}
```

### Attaching Files

```php
$user = User::find(1);

// Attach uploaded file from $_FILES
$user->attachFile($_FILES['avatar'], 'avatar');

// Attach multiple files
$user->attachFile($_FILES['documents'], 'documents');

// Attach from local file path
$user->attachFileFromPath('/path/to/file.pdf', 'documents');

// Attach to specific disk
$user->attachFile($_FILES['photo'], 'photos', 's3');
```

### Retrieving Files

```php
// Get single file from collection
$avatar = $user->getFile('avatar');

// Get all files from collection
$documents = $user->getFiles('documents');

// Get all files across all collections
$allFiles = $user->getFiles();

// Get file URL
$avatarUrl = $user->getFileUrl('avatar');

// Get all URLs
$documentUrls = $user->getFileUrls('documents');

// Check if file exists
if ($user->hasFile('avatar')) {
    // User has an avatar
}
```

### Deleting Files

```php
// Delete single file from collection
$user->deleteFile('avatar');

// Delete all files from collection
$user->deleteFiles('documents');

// Delete all files across all collections (called automatically on model delete)
$user->deleteFiles();
```

### File Structure

Files are organized automatically:

```
storage/
└── user/
    └── avatar/
        └── 2026/
            └── 05/
                └── 05/
                    └── avatar_6a8f9c2d1e.jpg
```

## Cascade Deletes

When using the `HasFiles` trait, files are automatically deleted when the parent model is removed:

```php
// Cascade delete on instance removal
$user = User::find(1);
$user->remove(); // Deletes user AND all attached files

// Cascade delete on destroy
User::destroy(1); // Deletes user AND all attached files

// Cascade delete on bulk delete
User::where('status', 0)->deleteRows(); // Deletes users AND their files
```

### How It Works

The `HasFiles` trait overrides three methods:

1. `remove()` - Instance deletion
2. `destroy($id)` - Static deletion by ID
3. `deleteRows()` - Bulk deletion with conditions

Each method ensures all associated files are removed from storage before deleting the database records.

## Migration Helper

The `ModelFilesMigration` class creates the required `model_files` table:

```php
use Framework\Model\ModelFilesMigration;

// Create table
ModelFilesMigration::create();

// Drop table
ModelFilesMigration::drop();
```

### Table Schema

| Column        | Type         | Description                          |
|--------------|--------------|--------------------------------------|
| id           | INT PK       | Auto-increment primary key           |
| model_type   | VARCHAR(255) | Fully qualified model class name     |
| model_id     | INT          | ID of the parent model               |
| collection   | VARCHAR(100) | File collection name (e.g., 'avatar')|
| disk         | VARCHAR(50)  | Storage disk name (e.g., 'local')    |
| file_path    | VARCHAR(500) | Path within the storage disk         |
| original_name| VARCHAR(255) | Original uploaded file name          |
| mime_type    | VARCHAR(100) | File MIME type                       |
| file_size    | INT          | File size in bytes                   |
| created_at   | TIMESTAMP    | Upload timestamp                     |

### Indexes

- `idx_model_files_model` - (model_type, model_id)
- `idx_model_files_collection` - (model_type, model_id, collection)

## Helper Functions

### storage_upload()

One-line upload with automatic model creation and file attachment:

```php
// Create model and attach file in one call
$result = storage_upload(
    $_FILES['avatar'],      // Uploaded file
    User::class,            // Model class
    null,                   // Model ID (null = create new)
    'avatar',               // Collection name
    'local'                 // Storage disk
);

// Attach to existing model
$result = storage_upload(
    $_FILES['photo'],
    User::class,
    5,                      // User ID
    'photos'
);
```

### class_basename()

Get the class name without namespace:

```php
class_basename(\App\Models\User::class); // Returns "User"
```

### class_uses_recursive()

Get all traits used by a class (including parent classes):

```php
$traits = class_uses_recursive(User::class);
// Returns ['Framework\Model\HasFiles', ...]
```

## Examples

### Complete User Model with Files

```php
<?php

namespace App\Models;

use Framework\Model\Model;
use Framework\Model\HasFiles;

class User extends Model
{
    use HasFiles;

    protected static $table = 'users';

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 255);
        $table->string('email', 255);
        $table->string('password', 255);
        $table->timestamps();
    }

    public function getAvatarUrl(): ?string
    {
        return $this->getFileUrl('avatar');
    }

    public function getDocumentUrls(): array
    {
        return $this->getFileUrls('documents');
    }
}
```

### Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController
{
    public function store()
    {
        $user = User::create([
            'name' => request('name'),
            'email' => request('email'),
            'password' => hash_password(request('password')),
        ]);

        if (isset($_FILES['avatar'])) {
            $user->attachFile($_FILES['avatar'], 'avatar');
        }

        return redirect('/users/' . $user->id);
    }

    public function update($id)
    {
        $user = User::find($id);

        if (isset($_FILES['avatar'])) {
            // Delete old avatar if exists
            $user->deleteFile('avatar');
            // Attach new avatar
            $user->attachFile($_FILES['avatar'], 'avatar');
        }

        User::updateRecord($id, [
            'name' => request('name'),
            'email' => request('email'),
        ]);

        return redirect('/users/' . $id);
    }

    public function destroy($id)
    {
        // Automatically deletes user AND all attached files
        User::destroy($id);
        return redirect('/users');
    }
}
```

### Migration Setup

```php
<?php

// In your setup script or migration runner
require_once 'vendor/autoload.php';

use Framework\Model\ModelFilesMigration;

// Create the model_files table
ModelFilesMigration::create();

echo "model_files table created successfully.\n";
```
