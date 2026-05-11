<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/Storage/logs/php-error.log');

require __DIR__ . "/vendor/autoload.php";

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 🧠 Custom fallback autoloader (in case Composer misses App\)
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/';
    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: log request URI and session id to help trace redirect loops (remove in production)
// error_log("[DEBUG] Request URI: " . ($_SERVER['REQUEST_URI'] ?? '') . " | PHPSESSID: " . session_id());

use Framework\Router\Route;
use Framework\Middleware\AuthMiddleware;
use Framework\Middleware\GuestMiddleware;
use Framework\Middleware\CorsMiddleware;
use Framework\Middleware\RateLimitMiddleware;
use function Framework\Router\view;
use Framework\Middleware\CsrfMiddleware;
use Framework\Middleware\LogMiddleware;
use Framework\Auth\SocialAuthController;
// ------------------------------------------------------------------------


// ------------------------------------------------------------------------

Route::setViewPath(__DIR__ . '/Resources/views');
Route::registerMiddleware('auth', AuthMiddleware::class);
Route::registerMiddleware('guest', GuestMiddleware::class);
Route::registerMiddleware('cors', CorsMiddleware::class);
Route::registerMiddleware('rate_limit', RateLimitMiddleware::class);
Route::registerMiddleware('csrf', CsrfMiddleware::class);
Route::registerMiddleware('log', LogMiddleware::class);

// Social Authentication Routes
Route::get('/auth/{provider}', SocialAuthController::class, 'redirectToProvider');
Route::get('/auth/{provider}/callback', SocialAuthController::class, 'handleProviderCallback');
include __DIR__ . "/routes/web.php";

// ------------------------------------------------------------------------
Route::dispatch();
