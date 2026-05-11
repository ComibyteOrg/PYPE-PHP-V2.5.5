<?php

namespace Framework\Router;

use Framework\Helper\TwigManager;

class Route
{

    private static $routes = [];

    private static $viewPath = '';

    private static $currentRouteIndex = null;

    private static $middlewareAliases = [];

    private static $groupStack = [];

    // --- Configuration ---

    public static function setViewPath(string $path): void
    {

        self::$viewPath = rtrim($path, '/');

    }

    public static function registerMiddleware(string $alias, callable|string $handler): void
    {

        self::$middlewareAliases[$alias] = $handler;

    }

    public static function getTwig()
    {
        return TwigManager::getInstance();
    }

    // --- Route Definition ---

    private static function addRoute(string $method, string $path, $handler): self
    {
        $prefix = '';
        $middleware = [];

        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middleware = array_merge($middleware, $groupMiddleware);
            }
        }

        $path = $prefix . '/' . ltrim($path, '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        self::$routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => null,
            'csrf_exempt' => false
        ];

        self::$currentRouteIndex = count(self::$routes) - 1;

        return new self();
    }

    public static function get(string $path, $handler, ?string $method = null): self
    {
        if ($method !== null && is_string($handler)) {
            $handler = [$handler, $method];
        }
        return self::addRoute('GET', $path, $handler);
    }

    public static function post(string $path, $handler, ?string $method = null): self
    {
        if ($method !== null && is_string($handler)) {
            $handler = [$handler, $method];
        }
        return self::addRoute('POST', $path, $handler);
    }

    public static function put(string $path, $handler, ?string $method = null): self
    {
        if ($method !== null && is_string($handler)) {
            $handler = [$handler, $method];
        }
        return self::addRoute('PUT', $path, $handler);
    }

    public static function delete(string $path, $handler, ?string $method = null): self
    {
        if ($method !== null && is_string($handler)) {
            $handler = [$handler, $method];
        }
        return self::addRoute('DELETE', $path, $handler);
    }

    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = $attributes;
        call_user_func($callback);
        array_pop(self::$groupStack);
    }

    public static function socialAuth(): void
    {
        self::get('/auth/{provider}', [\Framework\Auth\SocialAuthController::class, 'redirectToProvider']);
        self::get('/auth/{provider}/callback', [\Framework\Auth\SocialAuthController::class, 'handleProviderCallback']);
    }

    // --- Middleware Chaining ---

    public function middleware($middleware): self
    {

        if (self::$currentRouteIndex !== null && isset(self::$routes[self::$currentRouteIndex])) {

            if (is_array($middleware)) {

                self::$routes[self::$currentRouteIndex]['middleware'] = array_merge(

                    self::$routes[self::$currentRouteIndex]['middleware'],

                    $middleware

                );

            } else {

                self::$routes[self::$currentRouteIndex]['middleware'][] = $middleware;

            }

        }

        return $this;

    }

    public function name(string $name): self
    {
        if (self::$currentRouteIndex !== null && isset(self::$routes[self::$currentRouteIndex])) {
            self::$routes[self::$currentRouteIndex]['name'] = $name;
        }
        return $this;
    }

    /**
     * Mark the current route as CSRF-exempt (skip automatic CSRF enforcement).
     */
    public function csrfExempt(): self
    {
        if (self::$currentRouteIndex !== null && isset(self::$routes[self::$currentRouteIndex])) {
            self::$routes[self::$currentRouteIndex]['csrf_exempt'] = true;
        }
        return $this;
    }

    public static function getUrl(string $name, array $parameters = []): ?string
    {
        // error_log("Route::getUrl called with name: $name, parameters: " . json_encode($parameters));

        foreach (self::$routes as $index => $route) {
            if (isset($route['name']) && $route['name'] === $name) {
                // error_log("Found route at index $index: " . json_encode($route));
                $path = $route['path'];
                // error_log("Original path: $path");

                // Replace parameters in path
                foreach ($parameters as $key => $value) {
                    // error_log("Processing parameter: $key = $value");
                    if (strpos($path, '{' . $key . '}') !== false) {
                        $path = str_replace('{' . $key . '}', $value, $path);
                        unset($parameters[$key]);
                        // error_log("Path after replacement: $path");
                    } else {
                        // error_log("Parameter $key not found in path");
                    }
                }

                // Append remaining parameters as query string
                if (!empty($parameters)) {
                    $path .= '?' . http_build_query($parameters);
                    // error_log("Path with query string: $path");
                }

                // error_log("Returning path: $path");
                return $path;
            }
        }
        // error_log("Route not found for name: $name");
        return null;
    }

    // --- Dispatch ---

    public static function dispatch()
    {

        $requestMethod = $_SERVER['REQUEST_METHOD'];

        // Handle method spoofing for PUT and DELETE requests
        if ($requestMethod === 'POST' && isset($_POST['_method'])) {
            $spoofedMethod = strtoupper($_POST['_method']);
            if (in_array($spoofedMethod, ['PUT', 'DELETE'])) {
                $requestMethod = $spoofedMethod;
            }
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        $requestUri = $requestUri === '/' ? '/' : rtrim($requestUri, '/');

        $matchedRoute = null;

        $params = [];

        foreach (self::$routes as $route) {

            if ($route['method'] !== $requestMethod && $requestMethod !== 'HEAD')
                continue;

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);

            $pattern = "#^" . $pattern . "$#";

            if (preg_match($pattern, $requestUri, $matches)) {

                foreach ($matches as $key => $value) {

                    if (is_string($key))
                        $params[$key] = $value;

                }

                $matchedRoute = $route;

                break;

            }

        }

        if (!$matchedRoute) {

            self::errorResponse(404, "Page Not Found");

            return;

        }

        $handler = $matchedRoute['handler'];

        // Convert string handler (Controller@method) to array format
        if (is_string($handler) && strpos($handler, '@') !== false) {
            $handler = explode('@', $handler, 2);
        }

        // Automatic CSRF enforcement for POST requests unless the route is explicitly exempt.
        if (strtoupper($matchedRoute['method']) === 'POST' && empty($matchedRoute['csrf_exempt'])) {
            if (function_exists('csrf_enforce')) {
                csrf_enforce();
            }
        }

        $middlewareStack = [];

        foreach ($matchedRoute['middleware'] as $middleware) {
            if (is_string($middleware) && isset(self::$middlewareAliases[$middleware])) {
                $middlewareStack[] = self::$middlewareAliases[$middleware];
            } else {
                $middlewareStack[] = $middleware;
            }
        }

        $finalHandler = function ($params) use ($handler) {

            if (is_callable($handler)) {

                return call_user_func_array($handler, $params);

            } elseif (is_array($handler) && count($handler) === 2) {

                [$controller, $method] = $handler;
                // error_log("Class exists: " . (class_exists($controller) ? 'Yes' : 'No'));
                // error_log("Autoload functions: " . print_r(spl_autoload_functions(), true));

                if (class_exists($controller)) {

                    $instance = new $controller();

                    if (method_exists($instance, $method)) {

                        return call_user_func_array([$instance, $method], $params);

                    } else {

                        self::errorResponse(500, "Method {$method} not found in {$controller}");

                    }

                } else {

                    self::errorResponse(500, "Controller {$controller} not found. Check if the class name and file path match PSR-4 standards.");

                }

            } else {

                self::errorResponse(500, "Invalid route handler");

            }

        };

        $next = $finalHandler;

        foreach (array_reverse($middlewareStack) as $middlewareHandler) {

            $next = function ($params) use ($middlewareHandler, $next) {

                if (is_string($middlewareHandler) && class_exists($middlewareHandler)) {

                    $middleware = new $middlewareHandler();

                    if (method_exists($middleware, 'handle')) {

                        return $middleware->handle($params, $next);

                    }

                } elseif (is_callable($middlewareHandler)) {

                    return $middlewareHandler($params, $next);

                }

                return $next($params);

            };

        }

        echo $next($params);

    }

    // --- Error Responses ---

    private static function errorResponse(int $code, string $message): void
    {

        http_response_code($code);

        echo "<div style='font-family: Arial; display: flex; height: 100vh; align-items: center; justify-content: center;'>

                <div><h2>{$code}</h2><p>{$message}</p></div>

              </div>";

    }

    public static function getViewPath(): string
    {

        return self::$viewPath;

    }

}

// --- Global View Helper ---

if (!function_exists('view')) {
    /**
     * Render a view file.
     *
     * @param string $viewName dot-notated view name (e.g. 'admin/login' or '_posts-list')
     * @param array $data variables to extract into the view
     * @param bool $return when true the rendered HTML is returned instead of echoed
     * @return void|string
     */
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
            $viewPath = Route::getViewPath();
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
            $viewPath = Route::getViewPath();

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







