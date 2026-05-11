<?php

namespace Framework\Security;

/**
 * Encryption
 * AES-256-CBC and AES-256-GCM encryption/decryption helpers
 * for sensitive data at rest.
 */
class Encryption
{
    private static ?string $key = null;
    private static string $cipher = 'aes-256-gcm';
    private static int $keyLength = 32; // 256 bits

    /* ============================================================
       KEY MANAGEMENT
       ============================================================ */

    /**
     * Set the encryption key.
     * Must be exactly 32 bytes for AES-256.
     */
    public static function setKey(string $key): void
    {
        if (strlen($key) < self::$keyLength) {
            // Derive a proper 256-bit key using HKDF
            $key = hash('sha256', $key, true);
        }

        self::$key = substr($key, 0, self::$keyLength);
    }

    /**
     * Initialize from environment variable.
     */
    public static function init(?string $envKey = null): void
    {
        $key = $envKey ?? $_ENV['APP_KEY'] ?? $_ENV['ENCRYPTION_KEY'] ?? '';

        if (empty($key)) {
            throw new \RuntimeException('No encryption key provided. Set APP_KEY in your .env file.');
        }

        // Remove 'base64:' prefix if present
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        self::setKey($key);
    }

    /**
     * Generate a new random encryption key.
     */
    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(self::$keyLength));
    }

    /* ============================================================
       ENCRYPT / DECRYPT
       ============================================================ */

    /**
     * Encrypt a value. Returns a base64-encoded string containing IV + tag + ciphertext.
     */
    public static function encrypt(mixed $value): string
    {
        self::ensureKey();

        $serialized = is_string($value) ? $value : serialize($value);

        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = random_bytes($ivLength);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $serialized,
            self::$cipher,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        // Pack: IV (variable) + Tag (16 bytes for GCM) + Ciphertext
        $payload = $iv . $tag . $ciphertext;

        return base64_encode($payload);
    }

    /**
     * Decrypt a value encrypted with encrypt().
     */
    public static function decrypt(string $encrypted): mixed
    {
        self::ensureKey();

        $payload = base64_decode($encrypted);

        if ($payload === false) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }

        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $tagLength = 16; // GCM tag length

        $iv = substr($payload, 0, $ivLength);
        $tag = substr($payload, $ivLength, $tagLength);
        $ciphertext = substr($payload, $ivLength + $tagLength);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::$cipher,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed. Invalid key or corrupted data.');
        }

        // Try to unserialize, fall back to raw string
        if (preg_match('/^[aOs]:[0-9]+:/', $decrypted)) {
            $unserialized = @unserialize($decrypted);
            if ($unserialized !== false || $decrypted === 'b:0;') {
                return $unserialized;
            }
        }

        return $decrypted;
    }

    /**
     * Encrypt and encode as hex instead of base64.
     */
    public static function encryptHex(mixed $value): string
    {
        return bin2hex(base64_decode(self::encrypt($value)));
    }

    /**
     * Decrypt a hex-encoded encrypted value.
     */
    public static function decryptHex(string $hex): mixed
    {
        return self::decrypt(base64_encode(hex2bin($hex)));
    }

    /* ============================================================
       ENCRYPTION WITHOUT SERIALIZATION (raw strings only)
       ============================================================ */

    /**
     * Encrypt a raw string (no serialization).
     */
    public static function encryptString(string $value): string
    {
        self::ensureKey();

        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = random_bytes($ivLength);

        $tag = '';
        $ciphertext = openssl_encrypt(
            $value,
            self::$cipher,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        $payload = $iv . $tag . $ciphertext;

        return base64_encode($payload);
    }

    /**
     * Decrypt a raw string.
     */
    public static function decryptString(string $encrypted): string
    {
        self::ensureKey();

        $payload = base64_decode($encrypted);

        if ($payload === false) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }

        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $tagLength = 16;

        $iv = substr($payload, 0, $ivLength);
        $tag = substr($payload, $ivLength, $tagLength);
        $ciphertext = substr($payload, $ivLength + $tagLength);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::$cipher,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed.');
        }

        return $decrypted;
    }

    /* ============================================================
       HASHING (One-way)
       ============================================================ */

    /**
     * Create an HMAC-SHA256 signature.
     */
    public static function hmac(string $data, ?string $key = null): string
    {
        $key = $key ?? self::$key;
        self::ensureKey();

        return hash_hmac('sha256', $data, self::$key);
    }

    /**
     * Verify an HMAC signature.
     */
    public static function verifyHmac(string $data, string $signature, ?string $key = null): bool
    {
        $expected = self::hmac($data, $key);
        return hash_equals($expected, $signature);
    }

    /**
     * Hash data with SHA-256 (one-way, not for passwords).
     */
    public static function hash(string $data): string
    {
        return hash('sha256', $data);
    }

    /* ============================================================
       INTERNAL
       ============================================================ */

    private static function ensureKey(): void
    {
        if (self::$key === null) {
            self::init();
        }
    }

    /**
     * Get the cipher being used.
     */
    public static function getCipher(): string
    {
        return self::$cipher;
    }
}
