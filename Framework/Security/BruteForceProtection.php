<?php

namespace Framework\Security;

use Framework\Helper\DB;
use Framework\Helper\Logger;
use Framework\Logging\Logger as FrameworkLogger;

/**
 * BruteForceProtection
 * Tracks failed login attempts per identifier and enforces lockout thresholds.
 */
class BruteForceProtection
{
    private static string $table = 'login_attempts';
    private static int $maxAttempts = 5;
    private static int $lockoutMinutes = 15;
    private static int $decayMinutes = 30;

    /**
     * Configure thresholds.
     */
    public static function configure(int $maxAttempts = 5, int $lockoutMinutes = 15, int $decayMinutes = 30): void
    {
        self::$maxAttempts = $maxAttempts;
        self::$lockoutMinutes = $lockoutMinutes;
        self::$decayMinutes = $decayMinutes;
    }

    /**
     * Record a failed login attempt. Returns true if account should be locked.
     */
    public static function recordFailedAttempt(string $identifier): bool
    {
        self::ensureTable();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = self::getCacheKey($identifier, $ip);

        // File-based tracking for speed (falls back to DB if needed)
        $storageDir = storage_path('security/bruteforce');
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $file = $storageDir . '/' . md5($key) . '.json';
        $data = self::loadAttemptData($file);

        // Reset if decay period has passed
        if (time() - $data['last_attempt'] > (self::$decayMinutes * 60)) {
            $data = ['attempts' => 0, 'last_attempt' => time(), 'locked_until' => 0];
        }

        $data['attempts']++;
        $data['last_attempt'] = time();

        // Lock if threshold reached
        if ($data['attempts'] >= self::$maxAttempts) {
            $data['locked_until'] = time() + (self::$lockoutMinutes * 60);
            Logger::warning('Account locked due to brute force', [
                'identifier' => $identifier,
                'ip' => $ip,
                'attempts' => $data['attempts']
            ]);
        }

        file_put_contents($file, json_encode($data));

        // Also log to database if possible
        try {
            DB::table(self::$table)->insert([
                'identifier' => $identifier,
                'ip_address' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'attempts' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return $data['locked_until'] > time();
    }

    /**
     * Check if an identifier is currently locked out.
     */
    public static function isLocked(string $identifier): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = self::getCacheKey($identifier, $ip);

        $storageDir = storage_path('security/bruteforce');
        $file = $storageDir . '/' . md5($key) . '.json';

        if (!file_exists($file)) {
            return false;
        }

        $data = self::loadAttemptData($file);

        // Check if locked
        if ($data['locked_until'] > time()) {
            return true;
        }

        // Reset if decay period has passed
        if (time() - $data['last_attempt'] > (self::$decayMinutes * 60)) {
            @unlink($file);
            return false;
        }

        return false;
    }

    /**
     * Clear failed attempts after successful login.
     */
    public static function clearAttempts(string $identifier): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = self::getCacheKey($identifier, $ip);

        $storageDir = storage_path('security/bruteforce');
        $file = $storageDir . '/' . md5($key) . '.json';

        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Get remaining attempts before lockout.
     */
    public static function remainingAttempts(string $identifier): int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = self::getCacheKey($identifier, $ip);

        $storageDir = storage_path('security/bruteforce');
        $file = $storageDir . '/' . md5($key) . '.json';

        if (!file_exists($file)) {
            return self::$maxAttempts;
        }

        $data = self::loadAttemptData($file);
        return max(0, self::$maxAttempts - $data['attempts']);
    }

    /**
     * Get remaining lockout time in seconds.
     */
    public static function lockoutRemaining(string $identifier): int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = self::getCacheKey($identifier, $ip);

        $storageDir = storage_path('security/bruteforce');
        $file = $storageDir . '/' . md5($key) . '.json';

        if (!file_exists($file)) {
            return 0;
        }

        $data = self::loadAttemptData($file);
        return max(0, $data['locked_until'] - time());
    }

    /* ============================================================
       INTERNAL
       ============================================================ */

    private static function getCacheKey(string $identifier, string $ip): string
    {
        return "bruteforce:{$identifier}:{$ip}";
    }

    private static function loadAttemptData(string $file): array
    {
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'attempts' => 0,
            'last_attempt' => 0,
            'locked_until' => 0
        ];
    }

    /**
     * Ensure the login_attempts table exists.
     */
    private static function ensureTable(): void
    {
        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS " . self::$table . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                attempts INT DEFAULT 1,
                created_at DATETIME NOT NULL,
                INDEX idx_identifier (identifier),
                INDEX idx_ip_address (ip_address),
                INDEX idx_created_at (created_at)
            )");
        } catch (\Exception $e) {
            // Ignore
        }
    }
}
