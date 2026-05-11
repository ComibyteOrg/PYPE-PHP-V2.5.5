# Infrastructure & Developer Experience Guide

Complete infrastructure, caching, events, scheduling, i18n, testing, and developer tooling.

## Cache System

Multi-driver caching with Redis, Memcached, File, and Array backends.

### Configuration

```env
CACHE_DRIVER=file  # Options: file, redis, memcached, array
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
```

### Basic Usage

```php
use Framework\Cache\Cache;

// Store
Cache::put('key', 'value', 3600); // TTL in seconds
Cache::set('key', 'value', 3600);

// Retrieve
$value = Cache::get('key');
$value = Cache::get('key', 'default');

// Check existence
if (Cache::has('key')) { ... }

// Delete
Cache::forget('key');
Cache::delete('key');

// Retrieve and delete
$value = Cache::pull('key');
```

### Advanced Operations

```php
// Remember (get or compute and cache)
$users = Cache::remember('all_users', 3600, function () {
    return User::all();
});

// Increment/Decrement
Cache::put('visits', 0, 3600);
Cache::increment('visits');
Cache::increment('visits', 5);
Cache::decrement('visits');

// Add (only if doesn't exist)
Cache::add('lock', true, 60);

// Forever (no TTL)
Cache::forever('config', $config);

// Bulk operations
Cache::putMany(['a' => 1, 'b' => 2, 'c' => 3], 3600);
$values = Cache::many(['a', 'b', 'c']);

// Flush all
Cache::flush();
Cache::clear();
```

### Tagged Cache

```php
// Store with tags
Cache::tags(['users', 'admin'])->put('user_1', $userData, 3600);

// Retrieve tagged
$data = Cache::tags(['users'])->get('user_1');

// Flush by tag (removes all items with this tag)
Cache::tags(['users'])->flush();
```

### Helpers

```php
cache('key');                        // Get
cache_put('key', 'value', 3600);     // Put
cache_forget('key');                 // Forget
cache_remember('key', 3600, fn() => compute());
cache_tags(['users'])->put('key', $value, 3600);
```

### Drivers

| Driver | Description | Requirements |
|---|---|---|
| `file` | Default, stores serialized files | None |
| `redis` | Fast in-memory, persistent | phpredis extension |
| `memcached` | Distributed cache | memcached extension |
| `array` | In-memory, per-request only | None (testing) |

## Event Dispatcher

Decouple application logic with events and listeners.

### Basic Usage

```php
use Framework\Events\Event;

// Register listener
Event::listen('user.created', function ($user) {
    sendWelcomeEmail($user->email);
});

// Register with class
Event::listen('user.created', SendWelcomeEmail::class);

// Dispatch
Event::dispatch('user.created', $user);
```

### Class-Based Events

```php
// Create event class
class UserRegistered extends BaseEvent
{
    public function __construct(
        public User $user,
    ) {}
}

// Create listener
class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        // Send email to $event->user->email
    }
}

// Register and dispatch
Event::listen(UserRegistered::class, SendWelcomeEmail::class);
(new UserRegistered($user))->dispatch();
```

### Wildcard Listeners

```php
// Listen to all user events
Event::listen('user.*', function ($payload, $eventName) {
    Logger::info("Event: {$eventName}");
});

// Matches: user.created, user.deleted, user.updated
```

### Multiple Listeners & Priority

```php
Event::listen('order.placed', 'LogOrder@handle', 10);    // First
Event::listen('order.placed', 'SendEmail@handle', 20);   // Second
Event::listen('order.placed', 'UpdateStock@handle', 30); // Third
```

### Queueing Events

```php
// Queue for later processing
Event::queue('user.created', $user);

// Flush and dispatch all queued events
$results = Event::flush();
```

### Subscriber Pattern

```php
class EventSubscriber
{
    public function subscribe(Event $dispatcher): void
    {
        $dispatcher->listen('user.created', [self::class, 'onUserCreated']);
        $dispatcher->listen('order.placed', [self::class, 'onOrderPlaced']);
    }

    public static function onUserCreated($user): void { }
    public static function onOrderPlaced($order): void { }
}

Event::subscribe(EventSubscriber::class);
```

### Conditional Dispatch

```php
Event::dispatchIf($isAdmin, 'admin.action', $data);
Event::dispatchUnless($isGuest, 'user.action', $data);
```

### Helpers

```php
event('user.created', $user);
listen('user.created', fn($user) => notify($user));
queue_event('notification.send', $data);
```

## Task Scheduler

Cron-like scheduling within PHP. Replace system crontab entries.

### Basic Usage

```php
use Framework\Scheduler\Scheduler;

$schedule = new Scheduler();

// Define tasks
$schedule->call(fn() => Article::publishScheduled())->daily();
$schedule->call(fn() => Cache::flush())->weekly();
$schedule->command('php ' . BASE_PATH . '/pype.php optimize')->monthly();
$schedule->job(ReportJob::class, 'generate')->dailyAt('09:00');

// Run (call this from system cron: * * * * * php pype.php schedule:run)
$results = $schedule->run();
```

### Scheduling Frequencies

```php
->everyMinute()
->everyFiveMinutes()
->everyTenMinutes()
->everyFifteenMinutes()
->everyThirtyMinutes()
->hourly() / ->everyHour()
->daily()                    // Midnight
->dailyAt('14:30')           // 2:30 PM
->twiceDaily(1, 13)          // 1 AM and 1 PM
->weekly()                   // Sunday midnight
->weeklyOn(1, '8:0')         // Monday 8 AM
->monthly()                  // 1st of month
->monthlyOn(15, '0:0')       // 15th midnight
->quarterly()
->yearly()
->weekdays()                 // Mon-Fri
->weekends()                 // Sat-Sun
->mondays() through ->sundays()
->cron('*/5 * * * *')        // Custom cron expression
```

### Constraints

```php
// Only run between 8 AM and 5 PM
->between('08:00', '17:00')

// Only in production
->inEnvironment('production')

// Name for monitoring
->name('Publish scheduled articles')

// Prevent overlapping (max 24 hours window)
->withoutOverlapping()
->withoutOverlapping(30) // 30 minute window
```

### System Cron Setup

Add to your crontab (`crontab -e`):

```
* * * * * cd /path/to/project && php pype.php schedule:run >> /dev/null 2>&1
```

### List Scheduled Tasks

```php
$tasks = $schedule->listTasks();
// Returns: [{ description, schedule, next_run }, ...]
```

## Localization (i18n)

Multi-language support with PHP translation files.

### Setup

```php
use Framework\Lang\Lang;

Lang::init('fr');           // Set locale
Lang::setLocale('es');      // Change locale
Lang::setFallback('en');    // Fallback for missing translations
```

### Translation Files

```php
// Resources/lang/en/messages.php
return [
    'welcome' => 'Welcome!',
    'goodbye' => 'Goodbye!',
    'users' => ':count user|:count users',
    'validation' => [
        'required' => 'The :field field is required.',
        'email' => 'The :field must be a valid email.',
    ],
];

// Resources/lang/fr/messages.php
return [
    'welcome' => 'Bienvenue!',
    'goodbye' => 'Au revoir!',
    'users' => ':count utilisateur|:count utilisateurs',
];
```

### Usage

```php
// Basic translation
echo Lang::get('messages.welcome');
echo __('messages.welcome');

// With replacements
echo __('messages.validation.required', ['field' => 'email']);
// Output: "The email field is required."

// Pluralization
echo trans_choice('messages.users', 1);  // "1 user"
echo trans_choice('messages.users', 5);  // "5 users"
echo trans_choice('messages.users', 0);  // "0 user"

// Check if exists
if (Lang::has('messages.welcome')) { ... }

// Available locales
$locales = Lang::availableLocales();
```

### JSON Translations

```php
// Resources/lang/fr.json
{
    "Welcome to our app": "Bienvenue dans notre application",
    "Sign In": "Se connecter"
}

echo __('Welcome to our app'); // Auto-checks JSON
```

### Auto-Detection

Locale is detected in this order:
1. `$_SESSION['locale']`
2. `$_COOKIE['locale']`
3. `HTTP_ACCEPT_LANGUAGE` header

### Helpers

```php
set_locale('fr');
echo __('messages.welcome');
echo trans_choice('messages.users', 3);
```

## Form Request Classes

Dedicated validation classes per request, like Laravel's FormRequest.

### Create Request Class

```php
// App/Requests/StoreUserRequest.php
use Framework\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please provide your email address.',
            'email.unique' => 'This email is already registered.',
        ];
    }
}
```

### Use in Controller

```php
// Controller
$request = StoreUserRequest::validate();

// Access validated data
$name = $request->input('name');
$email = $request->input('email');
$only = $request->only(['name', 'email']);
$except = $request->except(['password']);

// Check field
if ($request->has('remember')) { ... }
```

### Auto-Redirect on Failure

- **AJAX requests**: Returns JSON with 422 status and errors
- **Normal requests**: Redirects back with `$_SESSION['errors']` and `$_SESSION['old_input']`

## Pagination

Offset and cursor pagination with meta information.

### Offset Pagination

```php
use Framework\Pagination\Paginator;

$total = User::count();
$users = User::limit(15)->offset(($page - 1) * 15)->get();

$paginator = Paginator::make($users, $total, 15, $page);

// Access data
$paginator->items();        // Current page items
$paginator->currentPage();  // Current page number
$paginator->perPage();      // Items per page
$paginator->total();        // Total items
$paginator->lastPage();     // Last page number
$paginator->hasPages();     // Has more than 1 page
$paginator->hasMorePages(); // Has next page
$paginator->from();         // First item number
$paginator->to();           // Last item number

// URLs
$paginator->nextPageUrl();
$paginator->previousPageUrl();
$paginator->url(3);

// Render links
echo $paginator->links();           // Full pagination
echo $paginator->links('simple');   // Simple prev/next

// API response
return json($paginator->toArray());
```

### Cursor Pagination

Better for infinite scroll and large datasets.

```php
use Framework\Pagination\CursorPaginator;

$cursor = $_GET['cursor'] ?? null;
$items = User::limit(16)->get(); // Fetch + 1 to check for more

$paginator = CursorPaginator::make($items, 15, $cursor);

$paginator->items();       // Current page items
$paginator->nextCursor();  // Cursor for next page
$paginator->hasMore();     // Has more pages

echo $paginator->links();   // "Load More" button
```

### Helpers

```php
$paginator = paginate($items, $total, 15);
$cursor = cursor_paginate($items, 15, $cursor);
```

## Database Seeding & Faker

Generate test data with a built-in Faker library.

### Create Seeder

```php
// App/Seeders/UserSeeder.php
use Framework\Seeder\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 0; $i < 50; $i++) {
            User::create([
                'name' => $this->faker->name(),
                'email' => $this->faker->uniqueEmail(),
                'password' => hash_password('password123'),
                'phone' => $this->faker->phoneNumber(),
                'address' => $this->faker->address(),
                'city' => $this->faker->city(),
            ]);
        }
    }
}
```

### Run Seeders

```php
// Single seeder
(new UserSeeder())->run();
run_seeder(UserSeeder::class);

// Register and run all
SeederManager::add(UserSeeder::class);
SeederManager::add(PostSeeder::class);
SeederManager::runAll();
run_seeders();
```

### Faker Methods

```php
$faker = faker();

// Personal
$faker->name()          // "James Smith"
$faker->firstName()     // "James"
$faker->lastName()      // "Smith"
$faker->email()         // "james.smith42@gmail.com"
$faker->uniqueEmail()   // "user1@example.com"
$faker->username()      // "james_42"
$faker->phoneNumber()   // "(555) 123-4567"

// Location
$faker->address()       // "1234 Main St"
$faker->city()          // "New York"
$faker->state()         // "CA"
$faker->country()       // "US"

// Company
$faker->companyName()   // "Acme Corp"
$faker->jobTitle()      // "Developer"
$faker->domain()        // "smith.com"
$faker->url()           // "https://smith.com"

// Content
$faker->title()         // "The Amazing Guide"
$faker->sentence()      // Single sentence
$faker->paragraph()     // 5 sentences
$faker->text(3)         // 3 sentences
$faker->word()          // "lorem"
$faker->words(5)        // "lorem ipsum dolor sit amet"

// Numbers
$faker->numberBetween(1, 100)
$faker->randomNumber(5)     // 12345
$faker->randomFloat(2, 0, 100)  // 42.56
$faker->boolean()           // true or false

// Date/Time
$faker->dateTime()      // "2024-03-15 14:30:00"
$faker->date()          // "2024-03-15"
$faker->time()          // "14:30:00"

// Misc
$faker->uuid()          // UUID v4
$faker->color()         // "#A3F2B1"
$faker->imageUrl(800, 600)  // "https://picsum.photos/800/600"
$faker->randomElement(['a', 'b', 'c'])
$faker->shuffle($array)
```

## Debug Bar

In-browser debug panel for development.

### Enable

```php
// Automatically enabled when APP_DEBUG=true
debug_bar(true);

// Or manual
use Framework\Debug\DebugBar;
DebugBar::enable();
```

### Usage

```php
// Log messages
DebugBar::info('User logged in');
DebugBar::warning('Slow query detected');
DebugBar::error('Payment failed');
DebugBar::log($data, 'custom-label');

// Timing
DebugBar::time('query');
// ... database query ...
DebugBar::stopTime('query');

// Memory snapshot
DebugBar::memory('before-processing');

// Elapsed time
echo DebugBar::elapsed(); // "45.2ms"
```

### Debug Bar Shows

- **Queries**: SQL, bindings, execution time
- **Messages**: Info, warning, error logs
- **Timers**: Custom timing measurements
- **Memory**: Usage and peak snapshots

**Note**: Only renders when `APP_DEBUG=true` and content-type is HTML.

## Health Check

System health monitoring endpoint.

### Usage

```php
use Framework\Health\Health;

// Output JSON and exit (for /health endpoint)
Health::output();

// Or get array
$result = Health::check();
echo json_encode($result, JSON_PRETTY_PRINT);
```

### Checks Performed

| Check | Status | Description |
|---|---|---|
| PHP Version | ok/fail | Version >= 8.2 |
| Extensions | ok/fail | Required extensions loaded |
| Disk Space | ok/warning | Minimum free space |
| Database | ok/fail | Connection test |
| Cache | ok/warning/fail | Driver availability |
| Environment | ok/warning | Debug in production |
| Permissions | ok/fail | Storage writable |

### Custom Checks

```php
Health::addCheck('redis', 'ok', 'Redis connected');
Health::checkDatabase();
Health::checkDiskSpace('/path', 100); // Minimum 100MB
```

### Response

```json
{
    "status": "ok",
    "timestamp": "2026-05-05T12:00:00+00:00",
    "version": "2.5.5",
    "checks": {
        "php_version": { "status": "ok", "message": "PHP 8.2.1" },
        "database": { "status": "ok", "message": "Connected to mysql" }
    }
}
```

## Structured Logging

JSON logging with context for better log analysis.

### Usage

```php
use Framework\Health\Logger;

Logger::info('User logged in', ['user_id' => 1, 'ip' => '192.168.1.1']);
Logger::error('Payment failed', ['order_id' => 123, 'amount' => 99.99]);
Logger::warning('Rate limit approaching', ['user_id' => 1, 'count' => 95]);

// Log exceptions
try {
    // ...
} catch (\Throwable $e) {
    Logger::exception($e, ['context' => 'extra info']);
}

// Measure execution time
$result = Logger::measure('generate-report', function () {
    return generateReport();
}, ['user_id' => 1]);
```

### Levels

`emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`

### Output

Logs are written to `Storage/logs/structured-YYYY-MM-DD.log` as JSON:

```json
{
    "timestamp": "2026-05-05T12:00:00+00:00",
    "level": "ERROR",
    "message": "Payment failed",
    "context": { "order_id": 123 },
    "pid": 12345,
    "memory": "12.5MB"
}
```

### Channel-Based

```php
Logger::channel('payments')->info('Payment processed');
Logger::channel('auth')->warning('Failed login attempt');
```

### Helpers

```php
structured_log('error', 'Something failed', ['key' => 'value']);
log_info('User action', ['id' => 1]);
log_error('Something broke', ['context' => 'data']);
log_exception($e);
measure('operation-name', fn() => doWork());
```

## Docker Setup

### Quick Start

```bash
# Start all services
docker-compose up -d

# Run migrations
docker-compose exec app php pype.php migrate

# Run seeders
docker-compose exec app php pype.php seed

# View logs
docker-compose logs -f app

# Stop
docker-compose down
```

### Services

| Service | Port | Description |
|---|---|---|
| app | 8000 | PHP Apache application |
| db | 3306 | MySQL 8.0 |
| redis | 6379 | Redis cache |
| memcached | 11211 | Memcached |
| mailpit | 8025/1025 | Email testing UI + SMTP |

### Production Dockerfile

```bash
# Build
docker build -t pype-app .

# Run
docker run -p 8000:80 -e APP_ENV=production -e APP_DEBUG=false pype-app
```

## PHPUnit Testing

### Run Tests

```bash
composer install --dev
vendor/bin/phpunit

# With coverage
vendor/bin/phpunit --coverage-html coverage

# Specific test
vendor/bin/phpunit tests/Unit/CacheTest.php

# All unit tests
vendor/bin/phpunit --testsuite Unit
```

### Write Tests

```php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testBasic(): void
    {
        $this->assertTrue(true);
        $this->assertEquals('foo', 'foo');
        $this->assertArrayHasKey('key', ['key' => 'value']);
        $this->assertCount(3, [1, 2, 3]);
        $this->assertStringContainsString('foo', 'foobar');
        $this->assertNull(null);
        $this->assertIsArray([]);
    }
}
```

### Test Database

Tests use SQLite in-memory by default (configured in `phpunit.xml`):

```xml
<env name="DB_TYPE" value="sqlite"/>
<env name="DB_PATH" value=":memory:"/>
```

### GitHub Actions

CI runs automatically on push/PR to main:
- PHP 8.2, 8.3, 8.4
- MySQL + Redis services
- Code coverage upload
- PHP syntax linting
