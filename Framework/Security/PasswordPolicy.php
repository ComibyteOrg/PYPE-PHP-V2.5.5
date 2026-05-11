<?php

namespace Framework\Security;

/**
 * PasswordPolicy
 * Enforces password complexity, history, expiry, and breach checking.
 */
class PasswordPolicy
{
    private static array $config = [
        'min_length' => 8,
        'max_length' => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special' => true,
        'min_special_chars' => 1,
        'min_numbers' => 1,
        'special_characters' => '!@#$%^&*()_+-=[]{}|;:\'",.<>?/~`',
        'prevent_common' => true,
        'prevent_username' => true,
        'prevent_email' => true,
        'prevent_dictionary' => false,
        'max_repeated_chars' => 3,
        'history_count' => 5,
        'expiry_days' => 90,
        'algorithm' => PASSWORD_ARGON2ID,
        'argon_options' => [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ],
        'bcrypt_options' => [
            'cost' => 12,
        ],
    ];

    /* ============================================================
       CONFIGURATION
       ============================================================ */

    public static function configure(array $config): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    public static function setLevel(string $level = 'default'): void
    {
        switch ($level) {
            case 'minimal':
                self::configure([
                    'min_length' => 6,
                    'require_uppercase' => false,
                    'require_lowercase' => true,
                    'require_numbers' => false,
                    'require_special' => false,
                    'prevent_common' => false,
                    'history_count' => 0,
                    'expiry_days' => 0,
                    'algorithm' => PASSWORD_BCRYPT,
                ]);
                break;

            case 'default':
                self::configure([
                    'min_length' => 8,
                    'require_uppercase' => true,
                    'require_lowercase' => true,
                    'require_numbers' => true,
                    'require_special' => true,
                    'prevent_common' => true,
                    'history_count' => 5,
                    'expiry_days' => 90,
                    'algorithm' => PASSWORD_ARGON2ID,
                ]);
                break;

            case 'strong':
                self::configure([
                    'min_length' => 12,
                    'max_length' => 256,
                    'require_uppercase' => true,
                    'require_lowercase' => true,
                    'require_numbers' => true,
                    'require_special' => true,
                    'min_special_chars' => 2,
                    'min_numbers' => 2,
                    'prevent_common' => true,
                    'prevent_dictionary' => true,
                    'max_repeated_chars' => 2,
                    'history_count' => 10,
                    'expiry_days' => 60,
                    'algorithm' => PASSWORD_ARGON2ID,
                    'argon_options' => [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 3,
                    ],
                ]);
                break;

            case 'maximum':
                self::configure([
                    'min_length' => 16,
                    'max_length' => 512,
                    'require_uppercase' => true,
                    'require_lowercase' => true,
                    'require_numbers' => true,
                    'require_special' => true,
                    'min_special_chars' => 3,
                    'min_numbers' => 3,
                    'prevent_common' => true,
                    'prevent_dictionary' => true,
                    'max_repeated_chars' => 1,
                    'history_count' => 20,
                    'expiry_days' => 30,
                    'algorithm' => PASSWORD_ARGON2ID,
                    'argon_options' => [
                        'memory_cost' => 131072,
                        'time_cost' => 5,
                        'threads' => 4,
                    ],
                ]);
                break;
        }
    }

    /* ============================================================
       VALIDATION
       ============================================================ */

    /**
     * Validate a password against the policy.
     * Returns ['valid' => bool, 'errors' => array, 'score' => int].
     */
    public static function validate(string $password, string $level = 'default', ?string $username = null, ?string $email = null): array
    {
        self::setLevel($level);
        $errors = [];
        $score = 100;

        // Length checks
        if (strlen($password) < self::$config['min_length']) {
            $errors[] = "Password must be at least " . self::$config['min_length'] . " characters.";
            $score -= 20;
        }

        if (strlen($password) > self::$config['max_length']) {
            $errors[] = "Password must not exceed " . self::$config['max_length'] . " characters.";
            $score -= 10;
        }

        // Complexity checks
        if (self::$config['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
            $score -= 15;
        }

        if (self::$config['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
            $score -= 15;
        }

        if (self::$config['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
            $score -= 15;
        }

        if (self::$config['require_special']) {
            $specialCount = self::countSpecialChars($password);
            if ($specialCount < self::$config['min_special_chars']) {
                $errors[] = "Password must contain at least " . self::$config['min_special_chars'] . " special character(s).";
                $score -= 15;
            }
        }

        // Repeated characters
        if (self::$config['max_repeated_chars'] > 0) {
            if (preg_match('/(.)\1{' . self::$config['max_repeated_chars'] . ',}/', $password)) {
                $errors[] = "Password must not have more than " . self::$config['max_repeated_chars'] . " repeated characters.";
                $score -= 10;
            }
        }

        // Common password check
        if (self::$config['prevent_common'] && self::isCommonPassword($password)) {
            $errors[] = "This password is too common and easily guessable.";
            $score -= 25;
        }

        // Username check
        if (self::$config['prevent_username'] && $username) {
            if (stripos($password, $username) !== false) {
                $errors[] = "Password must not contain your username.";
                $score -= 20;
            }
        }

        // Email check
        if (self::$config['prevent_email'] && $email) {
            $emailPart = explode('@', $email)[0];
            if (strlen($emailPart) >= 3 && stripos($password, $emailPart) !== false) {
                $errors[] = "Password must not contain parts of your email.";
                $score -= 20;
            }
        }

        // Keyboard patterns
        if (self::isKeyboardPattern($password)) {
            $errors[] = "Password must not be a keyboard pattern.";
            $score -= 20;
        }

        $score = max(0, $score);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => $score,
            'strength' => self::getStrengthLabel($score),
        ];
    }

    /* ============================================================
       HASHING
       ============================================================ */

    /**
     * Hash a password using the configured algorithm.
     */
    public static function hash(string $password): string
    {
        $algorithm = self::$config['algorithm'];

        if ($algorithm === PASSWORD_ARGON2ID || $algorithm === PASSWORD_ARGON2I) {
            return password_hash($password, $algorithm, self::$config['argon_options']);
        }

        return password_hash($password, $algorithm, self::$config['bcrypt_options']);
    }

    /**
     * Verify a password against a hash.
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs rehashing (algorithm or options changed).
     */
    public static function needsRehash(string $hash): bool
    {
        $algorithm = self::$config['algorithm'];

        if ($algorithm === PASSWORD_ARGON2ID || $algorithm === PASSWORD_ARGON2I) {
            return password_needs_rehash($hash, $algorithm, self::$config['argon_options']);
        }

        return password_needs_rehash($hash, $algorithm, self::$config['bcrypt_options']);
    }

    /* ============================================================
       PASSWORD HISTORY
       ============================================================ */

    /**
     * Check if a password was used recently.
     */
    public static function checkHistory(string $newPassword, array $oldHashes): bool
    {
        if (self::$config['history_count'] <= 0) {
            return true;
        }

        foreach ($oldHashes as $oldHash) {
            if (self::verify($newPassword, $oldHash)) {
                return false;
            }
        }

        return true;
    }

    /* ============================================================
       PASSWORD EXPIRY
       ============================================================ */

    /**
     * Check if a password has expired.
     */
    public static function isExpired(string $lastChangedDate): bool
    {
        if (self::$config['expiry_days'] <= 0) {
            return false;
        }

        $lastChanged = strtotime($lastChangedDate);
        $expiry = $lastChanged + (self::$config['expiry_days'] * 86400);

        return time() > $expiry;
    }

    /**
     * Get days until password expires.
     */
    public static function daysUntilExpiry(string $lastChangedDate): int
    {
        if (self::$config['expiry_days'] <= 0) {
            return -1;
        }

        $lastChanged = strtotime($lastChangedDate);
        $expiry = $lastChanged + (self::$config['expiry_days'] * 86400);

        return max(0, (int) ceil(($expiry - time()) / 86400));
    }

    /* ============================================================
       BREACH CHECKING
       ============================================================ */

    /**
     * Check if a password has been exposed in data breaches.
     * Uses the Have I Been Pwned API (k-anonymity model).
     */
    public static function isBreached(string $password): int
    {
        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        $response = @file_get_contents("https://api.pwnedpasswords.com/range/{$prefix}");

        if ($response === false) {
            return -1; // Could not check
        }

        $lines = explode("\r\n", $response);

        foreach ($lines as $line) {
            [$hashSuffix, $count] = explode(':', $line);

            if (strtoupper($hashSuffix) === $suffix) {
                return (int) $count;
            }
        }

        return 0;
    }

    /* ============================================================
       INTERNAL
       ============================================================ */

    private static function countSpecialChars(string $password): int
    {
        $count = 0;
        $specials = str_split(self::$config['special_characters']);

        foreach (str_split($password) as $char) {
            if (in_array($char, $specials)) {
                $count++;
            }
        }

        return $count;
    }

    private static function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'monkey', 'master', 'dragon', '111111', 'baseball',
            'iloveyou', 'trustno1', 'sunshine', 'password1', 'letmein',
            'football', 'shadow', 'michael', '654321', 'superman',
            'qazwsx', '123456789', '1234', '12345', 'passw0rd',
            'admin', 'admin123', 'root', 'toor', 'welcome',
            'login', 'princess', 'starwars', 'hello', 'charlie',
            'donald', 'batman', 'access', 'thunder', 'matrix',
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    private static function isKeyboardPattern(string $password): bool
    {
        $patterns = [
            'qwerty', 'qwertyuiop', 'asdfgh', 'asdfghjkl', 'zxcvbn',
            'zxcvbnm', '1234567890', '0987654321', 'qazwsx', 'qweasdzxc',
            '1q2w3e4r', '1q2w3e', 'q1w2e3', 'abcd', 'abcdef',
            'abcdefg', 'abcdefgh',
        ];

        $lower = strtolower($password);

        foreach ($patterns as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function getStrengthLabel(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Very Strong',
            $score >= 75 => 'Strong',
            $score >= 50 => 'Moderate',
            $score >= 25 => 'Weak',
            default => 'Very Weak',
        };
    }

    /**
     * Get current configuration.
     */
    public static function getConfig(): array
    {
        return self::$config;
    }
}
