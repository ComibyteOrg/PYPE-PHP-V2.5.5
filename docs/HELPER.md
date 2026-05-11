# Helper Functions Guide

## Overview

Pype PHP provides a comprehensive set of global helper functions to simplify common tasks like input handling, redirects, CSRF protection, sessions, and more. These functions are available everywhere in your application.

---

## Request Helpers

### `input($key = null, $default = null)`

Get request input from POST or GET.

```php
// Get specific input
$name = input('name');
$email = input('email', 'default@example.com');

// Get all input
$all = input();
```

### `request($key = null, $default = null)`

Alias for `input()`.

```php
$name = request('name');
```

### `post($key = null, $default = null)`

Get POST data.

```php
$name = post('name');
$allPost = post(); // All POST data
```

### `get($key = null, $default = null)`

Get GET data.

```php
$search = get('search');
$allGet = get(); // All GET data
```

### `has($key)`

Check if request has a key.

```php
if (has('submit')) {
    // Form was submitted
}
```

### `method()`

Get the HTTP request method.

```php
$method = method(); // 'GET', 'POST', 'PUT', 'DELETE'
```

### `isAjax()`

Check if request is AJAX.

```php
if (isAjax()) {
    return json(['status' => 'success']);
}
```

---

## Response Helpers

### `redirect($url, $seconds = 0)`

Redirect to a URL.

```php
redirect('/dashboard');
redirect('/login', 3); // Redirect after 3 seconds
```

### `redirectWith($url, $key, $message = null, $seconds = 0)`

Redirect with flash message.

```php
redirectWith('/login', 'error', 'Invalid credentials');
redirectWith('/dashboard', 'success', 'Welcome back!');
```

### `json($data, $statusCode = 200)`

Return JSON response.

```php
json(['status' => 'success', 'data' => $users]);
json(['error' => 'Not found'], 404);
```

### `returnJson(array $data, int $statusCode = 200)`

Alias for `json()` (void return).

```php
returnJson(['message' => 'OK']);
```

### `abort($code, $message = '')`

Abort with HTTP error.

```php
abort(404, 'Page not found');
abort(403, 'Access denied');
```

---

## Authentication Helpers

### `auth($table = null)`

Get Auth instance.

```php
$auth = auth();           // Default users table
$auth = auth('admins');   // Admin table
```

### `check($table = 'users')`

Check if user is authenticated.

```php
if (check()) {
    // User is logged in
}
```

### `user($table = 'users')`

Get authenticated user.

```php
$user = user();
echo $user->name;
```

### `userId($table = 'users')`

Get authenticated user ID.

```php
$id = userId();
```

### `login($email, $password, $table = 'users', $remember = false)`

Login a user.

```php
$user = login($email, $password);
$user = login($email, $password, 'admins', true); // With remember me
```

### `register($data, $table = 'users', $autoLogin = true)`

Register a new user.

```php
$user = register([
    'name' => 'John',
    'email' => 'john@example.com',
    'password' => 'secret'
]);
```

### `logout($table = 'users')`

Logout user.

```php
logout();
```

### Admin Helpers

```php
isAdmin();              // Check if admin is logged in
admin();                // Get admin user
adminLogin($email, $password);
adminLogout();
```

---

## CSRF Protection

### `csrf_field()` / `csrfField()` / `csrfInput()`

Generate CSRF hidden input field for forms.

```html
<form method="POST" action="/submit">
    <?= csrf_field() ?>
    <!-- form fields -->
</form>
```

Output:
```html
<input type="hidden" name="_token" value="random_token_here">
```

### `csrf_token()`

Generate/get CSRF token.

```php
$token = csrf_token();
```

### `csrf_verify($token)`

Validate a CSRF token.

```php
if (csrf_verify($_POST['_token'])) {
    // Token is valid
}
```

---

## Session Helpers

### `session($key, $default = null)`

Get session value.

```php
$name = session('user_name');
$name = session('user_name', 'Guest'); // With default
```

### `old($key)`

Get old input (after redirect).

```html
<input type="text" name="email" value="<?= old('email') ?>">
```

### `flash($key, $message = null)`

Set or get flash message.

```php
// Set flash
flash('error', 'Something went wrong');

// Get flash
$error = flash('error');
```

### `getFlash($key)`

Get flash message.

```php
$message = getFlash('success');
```

### `set_alert($type, $message)`

Set session alert.

```php
set_alert('success', 'User created successfully');
set_alert('error', 'Failed to create user');
```

---

## Utility Helpers

### `dd(...$args)`

Dump and die - beautiful debug output.

```php
dd($user);
dd($users, $posts, $comments);
```

### `env($key, $default = null)`

Get environment variable.

```php
$dbHost = env('DB_HOST', 'localhost');
$appName = env('APP_NAME', 'My App');
```

### `now()`

Get current datetime.

```php
$now = now(); // 2024-01-15 10:30:00
```

### `today()`

Get current date.

```php
$date = today(); // 2024-01-15
```

### `strRandom($length = 16)`

Generate random string.

```php
$token = strRandom(32);
```

### `slugify($string, $separator = '-')`

Convert string to URL-friendly slug.

```php
$slug = slugify('Hello World!');        // hello-world
$slug = slugify('My Blog Post', '_');   // my_blog_post
```

### `excerpt($html, $length = 150, $suffix = '...')`

Generate text excerpt from HTML.

```php
$excerpt = excerpt($post->content, 200);
```

### `readingTime($content, $wpm = 200)`

Estimate reading time in minutes.

```php
$minutes = readingTime($articleContent);
```

---

## Password Helpers

### `hashPassword($password)` / `hash_password($password)`

Hash a password using Argon2ID.

```php
$hashed = hashPassword('secret123');
$hashed = hash_password('secret123');
```

### `verifyPassword($password, $hash)` / `verify_password($password, $hash)`

Verify a password against hash.

```php
if (verifyPassword($inputPassword, $user->password)) {
    // Password is correct
}
```

### `checkPasswordPolicy($password, $level = 'default', $name = '', $email = '')`

Validate password strength.

```php
$result = checkPasswordPolicy('MyP@ssw0rd!', 'strong', 'john', 'john@example.com');
if (!$result['valid']) {
    $errors = $result['errors'];
}
```

### `passwordStrength($password)`

Get password strength score and label.

```php
$strength = passwordStrength('MyP@ssw0rd!');
// ['score' => 85, 'strength' => 'Strong']
```

---

## Path Helpers

### `base_path($path = '')`

Get path relative to project root.

```php
$logFile = base_path('Storage/logs/app.log');
```

### `app_path($path = '')`

Get path relative to App directory.

```php
$controller = app_path('Controller/HomeController.php');
```

### `storage_path($path = '')`

Get path relative to Storage directory.

```php
$uploadPath = storage_path('uploads/avatars');
```

### `db_path()`

Get database path.

```php
$dbPath = db_path();
```

---

## URL & Asset Helpers

### `url($path, $parameters = [])`

Generate URL.

```php
$url = url('/users/profile');
$url = url('/users', ['id' => 1]);
```

### `asset($path)`

Generate asset URL.

```html
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<script src="<?= asset('js/app.js') ?>"></script>
<img src="<?= asset('images/logo.png') ?>">
```

---

## Database Helpers

### `db($table)`

Get DB query builder instance.

```php
$users = db('users')->get();
$user = db('users')->find(1);
```

### `table($table)`

Alias for `db()`.

```php
$users = table('users')->where('status', 'active')->get();
```

### `model($model)`

Get model instance.

```php
$user = model('User');
$users = model('User')->where('status', 'active')->get();
```

---

## File Upload Helper

### `upload($file, $directory, $allowedExtensions = [])`

Handle file uploads.

```php
$filePath = upload($_FILES['avatar'], 'uploads/avatars', ['jpg', 'png', 'gif']);
```

---

## Encryption Helpers

### `encrypt($data)` / `decrypt($encrypted)`

AES-256-GCM encryption for sensitive data.

```php
$encrypted = encrypt(['ssn' => '123-45-6789']);
$data = decrypt($encrypted);
```

### `encrypt_string($string)` / `decrypt_string($encrypted)`

String-only encryption.

```php
$encrypted = encrypt_string('sensitive text');
$decrypted = decrypt_string($encrypted);
```

---

## RBAC Helpers

### `can($permission, $subject = null)`

Check if current user has permission.

```php
if (can('edit_posts')) {
    // Allowed
}

if (can('update-post', $post)) {
    // Gate check
}
```

### `cannot($permission, $subject = null)`

Check if current user lacks permission.

```php
if (cannot('delete_users')) {
    abort(403);
}
```

---

## Audit Logging

### `audit_log($action, $entity, $entityId, $data = [], $severity = 'info')`

Log security-relevant actions.

```php
audit_log('created', 'post', $postId, ['title' => 'Hello']);
audit_log('login_failed', 'user', null, ['email' => $email], 'warning');
```

---

## Honeypot

### `honeypot_field($fieldName = 'honeypot')`

Generate invisible bot-trap field for forms.

```html
<form method="POST">
    <?= honeypot_field() ?>
    <!-- other fields -->
</form>
```

---

## API Key Helpers

### `generate_api_key($name, $userId, $scopes = [])`

Generate a new API key.

```php
$rawKey = generate_api_key('Production App', $userId, ['read', 'write']);
```

### `validate_api_key()`

Validate API key from request headers.

```php
$keyRecord = validate_api_key();
if (!$keyRecord) {
    abort(401);
}
```

---

## Text & Data Helpers

### `sanitize($input)`

Sanitize input data.

```php
$clean = sanitize($_POST['name']);
```

### `sanitize_input($input, $type = 'string')`

Context-aware input sanitization (15+ types).

```php
$email = sanitize_input($_POST['email'], 'email');
$age = sanitize_input($_POST['age'], 'int');
$url = sanitize_input($_POST['website'], 'url');
$slug = sanitize_input($_POST['slug'], 'slug');
```

### `sanitize_array($array, $rules = [])`

Sanitize multiple inputs at once.

```php
$clean = sanitize_array($_POST, [
    'name' => 'string',
    'email' => 'email',
    'age' => 'int',
]);
```

### `validate($data, $rules, $messages = [])`

Quick validation with 30+ rules.

```php
$result = validate($_POST, [
    'email' => 'required|email',
    'password' => 'required|min:8',
]);

if ($result->fails()) {
    $errors = $result->errors();
}
```

### `array_get($array, $key, $default = null)`

Get array value using dot notation.

```php
$value = array_get($data, 'user.name', 'Unknown');
```

### `writetxt($file_name, $values = [])`

Write to text file.

```php
writetxt('logs.txt', ['User logged in', time()]);
```

### `deletetxt($file_name, $cond)`

Delete from text file by condition.

```php
deletetxt('logs.txt', 'old_entry');
```

---

## Request Information

### `getClientIP()`

Get client IP address.

```php
$ip = getClientIP();
```

### `userAgent()`

Get user agent string.

```php
$agent = userAgent();
```

### `referer()`

Get referer URL.

```php
$from = referer();
```

---

## Quick Reference

| Function | Purpose |
|----------|---------|
| `input()` | Get request input |
| `redirect()` | Redirect URL |
| `redirectWith()` | Redirect with message |
| `json()` | JSON response |
| `abort()` | Error response |
| `check()` | Auth check |
| `user()` | Get user |
| `login()` | Login user |
| `logout()` | Logout user |
| `csrf_field()` | CSRF input |
| `session()` | Get session |
| `old()` | Old input |
| `flash()` | Flash message |
| `dd()` | Debug dump |
| `env()` | Env variable |
| `url()` | Generate URL |
| `asset()` | Asset URL |
| `db()` | Query builder |
| `upload()` | File upload |
| `now()` | Current datetime |
| `slugify()` | Create slug |
| `hashPassword()` | Hash password |
| `base_path()` | Root path |
| `storage_path()` | Storage path |
| `sanitize_input()` | Sanitize with type |
| `sanitize_array()` | Sanitize multiple |
| `validate()` | Quick validation |
| `checkPasswordPolicy()` | Password strength |
| `passwordStrength()` | Get strength score |
| `encrypt()` | AES-256-GCM encrypt |
| `decrypt()` | AES-256-GCM decrypt |
| `honeypot_field()` | Bot trap field |
| `can()` | RBAC permission check |
| `cannot()` | RBAC deny check |
| `audit_log()` | Log audit event |
| `generate_api_key()` | Generate API key |
| `validate_api_key()` | Validate API key |
| `verify_2fa()` | Verify 2FA code |
| `two_factor_uri()` | Get 2FA provisioning URI |
