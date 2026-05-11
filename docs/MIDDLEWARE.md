# Middleware Guide

## Overview

Middleware in Pype PHP provides a way to filter HTTP requests entering your application. They can check authentication, verify CSRF tokens, enforce rate limits, handle CORS, and more. Middleware runs before your controller logic executes.

---

## How Middleware Works

Middleware sits between the router and your controller:

```
Request → Middleware → Controller → Response
```

Each middleware can:
- **Allow** the request to continue
- **Modify** the request
- **Block** and redirect the request

---

## Built-in Middleware

### AuthMiddleware

Redirects unauthenticated users to login page.

```php
// Protect a route
Route::get('/dashboard', 'DashboardController@index')
    ->middleware(\Framework\Middleware\AuthMiddleware::class);

// Protect multiple routes
Route::group(['middleware' => \Framework\Middleware\AuthMiddleware::class], function() {
    Route::get('/dashboard', 'DashboardController@index');
    Route::get('/profile', 'ProfileController@show');
    Route::post('/settings', 'SettingsController@update');
});
```

### GuestMiddleware

Redirects authenticated users away from guest-only pages (like login).

```php
// Only show login to guests
Route::get('/login', 'AuthController@showLogin')
    ->middleware(\Framework\Middleware\GuestMiddleware::class);
```

### CsrfMiddleware

Validates CSRF tokens on POST requests.

```php
// Applied automatically to all POST routes
// No need to add manually
```

### CorsMiddleware

Handles Cross-Origin Resource Sharing headers.

```php
Route::group(['middleware' => \Framework\Middleware\CorsMiddleware::class], function() {
    Route::get('/api/data', 'ApiController@getData');
    Route::post('/api/submit', 'ApiController@submit');
});
```

### SecurityHeadersMiddleware

Automatically adds production-ready security headers (CSP, HSTS, X-Frame-Options, etc.).

```php
// Apply to all routes (recommended)
Route::group(['middleware' => \Framework\Middleware\SecurityHeadersMiddleware::class], function() {
    Route::get('/', 'HomeController@index');
});

// Use development config (relaxed CSP, no HSTS)
$dev = new \Framework\Middleware\SecurityHeadersMiddleware(
    \Framework\Middleware\SecurityHeadersMiddleware::developmentConfig()
);

// Use API config (minimal headers)
$api = new \Framework\Middleware\SecurityHeadersMiddleware(
    \Framework\Middleware\SecurityHeadersMiddleware::apiConfig()
);
```

**Headers applied:** Strict-Transport-Security, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Content-Security-Policy, Permissions-Policy.

### HoneypotMiddleware

Invisible form fields + timing analysis to block bots.

```php
// In form (view)
<form method="POST" action="/contact">
    <?= honeypot_field() ?>
    <!-- other fields -->
</form>

// Apply to route
Route::post('/contact', 'ContactController@submit')
    ->middleware(\Framework\Middleware\HoneypotMiddleware::class);
```

**How it works:** Hidden CSS field that bots fill but humans can't see; timing check blocks submissions faster than minimum time (default 3s).

### RateLimitMiddleware

Limits request frequency to prevent abuse.

```php
Route::post('/api/login', 'AuthController@login')
    ->middleware(\Framework\Middleware\RateLimitMiddleware::class);
```

---

## Creating Custom Middleware

### Step 1: Create Middleware Class

Place middleware in `App/Middleware/`:

```php
<?php

namespace App\Middleware;

class AdminMiddleware
{
    public function handle(array $params, callable $next)
    {
        // Check if user is admin
        if (!isAdmin()) {
            redirect('/login');
            exit;
        }

        // Continue to next middleware/controller
        return $next($params);
    }
}
```

### Step 2: Register Middleware Alias (Optional)

```php
// In routes/web.php or bootstrap file
Route::registerMiddleware('admin', \App\Middleware\AdminMiddleware::class);
```

### Step 3: Apply to Routes

```php
// Using class name
Route::get('/admin', 'AdminController@dashboard')
    ->middleware(\App\Middleware\AdminMiddleware::class);

// Using alias (if registered)
Route::get('/admin', 'AdminController@dashboard')
    ->middleware('admin');
```

---

## Middleware Examples

### Age Verification Middleware

```php
<?php

namespace App\Middleware;

class AgeVerificationMiddleware
{
    public function handle(array $params, callable $next)
    {
        if (!session('age_verified')) {
            redirect('/verify-age');
            exit;
        }

        return $next($params);
    }
}
```

### API Key Middleware

```php
<?php

namespace App\Middleware;

class ApiKeyMiddleware
{
    public function handle(array $params, callable $next)
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        
        if (!$apiKey || $apiKey !== env('API_KEY')) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        return $next($params);
    }
}
```

### Maintenance Mode Middleware

```php
<?php

namespace App\Middleware;

class MaintenanceMiddleware
{
    public function handle(array $params, callable $next)
    {
        if (env('MAINTENANCE_MODE') === 'true') {
            http_response_code(503);
            view('maintenance');
            exit;
        }

        return $next($params);
    }
}
```

### Logging Middleware

```php
<?php

namespace App\Middleware;

use Framework\Logging\Logger;

class RequestLogMiddleware
{
    public function handle(array $params, callable $next)
    {
        Logger::info('Request', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'url' => $_SERVER['REQUEST_URI'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);

        return $next($params);
    }
}
```

---

## Route Groups with Middleware

Apply middleware to multiple routes at once:

```php
// Admin routes
Route::group(['prefix' => 'admin', 'middleware' => \App\Middleware\AdminMiddleware::class], function() {
    Route::get('/', 'AdminController@dashboard');
    Route::get('/users', 'AdminController@users');
    Route::get('/settings', 'AdminController@settings');
    Route::post('/users/create', 'AdminController@createUser');
});

// API routes with CORS
Route::group(['prefix' => 'api', 'middleware' => \Framework\Middleware\CorsMiddleware::class], function() {
    Route::get('/users', 'Api\UserController@index');
    Route::get('/users/{id}', 'Api\UserController@show');
    Route::post('/users', 'Api\UserController@store');
});
```

---

## Multiple Middleware

Apply multiple middleware to a single route:

```php
Route::post('/admin/users/create', 'AdminController@createUser')
    ->middleware([
        \Framework\Middleware\AuthMiddleware::class,
        \App\Middleware\AdminMiddleware::class,
        \Framework\Middleware\RateLimitMiddleware::class
    ]);
```

---

## Registering Middleware Aliases

Register aliases for cleaner route definitions:

```php
Route::registerMiddleware('auth', \Framework\Middleware\AuthMiddleware::class);
Route::registerMiddleware('guest', \Framework\Middleware\GuestMiddleware::class);
Route::registerMiddleware('admin', \App\Middleware\AdminMiddleware::class);
Route::registerMiddleware('cors', \Framework\Middleware\CorsMiddleware::class);
Route::registerMiddleware('rate.limit', \Framework\Middleware\RateLimitMiddleware::class);

// Use aliases in routes
Route::get('/dashboard', 'DashboardController@index')->middleware('auth');
Route::get('/admin', 'AdminController@dashboard')->middleware(['auth', 'admin']);
```

---

## Middleware Signature

All middleware must implement a `handle` method:

```php
public function handle(array $params, callable $next)
{
    // Before controller logic
    
    // Continue to next middleware/controller
    $response = $next($params);
    
    // After controller logic (optional)
    
    return $response;
}
```

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$params` | array | Route parameters from URL |
| `$next` | callable | Next middleware or controller |

---

## Built-in Middleware Reference

| Middleware | Purpose | Location |
|------------|---------|----------|
| `AuthMiddleware` | Require authentication | `Framework/Middleware/AuthMiddleware.php` |
| `GuestMiddleware` | Redirect if authenticated | `Framework/Middleware/GuestMiddleware.php` |
| `CsrfMiddleware` | CSRF token validation | `Framework/Middleware/CsrfMiddleware.php` |
| `CorsMiddleware` | CORS headers | `Framework/Middleware/CorsMiddleware.php` |
| `RateLimitMiddleware` | Rate limiting | `Framework/Middleware/RateLimitMiddleware.php` |
| `SecurityHeadersMiddleware` | HTTP security headers | `Framework/Middleware/SecurityHeadersMiddleware.php` |
| `HoneypotMiddleware` | Bot protection | `Framework/Middleware/HoneypotMiddleware.php` |
| `TestMiddleware` | Example middleware | `Framework/Middleware/TestMiddleware.php` |

---

## Best Practices

1. **Keep middleware focused** - one responsibility per middleware
2. **Use aliases** for frequently used middleware
3. **Order matters** - middleware runs in the order applied
4. **Always call `$next()`** unless blocking the request
5. **Use `exit`** only when blocking (with redirect or response)
6. **Group related routes** with shared middleware
7. **Test middleware** independently from controllers
