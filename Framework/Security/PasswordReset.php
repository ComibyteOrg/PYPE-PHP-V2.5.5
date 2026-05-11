<?php

namespace Framework\Security;

use Framework\Helper\DB;
use Framework\Helper\Logger;

/**
 * PasswordReset
 * Secure password reset token generation, validation, and expiry management.
 */
class PasswordReset
{
    private static string $table = 'password_resets';
    private static int $tokenExpiryMinutes = 60;
    private static int $maxTokensPerUser = 3;

    /**
     * Configure token settings.
     */
    public static function configure(int $expiryMinutes = 60, int $maxTokens = 3): void
    {
        self::$tokenExpiryMinutes = $expiryMinutes;
        self::$maxTokensPerUser = $maxTokens;
    }

    /**
     * Create a password reset token for an email address.
     * Returns the raw token (to be sent to user) or false on failure.
     */
    public static function createToken(string $email): string|false
    {
        self::ensureTable();
        self::cleanupExpired();

        // Remove excess tokens for this email
        $existingCount = DB::table(self::$table)->where('email', $email)->count();
        if ($existingCount >= self::$maxTokensPerUser) {
            DB::table(self::$table)
                ->where('email', $email)
                ->orderBy('created_at', 'ASC')
                ->limit($existingCount - self::$maxTokensPerUser + 1)
                ->delete([]);
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_ARGON2ID);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::$tokenExpiryMinutes * 60));

        try {
            DB::table(self::$table)->insert([
                'email' => $email,
                'token' => $hashedToken,
                'expires_at' => $expiresAt,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to create password reset token', ['email' => $email, 'error' => $e->getMessage()]);
            return false;
        }

        Logger::info('Password reset token created', ['email' => $email]);

        return $token;
    }

    /**
     * Validate a password reset token.
     * Returns the email if valid, false otherwise.
     * Token is deleted after successful validation (single-use).
     */
    public static function validateToken(string $email, string $token): string|false
    {
        self::ensureTable();
        self::cleanupExpired();

        $records = DB::table(self::$table)->where('email', $email)->get();

        foreach ($records as $record) {
            if (password_verify($token, $record['token'])) {
                // Delete used token (single-use)
                DB::table(self::$table)->where('id', $record['id'])->delete([]);

                Logger::info('Password reset token validated', ['email' => $email]);
                return $email;
            }
        }

        Logger::warning('Invalid password reset token', ['email' => $email]);
        return false;
    }

    /**
     * Delete all reset tokens for an email (e.g., after password change).
     */
    public static function deleteTokens(string $email): void
    {
        try {
            DB::table(self::$table)->where('email', $email)->delete([]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Check if an email has active reset tokens.
     */
    public static function hasActiveToken(string $email): bool
    {
        self::ensureTable();
        self::cleanupExpired();

        try {
            $count = DB::table(self::$table)->where('email', $email)->count();
            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean up expired tokens from the database.
     */
    public static function cleanupExpired(): void
    {
        try {
            $expired = date('Y-m-d H:i:s');
            DB::rawQuery("DELETE FROM " . self::$table . " WHERE expires_at < ?", [$expired]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Get time until token expires (in minutes).
     */
    public static function getExpiryMinutes(): int
    {
        return self::$tokenExpiryMinutes;
    }

    /* ============================================================
       INTERNAL
       ============================================================ */

    private static function ensureTable(): void
    {
        try {
            DB::rawQuery("CREATE TABLE IF NOT EXISTS " . self::$table . " (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME NOT NULL,
                INDEX idx_email (email),
                INDEX idx_expires_at (expires_at)
            )");
        } catch (\Exception $e) {
            // Ignore
        }
    }
}
