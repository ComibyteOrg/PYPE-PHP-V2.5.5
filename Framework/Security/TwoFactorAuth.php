<?php

namespace Framework\Security;

/**
 * TwoFactorAuth (2FA/MFA)
 * TOTP-based two-factor authentication compatible with Google Authenticator, Authy, etc.
 * Uses RFC 6238 TOTP algorithm (SHA-1, 30-second time step, 6-digit codes).
 */
class TwoFactorAuth
{
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const ALGORITHM = 'sha1';
    private const SECRET_LENGTH = 20;
    private const BACKUP_CODE_COUNT = 10;
    private const BACKUP_CODE_LENGTH = 8;

    /**
     * Generate a new secret key for a user.
     * Returns the raw secret (store hashed in DB) and base32 encoded (show to user).
     */
    public static function generateSecret(): array
    {
        $secret = random_bytes(self::SECRET_LENGTH);

        return [
            'secret' => $secret,
            'base32' => self::toBase32($secret),
            'backup_codes' => self::generateBackupCodes(),
        ];
    }

    /**
     * Generate the OTP provisioning URI for QR code generation.
     */
    public static function getProvisioningUri(string $secret, string $issuer, string $accountName): string
    {
        $base32 = self::toBase32($secret);
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$base32}&issuer={$encodedIssuer}&algorithm=" . strtoupper(self::ALGORITHM) . "&digits=" . self::DIGITS . "&period=" . self::PERIOD;
    }

    /**
     * Verify a TOTP code against a secret.
     * Allows for clock drift (±1 time step by default).
     */
    public static function verify(string|bytes $secret, string $code, int $drift = 1): bool
    {
        if (!is_string($secret) && !is_resource($secret)) {
            return false;
        }

        if (!is_string($code) || strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }

        $timeStep = floor(time() / self::PERIOD);

        for ($i = -$drift; $i <= $drift; $i++) {
            $computed = self::generateCode($secret, $timeStep + $i);
            if (hash_equals($computed, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify a backup code and remove it from the list.
     * Backup codes are one-time use.
     */
    public static function verifyBackupCode(array $storedCodes, string $inputCode): array|false
    {
        $inputHash = password_hash($inputCode, PASSWORD_DEFAULT);

        foreach ($storedCodes as $index => $storedHash) {
            if (password_verify($inputCode, $storedHash)) {
                // Remove used code
                unset($storedCodes[$index]);
                return [
                    'valid' => true,
                    'remaining_codes' => $storedCodes,
                ];
            }
        }

        return false;
    }

    /**
     * Generate new backup codes.
     */
    public static function generateBackupCodes(): array
    {
        $codes = [];
        $hashes = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = self::generateBackupCode();
            $codes[] = $code;
            $hashes[] = password_hash($code, PASSWORD_DEFAULT);
        }

        return [
            'codes' => $codes,
            'hashes' => $hashes,
        ];
    }

    /**
     * Check if a stored hash matches a TOTP secret (for database storage).
     */
    public static function hashSecret(string|bytes $secret): string
    {
        return password_hash($secret, PASSWORD_ARGON2ID);
    }

    /**
     * Verify a raw secret against a stored hash.
     */
    public static function verifySecret(string|bytes $secret, string $hash): bool
    {
        return password_verify($secret, $hash);
    }

    /* ============================================================
       INTERNAL — TOTP IMPLEMENTATION (RFC 6238)
       ============================================================ */

    /**
     * Generate a TOTP code for a given time step.
     */
    private static function generateCode(string|bytes $secret, int $timeStep): string
    {
        $timeBinary = pack('N*', 0) . pack('N*', $timeStep);
        $hash = hash_hmac(self::ALGORITHM, $timeBinary, $secret, true);
        $offset = ord($hash[19]) & 0x0f;
        $code = (ord($hash[$offset]) & 0x7f) << 24
            | (ord($hash[$offset + 1]) & 0xff) << 16
            | (ord($hash[$offset + 2]) & 0xff) << 8
            | (ord($hash[$offset + 3]) & 0xff);

        $code = $code % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Encode binary data to Base32 (RFC 4648).
     */
    private static function toBase32(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $result .= $alphabet[($buffer >> $bitsLeft) & 0x1f];
            }
        }

        if ($bitsLeft > 0) {
            $result .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1f];
        }

        return $result;
    }

    /**
     * Decode Base32 to binary.
     */
    public static function fromBase32(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32));
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
            $buffer = ($buffer << 5) | strpos($alphabet, $base32[$i]);
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $result;
    }

    /**
     * Generate a single backup code.
     */
    private static function generateBackupCode(): string
    {
        $chars = '0123456789';
        $code = '';

        for ($i = 0; $i < self::BACKUP_CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $code;
    }
}
