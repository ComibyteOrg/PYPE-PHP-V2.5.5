<?php

namespace Framework\Security;

use Framework\Helper\DB;
use Framework\Logging\Logger;

/**
 * ApiKey
 * API key management with generation, rotation, scopes, and rate limits.
 */
class ApiKey
{
    private static string $table = 'api_keys';
    private static int $defaultKeyLength = 64;
    private static string $prefix = 'pk_';

    /* ============================================================
       CONFIGURATION
       ============================================================ */

    public static function configure(int $keyLength = 64, string $prefix = 'pk_'): void
    {
        self::$defaultKeyLength = $keyLength;
        self::$prefix = $prefix;
    }

    /* ============================================================
       KEY GENERATION
       ============================================================ */

    /**
     * Generate a new API key.
     * Returns the raw key (show to user once) and stores a hash.
     */
    public static function generate(
        string $name,
        ?int $userId = null,
        array $scopes = [],
        ?int $rateLimit = null,
        ?string $expiresAt = null
    ): string|false {
        self::ensureTable();

        $rawKey = self::$prefix . bin2hex(random_bytes(self::$defaultKeyLength));
        $hashedKey = password_hash($rawKey, PASSWORD_ARGON2ID);

        try {
            DB::table(self::$table)->insert([
                'user_id' => $userId,
                'name' => $name,
                'key_hash' => $hashedKey,
                'scopes' => json_encode($scopes),
                'rate_limit' => $rateLimit,
                'expires_at' => $expiresAt,
                'last_used_at' => null,
                'is_revoked' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Logger::info('API key generated', [
                'name' => $name,
                'user_id' => $userId,
                'scopes' => $scopes,
            ]);

            // Return raw key — this is the ONLY time it's available
            return $rawKey;
        } catch (\Exception $e) {
            Logger::error('Failed to generate API key', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /* ============================================================
       KEY VALIDATION
       ============================================================ */

    /**
     * Validate an API key from the request.
     * Returns the key record if valid, false otherwise.
     */
    public static function validate(?string $key = null): array|false
    {
        self::ensureTable();

        $key = $key ?? self::getKeyFromRequest();

        if (empty($key)) {
            return false;
        }

        $keys = DB::table(self::$table)->where('is_revoked', 0)->get();

        foreach ($keys as $keyRecord) {
            if (password_verify($key, $keyRecord['key_hash'])) {
                // Check expiry
                if ($keyRecord['expires_at'] && strtotime($keyRecord['expires_at']) < time()) {
                    Logger::warning('Expired API key used', ['key_id' => $keyRecord['id']]);
                    return false;
                }

                // Update last used
                DB::table(self::$table)
                    ->where('id', $keyRecord['id'])
                    ->update(['last_used_at' => date('Y-m-d H:i:s')], ['id' => $keyRecord['id']]);

                return $keyRecord;
            }
        }

        return false;
    }

    /**
     * Check if a key has a specific scope.
     */
    public static function hasScope(array|object $keyRecord, string $scope): bool
    {
        $scopes = is_array($keyRecord) ? json_decode($keyRecord['scopes'] ?? '[]', true) : [];

        // Wildcard scope grants all
        if (in_array('*', $scopes)) {
            return true;
        }

        return in_array($scope, $scopes);
    }

    /**
     * Check rate limit for a key.
     */
    public static function checkRateLimit(array|object $keyRecord): bool
    {
        self::ensureTable();

        $rateLimit = $keyRecord['rate_limit'] ?? null;
        if ($rateLimit === null) {
            return true; // No limit
        }

        $storageDir = storage_path('security/api_rate_limits');
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $file = $storageDir . '/key_' . $keyRecord['id'] . '.json';
        $data = ['count' => 0, 'window_start' => time()];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: $data;
        }

        // Reset window if expired (1 hour window)
        if (time() - $data['window_start'] > 3600) {
            $data = ['count' => 0, 'window_start' => time()];
        }

        $data['count']++;
        file_put_contents($file, json_encode($data));

        if ($data['count'] > $rateLimit) {
            Logger::warning('API key rate limit exceeded', [
                'key_id' => $keyRecord['id'],
                'limit' => $rateLimit,
            ]);
            return false;
        }

        return true;
    }

    /* ============================================================
       KEY MANAGEMENT
       ============================================================ */

    /**
     * Revoke an API key by ID.
     */
    public static function revoke(int $keyId): bool
    {
        try {
            DB::table(self::$table)
                ->where('id', $keyId)
                ->update(['is_revoked' => 1], ['id' => $keyId]);

            Logger::info('API key revoked', ['key_id' => $keyId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revoke all keys for a user.
     */
    public static function revokeAllForUser(int $userId): bool
    {
        try {
            DB::table(self::$table)
                ->where('user_id', $userId)
                ->update(['is_revoked' => 1], ['user_id' => $userId]);

            Logger::info('All API keys revoked for user', ['user_id' => $userId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update scopes for a key.
     */
    public static function updateScopes(int $keyId, array $scopes): bool
    {
        try {
            DB::table(self::$table)
                ->where('id', $keyId)
                ->update(['scopes' => json_encode($scopes)], ['id' => $keyId]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update rate limit for a key.
     */
    public static function updateRateLimit(int $keyId, ?int $rateLimit): bool
    {
        try {
            DB::table(self::$table)
                ->where('id', $keyId)
                ->update(['rate_limit' => $rateLimit], ['id' => $keyId]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Rotate a key — generate new key and revoke old one.
     */
    public static function rotate(int $keyId, ?string $newName = null): string|false
    {
        // Get old key info
        $oldKey = DB::table(self::$table)->find($keyId);

        if (!$oldKey) {
            return false;
        }

        // Revoke old key
        self::revoke($keyId);

        // Generate new key
        return self::generate(
            $newName ?? $oldKey['name'],
            $oldKey['user_id'],
            json_decode($oldKey['scopes'] ?? '[]', true),
            $oldKey['rate_limit'],
            $oldKey['expires_at']
        );
    }

    /**
     * Get all keys for a user.
     */
    public static function getUserKeys(int $userId): array
    {
        try {
            $keys = DB::table(self::$table)->where('user_id', $userId)->get();

            // Mask the hashes
            return array_map(function ($key) {
                $key['key_hash'] = '***REDACTED***';
                return $key;
            }, $keys);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get key count for a user.
     */
    public static function countForUser(int $userId): int
    {
        try {
            return DB::table(self::$table)->where('user_id', $userId)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clean up expired keys.
     */
    public static function pruneExpired(): int
    {
        try {
            $now = date('Y-m-d H:i:s');
            $result = DB::rawQuery(
                "UPDATE " . self::$table . " SET is_revoked = 1 WHERE expires_at IS NOT NULL AND expires_at < ?",
                [$now]
            );

            return DB::rawQuery("SELECT ROW_COUNT() as count")->fetch()['count'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /* ============================================================
       REQUEST HANDLING
       ============================================================ */

    /**
     * Get API key from request (header, query, or body).
     */
    private static function getKeyFromRequest(): ?string
    {
        // Authorization header: Bearer <key> or ApiKey <key>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;

        if ($authHeader) {
            if (preg_match('/^(?:Bearer|ApiKey)\s+(.+)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // Query parameter
        if (!empty($_GET['api_key'])) {
            return $_GET['api_key'];
        }

        // POST body
        if (!empty($_POST['api_key'])) {
            return $_POST['api_key'];
        }

        return null;
    }

    /**
     * API key authentication middleware.
     */
    public static function middleware(array $params, callable $next, array $requiredScopes = []): mixed
    {
        $keyRecord = self::validate();

        if (!$keyRecord) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid or missing API key']);
            exit;
        }

        // Check scopes
        foreach ($requiredScopes as $scope) {
            if (!self::hasScope($keyRecord, $scope)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Forbidden', 'message' => "Missing required scope: {$scope}"]);
                exit;
            }
        }

        // Check rate limit
        if (!self::checkRateLimit($keyRecord)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Too Many Requests', 'message' => 'API rate limit exceeded']);
            exit;
        }

        return $next($params);
    }

    /* ============================================================
       INTERNAL
       ============================================================ */

    private static function ensureTable(): void
    {
        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS " . self::$table . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                name VARCHAR(100) NOT NULL,
                key_hash VARCHAR(255) NOT NULL,
                scopes JSON,
                rate_limit INT,
                expires_at DATETIME,
                last_used_at DATETIME,
                is_revoked TINYINT DEFAULT 0,
                created_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_is_revoked (is_revoked),
                INDEX idx_expires_at (expires_at)
            )");
        } catch (\Exception $e) {
            // Ignore
        }
    }
}
