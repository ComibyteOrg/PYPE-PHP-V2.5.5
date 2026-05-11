<?php

namespace Framework\Middleware;

class CorsMiddleware
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::defaultConfig(), $config);
    }

    public static function defaultConfig(): array
    {
        return [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token', 'Accept', 'Origin'],
            'exposed_headers' => [],
            'max_age' => 86400,
            'allow_credentials' => false,
        ];
    }

    public static function allowAll(): self
    {
        return new self(self::defaultConfig());
    }

    public static function allowOrigins(array $origins, array $extraConfig = []): self
    {
        return new self(array_merge([
            'allowed_origins' => $origins,
        ], $extraConfig));
    }

    public static function development(): self
    {
        return new self([
            'allowed_origins' => ['http://localhost:3000', 'http://localhost:5173', 'http://127.0.0.1:3000', 'http://127.0.0.1:5173'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token', 'Accept', 'Origin', 'X-Debug'],
            'exposed_headers' => ['X-Request-Id'],
            'max_age' => 3600,
            'allow_credentials' => true,
        ]);
    }

    public static function spa(array $frontendUrls = [], bool $credentials = true): self
    {
        $defaultFrontend = [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://localhost:8080',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:8080',
        ];

        $origins = empty($frontendUrls) ? $defaultFrontend : $frontendUrls;

        return new self([
            'allowed_origins' => $origins,
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
            'exposed_headers' => ['X-Request-Id', 'X-Total-Count'],
            'max_age' => 86400,
            'allow_credentials' => $credentials,
        ]);
    }

    public static function api(): self
    {
        return new self([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'Origin', 'X-Api-Key'],
            'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'],
            'max_age' => 86400,
            'allow_credentials' => false,
        ]);
    }

    public function handle(array $params, callable $next)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($this->isOriginAllowed($origin)) {
            $allowOrigin = in_array('*', $this->config['allowed_origins']) && !$this->config['allow_credentials']
                ? '*'
                : $origin;

            header("Access-Control-Allow-Origin: {$allowOrigin}");

            if ($this->config['allow_credentials']) {
                header('Access-Control-Allow-Credentials: true');
            }
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->config['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->config['allowed_headers']));

        if (!empty($this->config['exposed_headers'])) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $this->config['exposed_headers']));
        }

        header("Access-Control-Max-Age: {$this->config['max_age']}");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $response = $next($params);

        if ($origin && $this->isOriginAllowed($origin) && $this->config['allow_credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true;
        }

        $allowed = $this->config['allowed_origins'];

        if (in_array('*', $allowed)) {
            return true;
        }

        foreach ($allowed as $pattern) {
            if ($pattern === $origin) {
                return true;
            }

            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], $pattern) . '$/i';
                if (preg_match($regex, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }
}
