# API & Frontend Integration Guide

## Overview

Phase 2 adds production-ready API features for frontend frameworks (React, Next.js, Vue), AJAX clients, and third-party integrations. This covers JWT authentication, versioning, CORS, webhooks, SSE, chunked uploads, and standardized responses.

---

## Table of Contents

1. [JWT Authentication](#jwt-authentication)
2. [API Response Formatters](#api-response-formatters)
3. [RFC 7807 Problem Details](#rfc-7807-problem-details)
4. [API Versioning](#api-versioning)
5. [Resource Transformers](#resource-transformers)
6. [CORS Middleware (Enhanced)](#cors-middleware-enhanced)
7. [API Documentation (OpenAPI/Swagger)](#api-documentation-openapiswagger)
8. [Webhook System](#webhook-system)
9. [Server-Sent Events (SSE)](#server-sent-events-sse)
10. [Chunked File Uploads](#chunked-file-uploads)
11. [Frontend Integration Examples](#frontend-integration-examples)

---

## JWT Authentication

Stateless token-based authentication with access/refresh token pairs, scope authorization, and automatic token blacklisting.

### Configuration

```php
use Framework\Api\Jwt;

// In your bootstrap file
Jwt::configure([
    'secret' => env('JWT_SECRET'),  // Generate with: php -r "echo bin2hex(random_bytes(32));"
    'algorithm' => 'HS256',          // HS256, HS384, HS512
    'access_ttl' => 3600,            // 1 hour
    'refresh_ttl' => 604800,         // 7 days
    'issuer' => 'my-app',
]);

// .env
JWT_SECRET=your-64-character-hex-secret-here
```

### Generate Secret

```php
$secret = Jwt::generateSecret();
// Use in .env: JWT_SECRET=abcdef123456...
```

### Create Tokens

```php
// Single token
$token = Jwt::createToken([
    'sub' => $user->id,
    'user_id' => $user->id,
    'email' => $user->email,
    'scopes' => ['read:posts', 'write:posts'],
]);

// Access token only
$accessToken = Jwt::createAccessToken(['sub' => $user->id]);

// Refresh token only
$refreshToken = Jwt::createRefreshToken(['sub' => $user->id]);

// Token pair (recommended for login)
$tokens = Jwt::createTokenPair([
    'sub' => $user->id,
    'user_id' => $user->id,
    'scopes' => ['read', 'write'],
]);

// Returns:
// [
//     'access_token' => 'eyJ...',
//     'refresh_token' => 'eyJ...',
//     'token_type' => 'Bearer',
//     'expires_in' => 3600,
// ]
```

### Verify Tokens

```php
// Verify access token
$payload = Jwt::verifyAccessToken($token);
if ($payload === false) {
    // Invalid or expired
}

// Verify refresh token
$payload = Jwt::verifyRefreshToken($token);

// Get token from request (Authorization: Bearer header)
$payload = Jwt::getFromRequest();

// Get raw Bearer token
$token = Jwt::getBearerToken();
```

### Refresh Tokens

```php
$newTokens = Jwt::refreshToken($refreshToken);

if ($newTokens !== false) {
    // New access_token and refresh_token
    // Old refresh token is automatically blacklisted
}
```

### Scope Authorization

```php
$payload = Jwt::verifyAccessToken($token);

if (Jwt::hasScope($payload, 'write:posts')) {
    // Allowed
}
```

### Blacklist Tokens (Logout)

```php
// Blacklist current token
Jwt::blacklistToken($token);

// Clean up expired entries from blacklist
Jwt::cleanBlacklist();
```

### JWT Middleware

```php
use Framework\Api\JwtMiddleware;

// Require authentication
Route::get('/api/posts', function() {
    $userId = $_SERVER['JWT_USER_ID'];
    return json(['data' => Post::where('user_id', $userId)->get()]);
})->middleware(JwtMiddleware::class);

// Require specific scopes
Route::post('/api/posts', function() {
    return json(['message' => 'Created']);
})->middleware(JwtMiddleware::make(['write:posts']));

// Multiple scopes
Route::delete('/api/posts/{id}', function($params) {
    return json(['message' => 'Deleted']);
})->middleware(JwtMiddleware::make(['write:posts', 'delete:posts']));

// Optional auth (allows refresh)
Route::get('/api/posts/{id}', function($params) {
    $userId = $_SERVER['JWT_USER_ID'] ?? null;
    return json(['data' => Post::find($params['id'])]);
})->middleware(JwtMiddleware::optional());
```

### Complete Login Flow

```php
Route::post('/api/auth/login', function() {
    $email = input('email');
    $password = input('password');

    $user = \App\Models\User::findBy('email', $email);

    if (!$user || !password_verify($password, $user->password)) {
        api_error('Invalid credentials', 401);
    }

    $tokens = Jwt::createTokenPair([
        'sub' => $user->id,
        'user_id' => $user->id,
        'email' => $user->email,
        'scopes' => ['read', 'write'],
    ]);

    api_response($tokens, 'Login successful');
});

Route::post('/api/auth/refresh', function() {
    $refreshToken = input('refresh_token');

    $newTokens = Jwt::refreshToken($refreshToken);
    if ($newTokens === false) {
        api_error('Invalid refresh token', 401);
    }

    api_response($newTokens, 'Token refreshed');
});

Route::post('/api/auth/logout', function() {
    $token = Jwt::getBearerToken();
    if ($token) {
        Jwt::blacklistToken($token);
    }
    api_response(null, 'Logged out');
})->middleware(JwtMiddleware::class);
```

### Helper Functions

```php
$token = jwt_token(['sub' => 1, 'scopes' => ['read']]);
$payload = jwt_verify($token);
$pair = jwt_pair(['sub' => 1]);
$newPair = jwt_refresh($refreshToken);
$currentPayload = jwt_payload();
```

---

## API Response Formatters

Consistent JSON structure for all API responses.

### Success Responses

```php
use Framework\Api\ApiResponse;

// Basic success
ApiResponse::success(['id' => 1, 'name' => 'Post'], 'Post retrieved');
// {"success":true,"message":"Post retrieved","data":{"id":1,"name":"Post"},"meta":null}

// With metadata
ApiResponse::success($user, 'Profile loaded', 200, ['last_login' => '2024-01-01']);

// Created (201)
ApiResponse::created($newPost, 'Post created', '/api/posts/42');

// No Content (204)
ApiResponse::noContent();
```

### Error Responses

```php
// Bad request
ApiResponse::error('Invalid input', 400);

// Not found
ApiResponse::notFound('Post not found');

// Unauthorized
ApiResponse::unauthorized('Token expired');

// Forbidden
ApiResponse::forbidden('Insufficient permissions');

// Validation error (422)
ApiResponse::validationError([
    'email' => ['Email is required'],
    'password' => ['Must be at least 8 characters'],
]);

// Rate limited (429)
ApiResponse::tooManyRequests(60); // Retry after 60 seconds
```

### Paginated Responses

```php
$posts = Post::limit(20)->offset(($page - 1) * 20)->get();
$total = Post::count();

ApiResponse::paginated(
    items: $posts,
    page: 1,
    perPage: 20,
    total: 150,
    message: 'Posts loaded'
);

// Response:
// {
//     "success": true,
//     "message": "Posts loaded",
//     "data": [...],
//     "meta": {
//         "pagination": {
//             "current_page": 1,
//             "per_page": 20,
//             "total": 150,
//             "total_pages": 8,
//             "has_next": true,
//             "has_prev": false
//         }
//     }
// }
```

### Helper Functions

```php
api_response($data, 'Success', 200);
api_error('Something went wrong', 500, null, ['detail' => '...']);
```

---

## RFC 7807 Problem Details

Standardized error format for HTTP APIs (RFC 7807).

### Basic Usage

```php
use Framework\Api\ApiProblem;

// Return a problem details response
ApiProblem::badRequest('Invalid email format')->send();

// Content-Type: application/problem+json
// {
//     "type": "about:blank",
//     "title": "Bad Request",
//     "status": 400,
//     "detail": "Invalid email format"
// }
```

### Factory Methods

```php
ApiProblem::badRequest('Missing required field');
ApiProblem::unauthorized('Token expired');
ApiProblem::forbidden('Cannot access this resource');
ApiProblem::notFound('User not found');
ApiProblem::methodNotAllowed('GET not supported');
ApiProblem::conflict('Email already registered');
ApiProblem::unprocessableEntity('Validation failed', [
    'email' => ['Invalid format'],
]);
ApiProblem::tooManyRequests('Rate limited', 60);
ApiProblem::internalError('Something broke');
ApiProblem::serviceUnavailable('Maintenance mode');
```

### Custom Problem Details

```php
ApiProblem::make(422)
    ->type('https://api.example.com/errors/validation')
    ->title('Validation Failed')
    ->detail('The request body contains invalid fields')
    ->instance('/api/posts/42')
    ->extension('field_errors', [
        'title' => ['Must be at least 3 characters'],
        'body' => ['Cannot be empty'],
    ])
    ->extension('request_id', 'req_abc123')
    ->send();

// Response:
// {
//     "type": "https://api.example.com/errors/validation",
//     "title": "Validation Failed",
//     "status": 422,
//     "detail": "The request body contains invalid fields",
//     "instance": "/api/posts/42",
//     "field_errors": {...},
//     "request_id": "req_abc123"
// }
```

### Helper Function

```php
api_problem(400)->title('Bad Request')->detail('Invalid input')->send();
```

---

## API Versioning

Support multiple API versions with URL prefix, header, or query parameter.

### Configuration

```php
use Framework\Api\ApiVersion;

ApiVersion::configure([
    'current' => 'v2',
    'available' => ['v1', 'v2'],
    'header' => 'X-API-Version',
    'url_prefix' => 'api',
]);
```

### URL Prefix Versioning

```php
// /api/v1/posts
ApiVersion::group('v1', function() {
    Route::get('/posts', 'Api\V1\PostController@index');
    Route::post('/posts', 'Api\V1\PostController@store');
});

// /api/v2/posts
ApiVersion::group('v2', function() {
    Route::get('/posts', 'Api\V2\PostController@index');
    Route::post('/posts', 'Api\V2\PostController@store');
});
```

### Detect Version from Request

```php
// From URL: /api/v1/posts
// From header: X-API-Version: v1
// From query: ?api_version=v1

$version = ApiVersion::getFromRequest();

if (!ApiVersion::isSupported($version)) {
    ApiProblem::badRequest("Version {$version} not supported")->send();
}

if (ApiVersion::isDeprecated($version)) {
    header('Warning: 299 - "API version ' . $version . ' is deprecated"');
}
```

### Version Detection Priority

1. `X-API-Version` header
2. URL prefix (`/api/v1/...`)
3. Query parameter (`?api_version=v1`)
4. Falls back to configured current version

---

## Resource Transformers

Transform API responses with field control, relationships, and computed attributes.

### Basic Usage

```php
use Framework\Api\ResourceTransformer;

// Single resource
$transformer = ResourceTransformer::make();
$data = $transformer->transform($user);

// Collection
$users = User::all();
$data = $transformer->transformCollection($users);
```

### Field Visibility

```php
// Show only these fields
$transformer = ResourceTransformer::make()
    ->visible(['id', 'name', 'email']);

// Hide these fields
$transformer = ResourceTransformer::make()
    ->hidden(['password', 'remember_token']);
```

### Includes (Relationships)

```php
$transformer = ResourceTransformer::make()
    ->includes(['posts', 'profile', 'followers']);
```

### Appends (Computed Attributes)

```php
$transformer = ResourceTransformer::make()
    ->append('full_name', fn($user) => $user->first_name . ' ' . $user->last_name)
    ->append('is_online', fn($user) => time() - $user->last_seen < 300);
```

### Pagination

```php
$posts = Post::limit(20)->offset(0)->get();
$total = Post::count();

$transformer = ResourceTransformer::make()
    ->hidden(['password'])
    ->includes(['author']);

$data = $transformer->paginate($posts, 1, 20, $total);
```

### Helper Function

```php
$data = transform($user, [
    'visible' => ['id', 'name', 'email'],
    'hidden' => ['password'],
    'includes' => ['posts'],
    'meta' => ['api_version' => 'v2'],
]);
```

---

## CORS Middleware (Enhanced)

Configurable Cross-Origin Resource Sharing with presets for common scenarios.

### Basic Usage

```php
use Framework\Middleware\CorsMiddleware;

// Allow all origins
Route::group(['middleware' => CorsMiddleware::allowAll()], function() {
    Route::get('/api/data', 'ApiController@data');
});
```

### Presets

```php
// Development (localhost:3000, localhost:5173, credentials)
CorsMiddleware::development()

// SPA (React/Vue/Next.js defaults)
CorsMiddleware::spa()

// With custom frontend URLs
CorsMiddleware::spa(['https://myapp.com', 'https://admin.myapp.com'])

// Public API
CorsMiddleware::api()
```

### Custom Configuration

```php
Route::group(['middleware' => new CorsMiddleware([
    'allowed_origins' => ['https://myapp.com', 'https://admin.myapp.com'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'exposed_headers' => ['X-Request-Id', 'X-Total-Count'],
    'max_age' => 86400,
    'allow_credentials' => true,
])], function() {
    Route::get('/api/posts', 'ApiController@index');
});
```

### Wildcard Origins

```php
// Match all subdomains
CorsMiddleware::allowOrigins(['*.example.com'])
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `allowed_origins` | array | `['*']` | Allowed origin patterns |
| `allowed_methods` | array | All methods | HTTP methods allowed |
| `allowed_headers` | array | Common headers | Request headers allowed |
| `exposed_headers` | array | `[]` | Response headers exposed to browser |
| `max_age` | int | `86400` | Preflight cache duration (seconds) |
| `allow_credentials` | bool | `false` | Allow cookies/auth headers |

---

## API Documentation (OpenAPI/Swagger)

Auto-generate API documentation from routes.

### Basic Usage

```php
use Framework\Api\OpenApiGenerator;

// Auto-scan all routes
$generator = new OpenApiGenerator([
    'title' => 'My API',
    'description' => 'My application API',
    'version' => '2.0.0',
]);

$generator->autoScan();

// Serve Swagger UI
Route::get('/api/docs', function() {
    $generator = (new OpenApiGenerator())->autoScan();
    $generator->serveSwaggerUI('My API Docs');
});
```

### Add Routes Manually

```php
$generator = new OpenApiGenerator();

$generator->addRoute('GET', '/posts', [
    'summary' => 'List all posts',
    'description' => 'Returns paginated list of posts',
    'tags' => ['Posts'],
    'parameters' => [
        [
            'name' => 'page',
            'in' => 'query',
            'schema' => ['type' => 'integer', 'default' => 1],
        ],
    ],
    'responses' => [
        '200' => [
            'description' => 'List of posts',
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/PostList'],
                ],
            ],
        ],
    ],
    'security' => [['BearerAuth' => []]],
]);
```

### Add Schemas

```php
$generator->addSchema('Post', [
    'type' => 'object',
    'properties' => [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'string'],
        'body' => ['type' => 'string'],
        'created_at' => ['type' => 'string', 'format' => 'date-time'],
    ],
    'required' => ['title', 'body'],
]);
```

### Export Formats

```php
// JSON
$json = $generator->toJson();

// YAML
$yaml = $generator->toYaml();

// Download
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="openapi.json"');
echo $generator->toJson();
exit;
```

### Serve Raw Spec

```php
Route::get('/api/openapi.json', function() {
    header('Content-Type: application/json');
    echo (new OpenApiGenerator())->autoScan()->toJson();
    exit;
});
```

---

## Webhook System

Trigger external URLs on application events with HMAC signature verification, retries, and logging.

### Create Webhooks

```php
use Framework\Api\Webhook;

// Register a webhook
Webhook::create('user.created', 'https://example.com/webhooks/user-created', [
    'secret' => 'your-webhook-secret',
    'headers' => ['X-Custom-Header' => 'value'],
]);

// Create with random secret
Webhook::create('order.completed', 'https://example.com/webhooks/orders');
```

### Trigger Events

```php
// Synchronous delivery
Webhook::trigger('user.created', [
    'user_id' => 42,
    'email' => 'user@example.com',
    'name' => 'John Doe',
]);

// Async (queue to file for background processing)
Webhook::triggerAsync('order.completed', [
    'order_id' => 123,
    'total' => 99.99,
]);
```

### Webhook Payload Format

```json
{
    "event": "user.created",
    "timestamp": 1700000000,
    "data": {
        "user_id": 42,
        "email": "user@example.com",
        "name": "John Doe"
    }
}
```

### HMAC Verification (Receiver Side)

```php
// On the receiving end:
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$webhookId = $_SERVER['HTTP_X_WEBHOOK_ID'] ?? '';

$expected = 'sha256=' . hash_hmac('sha256', $payload, 'your-webhook-secret');

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process webhook
$data = json_decode($payload, true);
```

### Manage Webhooks

```php
// List webhooks
$webhooks = Webhook::list();
$webhooks = Webhook::list('user.created'); // By event

// Activate/deactivate
Webhook::activate($webhookId);
Webhook::deactivate($webhookId);

// Delete
Webhook::delete($webhookId);

// Manual delivery
$result = Webhook::deliver($webhookRecord, $payload);
```

### Configuration

```php
Webhook::configure([
    'timeout' => 30,        // Request timeout (seconds)
    'max_retries' => 3,     // Retry attempts on failure
    'retry_delay' => 60,    // Delay between retries (seconds)
]);
```

### Helper Function

```php
trigger_webhook('user.created', ['user_id' => 42]);
```

---

## Server-Sent Events (SSE)

Real-time server-to-client streaming without WebSockets complexity.

### Basic SSE Stream

```php
use Framework\Api\Sse;

Route::get('/api/stream', function() {
    Sse::stream(function($clientId, $lastId) {
        // Return data to send, or null to skip
        return [
            'time' => date('H:i:s'),
            'client' => $clientId,
            'message' => 'Heartbeat from server',
        ];
    });
});
```

### Channel-Based Broadcasting

```php
// Server: broadcast to a channel
Sse::broadcast('notifications', [
    'type' => 'new_message',
    'from' => 'Alice',
    'body' => 'Hello!',
]);

// Client endpoint: subscribe to channel
Route::get('/api/sse/notifications', function() {
    Sse::channelStream('notifications');
});
```

### Client-Side (JavaScript)

```javascript
const eventSource = new EventSource('/api/sse/notifications');

eventSource.addEventListener('message', (event) => {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
});

eventSource.addEventListener('close', (event) => {
    console.log('Connection closed');
    eventSource.close();
});

// Reconnect on error
eventSource.onerror = () => {
    console.log('Connection lost, reconnecting...');
};
```

### Custom Events

```php
Route::get('/api/stream', function() {
    Sse::stream(function($clientId, $lastId) {
        $data = checkForUpdates($clientId, $lastId);
        
        if ($data !== null) {
            // Event name + data
            Sse::sendEvent('update', $data, $clientId);
            return null; // Already sent
        }
        
        return null; // Skip this tick
    }, [
        'retry' => 5000, // Client retry delay (ms)
    ]);
});
```

### Configuration

```php
Sse::configure([
    'heartbeat' => 15,     // Heartbeat interval (seconds)
    'max_time' => 300,     // Max connection duration (seconds)
]);
```

### Helper Functions

```php
sse_event('notification', ['message' => 'New alert']);
sse_broadcast('chat', ['from' => 'Alice', 'text' => 'Hi!']);
```

---

## Chunked File Uploads

Resumable, progress-tracked uploads for large files.

### Client-Side Upload (JavaScript)

```javascript
async function uploadFile(file) {
    const chunkSize = 5 * 1024 * 1024; // 5MB
    const totalChunks = Math.ceil(file.size / chunkSize);
    const uploadId = crypto.randomUUID();

    for (let i = 0; i < totalChunks; i++) {
        const start = i * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('upload_id', uploadId);
        formData.append('chunk_index', i);
        formData.append('total_chunks', totalChunks);
        formData.append('filename', file.name);
        formData.append('file_size', file.size);
        formData.append('chunk', chunk);

        const response = await fetch('/api/upload/chunk', {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();
        console.log(`Progress: ${result.progress}%`);

        if (result.complete) {
            console.log('Upload complete!', result.filename);
        }
    }
}
```

### Server-Side Handling

```php
use Framework\Api\ChunkedUpload;

Route::post('/api/upload/chunk', function() {
    $uploader = ChunkedUpload::make(storage_path('uploads'))
        ->allowedMimes(['image/jpeg', 'image/png', 'video/mp4'])
        ->allowedExtensions(['jpg', 'png', 'mp4'])
        ->maxSize(1048576000) // 1GB
        ->chunkSize(5242880); // 5MB chunks

    $result = $uploader->handleUpload([
        'upload_id' => $_POST['upload_id'],
        'chunk_index' => $_POST['chunk_index'],
        'total_chunks' => $_POST['total_chunks'],
        'filename' => $_POST['filename'],
        'file_size' => $_POST['file_size'],
        'chunk' => $_FILES['chunk'],
    ]);

    if ($result === false) {
        api_error($uploader->getLastError(), 400);
    }

    api_response($result, $result['complete'] ? 'Upload complete' : 'Chunk uploaded');
});
```

### Check Progress

```php
Route::get('/api/upload/{uploadId}/progress', function($params) {
    $uploader = ChunkedUpload::make(storage_path('uploads'));
    $progress = $uploader->getProgress($params['uploadId'], (int) $_GET['total_chunks']);
    
    api_response($progress);
});
```

### Cancel Upload

```php
Route::delete('/api/upload/{uploadId}', function($params) {
    $uploader = ChunkedUpload::make(storage_path('uploads'));
    $uploader->cancelUpload($params['uploadId']);
    
    api_response(null, 'Upload cancelled');
});
```

---

## Frontend Integration Examples

### React/Next.js — JWT Auth

```javascript
// lib/api.js
const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

export async function api(path, options = {}) {
    const token = localStorage.getItem('access_token');
    
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`${API_URL}${path}`, {
        ...options,
        headers,
    });

    if (response.status === 401 && token) {
        // Try refresh
        const refreshResult = await refreshTokens();
        if (refreshResult) {
            return api(path, options); // Retry
        }
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        window.location.href = '/login';
    }

    const data = await response.json();
    
    if (!response.ok) {
        throw new Error(data.message || 'API error');
    }

    return data;
}

export async function login(email, password) {
    const response = await fetch(`${API_URL}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    });

    const data = await response.json();
    
    if (data.success) {
        localStorage.setItem('access_token', data.data.access_token);
        localStorage.setItem('refresh_token', data.data.refresh_token);
    }

    return data;
}

async function refreshTokens() {
    const refreshToken = localStorage.getItem('refresh_token');
    if (!refreshToken) return false;

    const response = await fetch(`${API_URL}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refreshToken }),
    });

    const data = await response.json();
    
    if (data.success) {
        localStorage.setItem('access_token', data.data.access_token);
        localStorage.setItem('refresh_token', data.data.refresh_token);
        return true;
    }

    return false;
}

export async function logout() {
    await api('/auth/logout', { method: 'POST' });
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
}

// Usage in component
// const { data } = await api('/posts');
// await api('/posts', { method: 'POST', body: JSON.stringify({ title: 'New' }) });
```

### React — SSE Real-Time Updates

```javascript
// hooks/useSse.js
import { useEffect, useRef, useState } from 'react';

export function useSse(url, options = {}) {
    const [data, setData] = useState(null);
    const [connected, setConnected] = useState(false);
    const eventSourceRef = useRef(null);

    useEffect(() => {
        const eventSource = new EventSource(url);
        eventSourceRef.current = eventSource;

        eventSource.onopen = () => setConnected(true);
        
        eventSource.addEventListener('message', (event) => {
            setData(JSON.parse(event.data));
        });

        eventSource.addEventListener(options.eventName || 'message', (event) => {
            setData(JSON.parse(event.data));
        });

        eventSource.onerror = () => {
            setConnected(false);
        };

        return () => {
            eventSource.close();
        };
    }, [url]);

    return { data, connected };
}

// Usage
// function Notifications() {
//     const { data, connected } = useSse('/api/sse/notifications');
//     return <div>{connected ? 'Connected' : 'Disconnected'} - {data?.message}</div>;
// }
```

### Vanilla JS — Chunked Upload

```javascript
// upload.js
async function chunkedUpload(file, endpoint, options = {}) {
    const chunkSize = options.chunkSize || 5 * 1024 * 1024;
    const totalChunks = Math.ceil(file.size / chunkSize);
    const uploadId = options.uploadId || crypto.randomUUID();
    const onProgress = options.onProgress || (() => {});

    for (let i = 0; i < totalChunks; i++) {
        const start = i * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('upload_id', uploadId);
        formData.append('chunk_index', i);
        formData.append('total_chunks', totalChunks);
        formData.append('filename', file.name);
        formData.append('file_size', file.size);
        formData.append('chunk', chunk);

        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData,
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
            },
        });

        const result = await response.json();

        onProgress({
            progress: result.progress || 0,
            uploadedChunks: result.uploaded_chunks,
            totalChunks: result.total_chunks,
        });

        if (result.complete) {
            return result;
        }
    }
}

// Usage
// const file = document.querySelector('#file-input').files[0];
// const result = await chunkedUpload(file, '/api/upload/chunk', {
//     onProgress: ({ progress }) => {
//         document.querySelector('#progress-bar').style.width = progress + '%';
//     },
// });
```

### React — File Upload with Progress

```jsx
// components/FileUpload.jsx
import { useState } from 'react';

export default function FileUpload({ endpoint, onUpload }) {
    const [progress, setProgress] = useState(0);
    const [uploading, setUploading] = useState(false);

    const handleUpload = async (file) => {
        setUploading(true);
        setProgress(0);

        const result = await chunkedUpload(file, endpoint, {
            onProgress: ({ progress }) => setProgress(progress),
        });

        setUploading(false);
        if (result.complete && onUpload) {
            onUpload(result);
        }
    };

    return (
        <div>
            <input
                type="file"
                onChange={(e) => handleUpload(e.target.files[0])}
                disabled={uploading}
            />
            {uploading && (
                <div>
                    <progress value={progress} max="100" />
                    <span>{Math.round(progress)}%</span>
                </div>
            )}
        </div>
    );
}
```

---

## Quick Reference

| Class | Purpose | Location |
|-------|---------|----------|
| `Jwt` | Token creation, verification, refresh, blacklist | `Framework/Api/Jwt.php` |
| `JwtMiddleware` | Bearer token validation + scope checks | `Framework/Api/JwtMiddleware.php` |
| `ApiResponse` | Standardized JSON responses | `Framework/Api/ApiResponse.php` |
| `ApiProblem` | RFC 7807 Problem Details errors | `Framework/Api/ApiProblem.php` |
| `ApiVersion` | API versioning with deprecation | `Framework/Api/ApiVersion.php` |
| `ResourceTransformer` | Field control, includes, pagination | `Framework/Api/ResourceTransformer.php` |
| `CorsMiddleware` | Enhanced CORS with presets | `Framework/Middleware/CorsMiddleware.php` |
| `OpenApiGenerator` | OpenAPI/Swagger documentation | `Framework/Api/OpenApiGenerator.php` |
| `Webhook` | Event-driven HTTP callbacks | `Framework/Api/Webhook.php` |
| `Sse` | Server-Sent Events streaming | `Framework/Api/Sse.php` |
| `ChunkedUpload` | Resumable chunked file uploads | `Framework/Api/ChunkedUpload.php` |

| Helper | Purpose |
|--------|---------|
| `api_response()` | Send success JSON response |
| `api_error()` | Send error JSON response |
| `api_problem()` | Create RFC 7807 problem detail |
| `jwt_token()` | Create JWT token |
| `jwt_verify()` | Verify JWT token |
| `jwt_pair()` | Create access + refresh token pair |
| `jwt_refresh()` | Refresh token pair |
| `jwt_payload()` | Get JWT payload from request |
| `api_version()` | Get current API version |
| `transform()` | Transform resource with config |
| `trigger_webhook()` | Trigger webhook event |
| `sse_event()` | Send SSE event |
| `sse_broadcast()` | Broadcast to SSE channel |
