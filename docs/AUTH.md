# Authentication Guide

## Overview

Pype PHP provides a universal authentication system that works with **any database table** - users, admins, members, or custom tables. The `Auth` class handles login, registration, session management, remember me tokens, and more.

## Quick Start

```php
use Framework\Helper\Auth;

// Login
$user = Auth::table('users')->login($email, $password);

// Check if authenticated
if (Auth::table('users')->check()) {
    $user = Auth::table('users')->user();
}

// Logout
Auth::table('users')->logout();
```

Or use the helper functions:

```php
// Login
$user = login($email, $password);

// Check
if (check()) {
    $user = user();
}

// Logout
logout();
```

---

## Configuration

### Default Settings

| Setting | Default Value | Description |
|---------|---------------|-------------|
| Table | `users` | Database table name |
| Email Column | `email` | Email/username column |
| Password Column | `password` | Password column |
| Session Key | `auth_user` | Session storage key |
| Cookie Name | `remember_me` | Remember me cookie |

### Custom Table Configuration

```php
// Authenticate against admins table
$auth = Auth::table('admins');

// Custom column names
$auth->columns('username', 'passwd');

// Custom session key
$auth->sessionKey('admin_session');

// Custom cookie name
$auth->cookieName('admin_remember');
```

---

## Methods

### `table(string $table)`

Set the database table to authenticate against.

```php
Auth::table('users');      // Default users table
Auth::table('admins');     // Admin authentication
Auth::table('members');    // Members table
```

### `login(string $email, string $password, bool $remember = false)`

Authenticate a user with email and password.

```php
// Basic login
$user = Auth::table('users')->login($email, $password);

// Login with remember me
$user = Auth::table('users')->login($email, $password, true);

// Helper function
$user = login($email, $password, 'users', true);
```

**Returns:** User object on success, `null` on failure.

### `register(array $data, bool $autoLogin = true)`

Register a new user. Password is automatically hashed.

```php
$user = Auth::table('users')->register([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123'
]);

// Register without auto-login
$user = Auth::table('users')->register($data, false);

// Helper function
$user = register([
    'name' => 'Jane',
    'email' => 'jane@example.com',
    'password' => 'password123'
]);
```

**Returns:** User object on success, `null` on failure.

### `check()`

Check if a user is currently authenticated.

```php
if (Auth::table('users')->check()) {
    // User is logged in
}

// Helper function
if (check()) {
    // User is logged in
}
```

### `user()`

Get the authenticated user object.

```php
$user = Auth::table('users')->user();
echo $user->name;
echo $user->email;

// Helper function
$user = user();
echo $user->name;
```

### `authenticate()`

Alias for `user()` - get the authenticated user.

```php
$user = Auth::table('users')->authenticate();
```

### `logout()`

Log out the current user and clear session/cookies.

```php
Auth::table('users')->logout();

// Helper function
logout();
```

### `id()`

Get the authenticated user's ID.

```php
$userId = Auth::table('users')->id();

// Helper function
$userId = userId();
```

---

## Admin Authentication

Pype includes built-in admin authentication helpers:

```php
// Check if admin is logged in
if (isAdmin()) {
    $admin = admin();
}

// Admin login
$admin = adminLogin($email, $password);

// Admin logout
adminLogout();
```

---

## Remember Me Feature

When login is called with `$remember = true`, a secure token is stored:

1. A 64-character random token is generated
2. Token is stored in `remember_me_tokens` database table
3. Token is set as a cookie valid for 30 days
4. On next visit, user is automatically authenticated

**Note:** The `remember_me_tokens` table must exist for this feature:

```php
// Create the table via migration
CREATE TABLE remember_me_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL
);
```

---

## Session Management

User data is stored in PHP sessions (password excluded):

```php
// Session key: auth_user (default)
$_SESSION['auth_user'] = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com'
    // password is NOT stored
];
```

---

## Security

### Password Hashing

Passwords are hashed using **Argon2ID** (the most secure algorithm available):

```php
use Framework\Security\PasswordPolicy;

// Hash (used automatically by Auth::register)
$hash = PasswordPolicy::hash('secret123');

// Verify (used automatically by Auth::login)
if (PasswordPolicy::verify($input, $user->password)) {
    // Correct
}

// Check if rehash needed (algorithm was upgraded)
if (PasswordPolicy::needsRehash($user->password)) {
    $user->password = PasswordPolicy::hash($input);
    $user->save();
}
```

### Password Policies

Enforce password strength requirements:

```php
// Validate against policy
$result = PasswordPolicy::validate($password, 'default');
// Returns: ['valid' => bool, 'errors' => [...], 'score' => int, 'strength' => string]

// Check if password was exposed in data breaches (HIBP API)
$breachCount = PasswordPolicy::isBreached($password);

// Prevent password reuse
if (!PasswordPolicy::checkHistory($newPassword, $oldHashes)) {
    echo "Cannot reuse a recent password.";
}

// Check expiry
if (PasswordPolicy::isExpired($user->password_changed_at)) {
    echo "Password expired. Please reset.";
}
```

**Strength Levels:**

| Level | Min Length | Requirements |
|-------|------------|--------------|
| `minimal` | 6 | Lowercase only |
| `default` | 8 | Upper, lower, number, special |
| `strong` | 12 | Upper, lower, 2 numbers, 2 special |
| `maximum` | 16 | Upper, lower, 3 numbers, 3 special |

### Brute Force Protection

Automatic lockout after failed attempts:

```php
use Framework\Security\BruteForceProtection;

// Check before login attempt
if (BruteForceProtection::isLocked($email)) {
    $remaining = BruteForceProtection::lockoutRemaining($email);
    echo "Locked. Try again in {$remaining}s.";
    return;
}

// On successful login
BruteForceProtection::clearAttempts($email);

// On failed login
$locked = BruteForceProtection::recordFailedAttempt($email);
```

**Default:** 5 attempts, 15 min lockout, 30 min decay. Configure with `BruteForceProtection::configure(5, 15, 30)`.

### Password Reset Tokens

Secure, single-use tokens with auto-expiry:

```php
use Framework\Security\PasswordReset;

// Generate token (send via email)
$token = PasswordReset::createToken($email);

// Validate and reset
$email = PasswordReset::validateToken($email, $token);
if ($email) {
    $user = \App\Models\User::findBy('email', $email);
    $user->password = PasswordPolicy::hash($newPassword);
    $user->save();
}
```

**Default:** 30 min expiry, max 3 active tokens per user.

---

## Two-Factor Authentication (2FA)

TOTP-based 2FA compatible with Google Authenticator, Authy, Microsoft Authenticator.

### Setup for a User

```php
use Framework\Security\TwoFactorAuth;

// Generate secret + backup codes
$setup = TwoFactorAuth::generateSecret();

// QR code URI (display as QR for user to scan)
$uri = TwoFactorAuth::getProvisioningUri($setup['secret'], 'MyApp', $user->email);

// Store in database
$user->two_factor_secret = TwoFactorAuth::hashSecret($setup['secret']);
$user->backup_codes = json_encode($setup['backup_codes']['hashes']);
$user->two_factor_enabled = true;
$user->save();
```

### Verify During Login

```php
// After password verification
if ($user->two_factor_enabled) {
    if (TwoFactorAuth::verify($user->two_factor_secret, $_POST['code'])) {
        // Valid - complete login
    } else {
        // Try backup codes
        $hashes = json_decode($user->backup_codes, true);
        $result = TwoFactorAuth::verifyBackupCode($hashes, $_POST['code']);
        if ($result) {
            $user->backup_codes = json_encode($result['remaining_codes']);
            $user->save();
            // Complete login
        }
    }
}
```

### Helper Functions

```php
$uri = two_factor_uri($secret, 'MyApp', 'user@example.com');
$valid = verify_2fa($secret, '123456');
```

---

## Social Login

Pype supports social authentication via Google, GitHub, and Facebook:

```php
// In routes/web.php
Route::socialAuth();

// Or manually:
Route::get('/auth/{provider}', [\Framework\Auth\SocialAuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [\Framework\Auth\SocialAuthController::class, 'handleProviderCallback']);
```

Configure providers in `.env`:

```env
GOOGLE_CLIENT_ID=your_id
GOOGLE_CLIENT_SECRET=your_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

GITHUB_CLIENT_ID=your_id
GITHUB_CLIENT_SECRET=your_secret
GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback
```

### Login Controller

```php
namespace App\Controller;

use Framework\Helper\Validator;
use Framework\Security\{BruteForceProtection, SessionGuard, AuditLog};

class AuthController
{
    public function showLogin()
    {
        view('auth.login');
    }

    public function handleLogin()
    {
        $email = input('email');
        $password = input('password');

        // Check brute force
        if (BruteForceProtection::isLocked($email)) {
            $seconds = BruteForceProtection::lockoutRemaining($email);
            redirectWith('/login', 'error', "Locked. Try again in {$seconds}s.");
            return;
        }

        $validator = Validator::make($_POST, [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            redirectWith('/login', 'error', 'Invalid credentials');
            return;
        }

        $remember = isset($_POST['remember']);
        $user = login($email, $password, 'users', $remember);

        if ($user) {
            BruteForceProtection::clearAttempts($email);
            SessionGuard::onLogin();
            AuditLog::login($user->id);
            redirect('/dashboard');
        } else {
            BruteForceProtection::recordFailedAttempt($email);
            AuditLog::loginFailed($email);
            $remaining = BruteForceProtection::remainingAttempts($email);
            redirectWith('/login', 'error', "Invalid. {$remaining} attempts left.");
        }
    }

    public function logout()
    {
        logout();
        redirect('/login');
    }
}
```

---

## Social Login

Pype supports social authentication via Google, GitHub, and Facebook:

```php
// In routes/web.php
Route::socialAuth();

// Or manually:
Route::get('/auth/{provider}', [\Framework\Auth\SocialAuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [\Framework\Auth\SocialAuthController::class, 'handleProviderCallback']);
```

Configure providers in `.env`:

```env
GOOGLE_CLIENT_ID=your_id
GOOGLE_CLIENT_SECRET=your_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

GITHUB_CLIENT_ID=your_id
GITHUB_CLIENT_SECRET=your_secret
GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback
```
