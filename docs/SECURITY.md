# Security Guide

## Overview

Pype PHP provides a **fortress-level security suite** covering authentication hardening, input validation, encryption, access control, audit logging, and more. This guide covers all Phase 1 security features.

---

## Table of Contents

1. [Input Sanitization](#input-sanitization)
2. [Enhanced Validation](#enhanced-validation)
3. [Password Policies & Hashing](#password-policies--hashing)
4. [Brute Force Protection](#brute-force-protection)
5. [Password Reset Tokens](#password-reset-tokens)
6. [Two-Factor Authentication (2FA)](#two-factor-authentication-2fa)
7. [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
8. [Session Security](#session-security)
9. [Encryption](#encryption)
10. [Secure File Uploads](#secure-file-uploads)
11. [Security Headers](#security-headers)
12. [Honeypot & Bot Protection](#honeypot--bot-protection)
13. [Audit Logging](#audit-logging)
14. [API Key Management](#api-key-management)

---

## Input Sanitization

The `InputSanitizer` provides context-aware sanitization for 15+ data types.

### Basic Usage

```php
use Framework\Helper\InputSanitizer;

// Sanitize with type
$name = InputSanitizer::sanitize($_POST['name'], 'string');
$email = InputSanitizer::sanitize($_POST['email'], 'email');
$age = InputSanitizer::sanitize($_POST['age'], 'int');
$price = InputSanitizer::sanitize($_POST['price'], 'float');
$url = InputSanitizer::sanitize($_POST['website'], 'url');
$phone = InputSanitizer::sanitize($_POST['phone'], 'phone');
$filename = InputSanitizer::sanitize($_FILES['avatar']['name'], 'filename');
$uuid = InputSanitizer::sanitize($_POST['id'], 'uuid');
$slug = InputSanitizer::sanitize($_POST['slug'], 'slug');
$ip = InputSanitizer::sanitize($_SERVER['REMOTE_ADDR'], 'ip');
```

### Sanitize Entire Arrays

```php
// With type rules
$clean = InputSanitizer::sanitizeArray($_POST, [
    'name' => 'string',
    'email' => 'email',
    'age' => 'int',
    'bio' => 'html',
    'website' => 'url',
]);

// Without rules (defaults to string)
$clean = InputSanitizer::sanitizeArray($_POST);
```

### Whitelist/Blacklist

```php
// Only allow specific fields
$data = InputSanitizer::only($_POST, ['name', 'email', 'password']);

// Remove specific fields
$data = InputSanitizer::except($_POST, ['is_admin', 'role']);

// Remove empty values
$data = InputSanitizer::stripEmpty($_POST);
```

### Available Types

| Type | Description |
|------|-------------|
| `string` | Trim, HTML encode, strip null bytes |
| `email` | Lowercase, filter sanitize email |
| `url` | Filter sanitize URL |
| `html` | HTMLPurifier sanitization |
| `int` | Validate and cast to integer |
| `float` | Validate and cast to float |
| `bool` | Cast to boolean |
| `filename` | Remove dangerous characters |
| `path` | Remove path traversal (../) |
| `sql` | Strip null/control bytes |
| `json` | Validate and decode JSON |
| `phone` | Strip non-phone characters |
| `ip` | Validate IP address |
| `uuid` | Strip non-UUID characters |
| `slug` | URL-friendly slug |

### Custom Sanitizer Rules

```php
InputSanitizer::registerRule('username', function($value) {
    return preg_replace('/[^a-z0-9_]/i', '', strtolower($value));
});

$username = InputSanitizer::applyCustom('username', $_POST['username']);
```

### Helper Functions

```php
$name = sanitize_input($_POST['name'], 'string');
$email = sanitize_input($_POST['email'], 'email');
$clean = sanitize_array($_POST, ['name' => 'string', 'email' => 'email']);
```

---

## Enhanced Validation

The `EnhancedValidator` supports **30+ validation rules** with nested array support and custom messages.

### Basic Usage

```php
use Framework\Helper\EnhancedValidator;

$validator = EnhancedValidator::make($_POST, [
    'name' => 'required|min:3|max:100',
    'email' => 'required|email|unique:users',
    'password' => 'required|strong_password|confirmed',
    'age' => 'required|integer|min:18|max:120',
    'website' => 'url',
    'terms' => 'accepted',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
}
```

### All Available Rules

| Rule | Description |
|------|-------------|
| `required` | Field must not be empty |
| `email` | Valid email address |
| `url` | Valid URL |
| `numeric` | Any number |
| `integer` | Integer only |
| `alpha` | Letters only |
| `alpha_num` | Letters and numbers |
| `alpha_dash` | Letters, numbers, dashes, underscores |
| `min:n` | Minimum length/value |
| `max:n` | Maximum length/value |
| `between:min,max` | Between two values |
| `size:n` | Exact length |
| `in:a,b,c` | Must be in list |
| `not_in:a,b,c` | Must not be in list |
| `regex:pattern` | Match regex pattern |
| `confirmed` | Must match `field_confirmation` |
| `same:field` | Must match another field |
| `different:field` | Must differ from another field |
| `unique:table,column` | Must not exist in database |
| `exists:table,column` | Must exist in database |
| `ip` | Valid IP address |
| `ipv4` | Valid IPv4 |
| `ipv6` | Valid IPv6 |
| `json` | Valid JSON string |
| `uuid` | Valid UUID |
| `date` | Valid date |
| `before:date` | Date must be before |
| `after:date` | Date must be after |
| `file` | Must be uploaded file |
| `image` | Must be image file |
| `mimes:jpg,png` | Allowed MIME types |
| `phone` | Valid phone number |
| `password` | Meets password policy |
| `strong_password` | Meets strong password policy |
| `nullable` | Field can be null |
| `required_if:field,value` | Required if another field equals value |
| `required_unless:field,value` | Required unless another field equals value |
| `required_with:field` | Required if another field is present |

### Custom Error Messages

```php
$validator = EnhancedValidator::make($_POST, [
    'email' => 'required|email',
    'password' => 'required|min:8',
], [
    'email.required' => 'Please enter your email address.',
    'email.email' => 'That email address doesn\'t look right.',
    'password.min' => 'Password must be at least 8 characters.',
    'password.required' => 'Password is required.',
]);
```

### Nested Array Validation

```php
$validator = EnhancedValidator::make($_POST, [
    'user.name' => 'required|min:3',
    'user.email' => 'required|email',
    'address.street' => 'required',
    'address.city' => 'required',
]);
```

### Conditional Validation

```php
$validator = EnhancedValidator::make($_POST, [
    'type' => 'required|in:personal,business',
    'company_name' => 'required_if:type,business',
    'tax_id' => 'required_if:type,business',
    'referral_code' => 'required_with:referrer_name',
]);
```

### Helper Function

```php
$result = validate($_POST, [
    'email' => 'required|email',
    'password' => 'required|min:8',
]);

if ($result->fails()) {
    $errors = $result->errors();
}
```

---

## Password Policies & Hashing

### Password Strength Levels

```php
use Framework\Security\PasswordPolicy;

// Validate against policy
$result = PasswordPolicy::validate($password, 'default');

// Returns:
// [
//     'valid' => true/false,
//     'errors' => ['Password must contain...'],
//     'score' => 85,
//     'strength' => 'Strong'
// ]
```

### Strength Levels

| Level | Min Length | Requirements | Use Case |
|-------|------------|--------------|----------|
| `minimal` | 6 | Lowercase only | Internal tools |
| `default` | 8 | Upper, lower, number, special | Standard apps |
| `strong` | 12 | Upper, lower, 2 numbers, 2 special, no patterns | Social media, blogs |
| `maximum` | 16 | Upper, lower, 3 numbers, 3 special, max 1 repeated char | E-commerce, finance |

### Hashing & Verification

```php
// Hash with Argon2ID (or configured algorithm)
$hash = PasswordPolicy::hash('MyP@ssw0rd!');

// Verify
if (PasswordPolicy::verify($input, $hash)) {
    // Password is correct
}

// Check if hash needs rehashing (algorithm changed)
if (PasswordPolicy::needsRehash($hash)) {
    $newHash = PasswordPolicy::hash($password);
    // Update database
}
```

### Breach Checking

```php
// Check if password was exposed in data breaches (HIBP API)
$breachCount = PasswordPolicy::isBreached($password);

if ($breachCount > 0) {
    echo "This password was found in {$breachCount} data breaches!";
}
```

### Password History

```php
// Check if password was used recently
$oldHashes = ['hash1', 'hash2', 'hash3']; // From database

if (!PasswordPolicy::checkHistory($newPassword, $oldHashes)) {
    echo "You cannot reuse a recent password.";
}
```

### Password Expiry

```php
// Check if password has expired
if (PasswordPolicy::isExpired($user->password_changed_at)) {
    echo "Your password has expired. Please reset it.";
}

// Days until expiry
$days = PasswordPolicy::daysUntilExpiry($user->password_changed_at);
```

### Configure Policy

```php
PasswordPolicy::configure([
    'min_length' => 10,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_special' => true,
    'min_special_chars' => 2,
    'max_repeated_chars' => 2,
    'prevent_common' => true,
    'history_count' => 10,
    'expiry_days' => 60,
    'algorithm' => PASSWORD_ARGON2ID,
]);
```

### Helper Functions

```php
$hash = hash_password('secret');
$valid = verify_password('secret', $hash);
$result = check_password_policy('MyP@ss', 'strong', 'john', 'john@example.com');
$strength = password_strength('MyP@ssw0rd!');
```

---

## Brute Force Protection

Tracks failed login attempts and enforces configurable lockout thresholds.

### Basic Usage

```php
use Framework\Security\BruteForceProtection;

// On login attempt
if (BruteForceProtection::isLocked($email)) {
    $remaining = BruteForceProtection::lockoutRemaining($email);
    echo "Account locked. Try again in {$remaining} seconds.";
    return;
}

// Validate credentials
if (verify_password($password, $user->password)) {
    BruteForceProtection::clearAttempts($email);
    // Login successful
} else {
    $locked = BruteForceProtection::recordFailedAttempt($email);
    
    if ($locked) {
        echo "Account locked due to too many failed attempts.";
    } else {
        $remaining = BruteForceProtection::remainingAttempts($email);
        echo "Invalid credentials. {$remaining} attempts remaining.";
    }
}
```

### Configuration

```php
// Lock after 5 failed attempts, 15 minute lockout, 30 minute decay
BruteForceProtection::configure(
    maxAttempts: 5,
    lockoutMinutes: 15,
    decayMinutes: 30
);
```

---

## Password Reset Tokens

Secure password reset with Argon2ID-hashed tokens, single-use, and auto-expiry.

### Generate Token

```php
use Framework\Security\PasswordReset;

// Create reset token (send this to user via email)
$token = PasswordReset::createToken($user->email);

if ($token) {
    // Send email with reset link
    $link = url("/reset-password?token={$token}&email={$user->email}");
    Mailer::send($user->email, 'Reset Password', "Click: {$link}");
}
```

### Validate Token

```php
// Validate and get email
$email = PasswordReset::validateToken($email, $token);

if ($email) {
    // Token is valid, allow password reset
    // Token is automatically deleted (single-use)
    $user = User::findBy('email', $email);
    $user->password = hash_password($newPassword);
    $user->save();
    
    // Clean up any remaining tokens
    PasswordReset::deleteTokens($email);
}
```

### Configuration

```php
// 30 minute expiry, max 3 active tokens per user
PasswordReset::configure(expiryMinutes: 30, maxTokens: 3);

// Check if user has active token
if (PasswordReset::hasActiveToken($email)) {
    echo "A reset link was already sent.";
}
```

---

## Two-Factor Authentication (2FA)

TOTP-based 2FA compatible with Google Authenticator, Authy, Microsoft Authenticator.

### Setup 2FA for a User

```php
use Framework\Security\TwoFactorAuth;

// Generate secret and backup codes
$setup = TwoFactorAuth::generateSecret();

// $setup contains:
// - 'secret': Binary secret (store in database)
// - 'base32': Base32 encoded secret (show to user)
// - 'backup_codes': ['codes' => [...], 'hashes' => [...]]

// Generate QR code URI
$uri = TwoFactorAuth::getProvisioningUri(
    $setup['secret'],
    'MyApp',
    $user->email
);

// Display URI as QR code (use a QR library)
// User scans with their authenticator app

// Store backup code hashes in database
$user->two_factor_secret = TwoFactorAuth::hashSecret($setup['secret']);
$user->backup_codes = json_encode($setup['backup_codes']['hashes']);
$user->save();
```

### Verify 2FA Code

```php
// During login (after password verification)
if ($user->two_factor_enabled) {
    $code = $_POST['code']; // From user's authenticator app
    
    // Verify against stored hash
    if (TwoFactorAuth::verify($user->two_factor_secret, $code)) {
        // 2FA valid, complete login
    } else {
        // Try backup codes
        $backupHashes = json_decode($user->backup_codes, true);
        $result = TwoFactorAuth::verifyBackupCode($backupHashes, $code);
        
        if ($result) {
            // Backup code valid, update stored hashes
            $user->backup_codes = json_encode($result['remaining_codes']);
            $user->save();
            // Complete login
        } else {
            echo "Invalid code";
        }
    }
}
```

### Disable 2FA

```php
$user->two_factor_enabled = false;
$user->two_factor_secret = null;
$user->backup_codes = null;
$user->save();
```

### Helper Functions

```php
$uri = two_factor_uri($secret, 'MyApp', 'user@example.com');
$valid = verify_2fa($secret, '123456');
```

---

## Role-Based Access Control (RBAC)

Granular permissions with roles, gates, and policies.

### Define Roles & Permissions

```php
use Framework\Security\RBAC;

// Seed default roles and permissions
RBAC::seedDefaults();
// Creates: superadmin, admin, moderator, user, guest

// Or define manually
RBAC::defineRole('editor', 'Can edit content');
RBAC::definePermission('edit_posts');
RBAC::definePermission('publish_posts');
RBAC::grant('editor', 'edit_posts');
RBAC::grant('editor', 'publish_posts');
```

### Assign Roles to Users

```php
// Assign role
RBAC::assignRole($userId, 'editor');

// Remove role
RBAC::removeRole($userId, 'editor');

// Get user roles
$roles = RBAC::getUserRoles($userId);
```

### Check Permissions

```php
// Check if user has permission
if (RBAC::userHasPermission($userId, 'edit_posts')) {
    // Allow editing
}

// Check if user has role
if (RBAC::userHasRole($userId, 'admin')) {
    // Admin only area
}

// Check if role has permission
if (RBAC::roleHasPermission('editor', 'edit_posts')) {
    // ...
}
```

### Gates (Closure-based Authorization)

```php
// Define a gate
RBAC::gate('update-post', function($userId, $post) {
    return $post->author_id === $userId;
});

// Check gate
if (RBAC::allows('update-post', $post)) {
    // User can update this post
}

if (RBAC::denies('update-post', $post)) {
    abort(403);
}

// Throws exception if not allowed
RBAC::authorize('update-post', $post);

// Alias
if (can('update-post', $post)) {
    // ...
}
```

### Database Persistence

```php
// Initialize RBAC from database
RBAC::init(useDatabase: true);

// Save role-permission pair
RBAC::saveRolePermission('editor', 'edit_posts');
```

### Helper Functions

```php
if (can('edit_posts')) { /* ... */ }
if (cannot('delete_users')) { /* ... */ }
```

---

## Session Security

Comprehensive session hardening with fixation protection, concurrent limits, and device tracking.

### Start Secure Session

```php
use Framework\Security\SessionGuard;

// Call once at application start
SessionGuard::start([
    'secure' => true,           // HTTPS only cookies
    'httponly' => true,         // No JavaScript access
    'samesite' => 'Lax',        // CSRF protection
    'lifetime' => 86400,        // 24 hours
    'name' => 'pype_session',   // Custom session name
]);
```

### On Login

```php
// Call on successful login
SessionGuard::onLogin();

// This regenerates session ID, stores fingerprint, and marks as validated
```

### Validate on Every Request

```php
// Call at the start of each request
if (!SessionGuard::check()) {
    // Session is invalid or expired
    redirect('/login');
}
```

### Concurrent Session Limits

```php
// Set max concurrent sessions
SessionGuard::setMaxSessions(3);

// Enforce limit
SessionGuard::enforceSessionLimit($userId);

// Get active sessions
$sessions = SessionGuard::getUserSessions($userId);

// Revoke a specific session
SessionGuard::revokeSession($userId, $sessionId);

// Revoke all except current
SessionGuard::revokeAllExceptCurrent($userId);
```

### Idle Timeout & Expiry

```php
// Set idle timeout (30 minutes)
SessionGuard::setIdleTimeout(1800);

// Set session expiry (24 hours)
SessionGuard::setExpiry(86400);
```

### Device Tracking

```php
$device = SessionGuard::getDeviceInfo();
// Returns: ['ip', 'user_agent', 'browser', 'platform', 'is_mobile', 'login_time']
```

---

## Encryption

AES-256-GCM encryption for sensitive data at rest.

### Configuration

```php
use Framework\Security\Encryption;

// Initialize from .env
Encryption::init();
// Requires APP_KEY or ENCRYPTION_KEY in .env

// Or set key directly
Encryption::setKey('your-32-byte-key-here');

// Generate a new key
$key = Encryption::generateKey();
// Output: base64:xxxxxxxxx...
```

### Encrypt/Decrypt

```php
// Encrypt any value (arrays, objects, strings)
$encrypted = Encryption::encrypt(['ssn' => '123-45-6789', 'bank' => 'Chase']);

// Decrypt
$data = Encryption::decrypt($encrypted);

// String-only encryption
$encrypted = Encryption::encryptString('sensitive text');
$decrypted = Encryption::decryptString($encrypted);

// Hex encoding
$hex = Encryption::encryptHex($data);
$decrypted = Encryption::decryptHex($hex);
```

### HMAC Signatures

```php
// Create signature
$signature = Encryption::hmac($data);

// Verify signature
if (Encryption::verifyHmac($data, $signature)) {
    // Data is authentic
}
```

### Helper Functions

```php
$encrypted = encrypt($data);
$decrypted = decrypt($encrypted);
$encrypted = encrypt_string('text');
$decrypted = decrypt_string($encrypted);
```

### Environment Setup

```env
# Generate with: php -r "echo 'base64:' . base64_encode(random_bytes(32));"
APP_KEY=base64:your-generated-key-here
```

---

## Secure File Uploads

Production-grade upload pipeline with MIME validation, image processing, and quarantine.

### Basic Usage

```php
use Framework\Security\SecureUploader;

$uploader = SecureUploader::make('uploads/avatars')
    ->mimes(['image/jpeg', 'image/png', 'image/webp'])
    ->extensions(['jpg', 'jpeg', 'png', 'webp'])
    ->maxSize(5242880) // 5MB
    ->rename(true)
    ->quarantine(true);

$filename = $uploader->upload($_FILES['avatar']);

if (!$filename) {
    $errors = $uploader->errors();
    // Handle errors
}
```

### Image Processing

```php
$filename = SecureUploader::make('uploads/photos')
    ->mimes(['image/jpeg', 'image/png'])
    ->extensions(['jpg', 'png'])
    ->maxSize(10485760) // 10MB
    ->processImages(true)
    ->imageConstraints(1920, 1080, 85) // maxWidth, maxHeight, quality
    ->upload($_FILES['photo']);
```

### Multiple Files

```php
$uploader = SecureUploader::make('uploads/documents')
    ->mimes(['application/pdf', 'application/msword'])
    ->extensions(['pdf', 'doc', 'docx'])
    ->maxSize(20971520); // 20MB

$filenames = $uploader->uploadMultiple($_FILES['documents']);
```

### Security Features

- **MIME validation** via `finfo` (not relying on browser-reported type)
- **Double-extension detection** (blocks `file.php.jpg` attacks)
- **Content verification** (scans for PHP code in image files)
- **Image re-processing** (recreates images to strip metadata/malware)
- **Quarantine** (suspicious files moved to inaccessible folder)
- **Secure permissions** (0644 on uploaded files)
- **Automatic renaming** (random hex filenames)

---

## Security Headers

Automatic HTTP security headers for every response.

### Basic Usage

```php
// In routes/web.php
Route::group(['middleware' => \Framework\Middleware\SecurityHeadersMiddleware::class], function() {
    // All routes get security headers
    Route::get('/', 'HomeController@index');
});
```

### Configuration Presets

```php
use Framework\Middleware\SecurityHeadersMiddleware;

// Default (production) — strict CSP, HSTS, no framing
$middleware = new SecurityHeadersMiddleware(
    SecurityHeadersMiddleware::defaultConfig()
);

// Development — relaxed CSP, no HSTS, allows localhost
$middleware = new SecurityHeadersMiddleware(
    SecurityHeadersMiddleware::developmentConfig()
);

// API — minimal headers, no browser-specific ones
$middleware = new SecurityHeadersMiddleware(
    SecurityHeadersMiddleware::apiConfig()
);

// Custom
$middleware = new SecurityHeadersMiddleware([
    'hsts' => ['max_age' => 31536000, 'include_sub_domains' => true],
    'x_frame_options' => 'SAMEORIGIN',
    'csp' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", 'https://cdn.example.com'],
    ],
]);
```

### Headers Applied

| Header | Default Value |
|--------|---------------|
| `Strict-Transport-Security` | max-age=31536000; includeSubDomains |
| `X-Frame-Options` | DENY |
| `X-Content-Type-Options` | nosniff |
| `X-XSS-Protection` | 0 |
| `Referrer-Policy` | strict-origin-when-cross-origin |
| `Content-Security-Policy` | default-src 'self', etc. |
| `Permissions-Policy` | camera=(), microphone=(), etc. |
| `X-Permitted-Cross-Domain-Policies` | none |

---

## Honeypot & Bot Protection

Invisible form fields + timing analysis to block bots.

### Add to Forms

```html
<form method="POST" action="/register">
    <?= honeypot_field() ?>
    
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <button type="submit">Register</button>
</form>
```

### Apply Middleware

```php
// In routes/web.php
Route::post('/register', 'AuthController@register')
    ->middleware(\Framework\Middleware\HoneypotMiddleware::class);

// Custom configuration
Route::post('/contact', 'ContactController@submit')
    ->middleware(new \Framework\Middleware\HoneypotMiddleware('bot_trap', 3, true));
```

### How It Works

1. **Honeypot field** — Hidden with CSS, legitimate users can't see/fill it; bots will
2. **Timing check** — Tracks form render time; submissions under `minTime` seconds are blocked
3. **Silent blocking** — Returns 200 OK to avoid giving bots feedback

---

## Audit Logging

Track who did what, when, and from where.

### Basic Logging

```php
use Framework\Security\AuditLog;

// Log any action
AuditLog::log('created', 'user', $userId, ['name' => 'John', 'email' => 'john@example.com']);

// Shortcut methods
AuditLog::created('post', $postId, $postData);
AuditLog::updated('user', $userId, $oldData, $newData);
AuditLog::deleted('comment', $commentId, $commentData);
AuditLog::login($userId);
AuditLog::logout($userId);
AuditLog::loginFailed($email, ['ip' => $_SERVER['REMOTE_ADDR']]);
AuditLog::security('suspicious_activity', ['detail' => '...']);
```

### Query Logs

```php
// Get logs with filters
$logs = AuditLog::query(
    action: 'login_failed',
    severity: 'warning',
    startDate: '2024-01-01',
    limit: 50
);

// Get logs for specific entity
$logs = AuditLog::forEntity('user', $userId);

// Get logs for specific user
$logs = AuditLog::forUser($userId);

// Get recent security events
$logs = AuditLog::recentSecurity();

// Count logs
$count = AuditLog::count(action: 'login_failed');
```

### Prune Old Logs

```php
// Delete logs older than 365 days
AuditLog::prune(daysOld: 365);
```

### Enable/Disable

```php
AuditLog::disable(); // Temporarily stop logging
AuditLog::enable();  // Resume logging
```

### Helper Function

```php
audit_log('updated', 'post', $postId, ['old' => $old, 'new' => $new]);
```

---

## API Key Management

API keys with generation, rotation, scopes, and rate limits.

### Generate Key

```php
use Framework\Security\ApiKey;

// Generate new API key
$rawKey = ApiKey::generate(
    name: 'Production App',
    userId: $userId,
    scopes: ['read:posts', 'write:posts', 'read:users'],
    rateLimit: 1000, // requests per hour
    expiresAt: '2025-12-31 23:59:59'
);

// $rawKey is shown to user ONCE — store securely
// Example: pk_a1b2c3d4e5f6...
```

### Validate Key

```php
// From request (checks Authorization header, X-API-Key header, or api_key param)
$keyRecord = ApiKey::validate();

if (!$keyRecord) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

// Check scope
if (!ApiKey::hasScope($keyRecord, 'write:posts')) {
    http_response_code(403);
    echo json_encode(['error' => 'Missing scope: write:posts']);
    exit;
}
```

### Use as Middleware

```php
// In routes/web.php
Route::group(['prefix' => 'api'], function() {
    Route::get('/posts', function($params) {
        return json(['data' => Post::all()]);
    });
    
    Route::post('/posts', function($params) {
        return json(['message' => 'Created']);
    });
})->middleware(function($params, $next) {
    return ApiKey::middleware($params, $next, ['read:posts']);
});
```

### Key Management

```php
// Revoke a key
ApiKey::revoke($keyId);

// Revoke all keys for a user
ApiKey::revokeAllForUser($userId);

// Rotate a key (revokes old, generates new)
$newKey = ApiKey::rotate($keyId, 'New Name');

// Update scopes
ApiKey::updateScopes($keyId, ['read:posts', 'write:posts']);

// Update rate limit
ApiKey::updateRateLimit($keyId, 5000);

// Get user's keys
$keys = ApiKey::getUserKeys($userId);

// Clean up expired keys
ApiKey::pruneExpired();
```

### Helper Functions

```php
$key = generate_api_key('My App', $userId, ['read', 'write']);
$record = validate_api_key();
```

---

## Complete Login Example with All Security Features

```php
<?php

namespace App\Controller;

use Framework\Security\{
    BruteForceProtection,
    PasswordReset,
    PasswordPolicy,
    SessionGuard,
    AuditLog,
    TwoFactorAuth
};
use Framework\Helper\EnhancedValidator;

class AuthController
{
    public function login()
    {
        // Check brute force
        $email = input('email');
        
        if (BruteForceProtection::isLocked($email)) {
            $seconds = BruteForceProtection::lockoutRemaining($email);
            redirectWith('/login', 'error', "Account locked. Try again in {$seconds}s.");
            return;
        }

        // Validate
        $validator = EnhancedValidator::make($_POST, [
            'email' => 'required|email',
            'password' => 'required',
        ])->validate();

        if ($validator->fails()) {
            redirectWith('/login', 'errors', $validator->errors());
            return;
        }

        // Find user
        $user = \App\Models\User::findBy('email', $email);

        if (!$user || !PasswordPolicy::verify($password, $user->password)) {
            BruteForceProtection::recordFailedAttempt($email);
            AuditLog::loginFailed($email);
            
            $remaining = BruteForceProtection::remainingAttempts($email);
            redirectWith('/login', 'error', "Invalid credentials. {$remaining} attempts left.");
            return;
        }

        // Check password needs rehash
        if (PasswordPolicy::needsRehash($user->password)) {
            $user->password = PasswordPolicy::hash($password);
            $user->save();
        }

        // Check 2FA
        if ($user->two_factor_enabled) {
            $_SESSION['pending_login_user_id'] = $user->id;
            redirect('/2fa-verify');
            return;
        }

        // Complete login
        $this->completeLogin($user);
    }

    public function verify2fa()
    {
        $userId = $_SESSION['pending_login_user_id'] ?? null;
        if (!$userId) {
            redirect('/login');
            return;
        }

        $user = \App\Models\User::find($userId);
        $code = input('code');

        if (TwoFactorAuth::verify($user->two_factor_secret, $code)) {
            $this->completeLogin($user);
            return;
        }

        // Try backup codes
        $hashes = json_decode($user->backup_codes, true);
        $result = TwoFactorAuth::verifyBackupCode($hashes, $code);

        if ($result) {
            $user->backup_codes = json_encode($result['remaining_codes']);
            $user->save();
            $this->completeLogin($user);
            return;
        }

        redirectWith('/2fa-verify', 'error', 'Invalid code');
    }

    private function completeLogin($user)
    {
        // Clear brute force attempts
        BruteForceProtection::clearAttempts($user->email);

        // Start secure session
        SessionGuard::onLogin();

        // Set user in session
        $_SESSION['user_id'] = $user->id;

        // Audit log
        AuditLog::login($user->id, ['email' => $user->email]);

        redirect('/dashboard');
    }
}
```
