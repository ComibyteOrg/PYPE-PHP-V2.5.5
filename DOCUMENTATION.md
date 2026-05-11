# PYPE PHP Framework v2.5.5 - Complete Beginner's Guide

<div align="center">
  <img src="https://imgs.search.brave.com/a2QJ4QGpzGpXeDGHk1c-pL3FdZ-v47YnUIxeu4pjCe4/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9vbHV3YWRpbXUtYWRlZGVq/aS53ZWIuYXBwL2ltYWdlcy9sb2dvLnBu/Zw" alt="Pype PHP Framework" width="300">
  <br>
  <p>
    <img src="https://img.shields.io/badge/PHP-8.2%2B-blue?style=for-the-badge&logo=php" alt="PHP Version">
    <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License">
    <img src="https://img.shields.io/badge/Status-In%20Development-orange?style=for-the-badge" alt="Status">
  </p>
</div>

---

## 📚 Table of Contents

1. [What is Pype?](#what-is-pype)
2. [Installation & Setup](#installation--setup)
3. [Project Structure](#project-structure)
4. [Core Concepts](#core-concepts)
5. [Routing (URLs & Endpoints)](#routing-urls--endpoints)
6. [Models & Database](#models--database)
7. [Database Migrations](#database-migrations)
8. [Validation](#validation)
9. [Authentication](#authentication)
10. [JWT API Authentication](#jwt-api-authentication)
11. [Mail & Email](#mail--email)
12. [Views & Twig Templating](#views--twig-templating)
13. [Middleware](#middleware)
14. [Helpers & Utilities](#helpers--utilities)
15. [API Resources](#api-resources)
16. [API Versioning](#api-versioning)
17. [Real-Time Updates (SSE)](#real-time-updates-sse)
18. [File Uploads](#file-uploads)
19. [Common Examples](#common-examples)
20. [Troubleshooting](#troubleshooting)

---

## 🎯 What is Pype?

**Pype** is a modern PHP framework inspired by Django (Python's web framework). If you're new to PHP, think of Pype as a toolkit that makes web development much easier by:

- **Organizing your code** into Models, Views, and Controllers (MVC)
- **Handling database operations** without writing raw SQL
- **Managing user authentication** built-in
- **Routing URLs** to the right code
- **Validating user input** automatically
- **Sending emails** easily
- **Rendering HTML templates** with Twig

### Why Use Pype Instead of Plain PHP?

| Feature | Plain PHP | Pype |
|---------|-----------|------|
| Database queries | Write SQL manually | Use ORM (Object-Relational Mapping) |
| Routing | Use .htaccess | Elegant routing system |
| Authentication | Build from scratch | Built-in with sessions |
| Validation | Manual validation | Built-in validators |
| Templates | PHP files | Twig templates |

---

## 🚀 Installation & Setup

### Prerequisites

Before starting, make sure you have:
- **PHP 8.0+** (check with `php --version`)
- **Composer** (check with `composer --version`)
- **Git** (optional, for cloning)
- A **web server** (Apache/Nginx) or use PHP's built-in server

### Step 1: Get the Framework

**Option A: Clone from GitHub**
```bash
git clone https://github.com/ComibyteOrg/PYPE-PHP-V2.5.git my-project 
cd my-project 
```

**Option B: Download & Extract**
- Download the ZIP file
- Extract it to your project folder
- Open terminal in that folder

### Step 2: Install Dependencies

```bash
composer install
```

This downloads all the libraries Pype needs to work.

### Step 3: Initialize the Framework

```bash
php pype.php init
```

This creates necessary files and folders.

### Step 4: Configure Database (`.env` file)

Open `.env` file in the root directory and set your database:

**For SQLite (Easiest for beginners):**
```env
DB_TYPE=sqlite
DB_PATH=database.sqlite
```

**For MySQL:**
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=root
DB_PASS=password
```

**For PostgreSQL:**
```env
DB_TYPE=postgresql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=postgres
DB_PASS=password
DB_PORT=5432
```

### Step 5: Start Developing!

Run the development server:
```bash
php pype.php serve
```

Visit `http://localhost:8000` in your browser. You should see the welcome page!

---

## 📁 Project Structure

Here's how a Pype project is organized:

```
my-project/
├── Framework/           ← Core framework code (don't modify)
│   ├── Api/            ← API classes (JWT, SSE, Webhooks, etc.)
│   ├── Auth/           ← Authentication system
│   ├── Database/       ← Database & ORM
│   ├── Router/         ← Routing system
│   ├── Model/          ← Model base class
│   ├── Http/           ← Controllers & Resources
│   ├── Mail/           ← Email system
│   ├── Helper/         ← Utilities
│   ├── Security/       ← Security (encryption, 2FA, RBAC)
│   └── Middleware/     ← Middleware classes
├── App/                ← YOUR APPLICATION CODE
│   ├── Models/         ← Database models
│   ├── Controller/     ← Request handlers
│   ├── Middleware/     ← Custom middleware
│   └── Helpers/        ← Custom helpers
├── routes/
│   └── web.php         ← Define your URLs here
├── Resources/
│   └── views/          ← HTML templates (Twig)
├── Storage/
│   └── logs/           ← Application logs
├── .env                ← Configuration file
├── index.php           ← Entry point
└── pype.php            ← CLI commands
```

---

## 🧠 Core Concepts

### What is MVC?

**MVC** = Model, View, Controller. It's a way to organize code:

```
Request (URL) → Router → Controller → Model → Database
                                   ↓
                                View ← Database
                                  ↓
                            HTML Response
```

**Model**: Represents data (like a User, Post, Product)
**View**: HTML/template that users see
**Controller**: Logic that handles the request

### Example Flow:

1. User visits `/users/5`
2. Router sees this URL and calls `UserController@show`
3. Controller asks the `User` model for user #5
4. Model queries the database
5. Controller passes data to the View
6. View renders HTML and sends to user's browser

---

## 🛣️ Routing (URLs & Endpoints)

Routing is how you define URLs and connect them to code.

### File Location

Edit: `routes/web.php`

### Basic Routes

```php
<?php
use Framework\Router\Route;

// Simple GET route
Route::get('/', function() {
    return "Hello World!";
});

// Route that calls a controller method
Route::get('/users', 'UserController@index');

// Route with URL parameter
Route::get('/users/{id}', 'UserController@show');

// POST route (for forms)
Route::post('/users', 'UserController@store');

// PUT route (for updating)
Route::put('/users/{id}', 'UserController@update');

// DELETE route (for deleting)
Route::delete('/users/{id}', 'UserController@destroy');
```

### URL Parameters

Capture values from the URL:

```php
// Route: /users/5/posts/3
Route::get('/users/{userId}/posts/{postId}', function($userId, $postId) {
    return "User $userId, Post $postId";
});

// In controller:
Route::get('/products/{slug}', 'ProductController@show');

// In controller method:
public function show($slug) {
    $product = Product::where('slug', $slug)->first();
    // ... use $product
}
```

### Route Groups (Organizing Routes)

Group related routes together:

```php
// All admin routes
Route::group(['prefix' => 'admin', 'middleware' => 'auth'], function() {
    Route::get('/dashboard', 'AdminController@dashboard');
    Route::get('/users', 'AdminController@users');
});

// Results in URLs: /admin/dashboard, /admin/users
```

### Named Routes

Give routes names for easy linking:

```php
Route::get('/users', 'UserController@index')->name('users.index');
Route::get('/users/{id}', 'UserController@show')->name('users.show');
Route::post('/users', 'UserController@store')->name('users.store');

// In controller or view:
// Generates: /users
route('users.index')

// Generates: /users/5
route('users.show', ['id' => 5])
```

### RESTful Routes

Pype makes it easy to create full CRUD (Create, Read, Update, Delete) routes:

```php
// Manually create all CRUD routes:
Route::get('/posts', 'PostController@index');        // List all
Route::get('/posts/create', 'PostController@create'); // Show create form
Route::post('/posts', 'PostController@store');        // Save new
Route::get('/posts/{id}', 'PostController@show');     // View one
Route::get('/posts/{id}/edit', 'PostController@edit'); // Show edit form
Route::put('/posts/{id}', 'PostController@update');   // Save changes
Route::delete('/posts/{id}', 'PostController@destroy'); // Delete
```

### Middleware on Routes

Apply security checks or transformations:

```php
// Require authentication for this route
Route::get('/dashboard', 'DashboardController@index')->middleware('auth');

// Multiple middleware
Route::post('/admin', 'AdminController@store')
    ->middleware('auth')
    ->middleware('admin');

// All routes in group use middleware
Route::group(['middleware' => 'auth'], function() {
    Route::get('/profile', 'ProfileController@show');
    Route::post('/profile', 'ProfileController@update');
});
```

---

## 💾 Models & Database

### What is a Model?

A **Model** represents a database table and helps you query it without writing SQL.

### Creating a Model

Create file: `App/Models/User.php`

```php
<?php

namespace App\Models;

use Framework\Model\Model;

class User extends Model
{
    // Table name (optional - defaults to lowercase plural: "users")
    protected static $table = 'users';
    
    // Primary key (optional - defaults to "id")
    protected static $primaryKey = 'id';
    
    // Define table schema (used by migrations)
    public static function schema($table)
    {
        $table->id();                           // Auto-incrementing ID
        $table->string('name', 255);           // String field
        $table->string('email', 255)->unique(); // Unique email
        $table->string('password', 255);       // Password
        $table->string('phone', 20)->nullable(); // Optional phone
        $table->integer('age')->default(0);    // Integer with default
        $table->timestamps();                  // created_at, updated_at
    }
}
```

### Querying with Models

```php
// Get ALL users
$users = User::all();

// Get ONE user by ID
$user = User::find(1);

// Get first matching user
$user = User::where('email', 'john@example.com')->first();

// Get ALL matching users
$users = User::where('age', '>', 18)->get();

// Complex queries
$users = User::where('age', '>', 18)
    ->where('status', 'active')
    ->orderBy('name', 'asc')
    ->limit(10)
    ->get();

// Count results
$count = User::where('status', 'active')->count();

// Check if exists
if (User::where('email', 'john@example.com')->exists()) {
    echo "Email already taken";
}

// Get only specific columns
$users = User::select(['id', 'name', 'email'])->get();
```

### Creating & Saving Data

**Method 1: Using create()**
```php
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret123', PASSWORD_BCRYPT)
]);
```

**Method 2: Using new and save()**
```php
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->password = password_hash('secret123', PASSWORD_BCRYPT);
$user->save();
```

### Updating Data

```php
// Find and update
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Update multiple at once
User::where('status', 'inactive')->update([
    'status' => 'active'
]);

// Increment/Decrement
$user->increment('login_count');  // Add 1
$user->decrement('credits');       // Subtract 1
```

### Deleting Data

```php
// Delete one
$user = User::find(1);
$user->delete();

// Delete multiple
User::where('status', 'deleted')->delete();

// Delete all (be careful!)
User::truncate();
```

### Relationships Between Models

Models can be related to each other:

```php
// One User has Many Posts
class User extends Model
{
    public function posts()
    {
        return $this->hasMany('App\Models\Post', 'user_id');
    }
}

// One Post belongs to One User
class Post extends Model
{
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}

// Usage:
$user = User::find(1);
$posts = $user->posts(); // Get all posts by this user
```

---

## 🔄 Database Migrations

**Migrations** are like version control for your database. They let you create/modify tables using code.

### Create a Migration

```bash
php pype.php make:migration create_users_table
```

This creates: `migrations/2024_01_01_120000_create_users_table.php`

### Write Migration Code

```php
<?php

namespace Database\Migrations;

use Framework\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->createTable('users', function($table) {
            $table->id();                              // Auto-increment ID
            $table->string('name', 255);              // Text up to 255 chars
            $table->string('email', 255)->unique();   // Must be unique
            $table->string('password', 255);          // Hashed password
            $table->string('phone', 20)->nullable();  // Can be empty
            $table->integer('age')->default(0);       // Default to 0
            $table->text('bio')->nullable();           // Long text
            $table->enum('status', ['active', 'inactive']); // Pick one value
            $table->timestamps();                      // created_at, updated_at
        });
    }

    public function down()
    {
        $this->dropTable('users');
    }
}
```

### Field Types Available

```php
$table->id();                              // Auto-increment PRIMARY KEY
$table->string('name', 255);              // VARCHAR (up to 255 chars)
$table->text('description');              // TEXT (long strings)
$table->integer('count');                 // INTEGER
$table->float('price');                   // FLOAT
$table->boolean('active');                // BOOLEAN (true/false)
$table->date('birthday');                 // DATE (YYYY-MM-DD)
$table->timestamp('created_at');          // DATETIME
$table->timestamps();                     // created_at & updated_at
$table->json('metadata');                 // JSON data
$table->enum('status', ['a', 'b']);      // Choose one option
```

### Field Modifiers

```php
$table->string('email')->unique();        // Must be unique
$table->string('phone')->nullable();      // Can be NULL
$table->integer('age')->default(0);       // Default value
$table->string('slug')->index();          // Create index (faster queries)
```

### Run Migrations

```bash
# Run all pending migrations
php pype.php migrate

# Rollback last migration
php pype.php migrate:rollback

# Rollback all
php pype.php migrate:reset
```

---

## ✅ Validation

**Validation** checks if user input is correct before saving to database.

### Basic Validation

```php
use Framework\Helper\Validator;

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25
];

$rules = [
    'name' => 'required|alpha',      // Must exist, letters only
    'email' => 'required|email',     // Must exist, must be email
    'age' => 'required|numeric|min:18|max:100'  // Must be number, 18-100
];

$validator = Validator::make($data, $rules);

if ($validator->fails()) {
    $errors = $validator->errors();
    // Handle errors
} else {
    // Data is valid, save to database
    User::create($data);
}
```

### Validation Rules

```php
'field_name' => [
    'required',          // Field must exist and not be empty
    'email',            // Must be valid email format
    'numeric',          // Must be a number
    'integer',          // Must be whole number (no decimals)
    'alpha',            // Letters only
    'alpha_num',        // Letters and numbers only
    'alpha_dash',       // Letters, numbers, dashes, underscores
    'url',              // Must be valid URL
    'min:5',            // Minimum 5 characters
    'max:255',          // Maximum 255 characters
    'match:password',   // Must match another field (password confirm)
    'unique:table',     // Must be unique in database table
    'regex:/^[a-z]+$/', // Match regex pattern
]
```

### In Controller

```php
<?php

namespace App\Controller;

use Framework\Helper\Validator;

class UserController
{
    public function store()
    {
        $data = [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
        ];

        $rules = [
            'name' => 'required|alpha',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return [
                'success' => false,
                'errors' => $validator->errors()
            ];
        }

        // Data is valid
        User::create($data);
        return ['success' => true, 'message' => 'User created'];
    }
}
```

---

## 🔐 Authentication

**Authentication** manages user login/logout and sessions.

### Creating Users Table

Migration: `migrations/create_users_table.php`

```php
public function up()
{
    $this->createTable('users', function($table) {
        $table->id();
        $table->string('name', 255);
        $table->string('email', 255)->unique();
        $table->string('password', 255);
        $table->timestamps();
    });
}
```

### User Model

`App/Models/User.php`

```php
<?php

namespace App\Models;

use Framework\Model\Model;

class User extends Model
{
    protected static $table = 'users';

    public static function schema($table)
    {
        $table->id();
        $table->string('name', 255);
        $table->string('email', 255)->unique();
        $table->string('password', 255);
        $table->timestamps();
    }
}
```

### Login Route

`routes/web.php`

```php
Route::post('/login', function() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check credentials
    $user = User::where('email', $email)->first();
    
    if ($user && password_verify($password, $user->password)) {
        // Login successful
        $_SESSION['user_id'] = $user->id;
        return redirect('/dashboard');
    } else {
        // Login failed
        return redirect('/login?error=Invalid credentials');
    }
});
```

### Using Authentication

```php
use Framework\Helper\Auth;

// Login
Auth::table('users')->login($email, $password);

// Check if logged in
if (Auth::table('users')->check()) {
    echo "User is logged in";
}

// Get current user
$user = Auth::table('users')->user();
echo "Hello " . $user['name'];

// Logout
Auth::table('users')->logout();

// Login with remember me
Auth::table('users')->login($email, $password, true);
```

### Protecting Routes with Middleware

```php
// Require login
Route::get('/dashboard', 'DashboardController@index')->middleware('auth');

// For guests only (show login if already logged in)
Route::get('/login', 'AuthController@login')->middleware('guest');
```

### Social Login (OAuth)

Pype supports Google, GitHub, and Facebook login:

```php
// Routes already configured in index.php
// Users just visit:
// /auth/google
// /auth/github
// /auth/facebook
```

---

## 🔑 JWT API Authentication

For building APIs that work with React, Next.js, or mobile apps, use **JWT** (JSON Web Tokens) instead of sessions.

### Setup JWT

```php
// In your bootstrap file
use Framework\Api\Jwt;

Jwt::configure([
    'secret' => env('JWT_SECRET'),   // Set in .env
    'access_ttl' => 3600,             // 1 hour
    'refresh_ttl' => 604800,          // 7 days
]);
```

In `.env`:
```env
JWT_SECRET=your-random-64-char-hex-string
```

### Login — Return Token Pair

```php
Route::post('/api/auth/login', function() {
    $user = \App\Models\User::where('email', input('email'))->first();
    
    if (!$user || !password_verify(input('password'), $user->password)) {
        return json(['success' => false, 'message' => 'Invalid credentials'], 401);
    }

    // Generate access + refresh tokens
    $tokens = Jwt::createTokenPair([
        'sub' => $user->id,
        'user_id' => $user->id,
        'scopes' => ['read', 'write'],
    ]);

    return json(['success' => true, 'data' => $tokens]);
});
```

### Protect Routes with JWT Middleware

```php
use Framework\Api\JwtMiddleware;

// Require valid JWT
Route::get('/api/profile', function() {
    $userId = $_SERVER['JWT_USER_ID'];
    return json(['success' => true, 'data' => \App\Models\User::find($userId)]);
})->middleware(JwtMiddleware::class);

// Require specific permission scope
Route::post('/api/posts', function() {
    // Only users with 'write' scope can access
    return json(['success' => true, 'message' => 'Post created']);
})->middleware(JwtMiddleware::make(['write']));
```

### Refresh Tokens

```php
Route::post('/api/auth/refresh', function() {
    $newTokens = Jwt::refreshToken(input('refresh_token'));
    
    if ($newTokens === false) {
        return json(['success' => false, 'message' => 'Invalid token'], 401);
    }
    
    return json(['success' => true, 'data' => $newTokens]);
});
```

### Client-Side Usage (JavaScript)

```javascript
// After login, save tokens
localStorage.setItem('access_token', data.access_token);
localStorage.setItem('refresh_token', data.refresh_token);

// Send with every API request
fetch('/api/profile', {
    headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('access_token'),
        'Content-Type': 'application/json',
    },
});
```

---

## Mail & Email

**Mail** system lets you send emails to users.

### Configuration

In `.env` file:

```env
# Using SMTP
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_EMAIL=noreply@example.com
MAIL_FROM_NAME=My App

# Or just log emails (for testing)
MAIL_DRIVER=log
```

### Sending Email

```php
use Framework\Mail\Mailer;

// Send email
Mailer::send(
    'user@example.com',           // To
    'Welcome to our site!',       // Subject
    '<h1>Hello!</h1>Welcome to our site.'  // Body (HTML)
);

// With attachments
Mailer::send(
    'user@example.com',
    'Your invoice',
    'Please see attached invoice',
    ['/path/to/invoice.pdf']
);
```

### Email Service Helper

```php
use Framework\Helper\EmailService;

$email = EmailService::getInstance();
$email->send(
    'admin@example.com',
    'welcome.html',  // Template file in Resources/views/emails/
    [
        'name' => 'John',
        'activation_link' => 'https://example.com/activate/123'
    ]
);
```

### Email Template Example

`Resources/views/emails/welcome.html` (Using Twig)

```html
<h1>Welcome {{ name }}!</h1>
<p>Thank you for signing up. Click the link below to activate your account:</p>
<a href="{{ activation_link }}">Activate Account</a>
```

---

## 🎨 Views & Twig Templating

**Views** are HTML files that display data to users. Pype uses **Twig** templating engine.

### What is Twig?

Twig lets you write HTML with dynamic content using special tags like `{{ }}`, `{% %}`, etc.

### File Location

Put templates in: `Resources/views/`

### Basic Twig Syntax

```twig
{# Comments #}

{# Print variables #}
<h1>Hello {{ name }}</h1>

{# Print with defaults #}
<p>{{ title | default('No title') }}</p>
```

### Passing Data from Controller

```php
<?php

namespace App\Controller;

class BlogController
{
    public function show($id)
    {
        $post = Post::find($id);
        
        return view('posts.show', [
            'post' => $post,
            'title' => $post->title,
            'author' => $post->author
        ]);
    }
}
```

### Rendering Template

```php
// In route
Route::get('/posts/{id}', function($id) {
    $post = Post::find($id);
    return view('posts/show', ['post' => $post]);
});
```

The `view()` function finds `Resources/views/posts/show.html` and passes data.

### Template File

`Resources/views/posts/show.html`

```html
<!DOCTYPE html>
<html>
<head>
    <title>{{ post.title }}</title>
</head>
<body>
    <h1>{{ post.title }}</h1>
    <p>By {{ post.author }}</p>
    <article>{{ post.content }}</article>
</body>
</html>
```

### If/Else Statements

```twig
{% if user.loggedIn %}
    <p>Welcome back, {{ user.name }}!</p>
{% elseif user.hasToken %}
    <p>Please log in</p>
{% else %}
    <p>Guest user</p>
{% endif %}
```

### Loops

```twig
{# Loop through array #}
<ul>
{% for post in posts %}
    <li>
        <a href="/posts/{{ post.id }}">{{ post.title }}</a>
        <p>By {{ post.author }} - {{ post.createdAt }}</p>
    </li>
{% endfor %}
</ul>

{# If empty #}
{% if posts|length == 0 %}
    <p>No posts found</p>
{% endif %}
```

### Filters (Transform Data)

```twig
{{ text | upper }}              {# CONVERT TO UPPERCASE #}
{{ text | lower }}              {# convert to lowercase #}
{{ text | capitalize }}         {# Capitalize first letter #}
{{ text | length }}             {# Get string length #}
{{ text | slice(0, 10) }}      {# Get first 10 characters #}
{{ price | number_format(2) }}  {# Format as number #}
{{ date | date('Y-m-d') }}      {# Format date #}

{# Null coalescing (default value) #}
{{ undefined_var | default('Not set') }}
```

### Include Templates

Reuse parts of templates:

`Resources/views/header.html`
```html
<header>
    <h1>My Website</h1>
    <nav>...</nav>
</header>
```

`Resources/views/posts/show.html`
```twig
{% include 'header.html' %}

<article>
    <h1>{{ post.title }}</h1>
    <!-- content -->
</article>
```

### Template Inheritance

Create a base layout and extend it:

`Resources/views/layout.html`
```html
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}My Site{% endblock %}</title>
</head>
<body>
    <header>
        {% include 'header.html' %}
    </header>

    <main>
        {% block content %}{% endblock %}
    </main>

    <footer>
        {% include 'footer.html' %}
    </footer>
</body>
</html>
```

`Resources/views/posts/show.html`
```twig
{% extends "layout.html" %}

{% block title %}{{ post.title }} - My Blog{% endblock %}

{% block content %}
    <article>
        <h1>{{ post.title }}</h1>
        <p>{{ post.content }}</p>
    </article>
{% endblock %}
```

### More Twig Resources

For complete Twig documentation, visit: [Twig Official Documentation](https://twig.symfony.com/)

Key topics:
- [Functions](https://twig.symfony.com/doc/3.x/functions/index.html)
- [Filters](https://twig.symfony.com/doc/3.x/filters/index.html)
- [Control Structures](https://twig.symfony.com/doc/3.x/tags/index.html)

---

## 🧱 Middleware

**Middleware** intercepts requests and can allow or block them. Think of it as a security guard.

### Common Middleware

```php
// Authentication - Check if user is logged in
Route::get('/dashboard', 'DashboardController@index')->middleware('auth');

// Guest - For non-logged-in users only
Route::get('/login', 'AuthController@login')->middleware('guest');

// CSRF - Protect forms
Route::post('/users', 'UserController@store')->middleware('csrf');

// CORS - Cross-origin requests
Route::get('/api/users', 'UserController@index')->middleware('cors');

// Rate Limit - Prevent spam
Route::post('/contact', 'ContactController@send')->middleware('rate_limit');
```

### Creating Custom Middleware

Create file: `App/Middleware/AdminMiddleware.php`

```php
<?php

namespace App\Middleware;

class AdminMiddleware
{
    public function handle($request, $next)
    {
        // Check if user is admin
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            return $next($request);  // Allow
        }
        
        // Deny access
        return redirect('/login');
    }
}
```

### Register Custom Middleware

In `index.php`:

```php
Route::registerMiddleware('admin', 'App\Middleware\AdminMiddleware');
```

### Use Middleware

```php
Route::group(['prefix' => 'admin', 'middleware' => 'admin'], function() {
    Route::get('/dashboard', 'AdminController@dashboard');
    Route::get('/users', 'AdminController@users');
});
```

---

## 🛠️ Helpers & Utilities

### Useful Helper Functions

```php
use Framework\Helper\Helper;
use Framework\Helper\Validator;
use Framework\Helper\XSSProtection;

// Redirect
redirect('/home');
redirect('/home', 2);  // Wait 2 seconds

// Set alerts/messages
set_alert('success', 'User created successfully!');
set_alert('error', 'Something went wrong');

// Sanitize input (prevent XSS)
$name = sanitize($_POST['name']);

// Get form input (with default)
$email = Helper::input('email', 'default@example.com');

// Hash password
$hashed = password_hash('mypassword', PASSWORD_BCRYPT);

// Verify password
if (password_verify('mypassword', $hashed)) {
    echo "Password correct!";
}

// JSON response
returnJson([
    'success' => true,
    'data' => $user
]);

// Get excerpt (first X chars)
$summary = excerpt($longText, 150);

// Calculate reading time
echo readingTime($article) . " min read";

// Slug (user-friendly URL text)
$slug = slug('My Blog Post');  // Result: my-blog-post

// Generate UUID
$id = uuid();  // Result: 550e8400-e29b-41d4-a716-446655440000
```

### XSS Protection

Protect against malicious JavaScript injection:

```php
use Framework\Helper\XSSProtection;

$userInput = '<script>alert("hack")</script>';
$safe = XSSProtection::clean($userInput);
// Result: &lt;script&gt;alert("hack")&lt;/script&gt;

// In Twig (automatic)
{{ userComment }}  {# Automatically escaped #}
```

### Logging

Log debugging information:

```php
use Framework\Logging\Logger;

Logger::info('User logged in', ['user_id' => 5]);
Logger::warning('Low disk space');
Logger::error('Database connection failed', ['error' => $e->getMessage()]);
Logger::debug('Query took 100ms');

// Logs go to: Storage/logs/
```

---

## 📊 API Resources

**Resources** format database models into clean JSON for APIs.

### Creating a Resource

Create file: `App/Http/Resources/UserResource.php`

```php
<?php

namespace App\Http\Resources;

use Framework\Http\Resources\Resource;

class UserResource extends Resource
{
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            // Don't include password!
        ];
    }
}
```

### Using in Controller

```php
<?php

namespace App\Controller;

use App\Http\Resources\UserResource;
use Framework\Helper\ApiResponse;

class UserController
{
    public function index()
    {
        $users = User::all();
        
        // Return single resource
        return ApiResponse::success(
            UserResource::make($users[0]),
            "User retrieved"
        );
        
        // Return collection of resources
        return ApiResponse::success(
            UserResource::collection($users),
            "Users retrieved"
        );
    }

    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return ApiResponse::error("User not found", 404);
        }
        
        return ApiResponse::success(
            UserResource::make($user),
            "User retrieved"
        );
    }
}
```

### API Response Format

```json
{
    "success": true,
    "message": "Users retrieved",
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "created_at": "2024-01-15 10:30:00"
        }
    ]
}
```

---

## 🔄 API Versioning

When your API evolves, versioning lets you support old and new clients at the same time.

### URL-Based Versioning

```php
use Framework\Api\ApiVersion;

// Version 1 routes: /api/v1/posts
ApiVersion::group('v1', function() {
    Route::get('/posts', 'Api\V1\PostController@index');
});

// Version 2 routes: /api/v2/posts
ApiVersion::group('v2', function() {
    Route::get('/posts', 'Api\V2\PostController@index');
});
```

### Version Detection

The framework detects version from (in order):
1. Header: `X-API-Version: v1`
2. URL: `/api/v1/posts`
3. Query: `?api_version=v1`

```php
$currentVersion = api_version(); // Returns 'v1', 'v2', etc.
```

---

## 📡 Real-Time Updates (SSE)

**Server-Sent Events** let you push updates from server to browser without WebSockets.

### Server: Stream Events

```php
use Framework\Api\Sse;

Route::get('/api/notifications/stream', function() {
    Sse::stream(function($clientId, $lastId) {
        // Check for new notifications
        $new = checkForNewNotifications();
        if ($new) {
            return ['count' => count($new), 'items' => $new];
        }
        return null; // No new data this tick
    });
});
```

### Server: Broadcast to Channel

```php
// From anywhere in your code:
Sse::broadcast('chat', ['from' => 'Alice', 'message' => 'Hello!']);
```

### Client: Listen for Events

```javascript
const source = new EventSource('/api/notifications/stream');

source.addEventListener('message', (event) => {
    const data = JSON.parse(event.data);
    console.log('New notification:', data);
});

source.onerror = () => {
    console.log('Connection lost — browser will auto-reconnect');
};
```

---

## 📤 File Uploads

Handle user file uploads safely.

### HTML Form

```html
<form action="/upload" method="POST" enctype="multipart/form-data">
    <input type="file" name="avatar" accept="image/*" required>
    <button type="submit">Upload</button>
</form>
```

### Controller

```php
<?php

namespace App\Controller;

use Framework\Helper\FileUploader;

class ProfileController
{
    public function uploadAvatar()
    {
        if (!isset($_FILES['avatar'])) {
            return ['error' => 'No file provided'];
        }

        $uploader = FileUploader::getInstance();
        
        try {
            $file = $uploader->upload(
                $_FILES['avatar'],
                'uploads/avatars',      // Destination folder
                ['jpg', 'png', 'gif'],  // Allowed extensions
                2000000                 // Max size: 2MB
            );
            
            return [
                'success' => true,
                'path' => $file
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

### Validation for Files

```php
$rules = [
    'avatar' => 'file|mimes:jpg,png|max_size:2000000'
];

$validator = Validator::make($_FILES, $rules);

if ($validator->fails()) {
    echo "File upload failed: " . implode(', ', $validator->errors()['avatar']);
}
```

---

## 💡 Common Examples

### Example 1: Create a Blog

**1. Create Models**

`App/Models/Post.php`
```php
class Post extends Model
{
    protected static $table = 'posts';

    public static function schema($table)
    {
        $table->id();
        $table->string('title', 255);
        $table->text('content');
        $table->string('author', 255);
        $table->integer('views')->default(0);
        $table->timestamps();
    }
}
```

**2. Create Migration**

```bash
php pype.php make:migration create_posts_table
```

**3. Define Routes**

`routes/web.php`
```php
Route::get('/blog', 'PostController@index');        // All posts
Route::get('/blog/{id}', 'PostController@show');    // One post
Route::post('/blog', 'PostController@store');       // Create post
Route::put('/blog/{id}', 'PostController@update');  // Update post
Route::delete('/blog/{id}', 'PostController@destroy'); // Delete post
```

**4. Create Controller**

`App/Controller/PostController.php`
```php
<?php

namespace App\Controller;

use App\Models\Post;

class PostController
{
    public function index()
    {
        $posts = Post::orderBy('created_at', 'desc')->get();
        return view('blog/index', ['posts' => $posts]);
    }

    public function show($id)
    {
        $post = Post::find($id);
        if (!$post) return ['error' => 'Post not found'];
        
        $post->increment('views');
        return view('blog/show', ['post' => $post]);
    }

    public function store()
    {
        $data = [
            'title' => $_POST['title'],
            'content' => $_POST['content'],
            'author' => $_SESSION['user']['name']
        ];
        
        Post::create($data);
        return redirect('/blog?success=Post created');
    }
}
```

**5. Create Views**

`Resources/views/blog/index.html`
```twig
{% extends "layout.html" %}

{% block title %}Blog{% endblock %}

{% block content %}
    <h1>Blog Posts</h1>
    
    {% for post in posts %}
        <article>
            <h2><a href="/blog/{{ post.id }}">{{ post.title }}</a></h2>
            <p>By {{ post.author }} - {{ post.created_at|date('F j, Y') }}</p>
            <p>{{ post.content|slice(0, 200) }}...</p>
            <a href="/blog/{{ post.id }}">Read more →</a>
        </article>
    {% endfor %}
{% endblock %}
```

### Example 2: User Registration

**Routes:**
```php
Route::get('/register', 'AuthController@register');
Route::post('/register', 'AuthController@registerStore');
```

**Controller:**
```php
public function registerStore()
{
    $rules = [
        'name' => 'required|alpha',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8'
    ];

    $data = [
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'password' => $_POST['password']
    ];

    $validator = Validator::make($data, $rules);

    if ($validator->fails()) {
        return view('auth/register', [
            'errors' => $validator->errors(),
            'old' => $_POST
        ]);
    }

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => password_hash($data['password'], PASSWORD_BCRYPT)
    ]);

    // Log user in
    $_SESSION['user_id'] = $user->id;

    return redirect('/dashboard?success=Welcome!');
}
```

### Example 3: Admin Panel with Authentication

**Routes:**
```php
Route::group(['prefix' => 'admin', 'middleware' => 'auth'], function() {
    Route::get('/dashboard', 'AdminController@dashboard');
    Route::get('/users', 'AdminController@users');
    Route::delete('/users/{id}', 'AdminController@deleteUser');
});
```

**Controller:**
```php
class AdminController
{
    public function dashboard()
    {
        $userCount = User::count();
        $postCount = Post::count();
        
        return view('admin/dashboard', [
            'userCount' => $userCount,
            'postCount' => $postCount
        ]);
    }

    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return view('admin/users', ['users' => $users]);
    }

    public function deleteUser($id)
    {
        User::find($id)->delete();
        return redirect('/admin/users?success=User deleted');
    }
}
```

---

## 🐛 Troubleshooting

### Problem: "Class not found" Error

**Solution:** Make sure you're using the correct namespace.

```php
// WRONG
use User;

// CORRECT
use App\Models\User;
```

### Problem: Database Connection Error

**Solution:** Check your `.env` file:

```env
# Make sure these are correct:
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=root
DB_PASS=password
```

### Problem: Route Not Found (404)

**Solution:** Check your routes are registered:

1. Make sure your route is in `routes/web.php`
2. Make sure controller method exists
3. Check URL matches the route definition

```php
// This route:
Route::get('/users/{id}', 'UserController@show');

// Handles URLs like:
// /users/1  ✓
// /users/5  ✓
// /users    ✗ (wrong - needs id)
```

### Problem: Session Data Not Saving

**Solution:** Make sure sessions are started:

In `index.php`:
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### Problem: Validation Not Working

**Solution:** Check rule syntax:

```php
// WRONG
$rules = ['email' => 'required, email'];  // comma!

// CORRECT
$rules = ['email' => 'required|email'];   // pipe!
```

### Problem: Twig Template Not Found

**Solution:** Check file location and name:

```php
// Template must be in: Resources/views/blog/index.html
view('blog/index');

// NOT Resources/views/blog/index.php (wrong extension)
// NOT Resources/views/blog/index.html.php
```

### Problem: GET More Help

1. **Check logs:** `Storage/logs/`
2. **Enable debug:** In `.env` set `APP_DEBUG=true`
3. **Read errors:** PHP errors show helpful messages
4. **Google error:** Copy the error message and search

---

## 🎓 Next Steps

1. **Read Official Docs:** [Pype Framework GitHub](https://github.com/ComibyteOrg/PYPE-PHP-V2.5)
2. **Learn More Twig:** [Twig Official Docs](https://twig.symfony.com/)
3. **Build a Project:** Create a simple blog, to-do app, or portfolio
4. **Join Community:** Find help and share your projects

---

## 📞 Support

- **GitHub Issues:** Report bugs on GitHub
- **Documentation:** This guide and official README
- **Community:** Ask other developers

---

**Happy coding! 🚀**
