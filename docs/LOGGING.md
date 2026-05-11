# Logging Guide

## Overview

Pype PHP includes a built-in logging system to track application events, errors, warnings, and debug information. Logs are stored in `Storage/logs/app.log`.

---

## Quick Start

```php
use Framework\Logging\Logger;

// Log an info message
Logger::info('User logged in', ['user_id' => 1]);

// Log an error
Logger::error('Database connection failed', ['error' => $e->getMessage()]);

// Log a warning
Logger::warning('Disk space low', ['available' => '10%']);

// Log debug info
Logger::debug('Query executed', ['sql' => $query, 'time' => $duration]);
```

---

## Log Levels

| Level | Method | Use Case |
|-------|--------|----------|
| INFO | `Logger::info()` | Normal operational events |
| ERROR | `Logger::error()` | Errors and exceptions |
| WARNING | `Logger::warning()` | Potential issues |
| DEBUG | `Logger::debug()` | Debugging information |

---

## Methods

### `info($message, $context = [])`

Log informational messages.

```php
Logger::info('Application started');
Logger::info('User registered', ['email' => 'user@example.com']);
Logger::info('Payment processed', [
    'amount' => 99.99,
    'currency' => 'USD',
    'user_id' => 42
]);
```

### `error($message, $context = [])`

Log error messages.

```php
try {
    // Risky operation
} catch (\Exception $e) {
    Logger::error('Failed to send email', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
```

### `warning($message, $context = [])`

Log warning messages.

```php
Logger::warning('Deprecated function called', [
    'function' => 'old_method()',
    'replacement' => 'new_method()'
]);

Logger::warning('Rate limit approaching', [
    'current' => 95,
    'limit' => 100
]);
```

### `debug($message, $context = [])`

Log debug information (development only).

```php
Logger::debug('SQL Query', [
    'sql' => 'SELECT * FROM users WHERE id = ?',
    'params' => [1],
    'execution_time' => '0.003s'
]);
```

---

## Enable/Disable Logging

```php
// Disable logging
Logger::disable();

// Enable logging
Logger::enable();
```

---

## Log File Location

```php
// Get log file path
$logPath = Logger::getLogPath();
// Returns: /path/to/project/Storage/logs/app.log
```

---

## Log Format

Each log entry follows this format:

```
[2024-01-15 10:30:00] INFO: User logged in {"user_id": 1, "ip": "127.0.0.1"}
[2024-01-15 10:31:00] ERROR: Database connection failed {"error": "Access denied"}
[2024-01-15 10:32:00] WARNING: Disk space low {"available": "10%"}
[2024-01-15 10:33:00] DEBUG: Query executed {"sql": "SELECT...", "time": "0.003s"}
```

---

## Common Use Cases

### Request Logging

```php
Logger::info('Request received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'url' => $_SERVER['REQUEST_URI'],
    'ip' => $_SERVER['REMOTE_ADDR']
]);
```

### Error Handling

```php
set_exception_handler(function($e) {
    Logger::error('Uncaught exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Show user-friendly error
    echo "An error occurred. Please try again later.";
});
```

### Database Query Logging

```php
// Before query
Logger::debug('Executing query', ['sql' => $sql, 'params' => $params]);

// After query
Logger::debug('Query completed', ['rows_affected' => $affected, 'time' => $duration]);
```

### User Activity Logging

```php
Logger::info('User action', [
    'action' => 'password_changed',
    'user_id' => userId(),
    'ip' => getClientIP(),
    'user_agent' => userAgent()
]);
```

### Email Logging

The Mailer automatically logs emails:

```php
Mailer::send('user@example.com', 'Welcome', 'Hello!');
// Automatically logged:
// INFO: Email sent via log {"to": "user@example.com", "subject": "Welcome"}
```

---

## Tips

- Use `Logger::disable()` in production if you don't need logging
- Rotate log files periodically to prevent large files
- Never log sensitive data (passwords, tokens, credit cards)
- Use DEBUG level only during development
- Include context arrays for better troubleshooting
