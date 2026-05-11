<?php
namespace Framework\Middleware;

use Framework\Helper\Helper;

class GuestMiddleware
{
    public function handle(array $params, callable $next)
    {
        if (!isset($_SESSION['user_id']) && !isset($_COOKIE['remember_me'])) {
            return $next($params);
        } else {
            header('Location: ' . Helper::url('dashboard'));
            exit;
        }
    }
}