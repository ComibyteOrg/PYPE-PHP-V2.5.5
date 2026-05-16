# Pype PHP Framework v2.5.5
### The Professional, Beginner-Friendly PHP Framework By Comibyte

<div align="center">
  <img src="https://oluwadimu-adedeji.web.app/images/logo1.png" alt="Pype PHP Framework" width="300">
  <br>
  <p>
    <img src="https://img.shields.io/badge/PHP-8.2%2B-blue?style=for-the-badge&logo=php" alt="PHP Version">
    <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License">
    <img src="https://img.shields.io/badge/Status-Production%20Ready-brightgreen?style=for-the-badge" alt="Status">
  </p>
  <p><strong>Modern • Fast • Beginner-Friendly • Django-Inspired</strong></p>
</div>

---

**Pype V2.5.5** comes with extra features our of the box, updating the errors in the <a href="https://github.com/ComibyteOrg/PYPE-PHP-V2.5">Pype PHP v2.5</a> release, adding more helping features and support for more rapid and secure development 

## 📚 Documentation

**NEW USERS?** Start with the [Getting Started Guide](#-quick-start-5-minutes) then explore the detailed docs below.

| Topic | Description | Link |
|-------|-------------|------|
| 🔐 Authentication | Login, register, sessions, social auth | [Auth Guide](docs/AUTH.md) |
| 🔌 API & Frontend | JWT, versioning, CORS, SSE, webhooks, uploads | [API Guide](docs/API.md) |
| ☁️ Cloud Storage | S3, Cloudinary, GCS, Azure, image processing | [Storage Guide](docs/STORAGE.md) |
| 🛒 E-Commerce | Cart, products, orders, payments, subscriptions | [Commerce Guide](docs/COMMERCE.md) |
| ✍️ Blog & CMS | Articles, SEO, RSS, markdown, comments, analytics | [Blog Guide](docs/BLOG.md) |
| ⚙️ Infrastructure | Cache, events, scheduler, i18n, testing, Docker | [Infrastructure Guide](docs/INFRASTRUCTURE.md) |
| 👥 Social Media | Posts, feeds, follows, messages, notifications | [Social Guide](docs/SOCIAL.md) |
| 🗄️ Database | Query builder, migrations, seeders, transactions | [Database Guide](docs/DATABASE.md) |
| 🛠️ Helpers | Global functions, input, redirects, CSRF, utilities | [Helper Guide](docs/HELPER.md) |
| 🌐 HTTP | Controllers, API resources, request/response handling | [HTTP Guide](docs/HTTP.md) |
| 📝 Logging | Application logging, error tracking, debug info | [Logging Guide](docs/LOGGING.md) |
| 📧 Mailing | Email sending, templates, queue | [Mailing Guide](docs/MAILING.md) |
| 🔒 Middleware | Request filtering, auth guards, CORS, rate limiting | [Middleware Guide](docs/MIDDLEWARE.md) |
| 📦 Models | ORM, schema definition, query builder, relationships | [Model Guide](docs/MODEL.md) |
| 🛣️ Router | URL routing, route groups, named routes, parameters | [Router Guide](docs/ROUTER.md) |
| 🛡️ Security | Encryption, 2FA, RBAC, audit logs, hardening | [Security Guide](docs/SECURITY.md) |
| 🎨 Twig | Templating, inheritance, filters, macros | [Twig Guide](docs/TWIG.md) |

---

## 📖 Complete Beginner's Guide

**Looking for a full tutorial?** Check out: **[📚 COMPLETE BEGINNER'S GUIDE - DOCUMENTATION.md](DOCUMENTATION.md)**

Or jump to specific topics:
- [🛣️ Routing](docs/ROUTER.md)
- [📦 Models & Database](docs/MODEL.md)
- [🔐 Authentication](docs/AUTH.md)
- [🔌 API & Frontend](docs/API.md)
- [☁️ Cloud Storage](docs/STORAGE.md)
- [📧 Mailing](docs/MAILING.md)
- [🎨 Twig Templates](docs/TWIG.md)
- [🛠️ Helper Functions](docs/HELPER.md)
- [🔒 Middleware](docs/MIDDLEWARE.md)
- [📝 Logging](docs/LOGGING.md)

---

## ✨ What is Pype?

Pype is a **modern PHP framework** inspired by Django (Python's popular framework). It makes web development **easier, faster, and more enjoyable** for beginners and experienced developers.

### Why Choose Pype?

| Feature | Plain PHP | Pype |
|---------|-----------|------|
| Database queries | Write SQL manually ❌ | Use Models (ORM) ✅ |
| URL routing | Complex .htaccess ❌ | Simple & elegant ✅ |
| User authentication | Build from scratch ❌ | Built-in ready ✅ |
| Input validation | Manual validation ❌ | Built-in validators ✅ |
| Templates | PHP files ❌ | Twig templates ✅ |
| Email sending | Manual SMTP setup ❌ | 1 line of code ✅ |
| File uploads | Handle manually ❌ | Helper functions ✅ |

---

## 🚀 Newly Updated Features

- ✅ **Model-View-Controller (MVC)** - Organize code cleanly
- ✅ **Django-style Models** - Define database schema with code
- ✅ **Powerful ORM** - Query database without SQL
- ✅ **Elegant Routing** - Simple URL to controller mapping
- ✅ **Built-in Authentication** - User login/logout included
- ✅ **Two-Factor Authentication** - TOTP-based 2FA with backup codes
- ✅ **JWT API Authentication** - Token pairs, refresh, scope-based auth
- ✅ **Social Login** - Google, GitHub, Facebook authentication
- ✅ **Database Migrations** - Version control for database
- ✅ **Twig Templating** - Clean, secure HTML templates
- ✅ **Email Service** - Send emails with one line
- ✅ **Multi-Disk Storage** - S3, Cloudinary, GCS, Azure, local
- ✅ **Image Processing** - Resize, crop, watermark, format conversion
- ✅ **File Uploads** - Chunked, resumable, with progress tracking
- ✅ **Input Validation** - 30+ validation rules, 15+ sanitization types
- ✅ **AES-256-GCM Encryption** - Encrypt sensitive data at rest
- ✅ **Role-Based Access Control** - Granular permissions with gates
- ✅ **Audit Logging** - Track who did what, when, and from where
- ✅ **Brute Force Protection** - Automatic lockout after failed attempts
- ✅ **Security Headers** - CSP, HSTS, Permissions-Policy automatically
- ✅ **Bot Protection** - Honeypot fields + timing analysis
- ✅ **API Key Management** - Generation, rotation, scopes, rate limits
- ✅ **API Versioning** - URL, header, and query-based versioning
- ✅ **Server-Sent Events** - Real-time streaming without WebSockets
- ✅ **Webhook System** - Event-driven HTTP callbacks with retries
- ✅ **OpenAPI/Swagger** - Auto-generated API documentation
- ✅ **CORS Middleware** - Allow-list, credentials, SPA presets
- ✅ **RFC 7807 Errors** - Standardized Problem Details responses
- ✅ **CDN Integration** - Automatic CDN URL rewriting
- ✅ **Upload Queues** - Background processing for large files
- ✅ **Model File Attachments** - Laravel-style file associations with cascade deletes
- ✅ **Auto File Cleanup** - Automatic file deletion when models are removed
- ✅ **Multi-Collection Files** - Organize files by type (avatar, documents, photos)
- ✅ **Social Media System** - Posts, feeds, follows, engagement, messaging
- ✅ **Multi-Type Posts** - Text, image, video, link, poll, and story posts
- ✅ **Activity Feeds** - Chronological + algorithmic timeline generation
- ✅ **Follow System** - Self-referential follows with follower counts
- ✅ **Engagement** - Likes, comments, shares, bookmarks
- ✅ **Direct Messaging** - Real-time DMs with read receipts
- ✅ **Notifications** - Database, SSE realtime, and email delivery
- ✅ **Hashtag & Mentions** - Auto-parsing #tags and @mentions
- ✅ **Content Moderation** - Reports, auto-flagging, approval queues
- ✅ **User Profiles** - Avatars, cover photos, bios, verified badges
- ✅ **Cursor Pagination** - Infinite scroll support for feeds
- ✅ **E-Commerce System** - Full store with cart, orders, payments, subscriptions
- ✅ **Blog & CMS** - Articles, SEO, RSS, markdown, comments, analytics
- ✅ **Infrastructure & DevEx** - Cache, events, scheduler, i18n, PHPUnit, Docker, debug bar, health checks
- ✅ **Shopping Cart** - Session + DB cart with merge on login
- ✅ **Product Catalog** - Variants, SKUs, categories, tags, images
- ✅ **Order Management** - Status tracking, history, refunds, returns
- ✅ **Payment Gateways** - Stripe, PayPal, Flutterwave, Paystack
- ✅ **Coupons & Discounts** - Percentage, fixed, BOGO, user-specific codes
- ✅ **Inventory Tracking** - Stock levels, alerts, backorder support
- ✅ **Tax Engine** - Location-based tax calculation
- ✅ **Shipping Calculator** - Flat rate, weight-based shipping
- ✅ **Invoice Generation** - HTML invoices with print/download
- ✅ **Subscriptions** - Recurring billing, trials, plan upgrades
- ✅ **User Profiles** - Avatars, cover photos, bios, verified badges
- ✅ **Cursor Pagination** - Infinite scroll support for feeds
- ✅ **CLI Tools** - Generate code with commands
- ✅ **Middleware** - Secure and filter requests
- ✅ **API Resources** - Build APIs easily
- ✅ **Logging** - Debug and track issues

---

## ⚡ Quick Start (5 Minutes)

### 1️⃣ Install

```bash
# Clone the repository
git clone https://github.com/ComibyteOrg/PYPE-PHP-V2.5.git your-project-name
cd your-project-name

# Install dependencies
composer install

# Initialize
php pype.php init
```

### 2️⃣ Configure Database

Edit `.env`:
```env
DB_TYPE=sqlite
DB_PATH=database.sqlite
```

### 3️⃣ Run Server

```bash
php pype.php serve
```

Visit `http://localhost:8000` - **Done!** 🎉

---

## 📚 Complete Installation Guide

### Prerequisites
- **PHP 8.0+** (`php --version`)
- **Composer** (`composer --version`)
- **Web server** (Apache/Nginx) or PHP built-in server
- **Git** (optional)

### Step-by-Step Setup

```bash
# 1. Clone
git clone https://github.com/ComibyteOrg/PYPE-PHP-V2.5.git your-project-name
cd your-project-name

# 2. Install dependencies
composer install

# 3. Initialize framework
php pype.php init

# 4. Configure database in .env
# Edit .env file with your database details

# 5. Run migrations
php pype.php migrate

# 6. Start development server
php pype.php serve
# Opens at http://localhost:8000
```

### Database Configuration Options

**SQLite (Easiest for beginners):**
```env
DB_TYPE=sqlite
DB_PATH=database.sqlite
```

**MySQL:**
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=root
DB_PASS=password
```

**PostgreSQL:**
```env
DB_TYPE=postgresql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=postgres
DB_PASS=password
DB_PORT=5432
```

---

## 🛣️ Quick Examples

### Define Routes (URLs)

```php
// routes/web.php
use Framework\Router\Route;

// Simple route
Route::get('/', function() {
    return "Hello World!";
});

// CRUD Routes
Route::get('/users', 'UserController@index');
Route::get('/users/{id}', 'UserController@show');
Route::post('/users', 'UserController@store');
Route::put('/users/{id}', 'UserController@update');
Route::delete('/users/{id}', 'UserController@destroy');

// Or Can also be used as 
Route::get("/users", [UserController::class, 'index'])
```

📖 Learn more: **[Router Guide](docs/ROUTER.md)** | **[Middleware Guide](docs/MIDDLEWARE.md)** | **[HTTP Guide](docs/HTTP.md)**

### Create Models

```php
// App/Models/User.php
namespace App\Models;
use Framework\Model\Model;

class User extends Model
{
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

📖 Learn more: **[Model Guide](docs/MODEL.md)** | **[Database Guide](docs/DATABASE.md)**

### Query Database

```php
// Get all users
$users = User::all();

// Find by ID
$user = User::find(1);

// Filter
$active = User::where('status', 'active')->get();

// Create
User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Delete
User::find(1)->delete();
```

📖 Learn more: **[Database Guide](docs/DATABASE.md)** | **[Model Guide](docs/MODEL.md)**

### Validate Input

```php
use Framework\Helper\Validator;

$validator = Validator::make($_POST, [
    'name' => 'required|alpha',
    'email' => 'required|email|unique:users',
    'password' => 'required|min:8'
]);

if ($validator->fails()) {
    $errors = $validator->errors();
}

// Or use the helper function
$result = validate($_POST, [
    'email' => 'required|email',
    'password' => 'required|strong_password',
    'age' => 'integer|between:18,120',
]);
```

📖 Learn more: **[Helper Guide](docs/HELPER.md)** | **[Security Guide](docs/SECURITY.md)**

### Handle Authentication

```php
use Framework\Helper\Auth;

// Login
Auth::table('users')->login($email, $password);

// Check if logged in
if (Auth::table('users')->check()) {
    $user = Auth::table('users')->user();
}

// Logout
Auth::table('users')->logout();
```

📖 Learn more: **[Auth Guide](docs/AUTH.md)** | **[Middleware Guide](docs/MIDDLEWARE.md)**

### Send Emails

```php
use Framework\Mail\Mailer;

Mailer::send(
    'user@example.com',
    'Welcome!',
    '<h1>Hello!</h1><p>Welcome to our site.</p>'
);
```

📖 Learn more: **[Mailing Guide](docs/MAILING.md)** | **[Logging Guide](docs/LOGGING.md)**

### Create Templates (Twig)

```twig
{# Resources/views/users/index.html #}
{% extends "layout.html" %}

{% block title %}Users{% endblock %}

{% block content %}
    <h1>Users</h1>
    <ul>
    {% for user in users %}
        <li>
            <a href="/users/{{ user.id }}">{{ user.name }}</a>
            <p>{{ user.email }}</p>
        </li>
    {% endfor %}
    </ul>
{% endblock %}
```

📖 Learn more: **[Twig Guide](docs/TWIG.md)**

### Attach Files to Models

```php
use App\Models\User;

// Enable file attachments
class User extends Model
{
    use \Framework\Model\HasFiles;
}

// Attach files
$user = User::find(1);
$user->attachFile($_FILES['avatar'], 'avatar');
$user->attachFile($_FILES['documents'], 'documents');

// Get file URLs
$avatarUrl = $user->getFileUrl('avatar');
$docUrls = $user->getFileUrls('documents');

// Files auto-delete when model is removed
$user->remove(); // Deletes user AND all attached files
```

📖 Learn more: **[Model Guide](docs/MODEL.md)** | **[Storage Guide](docs/STORAGE.md)**

### Social Media Features

```php
use App\Models\User;
use Framework\Social\Post;
use Framework\Social\FeedBuilder;

// Add social capabilities to User model
class User extends Model {
    use \Framework\Social\Followable;
    use \Framework\Model\HasFiles;
}

// Follow/unfollow users
$user->follow($otherUser);
$user->isFollowing($otherUser);

// Create posts
$post = Post::createPost($userId, 'image', 'Check this out!');
$post->attachFile($_FILES['photo'], 'media');

// Engagement
$post->like($userId);
$post->addComment($userId, 'Amazing!');
$post->share($userId);

// Generate feed
$feed = FeedBuilder::forUser($userId)
    ->algorithmic()
    ->limit(20)
    ->get();

// Direct messaging
$conv = Conversation::createBetween($userId1, $userId2);
$conv->sendMessage($userId1, 'Hello!');

// Notifications
Notification::send($userId, 'new_follower', ['from_user_id' => 5]);
$unread = Notification::unread($userId);
```

📖 Learn more: **[Social Guide](docs/SOCIAL.md)**

### E-Commerce & Store

```php
use Framework\Commerce\Product;
use Framework\Commerce\Order;
use Framework\Commerce\Cart;
use Framework\Commerce\Payment;

// Setup commerce tables
\Framework\Commerce\CommerceMigrations::create();

// Create product
$product = Product::createProduct([
    'name' => 'Premium Headphones',
    'price' => 99.99,
    'sku' => 'HP-001',
    'status' => 'active',
]);
$product->attachFile($_FILES['image'], 'images');

// Shopping cart
Cart::add($product->id, 2);
$items = Cart::getItems();
$total = Cart::total();

// Apply coupon
$coupon = Coupon::validate('SAVE20', $userId, Cart::total());

// Create order from cart
$order = Order::createFromCart($userId, Cart::getItems(), $shippingAddress, 'stripe', [
    'tax_total' => $tax['amount'],
    'shipping_total' => $shipping,
    'discount_total' => $coupon['discount'] ?? 0,
]);

// Process payment
$payment = Payment::charge($order->total, 'USD', [
    'token' => $_POST['stripe_token'],
    'description' => "Order #{$order->order_number}",
]);

if ($payment['success']) {
    $order->markAsPaid($payment['data']['id']);
}

// Subscriptions
$sub = Subscription::createSubscription($userId, $planId, 'stripe', [
    'trial_days' => 14,
]);

// Generate invoice
Invoice::download($order->id);
```

### Blog & Content Management

```php
use Framework\Blog\Article;
use Framework\Blog\Category;
use Framework\Blog\Comment;
use Framework\Blog\Markdown;
use Framework\Blog\SEO;
use Framework\Blog\RSS;
use Framework\Blog\Search;
use Framework\Blog\Analytics;
use Framework\Blog\BlogMigrations;

BlogMigrations::create();

// Create & manage articles
$article = create_article([
    'title' => 'Getting Started',
    'content' => '# Hello World',
    'author_id' => 1,
    'status' => 'draft',
]);

$article->schedulePublish('2026-06-01 09:00:00');
$article->saveRevision('Fixed typo');
$article->publish();

// Markdown with syntax highlighting
$html = parse_markdown("```php\necho 'Hello';\n```");

// SEO toolkit
echo generate_seo([
    'title' => $article->title,
    'description' => $article->excerpt,
    'url' => $articleUrl,
    'image' => $article->getCoverImageUrl(),
]);

// Search with ranking
$results = blog_search('php tutorial', ['limit' => 10]);

// Comments with moderation
add_comment([
    'article_id' => $article->id,
    'author_name' => 'Jane',
    'author_email' => 'jane@example.com',
    'content' => 'Great post!',
]);

// Analytics
track_view($article->id);
$popular = popular_articles(10, 30);
$trending = trending_posts(24);
```

📖 Learn more: **[Blog Guide](docs/BLOG.md)**

### Infrastructure & DevEx

```php
use Framework\Cache\Cache;
use Framework\Events\Event;
use Framework\Scheduler\Scheduler;
use Framework\Lang\Lang;
use Framework\Debug\DebugBar;
use Framework\Health\Health;
use Framework\Health\Logger;
use Framework\Pagination\Paginator;

// Cache
Cache::put('key', 'value', 3600);
$value = Cache::remember('users', 60, fn() => User::all());
Cache::tags(['users', 'admin'])->put('data', $value, 3600);

// Events
Event::listen('user.created', fn($user) => sendWelcomeEmail($user));
Event::dispatch('user.created', $user);

// Scheduler (run via cron: * * * * * php pype.php schedule:run)
$schedule = new Scheduler();
$schedule->call(fn() => Article::publishScheduled())->daily();
$schedule->command('php artisan cache:clear')->weekly();
$schedule->run();

// Localization
echo __('messages.welcome');
echo trans_choice('messages.users', 5);

// Pagination
$paginator = paginate($items, $total, 15);
echo $paginator->links();

// Debug Bar (dev only)
debug_bar(true);
debug_time('query');
// ... database query ...
debug_stop_time('query');

// Health Check
Health::output(); // GET /health

// Structured Logging
Logger::info('User logged in', ['user_id' => 1]);
Logger::exception($e);
measure('expensive-operation', fn() => heavyTask());

// Docker
// docker-compose up -d
// docker-compose exec app php pype.php migrate
```

📖 Learn more: **[Infrastructure Guide](docs/INFRASTRUCTURE.md)**

---

## 📁 Project Structure

```
my-project/
├── Framework/              ← Core framework code
│   ├── Auth/              ← Authentication
│   ├── Database/          ← Database & ORM
│   ├── Router/            ← Routing
│   ├── Model/             ← Model base class
│   ├── Http/              ← Controllers & Resources
│   ├── Mail/              ← Email service
│   ├── Middleware/        ← Security & middleware
│   ├── Social/            ← Social media features
│   ├── Commerce/          ← E-commerce system
│   ├── Blog/              ← Blog & CMS features
│   ├── Cache/             ← Caching system
│   ├── Events/            ← Event dispatcher
│   ├── Scheduler/         ← Task scheduler
│   ├── Lang/              ← Localization
│   ├── Pagination/        ← Pagination helpers
│   ├── Seeder/            ← Database seeding & faker
│   ├── Debug/             ← Debug bar
│   ├── Health/            ← Health checks & structured logging
│   ├── Storage/           ← Cloud storage drivers
│   ├── Api/               ← API & JWT auth
│   ├── Security/          ← Security suite
│   └── Helper/            ← Utilities
├── App/                    ← Your application code
│   ├── Models/            ← Database models
│   ├── Controller/        ← Request handlers
│   ├── Middleware/        ← Custom middleware
│   └── Helpers/           ← Custom utilities
├── routes/
│   └── web.php            ← URL definitions
├── Resources/
│   └── views/             ← HTML templates
├── Storage/
│   ├── logs/              ← Application logs
│   └── uploads/           ← Uploaded files
├── migrations/            ← Database migrations
├── .env                   ← Configuration
├── index.php              ← Entry point
└── pype.php               ← CLI tool
```

---

## 🔧 CLI Commands

```bash
# Development server
php pype.php serve

# Create migration
php pype.php make:migration create_users_table

# Run migrations
php pype.php migrate

# Rollback
php pype.php migrate:rollback

# Create seeder
php pype.php make:seeder UserSeeder

# Run seeders
php pype.php seed

# Initialize
php pype.php init
```

📖 Learn more: **[Database Guide](docs/DATABASE.md)**

---

## 🔒 Security Best Practices

- ✅ **Validate input** - 30+ validation rules, 15+ sanitization types → [Helper Guide](docs/HELPER.md) | [Security Guide](docs/SECURITY.md)
- ✅ **Hash passwords** - Argon2ID with configurable policies → [Auth Guide](docs/AUTH.md)
- ✅ **Enable 2FA** - TOTP-based two-factor authentication → [Security Guide](docs/SECURITY.md)
- ✅ **Use CSRF tokens** - Middleware applied automatically → [Middleware Guide](docs/MIDDLEWARE.md)
- ✅ **Escape output** - Twig does this automatically → [Twig Guide](docs/TWIG.md)
- ✅ **Use parameterized queries** - ORM does this → [Model Guide](docs/MODEL.md)
- ✅ **Encrypt sensitive data** - AES-256-GCM encryption → [Security Guide](docs/SECURITY.md)
- ✅ **Implement RBAC** - Role-based access control → [Security Guide](docs/SECURITY.md)
- ✅ **Audit log actions** - Track who did what → [Security Guide](docs/SECURITY.md)
- ✅ **Security headers** - CSP, HSTS, X-Frame-Options → [Middleware Guide](docs/MIDDLEWARE.md)
- ✅ **Brute force protection** - Automatic lockout → [Security Guide](docs/SECURITY.md)
- ✅ **Keep dependencies updated** - Run `composer update`
- ✅ **Use HTTPS** - Always in production
- ✅ **Environment variables** - Store secrets in .env → [Database Guide](docs/DATABASE.md)

---

## 📖 Learning Resources

1. **[Component Documentation](#-documentation)** - Detailed guides for each feature
2. **[Complete Beginner's Guide](DOCUMENTATION.md)** - Step-by-step tutorials
3. **[Router Guide](docs/ROUTER.md)** - URL routing & controllers
4. **[Model Guide](docs/MODEL.md)** - Database ORM & queries
5. **[Auth Guide](docs/AUTH.md)** - Login, register, sessions
6. **[Twig Documentation](https://twig.symfony.com/)** - Official Twig docs
7. **[GitHub Repository](https://github.com/ComibyteOrg/PYPE-PHP-V2.5)** - Source code
8. **CLI Help** - `php pype.php --help`

---

## 🐛 Common Issues & Solutions

### "Class not found" Error
Check you're using the correct namespace:
```php
// Correct
use App\Models\User;
```
📖 See: **[Model Guide](docs/MODEL.md)**

### Database Connection Error
Verify `.env` file:
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=my_database
DB_USER=root
DB_PASS=password
```
📖 See: **[Database Guide](docs/DATABASE.md)**

### Route Not Found (404)
Check route is defined in `routes/web.php` and controller method exists.
📖 See: **[Router Guide](docs/ROUTER.md)**

### Template Not Found
Templates must be in `Resources/views/` with `.html` extension.
📖 See: **[Twig Guide](docs/TWIG.md)**

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details.

---

## 💬 Support

- **Issues:** [GitHub Issues](https://github.com/ComibyteOrg/PYPE-PHP-V2.5/issues)
- **Documentation:** [Component Docs](#-documentation) | [Beginner Guide](DOCUMENTATION.md)
- **Twig Help:** [Twig Docs](https://twig.symfony.com/)

---

## 🎉 Get Started Today!

```bash
git clone https://github.com/ComibyteOrg/PYPE-PHP-V2.5.git
cd PYPE-PHP-V2.5
composer install
php pype.php serve
```

Visit `http://localhost:8000` and start building! 🚀

---

**Made with ❤️ by Comibyte**
