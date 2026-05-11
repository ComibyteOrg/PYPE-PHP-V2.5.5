<?php

namespace Framework\Api;

use Framework\Logging\Logger;

class Jwt
{
    private static string $secret = '';
    private static string $algorithm = 'HS256';
    private static int $accessTokenTtl = 3600;
    private static int $refreshTokenTtl = 604800;
    private static string $issuer = 'pype-php';
    private static array $blacklist = [];

    public static function configure(array $config): void
    {
        self::$secret = $config['secret'] ?? self::$secret;
        self::$algorithm = $config['algorithm'] ?? self::$algorithm;
        self::$accessTokenTtl = $config['access_ttl'] ?? self::$accessTokenTtl;
        self::$refreshTokenTtl = $config['refresh_ttl'] ?? self::$refreshTokenTtl;
        self::$issuer = $config['issuer'] ?? self::$issuer;

        if (empty(self::$secret)) {
            $envKey = env('JWT_SECRET', '');
            if (!empty($envKey)) {
                self::$secret = $envKey;
            }
        }
    }

    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function createToken(array $payload, ?string $type = 'access'): string
    {
        self::ensureSecret();

        $now = time();
        $ttl = $type === 'refresh' ? self::$refreshTokenTtl : self::$accessTokenTtl;

        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm,
        ];

        $claims = [
            'iss' => self::$issuer,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
            'type' => $type,
        ];

        $tokenPayload = array_merge($claims, $payload);

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($tokenPayload));
        $signature = self::sign("{$headerEncoded}.{$payloadEncoded}");

        return "{$headerEncoded}.{$payloadEncoded}.{$signature}";
    }

    public static function createAccessToken(array $payload): string
    {
        return self::createToken($payload, 'access');
    }

    public static function createRefreshToken(array $payload): string
    {
        return self::createToken($payload, 'refresh');
    }

    public static function createTokenPair(array $payload): array
    {
        $subject = $payload['sub'] ?? $payload['user_id'] ?? null;
        $accessPayload = $subject ? array_merge($payload, ['sub' => $subject]) : $payload;
        $refreshPayload = $subject ? ['sub' => $subject, 'user_id' => $subject] : $payload;

        return [
            'access_token' => self::createAccessToken($accessPayload),
            'refresh_token' => self::createRefreshToken($refreshPayload),
            'token_type' => 'Bearer',
            'expires_in' => self::$accessTokenTtl,
        ];
    }

    public static function verifyToken(string $token, ?string $type = null): array|false
    {
        self::ensureSecret();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        if (!self::verifySignature("{$headerEncoded}.{$payloadEncoded}", $signature)) {
            return false;
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!$payload) {
            return false;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        if (isset($payload['iss']) && $payload['iss'] !== self::$issuer) {
            return false;
        }

        if (isset($payload['jti']) && in_array($payload['jti'], self::$blacklist)) {
            return false;
        }

        $blacklistFile = self::getBlacklistFile();
        if (file_exists($blacklistFile)) {
            $blacklisted = json_decode(file_get_contents($blacklistFile), true) ?: [];
            if (isset($payload['jti']) && in_array($payload['jti'], $blacklisted)) {
                return false;
            }
        }

        if ($type !== null && isset($payload['type']) && $payload['type'] !== $type) {
            return false;
        }

        return $payload;
    }

    public static function verifyAccessToken(string $token): array|false
    {
        return self::verifyToken($token, 'access');
    }

    public static function verifyRefreshToken(string $token): array|false
    {
        return self::verifyToken($token, 'refresh');
    }

    public static function refreshToken(string $refreshToken): array|false
    {
        $payload = self::verifyRefreshToken($refreshToken);
        if (!$payload) {
            return false;
        }

        self::blacklistToken($refreshToken);

        $newPayload = [
            'sub' => $payload['sub'] ?? $payload['user_id'] ?? null,
            'user_id' => $payload['user_id'] ?? $payload['sub'] ?? null,
        ];

        if (isset($payload['scopes'])) {
            $newPayload['scopes'] = $payload['scopes'];
        }

        return self::createTokenPair($newPayload);
    }

    public static function blacklistToken(string $token): void
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        if (!$payload || !isset($payload['jti'])) {
            return;
        }

        self::$blacklist[] = $payload['jti'];

        $blacklistFile = self::getBlacklistFile();
        $blacklisted = [];
        if (file_exists($blacklistFile)) {
            $blacklisted = json_decode(file_get_contents($blacklistFile), true) ?: [];
        }

        $blacklisted[] = $payload['jti'];
        file_put_contents($blacklistFile, json_encode(array_unique($blacklisted)));
    }

    public static function hasScope(array $payload, string $scope): bool
    {
        $scopes = $payload['scopes'] ?? [];
        if (!is_array($scopes)) {
            $scopes = is_string($scopes) ? explode(',', $scopes) : [];
        }

        return in_array('*', $scopes) || in_array($scope, $scopes);
    }

    public static function getBearerToken(): string|false
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (empty($authHeader)) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        }

        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        return false;
    }

    public static function getFromRequest(): array|false
    {
        $token = self::getBearerToken();
        if ($token === false) {
            $token = $_GET['access_token'] ?? $_POST['access_token'] ?? false;
        }

        if ($token === false) {
            return false;
        }

        return self::verifyAccessToken($token);
    }

    public static function cleanBlacklist(): void
    {
        $blacklistFile = self::getBlacklistFile();
        if (!file_exists($blacklistFile)) {
            return;
        }

        $blacklisted = json_decode(file_get_contents($blacklistFile), true) ?: [];
        $cleaned = [];

        foreach ($blacklisted as $jti) {
            $cleaned[] = $jti;
        }

        if (count($cleaned) > 10000) {
            $cleaned = array_slice($cleaned, -5000);
        }

        file_put_contents($blacklistFile, json_encode($cleaned));
    }

    private static function sign(string $input): string
    {
        return match (self::$algorithm) {
            'HS256' => self::base64UrlEncode(hash_hmac('sha256', $input, self::$secret, true)),
            'HS384' => self::base64UrlEncode(hash_hmac('sha384', $input, self::$secret, true)),
            'HS512' => self::base64UrlEncode(hash_hmac('sha512', $input, self::$secret, true)),
            default => self::base64UrlEncode(hash_hmac('sha256', $input, self::$secret, true)),
        };
    }

    private static function verifySignature(string $input, string $signature): bool
    {
        $expected = self::sign($input);
        return hash_equals($expected, $signature);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private static function ensureSecret(): void
    {
        if (empty(self::$secret)) {
            throw new \RuntimeException('JWT secret not configured. Set JWT_SECRET in .env or call Jwt::configure(["secret" => "..."]).');
        }
    }

    private static function getBlacklistFile(): string
    {
        $dir = storage_path('security/jwt');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/blacklist.json';
    }
}
