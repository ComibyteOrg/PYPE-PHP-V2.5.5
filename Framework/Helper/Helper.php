<?php
namespace Framework\Helper;
use Framework\Helper\CSRF;
use Framework\Helper\DB;
use Framework\Helper\Auth;

// We may have model classes; keep Admin import if used elsewhere
use Framework\Model\Admin;

class Helper
{
    // =========================================================
    // AUTHENTICATION HELPERS (Universal - Works with any table)
    // =========================================================

    /**
     * Get Auth instance for a specific table
     * Usage: Auth::table('users')->login($email, $password)
     *        Auth::table('admins')->login($email, $password)
     */
    public static function auth($table = 'users')
    {
        return Auth::table($table);
    }

    /**
     * Login user (default: users table)
     * @param string $email
     * @param string $password
     * @param string $table Table name (default: users)
     * @param bool $remember
     * @return object|null
     */
    public static function login($email, $password, $table = 'users', $remember = false)
    {
        return Auth::table($table)->login($email, $password, $remember);
    }

    /**
     * Register new user
     * @param array $data
     * @param string $table
     * @param bool $autoLogin
     * @return object|null
     */
    public static function register($data, $table = 'users', $autoLogin = true)
    {
        return Auth::table($table)->register($data, $autoLogin);
    }

    /**
     * Check if user is authenticated
     * @param string $table
     * @return bool
     */
    public static function check($table = 'users')
    {
        return Auth::table($table)->check();
    }

    /**
     * Get authenticated user
     * @param string $table
     * @return object|null
     */
    public static function user($table = 'users')
    {
        return Auth::table($table)->user();
    }

    /**
     * Logout user
     * @param string $table
     * @return void
     */
    public static function logout($table = 'users')
    {
        Auth::table($table)->logout();
    }

    /**
     * Get authenticated user ID
     * @param string $table
     * @return int|null
     */
    public static function userId($table = 'users')
    {
        return Auth::table($table)->id();
    }

    // =========================================================
    // BASIC HELPER FUNCTIONS
    // =========================================================

    // Sanitize Inputs 
    public static function sanitize($input)
    {
        $input = trim($input ?? '');
        $input = htmlspecialchars($input);
        $input = stripcslashes($input);
        $input = htmlentities(strip_tags($input));

        return $input;
    }

    // Redirect Users
    public static function redirect($page, $seconds = 0)
    {
        // If immediate redirect requested, prefer HTTP Location header
        if ((int) $seconds === 0) {
            if (!headers_sent()) {
                header('Location: ' . $page);
                exit;
            }

            // Fallback to meta refresh if headers already sent
            echo "<!DOCTYPE html>\n<html>\n<head>\n<meta http-equiv='refresh' content='0;url=$page'>\n<title>Redirecting...</title>\n</head>\n<body>Redirecting...</body>\n</html>";
            exit;
        }

        // For delayed redirects use meta refresh
        echo "<!DOCTYPE html>\n<html>\n<head>\n<meta http-equiv='refresh' content='$seconds;url=$page'>\n<title>Redirecting...</title>\n</head>\n<body>Redirecting...</body>\n</html>";
        exit;
    }

    // Redirect with flash message
    public static function redirectWith($page, $key, $message, $seconds = 0)
    {
        self::flash($key, $message);
        self::redirect($page, $seconds);
    }


    // show alerts 
    public static function set_alert($type, $message)
    {
        return $alerts =
            "
                <div class='alert alert-$type alert-dismissible fade show text-center' role='alert'>
                    $message
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>
            ";
    }


    // write into a txt file 
    public static function writetxt($file_name, $values = array())
    {
        $filepath = $file_name;
        $file = fopen($filepath, 'a');

        $value = implode(',', $values);

        if ($file) {
            $data = [$value];

            fputcsv($file, $data) . "\n";
            fclose($file);
        }

        return $file;

    }


    // Delete from txt file 
    public static function deletetxt($file_name, $cond)
    {
        $filepath = $file_name;
        $condition = $cond;

        $lines = file($filepath, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $key => $line) {
            if (stripos($line, $condition) !== false) {
                unset($lines[$key]);
            }
        }

        $deleted = file_put_contents($filepath, implode("\n", $lines));

        return $deleted;
    }


    public static function view($file, $data = [], $return = false)
    {
        // Accept dot notation like 'admin.login' and convert to path
        $original = $file;
        $file = str_replace('.', '/', $file);

        // If the caller provided a full path or included .php, normalize it
        if (substr($file, -4) !== '.php') {
            $file = rtrim($file, '/') . '.php';
        }

        // Prefer Router view path if available to keep single source of truth
        $viewsDirCandidates = [
            // Router::getViewPath() may or may not exist; check gracefully
            function_exists('Framework\\Router\\Route::getViewPath') ? @\Framework\Router\Route::getViewPath() : null,
            realpath(__DIR__ . '/../../Resources/views/')
        ];

        // Resolve the first valid views directory
        $viewsDir = null;
        foreach ($viewsDirCandidates as $candidate) {
            if (is_string($candidate) && !empty($candidate) && realpath($candidate)) {
                $viewsDir = realpath($candidate);
                break;
            }
        }

        // Fallback to packaged views dir if nothing found
        if (!$viewsDir) {
            $viewsDir = realpath(__DIR__ . '/../../Resources/views/');
        }

        // Build absolute path
        $filepath = realpath($viewsDir . '/' . $file);

        // Debug information
        error_log("Attempting to load view: " . $original . " => " . $file);
        error_log("Full filepath: " . $filepath);
        error_log("Views directory: " . $viewsDir);

        // Security: ensure the resolved path is inside the views directory
        if ($filepath && strpos($filepath, $viewsDir) === 0 && file_exists($filepath)) {
            // Extract data array to variables
            if (!empty($data) && is_array($data)) {
                extract($data, EXTR_SKIP);
            }

            // Optionally return output as string
            if ($return) {
                ob_start();
                include $filepath;
                return ob_get_clean();
            } else {
                include $filepath;
            }
        } else {
            // Log error with more details
            error_log("View file not found: " . $original);
            error_log("Resolved filepath: " . $filepath);
            error_log("Views directory: " . $viewsDir);
            echo "View File Not found: " . $original;
        }
    }


    public static function returnJson(array $data, int $statusCode = 200): void
    {
        // End any previous output buffers (to prevent extra HTML)
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set response headers
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);

        // Return JSON
        echo json_encode($data);

        // Stop further script execution
        exit;
    }

    /**
     * Creates a short, plain-text excerpt from a string of HTML.
     *
     * @param string $html The input HTML string.
     * @param int $length The desired maximum length of the excerpt.
     * @param string $suffix The string to append if the text is truncated.
     * @return string The generated excerpt.
     */
    public static function excerpt(string $html, int $length = 150, string $suffix = '...'): string
    {
        // 1. Remove HTML tags to get plain text.
        $text = strip_tags($html);

        // 2. If the text is already short enough, no need to truncate.
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        // 3. Find the last space within the desired length to avoid cutting words.
        $lastSpace = mb_strrpos(mb_substr($text, 0, $length), ' ');
        $truncatedText = $lastSpace ? mb_substr($text, 0, $lastSpace) : mb_substr($text, 0, $length);

        return $truncatedText . $suffix;
    }

    /**
     * Estimates the reading time for a piece of content.
     *
     * @param string $content The content to be measured.
     * @param int $wpm Words per minute reading speed.
     * @return int The estimated reading time in minutes.
     */
    public static function readingTime(string $content, int $wpm = 200): int
    {
        $wordCount = str_word_count(strip_tags($content));
        $minutes = floor($wordCount / $wpm);
        return ($minutes < 1) ? 1 : $minutes;
    }

    /**
     * Dump the given variable and end the script.
     *
     * @param mixed $value The variable to dump.
     * @return void
     */
    public static function dd(...$args): void
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

    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public static function env(string $key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }

    /**
     * Generate a URL for an asset.
     *
     * @param string $path
     * @return string
     */
    public static function asset(string $path): string
    {
        $baseUrl = self::env('APP_URL', '');
        return rtrim($baseUrl, '/assets') . '/assets/' . ltrim($path, '/');
    }

    /**
     * Generate a full URL for the given path.
     *
     * @param string $path
     * @param array $parameters
     * @return string
     */
    public static function url(string $path, array $parameters = []): string
    {
        // Try to resolve as a named route first
        if (class_exists('Framework\\Router\\Route') && method_exists('Framework\\Router\\Route', 'getUrl')) {
            $routeUrl = \Framework\Router\Route::getUrl($path, $parameters);
            if ($routeUrl !== null) {
                $path = $routeUrl;
                // Parameters are already handled by getUrl for named routes
                $parameters = [];
            }
        }

        $baseUrl = self::env('APP_URL', '');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        // If there are parameters left (and it wasn't a named route, or they weren't path params), append them
        if (!empty($parameters)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($parameters);
        }

        return $url;


        /**
         * // Define a named route
         * Route::get('/user/{id}/profile', 'UserController@profile')->name('user.profile');
         * // Generate URL
         * $url = Helper::url('user.profile', ['id' => 123]);
         * // Output: http://your-app.com/user/123/profile
         */
    }

    /**
     * Generate a URL friendly "slug" from a given string.
     *
     * @param string $string
     * @param string $separator
     * @return string
     */
    public static function slugify(string $string, string $separator = '-'): string
    {
        // Convert all dashes/underscores into separator
        $flip = $separator === '-' ? '_' : '-';
        $string = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $string);

        // Replace @ with the word 'at'
        $string = str_replace('@', $separator . 'at' . $separator, $string);

        // Remove all characters that are not the separator, letters, numbers, or whitespace.
        $string = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', mb_strtolower($string));

        // Replace all separator characters and whitespace by a single separator
        $string = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $string);

        return trim($string, $separator);
    }

    /**
     * Get/Set a session value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function session(string $key, $default = null)
    {
        if (is_null($default)) {
            // Get session value
            return $_SESSION[$key] ?? null;
        }

        // Set session value
        $_SESSION[$key] = $default;
        return $default;
    }

    /**
     * Retrieve old input data from the session.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function old(string $key)
    {
        return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : '';
    }

    /**
     * Get the absolute path to the database file.
     */
    public static function db_path(): string
    {
        $dbType = self::env('DB_TYPE', 'sqlite');
        if ($dbType === 'sqlite') {
            return self::base_path(self::env('DB_PATH', 'database.sqlite'));
        }
        return 'Connection: ' . self::env('DB_HOST', 'localhost');
    }

    /**
     * Upload a file.
     */
    public static function upload($file, $directory, $allowedExtensions = [])
    {
        return FileUploader::upload($file, $directory, $allowedExtensions);
    }


    /**
     * Get an item from an array using "dot" notation.
     *
     * @param  array   $array
     * @param  string|int|null  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function array_get(array $array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Get the fully qualified path to the project root.
     *
     * @param string $path
     * @return string
     */
    public static function base_path(string $path = ''): string
    {
        // Assumes this Helper.php file is in /App/Helper/
        $basePath = realpath(__DIR__ . '/../../');
        return $basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    /**
     * Get the fully qualified path to the 'App' directory.
     *
     * @param string $path
     * @return string
     */
    public static function app_path(string $path = ''): string
    {
        return self::base_path('App') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    /**
     * Get the fully qualified path to the 'storage' directory.
     *
     * @param string $path
     * @return string
     */
    public static function storage_path(string $path = ''): string
    {
        // You might need to create a 'Storage' directory in your project root
        return self::base_path('Storage') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Get an item from the request (POST or GET).
     * $token = input('_token');
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function input($key = null, $default = null)
    {
        if ($key === null) {
            return array_merge($_GET, $_POST);
        }

        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Set a flash message
     *
     * @param string $key
     * @param string $message
     * @return void
     */
    public static function flash($key, $message)
    {
        $_SESSION['flash_' . $key] = $message;
    }

    /**
     * Get a flash message
     *
     * @param string $key
     * @return string|null
     */
    public static function getFlash($key)
    {
        $message = $_SESSION['flash_' . $key] ?? null;
        unset($_SESSION['flash_' . $key]);
        return $message;
    }



    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }


    public static function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }




    /**
     * Logout admin user
     */
    public static function adminLogout()
    {
        unset($_SESSION['admin_id']);
        if (isset($_COOKIE['admin_remember_me'])) {
            unset($_COOKIE['admin_remember_me']);
            setcookie('admin_remember_me', '', time() - 3600, '/');
        }
    }

    /**
     * Check if admin is authenticated
     * @return bool
     */
    public static function adminCheck()
    {
        return (bool) static::adminAuth();
    }

    /**
     * Get authenticated admin user
     * @return object|null
     */
    public static function adminAuth()
    {
        // Return cached user if already fetched during this request
        if (!empty($_SESSION['admin_auth_user'])) {
            return (object) $_SESSION['admin_auth_user'];
        }

        $adminId = $_SESSION['admin_id'] ?? null;
        $remember = $_COOKIE['admin_remember_me'] ?? null;

        // If we have a session admin id, fetch from admins table
        if ($adminId) {
            $admin = DB::table('admins')->find($adminId);
            if ($admin && is_array($admin)) {
                $_SESSION['admin_auth_user'] = $admin;
                return (object) $admin;
            }
        }

        // If remember cookie exists, try using it
        if ($remember) {
            $admin = DB::table('admins')->where('email', $remember)->where('is_active', 1)->first();
            if ($admin && is_array($admin)) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_auth_user'] = $admin;
                return (object) $admin;
            }
        }

        return null;
    }

    /**
     * Authenticate admin credentials
     * @param string $email
     * @param string $password
     * @return object|null Returns admin object on success, null on failure
     */
    public static function adminAuthenticate($email, $password)
    {
        $admin = DB::table('admins')->where('email', $email)->where('is_active', 1)->first();

        if ($admin && is_array($admin) && password_verify($password, $admin['password'])) {
            return (object) $admin;
        }

        return null;
    }

    /**
     * Login admin user
     * @param object $admin
     * @param bool $remember
     * @return bool
     */
    public static function adminLogin($admin, $remember = false)
    {
        if (!$admin) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin->id;
        $_SESSION['admin_auth_user'] = (array) $admin;

        if ($remember) {
            setcookie('admin_remember_me', $admin->email, time() + (30 * 24 * 60 * 60), '/');
        }

        return true;
    }


}
