<?php
namespace Framework\Middleware;

use Framework\Helper\Logger;

class RateLimitMiddleware
{
    private $maxAttempts = 60;
    private $decayMinutes = 1;

    public function handle($params, $next)
    {
        $identifier = $_SERVER['REMOTE_ADDR'];
        $key = "rate_limit_" . md5($identifier);

        // Using file-based storage for simplicity
        // In production, you might want to use Redis or database
        $storageFile = sys_get_temp_dir() . '/' . $key . '.json';

        $data = [];
        if (file_exists($storageFile)) {
            $data = json_decode(file_get_contents($storageFile), true);
        }

        $attempts = intval($data['attempts'] ?? 0);
        $lastAttempt = intval($data['last_attempt'] ?? 0);

        // Reset counter if decay period has passed
        if (time() - $lastAttempt > ($this->decayMinutes * 60)) {
            $attempts = 0;
        }

        // Block if limit exceeded
        if ($attempts >= $this->maxAttempts) {
            Logger::warning("Rate limit exceeded for IP: " . $identifier);
            http_response_code(429);
            echo json_encode(['error' => 'Too Many Requests']);
            exit;
        }

        // Increment attempts
        $attempts++;
        $data = [
            'attempts' => $attempts,
            'last_attempt' => time()
        ];

        file_put_contents($storageFile, json_encode($data));

        // Add rate limit headers
        header("X-RateLimit-Limit: " . $this->maxAttempts);
        header("X-RateLimit-Remaining: " . ($this->maxAttempts - $attempts));

        return $next($params);
    }
}