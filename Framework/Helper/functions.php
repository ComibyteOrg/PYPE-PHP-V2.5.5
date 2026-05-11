<?php

use Framework\Helper\Helper;
use Framework\Helper\CSRF;

if (!function_exists('sanitize')) {
    function sanitize($input)
    {
        return Helper::sanitize($input);
    }
}

if (!function_exists('redirect')) {
    function redirect($page, $seconds = 0)
    {
        return Helper::redirect($page, $seconds);
    }
}

if (!function_exists('set_alert')) {
    function set_alert($type, $message)
    {
        return Helper::set_alert($type, $message);
    }
}

if (!function_exists('writetxt')) {
    function writetxt($file_name, $values = array())
    {
        return Helper::writetxt($file_name, $values);
    }
}

if (!function_exists('deletetxt')) {
    function deletetxt($file_name, $cond)
    {
        return Helper::deletetxt($file_name, $cond);
    }
}

if (!function_exists('returnJson')) {
    function returnJson(array $data, int $statusCode = 200)
    {
        Helper::returnJson($data, $statusCode);
    }
}

if (!function_exists('excerpt')) {
    function excerpt(string $html, int $length = 150, string $suffix = '...')
    {
        return Helper::excerpt($html, $length, $suffix);
    }
}

if (!function_exists('readingTime')) {
    function readingTime(string $content, int $wpm = 200)
    {
        return Helper::readingTime($content, $wpm);
    }
}

if (!function_exists('dd')) {
    function dd(...$args)
    {
        Helper::dd(...$args);
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        return Helper::env($key, $default);
    }
}

if (!function_exists('asset')) {
    function asset(string $path)
    {
        return Helper::asset($path);
    }
}

if (!function_exists('url')) {
    function url(string $path, array $parameters = [])
    {
        return Helper::url($path, $parameters);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $string, string $separator = '-')
    {
        return Helper::slugify($string, $separator);
    }
}

if (!function_exists('session')) {
    function session(string $key, $default = null)
    {
        return Helper::session($key, $default);
    }
}

if (!function_exists('old')) {
    function old(string $key)
    {
        return Helper::old($key);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        return CSRF::getTokenField();
    }
}

if (!function_exists('csrfField')) {
    function csrfField()
    {
        return CSRF::getTokenField();
    }
}

if (!function_exists('csrfInput')) {
    function csrfInput()
    {
        return CSRF::getTokenField();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        return CSRF::generateToken();
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(string $token)
    {
        return CSRF::validateToken($token);
    }
}

if (!function_exists('csrf_enforce')) {
    /**
     * Enforce CSRF for form submissions.
     * If token missing or invalid, render a 419 Page Expired view and exit.
     */
    function csrf_enforce()
    {
        $tokenName = CSRF::getTokenName();
        $submitted = $_POST[$tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['X-CSRF-TOKEN'] ?? '';

        if (empty($submitted) || !CSRF::validateToken($submitted)) {
            http_response_code(419);

            // Check if expectation is JSON
            if (
                (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false)
            ) {

                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'CSRF token missing or invalid', 'error' => '419 Page Expired']);
                exit;
            }

            // Simple plain response (no template) — consistent with router errorResponse
            echo "<div style='font-family: Arial; text-align: center; display: flex; height: 100vh; align-items: center; justify-content: center;'>"
                . "<div><h2>419</h2><p>Page Expired — the form token is missing or invalid. Please reload and try again.</p></div>"
                . "</div>";
            exit;
        }
    }
}

if (!function_exists('getCSRFName')) {
    function getCSRFName()
    {
        $tokenName = CSRF::getTokenName();
        return $_POST[$tokenName] ?? '';
    }
}

if (!function_exists('view')) {
    function view(string $viewName, array $data = [], bool $return = false)
    {
        // Check if we should use Twig (based on file extension or automatic detection)
        $useTwig = false;
        $templateName = $viewName;

        // If view name already has twig extension, use Twig
        if (strpos($viewName, '.twig') !== false) {
            $useTwig = true;
            $templateName = str_replace('.', '/', $viewName);
        } else {
            // Check if a Twig template exists for this view
            $viewPath = \Framework\Router\Route::getViewPath();
            $twigTemplate = $viewPath . '/' . str_replace('.', '/', $viewName) . '.twig';

            if (file_exists($twigTemplate)) {
                $useTwig = true;
                $templateName = str_replace('.', '/', $viewName) . '.twig';
            }
        }

        if ($useTwig) {
            // Use Twig template
            try {
                $output = \Framework\Helper\TwigManager::render($templateName, $data);
                if ($return)
                    return $output;
                echo $output;
                return;
            } catch (\Exception $e) {
                $msg = "<b>Error:</b> Twig template error: " . $e->getMessage();
                if ($return)
                    return $msg;
                echo $msg;
                return;
            }
        } else {
            // Use traditional PHP templates
            $viewPath = \Framework\Router\Route::getViewPath();

            if (empty($viewPath)) {
                $msg = "<b>Error:</b> View path not configured.";
                if ($return)
                    return $msg;
                echo $msg;
                return;
            }

            $file = $viewPath . '/' . str_replace('.', '/', $viewName) . '.php';

            if (file_exists($file)) {
                extract($data);
                ob_start();
                require $file;
                $output = ob_get_clean();
                if ($return)
                    return $output;
                echo $output;
                return;
            } else {
                $msg = "<b>Error:</b> View '{$viewName}' not found at {$file}.";
                if ($return)
                    return $msg;
                echo $msg;
            }
        }
    }
}

if (!function_exists('input')) {
    function input($key = null, $default = null)
    {
        return Helper::input($key, $default);
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, $key, $default = null)
    {
        return Helper::array_get($array, $key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = '')
    {
        return Helper::base_path($path);
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = '')
    {
        return Helper::app_path($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = '')
    {
        return Helper::storage_path($path);
    }
}

if (!function_exists('method')) {
    function method()
    {
        return Helper::method();
    }
}

if (!function_exists('EmailService')) {
    function EmailService()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new \Framework\Helper\EmailService();
        }
        return $instance;
    }
}

if (!function_exists('db_path')) {
    function db_path()
    {
        return Helper::db_path();
    }
}

if (!function_exists('upload')) {
    function upload($file, $directory, $allowedExtensions = [])
    {
        return Helper::upload($file, $directory, $allowedExtensions);
    }
}

if (!function_exists('auth')) {
    function auth()
    {
        return Helper::auth();
    }
}

if (!function_exists('check')) {
    function check()
    {
        return Helper::check();
    }
}

if (!function_exists('logout')) {
    function logout()
    {
        return Helper::logout();
    }
}

if (!function_exists('flash')) {
    function flash($key, $message = null)
    {
        if ($message === null) {
            return Helper::getFlash($key);
        }
        Helper::flash($key, $message);
        return null;
    }
}

if (!function_exists('getFlash')) {
    function getFlash($key)
    {
        return Helper::getFlash($key);
    }
}


if (!function_exists('dd')) {
    function dd(...$args)
    {
        // End any previous output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        echo '<div style="background: #0f172a; color: #10b981; padding: 20px; font-family: monospace; border-left: 4px solid #3b82f6; margin: 20px; border-radius: 8px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">';
        echo '<div style="color: #3b82f6; font-weight: bold; margin-bottom: 15px; font-size: 14px;">';
        echo '🔍 Debug Dump - ' . date('Y-m-d H:i:s');
        echo '</div>';
        
        foreach ($args as $index => $arg) {
            echo '<div style="margin-bottom: 20px;">';
            echo '<span style="color: #f59e0b; font-size: 12px;">Argument ' . ($index + 1) . ':</span><br>';
            echo '<pre style="color: #10b981; overflow-x: auto; padding: 15px; background: rgba(15, 23, 42, 0.8); border-radius: 6px; border: 1px solid rgba(59, 130, 246, 0.2);">';
            var_dump($arg);
            echo '</pre>';
            echo '</div>';
        }
        
        echo '<div style="color: #ef4444; font-size: 12px; margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(59, 130, 246, 0.2);">';
        echo '⚠ Execution terminated';
        echo '</div>';
        echo '</div>';
        exit;
    }
}

if (!function_exists('env')) {
    function env($key, $default = null)
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}


if (!function_exists('redirectWith')) {
    function redirectWith($url, $key, $message = null, $seconds = 0)
    {
        Helper::redirectWith($url, $key, $message, $seconds);
    }
}

// =========================================================
// AUTHENTICATION HELPERS (Universal - Works with any table)
// =========================================================

if (!function_exists('auth')) {
    /**
     * Get Auth instance for a specific table or default user
     * @param string|null $table Table name (default: users)
     * @return \Framework\Helper\Auth|null
     */
    function auth($table = null)
    {
        if ($table === null) {
            return Helper::auth();
        }
        return Helper::auth($table);
    }
}

if (!function_exists('login')) {
    /**
     * Login user to any table
     * @param string $email
     * @param string $password
     * @param string $table Table name (default: users)
     * @param bool $remember Remember me
     * @return object|null
     */
    function login($email, $password, $table = 'users', $remember = false)
    {
        return Helper::login($email, $password, $table, $remember);
    }
}

if (!function_exists('register')) {
    /**
     * Register new user
     * @param array $data User data
     * @param string $table Table name (default: users)
     * @param bool $autoLogin Auto login after register
     * @return object|null
     */
    function register($data, $table = 'users', $autoLogin = true)
    {
        return Helper::register($data, $table, $autoLogin);
    }
}

if (!function_exists('check')) {
    /**
     * Check if user is authenticated
     * @param string $table Table name (default: users)
     * @return bool
     */
    function check($table = 'users')
    {
        return Helper::check($table);
    }
}

if (!function_exists('user')) {
    /**
     * Get authenticated user
     * @param string $table Table name (default: users)
     * @return object|null
     */
    function user($table = 'users')
    {
        return Helper::user($table);
    }
}

if (!function_exists('logout')) {
    /**
     * Logout user
     * @param string $table Table name (default: users)
     * @return void
     */
    function logout($table = 'users')
    {
        Helper::logout($table);
    }
}

if (!function_exists('userId')) {
    /**
     * Get authenticated user ID
     * @param string $table Table name (default: users)
     * @return int|null
     */
    function userId($table = 'users')
    {
        return Helper::userId($table);
    }
}

if (!function_exists('isAdmin')) {
    /**
     * Check if admin is authenticated
     * @return bool
     */
    function isAdmin()
    {
        return Helper::adminCheck();
    } 
}

if (!function_exists('admin')) {
    /**
     * Get authenticated admin
     * @return object|null
     */
    function admin()
    {
        return Helper::adminAuth();
    }
}

if (!function_exists('adminLogin')) {
    /**
     * Login admin
     * @param string $email
     * @param string $password
     * @param bool $remember Remember me
     * @return object|null
     */
    function adminLogin($email, $password, $remember = false)
    {
        return Helper::adminAuthenticate($email, $password);
    }
}

if (!function_exists('adminLogout')) {
    /**
     * Logout admin
     * @return void
     */
    function adminLogout()
    {
        Helper::adminLogout();
    }
}

// =========================================================
// DATABASE HELPER SHORTCUTS
// =========================================================

if (!function_exists('db')) {
    /**
     * Get DB table instance
     * @param string $table Table name
     * @return \Framework\Helper\DB
     */
    function db($table)
    {
        return \Framework\Helper\DB::table($table);
    }
}

if (!function_exists('table')) {
    /**
     * Get DB table instance (alias for db)
     * @param string $table Table name
     * @return \Framework\Helper\DB
     */
    function table($table)
    {
        return \Framework\Helper\DB::table($table);
    }
}

// =========================================================
// MODEL QUERY BUILDER SHORTCUTS
// =========================================================

if (!function_exists('model')) {
    /**
     * Get model instance for query building
     * @param string $model Model class name
     * @return \Framework\Model\Model
     */
    function model($model)
    {
        $class = "App\\Models\\$model";
        if (class_exists($class)) {
            return new $class();
        }
        throw new \Exception("Model class '$class' not found");
    }
}

// =========================================================
// REQUEST HELPER SHORTCUTS
// =========================================================

if (!function_exists('request')) {
    /**
     * Get request input
     * @param string|null $key Key name
     * @param mixed $default Default value
     * @return mixed
     */
    function request($key = null, $default = null)
    {
        return Helper::input($key, $default);
    }
}

if (!function_exists('post')) {
    /**
     * Get POST data
     * @param string|null $key Key name
     * @param mixed $default Default value
     * @return mixed
     */
    function post($key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }
}

if (!function_exists('get')) {
    /**
     * Get GET data
     * @param string|null $key Key name
     * @param mixed $default Default value
     * @return mixed
     */
    function get($key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }
}

if (!function_exists('has')) {
    /**
     * Check if request has key
     * @param string $key Key name
     * @return bool
     */
    function has($key)
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }
}

if (!function_exists('method')) {
    /**
     * Get request method
     * @return string
     */
    function method()
    {
        return Helper::method();
    }
}

if (!function_exists('isAjax')) {
    /**
     * Check if request is AJAX
     * @return bool
     */
    function isAjax()
    {
        return Helper::isAjax();
    }
}

// =========================================================
// RESPONSE HELPER SHORTCUTS
// =========================================================

if (!function_exists('json')) {
    /**
     * Return JSON response
     * @param array $data Data to encode
     * @param int $statusCode HTTP status code
     * @return void
     */
    function json($data, $statusCode = 200)
    {
        Helper::json($data, $statusCode);
    }
}

if (!function_exists('abort')) {
    /**
     * Abort with error
     * @param int $code HTTP status code
     * @param string $message Error message
     * @return void
     */
    function abort($code, $message = '')
    {
        http_response_code($code);
        if (empty($message)) {
            $message = "Error $code";
        }
        echo "<h1>Error $code</h1><p>$message</p>";
        exit;
    }
}

// =========================================================
// UTILITY HELPERS
// =========================================================

if (!function_exists('now')) {
    /**
     * Get current datetime
     * @return string
     */
    function now()
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('today')) {
    /**
     * Get current date
     * @return string
     */
    function today()
    {
        return date('Y-m-d');
    }
}

if (!function_exists('str_random')) {
    /**
     * Generate random string
     * @param int $length Length of string
     * @return string
     */
    function strRandom($length = 16)
    {
        return bin2hex(random_bytes($length / 2));
    }
}

if (!function_exists('hashPassword')) {
    /**
     * Hash password
     * @param string $password Password to hash
     * @return string
     */
    function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verifyPassword')) {
    /**
     * Verify password
     * @param string $password Password to verify
     * @param string $hash Hash to verify against
     * @return bool
     */
    function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
}

if (!function_exists('getClientIP')) {
    /**
     * Get client IP address
     * @return string
     */
    function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

if (!function_exists('userAgent')) {
    /**
     * Get user agent
     * @return string
     */
    function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}

if (!function_exists('referer')) {
    /**
     * Get referer URL
     * @return string
     */
    function referer()
    {
        return $_SERVER['HTTP_REFERER'] ?? '';
    }
}

// =========================================================
// SECURITY HELPERS
// =========================================================

if (!function_exists('sanitize_input')) {
    function sanitize_input($input, string $type = 'string')
    {
        return \Framework\Helper\InputSanitizer::sanitize($input, $type);
    }
}

if (!function_exists('sanitize_array')) {
    function sanitize_array(array $data, ?array $rules = null): array
    {
        return \Framework\Helper\InputSanitizer::sanitizeArray($data, $rules);
    }
}

if (!function_exists('validate')) {
    function validate(array $data, array $rules, array $messages = [])
    {
        return \Framework\Helper\EnhancedValidator::make($data, $rules, $messages)->validate();
    }
}

if (!function_exists('hash_password')) {
    function hash_password(string $password): string
    {
        return \Framework\Security\PasswordPolicy::hash($password);
    }
}

if (!function_exists('verify_password')) {
    function verify_password(string $password, string $hash): bool
    {
        return \Framework\Security\PasswordPolicy::verify($password, $hash);
    }
}

if (!function_exists('check_password_policy')) {
    function check_password_policy(string $password, string $level = 'default', ?string $username = null, ?string $email = null): array
    {
        return \Framework\Security\PasswordPolicy::validate($password, $level, $username, $email);
    }
}

if (!function_exists('password_strength')) {
    function password_strength(string $password): array
    {
        return \Framework\Security\PasswordPolicy::validate($password);
    }
}

if (!function_exists('encrypt')) {
    function encrypt($value): string
    {
        return \Framework\Security\Encryption::encrypt($value);
    }
}

if (!function_exists('decrypt')) {
    function decrypt(string $encrypted): mixed
    {
        return \Framework\Security\Encryption::decrypt($encrypted);
    }
}

if (!function_exists('encrypt_string')) {
    function encrypt_string(string $value): string
    {
        return \Framework\Security\Encryption::encryptString($value);
    }
}

if (!function_exists('decrypt_string')) {
    function decrypt_string(string $encrypted): string
    {
        return \Framework\Security\Encryption::decryptString($encrypted);
    }
}

if (!function_exists('audit_log')) {
    function audit_log(string $action, string $entity, ?int $entityId = null, array $data = [], string $severity = 'info'): void
    {
        \Framework\Security\AuditLog::log($action, $entity, $entityId, $data, $severity);
    }
}

if (!function_exists('two_factor_uri')) {
    function two_factor_uri(string $secret, string $issuer, string $accountName): string
    {
        return \Framework\Security\TwoFactorAuth::getProvisioningUri($secret, $issuer, $accountName);
    }
}

if (!function_exists('verify_2fa')) {
    function verify_2fa($secret, string $code, int $drift = 1): bool
    {
        return \Framework\Security\TwoFactorAuth::verify($secret, $code, $drift);
    }
}

if (!function_exists('generate_api_key')) {
    function generate_api_key(string $name, ?int $userId = null, array $scopes = [], ?int $rateLimit = null): string|false
    {
        return \Framework\Security\ApiKey::generate($name, $userId, $scopes, $rateLimit);
    }
}

if (!function_exists('validate_api_key')) {
    function validate_api_key(?string $key = null): array|false
    {
        return \Framework\Security\ApiKey::validate($key);
    }
}

if (!function_exists('can')) {
    function can(string $ability, $subject = null): bool
    {
        return \Framework\Security\RBAC::can($ability, $subject);
    }
}

if (!function_exists('cannot')) {
    function cannot(string $ability, $subject = null): bool
    {
        return \Framework\Security\RBAC::denies($ability, $subject);
    }
}

if (!function_exists('honeypot_field')) {
    function honeypot_field(?string $fieldName = null): string
    {
        return \Framework\Middleware\HoneypotMiddleware::render($fieldName);
    }
}

if (!function_exists('api_response')) {
    function api_response(mixed $data = null, string $message = 'OK', int $statusCode = 200): never
    {
        \Framework\Api\ApiResponse::success($data, $message, $statusCode);
    }
}

if (!function_exists('api_error')) {
    function api_error(string $message, int $statusCode = 400, mixed $data = null, array $errors = []): never
    {
        \Framework\Api\ApiResponse::error($message, $statusCode, $data, $errors);
    }
}

if (!function_exists('api_problem')) {
    function api_problem(int $status): \Framework\Api\ApiProblem
    {
        return \Framework\Api\ApiProblem::make($status);
    }
}

if (!function_exists('jwt_token')) {
    function jwt_token(array $payload): string
    {
        return \Framework\Api\Jwt::createToken($payload);
    }
}

if (!function_exists('jwt_verify')) {
    function jwt_verify(string $token): array|false
    {
        return \Framework\Api\Jwt::verifyToken($token);
    }
}

if (!function_exists('jwt_pair')) {
    function jwt_pair(array $payload): array
    {
        return \Framework\Api\Jwt::createTokenPair($payload);
    }
}

if (!function_exists('jwt_refresh')) {
    function jwt_refresh(string $refreshToken): array|false
    {
        return \Framework\Api\Jwt::refreshToken($refreshToken);
    }
}

if (!function_exists('jwt_payload')) {
    function jwt_payload(): array|false
    {
        return \Framework\Api\Jwt::getFromRequest();
    }
}

if (!function_exists('api_version')) {
    function api_version(): string
    {
        return \Framework\Api\ApiVersion::getFromRequest();
    }
}

if (!function_exists('transform')) {
    function transform(mixed $resource, array $config = []): array
    {
        $transformer = \Framework\Api\ResourceTransformer::make();

        if (isset($config['visible'])) {
            $transformer->visible($config['visible']);
        }
        if (isset($config['hidden'])) {
            $transformer->hidden($config['hidden']);
        }
        if (isset($config['includes'])) {
            $transformer->includes($config['includes']);
        }
        if (isset($config['meta'])) {
            $transformer->meta($config['meta']);
        }

        return $transformer->transform($resource);
    }
}

if (!function_exists('trigger_webhook')) {
    function trigger_webhook(string $event, array $payload = []): array
    {
        return \Framework\Api\Webhook::trigger($event, $payload);
    }
}

if (!function_exists('sse_event')) {
    function sse_event(string $event, mixed $data, ?string $id = null): string
    {
        return \Framework\Api\Sse::sendEvent($event, $data, $id);
    }
}

if (!function_exists('sse_broadcast')) {
    function sse_broadcast(string $channel, mixed $data, ?string $eventId = null): void
    {
        \Framework\Api\Sse::broadcast($channel, $data, $eventId);
    }
}

if (!function_exists('storage')) {
    function storage(?string $disk = null): \Framework\Storage\Disk
    {
        return \Framework\Storage\Disk::make($disk);
    }
}

if (!function_exists('storage_disk')) {
    function storage_disk(string $disk): \Framework\Storage\Disk
    {
        return \Framework\Storage\Disk::make($disk);
    }
}

if (!function_exists('storage_url')) {
    function storage_url(string $path, ?string $disk = null): string
    {
        return \Framework\Storage\Disk::make($disk)->url($path);
    }
}

if (!function_exists('storage_temp_url')) {
    function storage_temp_url(string $path, int $seconds = 3600, ?string $disk = null): string
    {
        return \Framework\Storage\Disk::make($disk)->temporaryUrl($path, $seconds);
    }
}

if (!function_exists('validate_file')) {
    function validate_file(array $file, array $rules = []): bool
    {
        return \Framework\Storage\FileValidator::validateUpload($file, $rules);
    }
}

if (!function_exists('process_image')) {
    function process_image(string $path): \Framework\Storage\ImageProcessor
    {
        return \Framework\Storage\ImageProcessor::make($path);
    }
}

if (!function_exists('cdn_url')) {
    function cdn_url(string $url, ?string $disk = null): string
    {
        return \Framework\Storage\CdnRewriter::rewrite($url, $disk);
    }
}

if (!function_exists('cdn_content')) {
    function cdn_content(string $content, ?string $disk = null): string
    {
        return \Framework\Storage\CdnRewriter::rewriteContent($content, $disk);
    }
}

if (!function_exists('queue_upload')) {
    function queue_upload(string $disk, string $path, string $localPath, array $options = []): string
    {
        return (new \Framework\Storage\UploadQueue())->enqueue($disk, $path, $localPath, $options);
    }
}

if (!function_exists('storage_upload')) {
    function storage_upload($file, string $modelClass, $modelId = null, string $collection = 'default', string $disk = 'local', array $options = []): ?array
    {
        if ($modelId === null) {
            $instance = new $modelClass();
            if (!$instance->save()) {
                return null;
            }
            $modelId = $instance->data[$instance::$primaryKey] ?? null;
            if (!$modelId) {
                return null;
            }
        }

        $model = $modelClass::find($modelId);
        if (!$model || !in_array('Framework\Model\HasFiles', class_uses_recursive($modelClass))) {
            return null;
        }

        return $model->attachFile($file, $collection, $disk, $options);
    }
}

if (!function_exists('class_basename')) {
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('class_uses_recursive')) {
    function class_uses_recursive($class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];
        foreach ([$class => $class] + class_parents($class) as $type) {
            $results += class_uses($type);
        }

        foreach ($results as $trait) {
            $results += class_uses_recursive($trait);
        }

        return array_unique($results);
    }
}

// =========================================================
// SOCIAL MEDIA HELPERS
// =========================================================

if (!function_exists('follow')) {
    function follow($user, $targetUser): bool
    {
        if (method_exists($user, 'follow')) {
            return $user->follow($targetUser);
        }
        return false;
    }
}

if (!function_exists('unfollow')) {
    function unfollow($user, $targetUser): bool
    {
        if (method_exists($user, 'unfollow')) {
            return $user->unfollow($targetUser);
        }
        return false;
    }
}

if (!function_exists('is_following')) {
    function is_following($user, $targetUser): bool
    {
        if (method_exists($user, 'isFollowing')) {
            return $user->isFollowing($targetUser);
        }
        return false;
    }
}

if (!function_exists('create_post')) {
    function create_post(int $userId, string $type, string $content, array $options = []): ?\Framework\Social\Post
    {
        return \Framework\Social\Post::createPost($userId, $type, $content, $options);
    }
}

if (!function_exists('parse_hashtags')) {
    function parse_hashtags(string $content): array
    {
        return \Framework\Social\HashtagParser::parse($content);
    }
}

if (!function_exists('format_hashtags')) {
    function format_hashtags(string $content, string $tagUrl = '/tag/{tag}', string $mentionUrl = '/user/{mention}'): string
    {
        return \Framework\Social\HashtagParser::formatContent($content, $tagUrl, $mentionUrl);
    }
}

if (!function_exists('get_feed')) {
    function get_feed(int $userId, string $strategy = 'chronological', int $limit = 20, ?string $cursor = null): array
    {
        $builder = \Framework\Social\FeedBuilder::forUser($userId)->limit($limit);

        if ($strategy === 'algorithmic') {
            $builder->algorithmic();
        } else {
            $builder->chronological();
        }

        if ($cursor) {
            $builder->cursor($cursor);
        }

        return $builder->get();
    }
}

if (!function_exists('send_notification')) {
    function send_notification(int $userId, string $type, array $data = [], string $channel = 'database'): ?\Framework\Social\Notification
    {
        return \Framework\Social\Notification::send($userId, $type, $data, $channel);
    }
}

if (!function_exists('unread_notifications')) {
    function unread_notifications(int $userId, int $limit = 50): array
    {
        return \Framework\Social\Notification::unread($userId, $limit);
    }
}

if (!function_exists('notification_count')) {
    function notification_count(int $userId): int
    {
        return \Framework\Social\Notification::unreadCount($userId);
    }
}

if (!function_exists('start_conversation')) {
    function start_conversation(int $userId1, int $userId2): \Framework\Social\Conversation
    {
        return \Framework\Social\Conversation::createBetween($userId1, $userId2);
    }
}

if (!function_exists('send_message')) {
    function send_message(int $conversationId, int $senderId, string $content): ?\Framework\Social\Message
    {
        $conversation = \Framework\Social\Conversation::find($conversationId);
        if ($conversation) {
            return $conversation->sendMessage($senderId, $content);
        }
        return null;
    }
}

if (!function_exists('report_content')) {
    function report_content(int $reporterId, string $modelType, int $modelId, string $reason, ?string $details = null): ?\Framework\Social\ContentModeration
    {
        return \Framework\Social\ContentModeration::report($reporterId, $modelType, $modelId, $reason, $details);
    }
}

if (!function_exists('user_profile')) {
    function user_profile(int $userId): ?\Framework\Social\UserProfile
    {
        return \Framework\Social\UserProfile::forUser($userId);
    }
}

if (!function_exists('trending_tags')) {
    function trending_tags(int $limit = 10, int $hours = 24): array
    {
        return \Framework\Social\HashtagParser::getTrendingTags($limit, $hours);
    }
}

if (!function_exists('search_tags')) {
    function search_tags(string $query, int $limit = 10): array
    {
        return \Framework\Social\HashtagParser::searchTags($query, $limit);
    }
}

// =========================================================
// E-COMMERCE HELPERS
// =========================================================

if (!function_exists('cart')) {
    function cart(): \Framework\Commerce\Cart
    {
        return new \Framework\Commerce\Cart();
    }
}

if (!function_exists('cart_add')) {
    function cart_add(int $productId, int $quantity = 1, array $options = []): bool
    {
        return \Framework\Commerce\Cart::add($productId, $quantity, $options);
    }
}

if (!function_exists('cart_update')) {
    function cart_update(int $cartItemId, int $quantity): bool
    {
        return \Framework\Commerce\Cart::update($cartItemId, $quantity);
    }
}

if (!function_exists('cart_remove')) {
    function cart_remove(int $cartItemId): bool
    {
        return \Framework\Commerce\Cart::remove($cartItemId);
    }
}

if (!function_exists('cart_items')) {
    function cart_items(): array
    {
        return \Framework\Commerce\Cart::getItems();
    }
}

if (!function_exists('cart_total')) {
    function cart_total(): float
    {
        return \Framework\Commerce\Cart::total();
    }
}

if (!function_exists('cart_count')) {
    function cart_count(): int
    {
        return \Framework\Commerce\Cart::count();
    }
}

if (!function_exists('cart_clear')) {
    function cart_clear(): void
    {
        \Framework\Commerce\Cart::clear();
    }
}

if (!function_exists('create_product')) {
    function create_product(array $data): ?\Framework\Commerce\Product
    {
        return \Framework\Commerce\Product::createProduct($data);
    }
}

if (!function_exists('create_order')) {
    function create_order(int $userId, array $cartItems, array $shippingAddress, string $paymentMethod, array $options = []): ?\Framework\Commerce\Order
    {
        return \Framework\Commerce\Order::createFromCart($userId, $cartItems, $shippingAddress, $paymentMethod, $options);
    }
}

if (!function_exists('validate_coupon')) {
    function validate_coupon(string $code, ?int $userId = null, float $cartTotal = 0, array $cartItems = []): array
    {
        return \Framework\Commerce\Coupon::validate($code, $userId, $cartTotal, $cartItems);
    }
}

if (!function_exists('create_coupon')) {
    function create_coupon(string $code, string $type, float $value, array $options = []): ?\Framework\Commerce\Coupon
    {
        return \Framework\Commerce\Coupon::createCode($code, $type, $value, $options);
    }
}

if (!function_exists('calculate_tax')) {
    function calculate_tax(float $subtotal, string $country, string $region = '', string $city = ''): array
    {
        return \Framework\Commerce\Tax::calculate($subtotal, $country, $region, $city);
    }
}

if (!function_exists('calculate_shipping')) {
    function calculate_shipping(float $weight, string $country, string $method, float $cartTotal = 0): float
    {
        return \Framework\Commerce\Shipping::calculate($weight, $country, $method, $cartTotal);
    }
}

if (!function_exists('shipping_rates')) {
    function shipping_rates(float $weight, string $country, float $cartTotal = 0): array
    {
        return \Framework\Commerce\Shipping::getRates($weight, $country, $cartTotal);
    }
}

if (!function_exists('payment_charge')) {
    function payment_charge(float $amount, string $currency, array $options = [], ?string $gateway = null): array
    {
        return \Framework\Commerce\Payment::charge($amount, $currency, $options, $gateway);
    }
}

if (!function_exists('payment_refund')) {
    function payment_refund(string $transactionId, float $amount, string $currency, ?string $gateway = null): array
    {
        return \Framework\Commerce\Payment::refund($transactionId, $amount, $currency, $gateway);
    }
}

if (!function_exists('create_subscription')) {
    function create_subscription(int $userId, int $planId, string $gateway, array $options = []): ?\Framework\Commerce\Subscription
    {
        return \Framework\Commerce\Subscription::createSubscription($userId, $planId, $gateway, $options);
    }
}

if (!function_exists('user_subscription')) {
    function user_subscription(int $userId): ?\Framework\Commerce\Subscription
    {
        return \Framework\Commerce\Subscription::activeForUser($userId);
    }
}

if (!function_exists('generate_invoice')) {
    function generate_invoice(int $orderId, bool $returnHtml = false): string
    {
        return \Framework\Commerce\Invoice::generate($orderId, $returnHtml);
    }
}

if (!function_exists('stock_level')) {
    function stock_level(int $productId, ?int $variantId = null): int
    {
        return \Framework\Commerce\Inventory::getStock($productId, $variantId);
    }
}

if (!function_exists('low_stock_products')) {
    function low_stock_products(?int $threshold = null): array
    {
        return \Framework\Commerce\Inventory::getLowStockProducts($threshold);
    }
}

// =========================================================
// BLOG HELPERS
// =========================================================

if (!function_exists('create_article')) {
    function create_article(array $data): ?\Framework\Blog\Article
    {
        return \Framework\Blog\Article::createArticle($data);
    }
}

if (!function_exists('publish_article')) {
    function publish_article(int $articleId): bool
    {
        $article = \Framework\Blog\Article::find($articleId);
        return $article ? $article->publish() : false;
    }
}

if (!function_exists('get_article')) {
    function get_article($slugOrId): ?\Framework\Blog\Article
    {
        if (is_numeric($slugOrId)) {
            return \Framework\Blog\Article::find($slugOrId);
        }
        return \Framework\Blog\Article::where('slug', $slugOrId)->where('status', 'published')->first();
    }
}

if (!function_exists('recent_articles')) {
    function recent_articles(int $limit = 10): array
    {
        return \Framework\Blog\Article::recent($limit);
    }
}

if (!function_exists('popular_articles')) {
    function popular_articles(int $limit = 10, int $days = 30): array
    {
        return \Framework\Blog\Article::popular($limit, $days);
    }
}

if (!function_exists('featured_articles')) {
    function featured_articles(int $limit = 5): array
    {
        return \Framework\Blog\Article::featured($limit);
    }
}

if (!function_exists('create_category')) {
    function create_category(array $data): ?\Framework\Blog\Category
    {
        return \Framework\Blog\Category::createCategory($data);
    }
}

if (!function_exists('get_categories')) {
    function get_categories(): array
    {
        return \Framework\Blog\Category::allCategories();
    }
}

if (!function_exists('category_tree')) {
    function category_tree(): array
    {
        return \Framework\Blog\Category::getTree();
    }
}

if (!function_exists('create_tag')) {
    function create_tag(string $name): \Framework\Blog\Tag
    {
        return \Framework\Blog\Tag::createTag(['name' => $name]);
    }
}

if (!function_exists('tag_cloud')) {
    function tag_cloud(int $limit = 50): array
    {
        return \Framework\Blog\Tag::cloud($limit);
    }
}

if (!function_exists('popular_tags')) {
    function popular_tags(int $limit = 10): array
    {
        return \Framework\Blog\Tag::popular($limit);
    }
}

if (!function_exists('add_comment')) {
    function add_comment(array $data): ?\Framework\Blog\Comment
    {
        return \Framework\Blog\Comment::createComment($data);
    }
}

if (!function_exists('get_comments')) {
    function get_comments(int $articleId): array
    {
        return \Framework\Blog\Comment::treeForArticle($articleId);
    }
}

if (!function_exists('moderation_queue')) {
    function moderation_queue(int $limit = 20): array
    {
        return \Framework\Blog\Comment::moderationQueue($limit);
    }
}

if (!function_exists('parse_markdown')) {
    function parse_markdown(string $markdown): string
    {
        return \Framework\Blog\Markdown::parse($markdown);
    }
}

if (!function_exists('generate_seo')) {
    function generate_seo(array $config = []): string
    {
        $seo = \Framework\Blog\SEO::make();
        if (isset($config['title'])) $seo->setTitle($config['title']);
        if (isset($config['description'])) $seo->setDescription($config['description']);
        if (isset($config['url'])) $seo->setUrl($config['url']);
        if (isset($config['image'])) $seo->setImage($config['image']);
        if (isset($config['keywords'])) $seo->setKeywords($config['keywords']);
        return $seo->generateMeta() . "\n" . $seo->generateJsonLd();
    }
}

if (!function_exists('generate_sitemap')) {
    function generate_sitemap(array $articles, string $baseUrl = ''): string
    {
        return \Framework\Blog\Sitemap::generateFromArticles($articles, $baseUrl);
    }
}

if (!function_exists('rss_feed')) {
    function rss_feed(string $title, string $link, array $items, array $options = []): string
    {
        return \Framework\Blog\RSS::feed($title, $link, $items, $options);
    }
}

if (!function_exists('blog_search')) {
    function blog_search(string $keyword, array $options = []): array
    {
        return \Framework\Blog\Search::query($keyword, $options);
    }
}

if (!function_exists('search_suggestions')) {
    function search_suggestions(string $prefix, int $limit = 5): array
    {
        return \Framework\Blog\Search::suggestions($prefix, $limit);
    }
}

if (!function_exists('track_view')) {
    function track_view(int $articleId, array $data = []): bool
    {
        return \Framework\Blog\Analytics::trackView($articleId, $data);
    }
}

if (!function_exists('trending_posts')) {
    function trending_posts(int $hours = 24, int $limit = 5): array
    {
        return \Framework\Blog\Analytics::trendingPosts($hours, $limit);
    }
}

if (!function_exists('blog_overview')) {
    function blog_overview(int $days = 30): array
    {
        return \Framework\Blog\Analytics::overview($days);
    }
}

if (!function_exists('author_stats')) {
    function author_stats(int $authorId, int $days = 30): array
    {
        return \Framework\Blog\Analytics::authorStats($authorId, $days);
    }
}

// =========================================================
// INFRASTRUCTURE & DEVEX HELPERS (Phase 7)
// =========================================================

if (!function_exists('cache')) {
    function cache(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return \Framework\Cache\Cache::driver();
        }
        return \Framework\Cache\Cache::get($key, $default);
    }
}

if (!function_exists('cache_put')) {
    function cache_put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return \Framework\Cache\Cache::put($key, $value, $ttl);
    }
}

if (!function_exists('cache_forget')) {
    function cache_forget(string $key): bool
    {
        return \Framework\Cache\Cache::forget($key);
    }
}

if (!function_exists('cache_remember')) {
    function cache_remember(string $key, int $ttl, callable $callback): mixed
    {
        return \Framework\Cache\Cache::remember($key, $ttl, $callback);
    }
}

if (!function_exists('cache_tags')) {
    function cache_tags(array $tags): \Framework\Cache\TaggedCache
    {
        return \Framework\Cache\Cache::tags($tags);
    }
}

if (!function_exists('event')) {
    function event(string|object $event, mixed $payload = null): array
    {
        return \Framework\Events\Event::dispatch($event, $payload);
    }
}

if (!function_exists('listen')) {
    function listen(string $event, callable|string $listener, int $priority = 0): void
    {
        \Framework\Events\Event::listen($event, $listener, $priority);
    }
}

if (!function_exists('queue_event')) {
    function queue_event(string|object $event, mixed $payload = null): void
    {
        \Framework\Events\Event::queue($event, $payload);
    }
}

if (!function_exists('set_locale')) {
    function set_locale(string $locale): void
    {
        \Framework\Lang\Lang::setLocale($locale);
    }
}

if (!function_exists('paginate')) {
    function paginate(array $items, int $total, int $perPage = 15, ?int $page = null): \Framework\Pagination\Paginator
    {
        return \Framework\Pagination\Paginator::make($items, $total, $perPage, $page);
    }
}

if (!function_exists('cursor_paginate')) {
    function cursor_paginate(array $items, int $perPage = 15, ?string $cursor = null): \Framework\Pagination\CursorPaginator
    {
        return \Framework\Pagination\CursorPaginator::make($items, $perPage, $cursor);
    }
}

if (!function_exists('debug_bar')) {
    function debug_bar(bool $enable = true): void
    {
        if ($enable) {
            \Framework\Debug\DebugBar::enable();
        } else {
            \Framework\Debug\DebugBar::disable();
        }
    }
}

if (!function_exists('debug_log')) {
    function debug_log(mixed $data, string $label = 'log'): void
    {
        \Framework\Debug\DebugBar::log($data, $label);
    }
}

if (!function_exists('debug_time')) {
    function debug_time(string $name): void
    {
        \Framework\Debug\DebugBar::time($name);
    }
}

if (!function_exists('debug_stop_time')) {
    function debug_stop_time(string $name): void
    {
        \Framework\Debug\DebugBar::stopTime($name);
    }
}

if (!function_exists('health_check')) {
    function health_check(): array
    {
        return \Framework\Health\Health::check();
    }
}

if (!function_exists('health_output')) {
    function health_output(): void
    {
        \Framework\Health\Health::output();
    }
}

if (!function_exists('structured_log')) {
    function structured_log(string $level, string $message, array $context = []): void
    {
        \Framework\Health\Logger::log($level, $message, $context);
    }
}

if (!function_exists('log_info')) {
    function log_info(string $message, array $context = []): void
    {
        \Framework\Health\Logger::info($message, $context);
    }
}

if (!function_exists('log_error')) {
    function log_error(string $message, array $context = []): void
    {
        \Framework\Health\Logger::error($message, $context);
    }
}

if (!function_exists('log_exception')) {
    function log_exception(\Throwable $e, array $context = []): void
    {
        \Framework\Health\Logger::exception($e, $context);
    }
}

if (!function_exists('measure')) {
    function measure(string $label, callable $callback, array $context = []): mixed
    {
        return \Framework\Health\Logger::measure($label, $callback, $context);
    }
}

if (!function_exists('faker')) {
    function faker(): \Framework\Seeder\Faker
    {
        return new \Framework\Seeder\Faker();
    }
}

if (!function_exists('run_seeders')) {
    function run_seeders(): void
    {
        \Framework\Seeder\SeederManager::runAll();
    }
}

if (!function_exists('run_seeder')) {
    function run_seeder(string $seeder): void
    {
        \Framework\Seeder\SeederManager::runOne($seeder);
    }
}


