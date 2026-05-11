<?php

namespace Framework\Middleware;

/**
 * SecurityHeaders middleware
 * Adds essential HTTP security headers to every response.
 * Configurable for CSP, HSTS, X-Frame-Options, X-Content-Type-Options, etc.
 */
class SecurityHeadersMiddleware
{
    private array $headers;

    public function __construct(?array $config = null)
    {
        $this->headers = $config ?? self::defaultConfig();
    }

    public function handle(array $params, callable $next)
    {
        $this->setHeaders();
        return $next($params);
    }

    /**
     * Apply all configured security headers.
     */
    private function setHeaders(): void
    {
        // Strict-Transport-Security (HSTS)
        if (!empty($this->headers['hsts'])) {
            $hsts = $this->headers['hsts'];
            $maxAge = $hsts['max_age'] ?? 31536000;
            $includeSubDomains = $hsts['include_sub_domains'] ?? true;
            $preload = $hsts['preload'] ?? false;

            $value = "max-age={$maxAge}";
            if ($includeSubDomains) $value .= '; includeSubDomains';
            if ($preload) $value .= '; preload';

            header("Strict-Transport-Security: {$value}");
        }

        // X-Frame-Options
        if (!empty($this->headers['x_frame_options'])) {
            header("X-Frame-Options: {$this->headers['x_frame_options']}");
        }

        // X-Content-Type-Options
        if ($this->headers['x_content_type_options'] ?? true) {
            header('X-Content-Type-Options: nosniff');
        }

        // X-XSS-Protection
        if (!empty($this->headers['x_xss_protection'])) {
            header("X-XSS-Protection: {$this->headers['x_xss_protection']}");
        }

        // Referrer-Policy
        if (!empty($this->headers['referrer_policy'])) {
            header("Referrer-Policy: {$this->headers['referrer_policy']}");
        }

        // Content-Security-Policy
        if (!empty($this->headers['csp'])) {
            $csp = $this->buildCsp($this->headers['csp']);
            header("Content-Security-Policy: {$csp}");
        }

        // Permissions-Policy
        if (!empty($this->headers['permissions_policy'])) {
            $pp = $this->buildPermissionsPolicy($this->headers['permissions_policy']);
            header("Permissions-Policy: {$pp}");
        }

        // X-Permitted-Cross-Domain-Policies
        if (!empty($this->headers['cross_domain_policies'])) {
            header("X-Permitted-Cross-Domain-Policies: {$this->headers['cross_domain_policies']}");
        }

        // Remove X-Powered-By
        if ($this->headers['remove_powered_by'] ?? true) {
            header_remove('X-Powered-By');
        }

        // Cache-Control for sensitive pages
        if (!empty($this->headers['cache_control'])) {
            header("Cache-Control: {$this->headers['cache_control']}");
        }
    }

    /**
     * Build CSP header value from config array.
     */
    private function buildCsp(array $config): string
    {
        $directives = [];

        foreach ($config as $directive => $sources) {
            if (is_array($sources)) {
                $sources = implode(' ', $sources);
            }
            $directives[] = "{$directive} {$sources}";
        }

        return implode('; ', $directives);
    }

    /**
     * Build Permissions-Policy header value.
     */
    private function buildPermissionsPolicy(array $config): string
    {
        $directives = [];

        foreach ($config as $feature => $allowlist) {
            if (is_array($allowlist)) {
                $allowlist = implode(' ', array_map(fn($origin) => "\"{$origin}\"", $allowlist));
            }
            $directives[] = "{$feature}=({$allowlist})";
        }

        return implode(', ', $directives);
    }

    /**
     * Default secure configuration.
     */
    public static function defaultConfig(): array
    {
        return [
            'hsts' => [
                'max_age' => 31536000,        // 1 year
                'include_sub_domains' => true,
                'preload' => false,
            ],
            'x_frame_options' => 'DENY',
            'x_content_type_options' => true,
            'x_xss_protection' => '0',         // Modern browsers prefer CSP over this
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'csp' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-inline'"],
                'style-src' => ["'self'", "'unsafe-inline'"],
                'img-src' => ["'self'", 'data:', 'https:'],
                'font-src' => ["'self'"],
                'connect-src' => ["'self'"],
                'media-src' => ["'self'"],
                'object-src' => ["'none'"],
                'frame-ancestors' => ["'none'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'upgrade-insecure-requests' => '',
            ],
            'permissions_policy' => [
                'camera' => [],
                'microphone' => [],
                'geolocation' => [],
                'payment' => ["'self'"],
            ],
            'cross_domain_policies' => 'none',
            'remove_powered_by' => true,
            'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ];
    }

    /**
     * Create a relaxed configuration for development.
     */
    public static function developmentConfig(): array
    {
        $config = self::defaultConfig();

        $config['csp'] = [
            'default-src' => ["'self'", 'http://localhost:*'],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'http://localhost:*'],
            'style-src' => ["'self'", "'unsafe-inline'", 'http://localhost:*'],
            'img-src' => ["'self'", 'data:', 'https:', 'http://localhost:*'],
            'font-src' => ["'self'", 'data:', 'http://localhost:*'],
            'connect-src' => ["'self'", 'http://localhost:*', 'ws://localhost:*'],
            'media-src' => ["'self'", 'http://localhost:*'],
        ];

        $config['cache_control'] = 'no-cache';
        $config['hsts'] = null; // Disable HSTS in development

        return $config;
    }

    /**
     * Create an API-optimized configuration.
     */
    public static function apiConfig(): array
    {
        $config = self::defaultConfig();

        // APIs don't need most browser-specific headers
        unset($config['csp']);
        unset($config['permissions_policy']);
        unset($config['x_frame_options']);
        unset($config['x_xss_protection']);

        $config['cache_control'] = 'no-store, no-cache, must-revalidate';

        return $config;
    }
}
