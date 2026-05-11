# HTTP Guide - Controllers & API Resources

## Overview

The HTTP layer in Pype PHP handles request/response flow through Controllers and API Resources. Controllers manage page requests, while Resources are designed for building RESTful APIs.

---

## Controllers

Controllers are classes that handle HTTP requests and return responses.

### Creating a Controller

Place controllers in `App/Controller/`:

```php
<?php

namespace App\Controller;

class HomeController
{
    public function index()
    {
        return "Welcome to Pype PHP!";
    }

    public function about()
    {
        view('about');
    }
}
```

### Routing to Controllers

```php
// routes/web.php
use Framework\Router\Route;

// Controller@method syntax
Route::get('/', 'App\Controller\HomeController@index');
Route::get('/about', 'App\Controller\HomeController@about');
Route::post('/contact', 'App\Controller\HomeController@contact');
```

### Controller with Parameters

Route parameters are passed to controller methods:

```php
// Route
Route::get('/users/{id}', 'App\Controller\UserController@show');
Route::get('/posts/{slug}/edit', 'App\Controller\PostController@edit');

// Controller
class UserController
{
    public function show($id)
    {
        $user = \App\Models\User::find($id);
        view('users.show', ['user' => $user]);
    }
}

class PostController
{
    public function edit($slug)
    {
        $post = \App\Models\Post::findBy('slug', $slug);
        view('posts.edit', ['post' => $post]);
    }
}
```

### Complete Controller Example

```php
<?php

namespace App\Controller;

use App\Models\User;
use Framework\Helper\Validator;

class UserController
{
    // Display all users
    public function index()
    {
        $users = User::all();
        view('users.index', ['users' => $users]);
    }

    // Show single user
    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            abort(404, 'User not found');
        }
        
        view('users.show', ['user' => $user]);
    }

    // Show create form
    public function create()
    {
        view('users.create');
    }

    // Store new user
    public function store()
    {
        $validator = Validator::make($_POST, [
            'name' => 'required|alpha',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            redirectWith('/users/create', 'errors', $validator->errors());
            return;
        }

        User::create([
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password' => hashPassword($_POST['password'])
        ]);

        redirectWith('/users', 'success', 'User created successfully');
    }

    // Show edit form
    public function edit($id)
    {
        $user = User::find($id);
        view('users.edit', ['user' => $user]);
    }

    // Update user
    public function update($id)
    {
        $user = User::find($id);
        
        $user->name = $_POST['name'];
        $user->email = $_POST['email'];
        $user->save();

        redirectWith('/users', 'success', 'User updated');
    }

    // Delete user
    public function destroy($id)
    {
        User::destroy($id);
        redirectWith('/users', 'success', 'User deleted');
    }
}
```

---

## API Resources

API Resources are specialized controllers for building RESTful APIs with consistent JSON responses.

### Creating a Resource

Place resources in `Framework/Http/Resources/` or `App/`:

```php
<?php

namespace App\Resources;

class UserResource
{
    public function index()
    {
        $users = \App\Models\User::all();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => array_map(fn($u) => $u->toArray(), $users)
        ]);
    }

    public function show($id)
    {
        $user = \App\Models\User::find($id);
        
        if (!$user) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $user->toArray()
        ]);
    }

    public function store()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $user = \App\Models\User::create($data);
        
        header('Content-Type: application/json');
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $user->toArray(),
            'message' => 'User created'
        ]);
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $user = \App\Models\User::find($id);
        
        foreach ($data as $key => $value) {
            $user->$key = $value;
        }
        $user->save();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $user->toArray(),
            'message' => 'User updated'
        ]);
    }

    public function destroy($id)
    {
        \App\Models\User::destroy($id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'User deleted'
        ]);
    }
}
```

### API Routes

```php
// routes/web.php
Route::get('/api/users', 'App\Resources\UserResource@index');
Route::get('/api/users/{id}', 'App\Resources\UserResource@show');
Route::post('/api/users', 'App\Resources\UserResource@store');
Route::put('/api/users/{id}', 'App\Resources\UserResource@update');
Route::delete('/api/users/{id}', 'App\Resources\UserResource@destroy');
```

---

## Closures as Route Handlers

For simple routes, you can use closures instead of controllers:

```php
Route::get('/', function() {
    return "Hello World!";
});

Route::get('/about', function() {
    view('about');
});

Route::get('/users/{id}', function($id) {
    $user = \App\Models\User::find($id);
    view('users.show', ['user' => $user]);
});
```

---

## Request Handling

### Accessing Request Data

```php
class UserController
{
    public function store()
    {
        // Using $_POST directly
        $name = $_POST['name'];
        
        // Using helper functions
        $name = input('name');
        $name = post('name');
        $name = request('name');
        
        // Get all data
        $allData = post();
        $allData = request();
    }
}
```

### Method Spoofing

HTML forms only support GET and POST. Use method spoofing for PUT/DELETE:

```html
<form method="POST" action="/users/1">
    <input type="hidden" name="_method" value="PUT">
    <!-- form fields -->
</form>
```

The router automatically converts this to a PUT request.

---

## Response Types

### Return String

```php
public function index()
{
    return "Hello World";
}
```

### Render View

```php
public function index()
{
    view('home', ['title' => 'Home Page']);
}
```

### JSON Response

```php
public function api()
{
    json(['status' => 'success', 'data' => $data]);
}
```

### Redirect

```php
public function store()
{
    // Process...
    redirect('/dashboard');
}
```

---

## HTTP Status Codes

```php
// Set status code
http_response_code(200);  // OK
http_response_code(201);  // Created
http_response_code(400);  // Bad Request
http_response_code(401);  // Unauthorized
http_response_code(403);  // Forbidden
http_response_code(404);  // Not Found
http_response_code(500);  // Server Error
```

---

## Headers

```php
// Set headers
header('Content-Type: application/json');
header('X-Custom-Header: value');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
```

---

## API Key Authentication

Use the built-in `ApiKey` middleware for API routes:

```php
use Framework\Security\ApiKey;

// Manual validation in controller
public function index()
{
    $key = ApiKey::validate();
    if (!$key) {
        return json(['error' => 'Invalid API key'], 401);
    }
    
    if (!ApiKey::hasScope($key, 'read:posts')) {
        return json(['error' => 'Missing scope'], 403);
    }
    
    return json(['data' => \App\Models\Post::all()]);
}

// Or as route middleware
Route::get('/api/posts', function() {
    return json(['data' => \App\Models\Post::all()]);
})->middleware(function($params, $next) {
    return ApiKey::middleware($params, $next, ['read:posts']);
});
```

**Key features:** Secure generation, Argon2ID hashing (raw key never stored), scope-based authorization, rate limiting, rotation, and expiry.

---

## Quick Reference

| Component | Location | Purpose |
|-----------|----------|---------|
| Controllers | `App/Controller/` | Handle web requests |
| Resources | `App/Resources/` | Handle API requests |
| Route Closures | `routes/web.php` | Simple inline handlers |

| Helper | Purpose |
|--------|---------|
| `view()` | Render template |
| `json()` | Return JSON |
| `redirect()` | Redirect user |
| `input()` | Get request data |
| `abort()` | Error response |
