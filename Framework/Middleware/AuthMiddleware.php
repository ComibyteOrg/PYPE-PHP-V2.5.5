<?php
namespace Framework\Middleware;

use Framework\Helper\Helper;

class AuthMiddleware
{
    public function handle(array $params, callable $next)
    {
        error_log('AuthMiddleware: Checking authentication');
        error_log('AuthMiddleware: SESSION[user_id] = ' . ($_SESSION['user_id'] ?? 'NOT SET'));
        error_log('AuthMiddleware: COOKIE[remember_me] = ' . ($_COOKIE['remember_me'] ?? 'NOT SET'));

        if (isset($_SESSION['user_id']) || isset($_COOKIE['remember_me'])) {
            error_log('AuthMiddleware: User is authenticated, proceeding');
            return $next($params);
        } else {
            error_log('AuthMiddleware: User not authenticated, redirecting to login');
            header('Location: ' . Helper::url('login'));
            exit;
        }
    }
}