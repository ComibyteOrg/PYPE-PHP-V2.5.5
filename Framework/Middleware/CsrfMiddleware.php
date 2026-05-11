<?php

namespace Framework\Middleware;

use Framework\Helper\CSRF;

class CsrfMiddleware
{
    public static array $exemptRoutes = [];

    public static function csrf_exempt(string $route): void
    {
        self::$exemptRoutes[] = $route;
    }

    public function handle($params, $next)
    {
        $currentRoute = $_SERVER['REQUEST_URI'] ?? '';
        $currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Skip CSRF validation for exempt routes or non-POST requests
        if ($currentMethod !== 'POST' || in_array($currentRoute, self::$exemptRoutes)) {
            return $next($params);
        }

        // Validate CSRF token
        $tokenName = CSRF::getTokenName();
        if (!isset($_POST[$tokenName]) || !CSRF::validateToken($_POST[$tokenName])) {
            http_response_code(419);
            echo '<div style="height: 100vh; display: flex; justify-content: center; align-items: center; font-size: 20px; font-family: arial;">419 | Page Expired';
            exit;
        }

        return $next($params);
    }
}