# Router Guide

## Overview

The Router in Pype PHP maps URLs to controllers, closures, or callbacks. It supports all HTTP methods, route parameters, middleware, route naming, and route groups with prefixes.

---

## Basic Routing

### Define Routes

Routes are defined in `routes/web.php`:

```php
<?php

use Framework\Router\Route;

// Simple GET route
Route::get('/', function() {
    return "Hello World!";
});

// Route to controller
Route::get('/about', 'App\Controller\HomeController@about');

// POST route
Route::post('/contact', 'App\Controller\HomeController@contact');

// PUT route
Route::put('/users/{id}', 'App\Controller\UserController@update');

// DELETE route
Route::delete('/users/{id}', 'App\Controller\UserController@destroy');
```

### Route Methods

| Method | Syntax |
|--------|--------|
| GET | `Route::get('/path', $handler)` |
| POST | `Route::post('/path', $handler)` |
| PUT | `Route::put('/path', $handler)` |
| DELETE | `Route::delete('/path', $handler)` |

---

## Route Handlers

### Closure Handler

```php
Route::get('/hello', function() {
    return "Hello!";
});

Route::get('/greet/{name}', function($name) {
    return "Hello, {$name}!";
});
```

### Controller Handler

```php
// Controller@method syntax
Route::get('/users', 'App\Controller\UserController@index');
Route::get('/users/{id}', 'App\Controller\UserController@show');
Route::post('/users', 'App\Controller\UserController@store');
```

### Alternative Controller Syntax

```php
// With explicit method name
use App\Controller\UserController;
Route::get("/users", [UserController::class, 'index'])
Route::get("/users", [UserController::class, 'index']);

---

## Route Parameters

### Required Parameters

```php
Route::get('/users/{id}', function($id) {
    return "User ID: {$id}";
});

Route::get('/posts/{slug}', 'App\Controller\PostController@show');
```

### Multiple Parameters

```php
Route::get('/users/{userId}/posts/{postId}', function($userId, $postId) {
    return "User: {$userId}, Post: {$postId}";
});
```

---

## Route Groups

Groups allow you to share prefixes and middleware across multiple routes.

### Prefix Groups

```php
Route::group(['prefix' => 'admin'], function() {
    Route::get('/', 'App\Controller\AdminController@dashboard');
    Route::get('/users', 'App\Controller\AdminController@users');
    Route::get('/settings', 'App\Controller\AdminController@settings');
});

// Routes: /admin, /admin/users, /admin/settings
```

### Nested Groups

```php
Route::group(['prefix' => 'api'], function() {
    Route::group(['prefix' => 'v1'], function() {
        Route::get('/users', 'Api\V1\UserController@index');
        Route::get('/posts', 'Api\V1\PostController@index');
    });
});

// Routes: /api/v1/users, /api/v1/posts
```

### Groups with Middleware

```php
Route::group([
    'prefix' => 'admin',
    'middleware' => \Framework\Middleware\AuthMiddleware::class
], function() {
    Route::get('/', 'AdminController@dashboard');
    Route::get('/users', 'AdminController@users');
});
```

---

## Middleware

### Route-level Middleware

```php
Route::get('/dashboard', 'DashboardController@index')
    ->middleware(\Framework\Middleware\AuthMiddleware::class);
```

### Multiple Middleware

```php
Route::post('/admin/users/create', 'AdminController@createUser')
    ->middleware([
        \Framework\Middleware\AuthMiddleware::class,
        \App\Middleware\AdminMiddleware::class
    ]);
```

### Register Middleware Aliases

```php
Route::registerMiddleware('auth', \Framework\Middleware\AuthMiddleware::class);
Route::registerMiddleware('admin', \App\Middleware\AdminMiddleware::class);

// Use alias
Route::get('/dashboard', 'DashboardController@index')
    ->middleware('auth');
```

---

## Named Routes

Name routes for easy URL generation.

### Define Named Route

```php
Route::get('/users/{id}/profile', 'UserController@profile')
    ->name('user.profile');

Route::get('/products/{slug}', 'ProductController@show')
    ->name('product.show');
```

### Generate URLs

```php
// In routes/controllers
$url = Route::getUrl('user.profile', ['id' => 1]);
// Returns: /users/1/profile

$url = Route::getUrl('product.show', ['slug' => 'my-product']);
// Returns: /products/my-product
```

---

## CSRF Protection

POST routes automatically enforce CSRF protection.

### Exempt Route from CSRF

```php
Route::post('/webhook', 'WebhookController@handle')
    ->csrfExempt();
```

### Add CSRF Token to Forms

```html
<form method="POST" action="/submit">
    <?= csrf_field() ?>
    <input type="text" name="name">
    <button type="submit">Submit</button>
</form>
```

---

## Method Spoofing

HTML forms only support GET and POST. Use `_method` field for PUT/DELETE:

```html
<form method="POST" action="/users/1">
    <input type="hidden" name="_method" value="PUT">
    <!-- form fields -->
</form>
```

The router converts this to a PUT request.

---

## Social Auth Routes

Quick setup for social authentication:

```php
Route::socialAuth();

// Creates:
// GET /auth/{provider}
// GET /auth/{provider}/callback
```

---

## View Configuration

### Set View Path

```php
Route::setViewPath(__DIR__ . '/../Resources/views');
```

### Get View Path

```php
$viewPath = Route::getViewPath();
```

---

## Error Handling

### 404 Not Found

Automatically displayed when no route matches:

```html
<div style='font-family: Arial;'>
    <h2>404</h2>
    <p>Page Not Found</p>
</div>
```

### 500 Server Error

Displayed when controller/method not found:

```html
<div style='font-family: Arial;'>
    <h2>500</h2>
    <p>Controller App\Controller\Foo not found</p>
</div>
```

---

## Dispatching Routes

The router automatically dispatches when included in `index.php`:

```php
// index.php
Route::dispatch();
```

This:
1. Gets the current request method and URI
2. Matches against defined routes
3. Runs middleware stack
4. Executes the matched handler
5. Returns the response

---

## Complete Example

```php
<?php

// routes/web.php
use Framework\Router\Route;

// Set view path
Route::setViewPath(__DIR__ . '/../Resources/views');

// Register middleware aliases
Route::registerMiddleware('auth', \Framework\Middleware\AuthMiddleware::class);
Route::registerMiddleware('guest', \Framework\Middleware\GuestMiddleware::class);
Route::registerMiddleware('admin', \App\Middleware\AdminMiddleware::class);

// Public routes
Route::get('/', 'App\Controller\HomeController@index')->name('home');
Route::get('/about', 'App\Controller\HomeController@about')->name('about');
Route::get('/contact', 'App\Controller\HomeController@showContact')->name('contact.show');
Route::post('/contact', 'App\Controller\HomeController@handleContact')->name('contact.submit');

// Auth routes (guest only)
Route::group(['prefix' => 'auth', 'middleware' => 'guest'], function() {
    Route::get('/login', 'App\Controller\AuthController@showLogin')->name('login');
    Route::post('/login', 'App\Controller\AuthController@handleLogin')->name('login.submit');
    Route::get('/register', 'App\Controller\AuthController@showRegister')->name('register');
    Route::post('/register', 'App\Controller\AuthController@handleRegister')->name('register.submit');
});

// Authenticated routes
Route::group(['middleware' => 'auth'], function() {
    Route::get('/dashboard', 'App\Controller\DashboardController@index')->name('dashboard');
    Route::get('/profile', 'App\Controller\ProfileController@show')->name('profile');
    Route::post('/logout', 'App\Controller\AuthController@logout')->name('logout');
});

// Admin routes
Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function() {
    Route::get('/', 'App\Controller\AdminController@dashboard')->name('admin.dashboard');
    Route::get('/users', 'App\Controller\AdminController@users')->name('admin.users');
    Route::post('/users/create', 'App\Controller\AdminController@createUser')->name('admin.users.create');
    Route::put('/users/{id}', 'App\Controller\AdminController@updateUser')->name('admin.users.update');
    Route::delete('/users/{id}', 'App\Controller\AdminController@deleteUser')->name('admin.users.delete');
});

// API routes
Route::group(['prefix' => 'api'], function() {
    Route::get('/users', 'App\Resources\UserResource@index');
    Route::get('/users/{id}', 'App\Resources\UserResource@show');
    Route::post('/users', 'App\Resources\UserResource@store');
    Route::put('/users/{id}', 'App\Resources\UserResource@update');
    Route::delete('/users/{id}', 'App\Resources\UserResource@destroy');
});

// Social login
Route::socialAuth();
```

---

## Quick Reference

| Method | Purpose |
|--------|---------|
| `Route::get()` | Define GET route |
| `Route::post()` | Define POST route |
| `Route::put()` | Define PUT route |
| `Route::delete()` | Define DELETE route |
| `Route::group()` | Group routes |
| `Route::dispatch()` | Process request |
| `Route::setViewPath()` | Set views directory |
| `Route::registerMiddleware()` | Register alias |
| `Route::socialAuth()` | Add social auth routes |
| `Route::getUrl()` | Generate named URL |
| `->middleware()` | Add middleware |
| `->name()` | Name route |
| `->csrfExempt()` | Skip CSRF |
