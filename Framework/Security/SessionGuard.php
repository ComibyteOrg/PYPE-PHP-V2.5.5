<?php

namespace Framework\Security;

use Framework\Logging\Logger;

/**
 * SessionGuard
 * Comprehensive session security suite: fixation protection,
 * concurrent session limits, device tracking, idle timeout, and regeneration.
 */
class SessionGuard
{
    private static string $sessionPrefix = 'pype_sec_';

    /* ============================================================
       SESSION INITIALIZATION
       ============================================================ */

    /**
     * Start a secure session with hardened settings.
     * Call this ONCE at the start of your application.
     */
    public static function start(array $config = []): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // Session cookie settings
        $secure = $config['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $httponly = $config['httponly'] ?? true;
        $samesite = $config['samesite'] ?? 'Lax';
        $lifetime = $config['lifetime'] ?? 86400; // 24 hours
        $path = $config['path'] ?? '/';
        $domain = $config['domain'] ?? '';

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);

        // Session ID settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.cookie_lifetime', $lifetime);
        ini_set('session.gc_maxlifetime', $lifetime);

        // Session name
        if (!empty($config['name'])) {
            session_name($config['name']);
        }

        session_start();

        // Initialize security data
        self::initializeSecurity();
    }

    /* ============================================================
       SESSION FIXATION PROTECTION
       ============================================================ */

    /**
     * Regenerate session ID on privilege changes (login, role change).
     */
    public static function regenerate(bool $deleteOldSession = true): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id($deleteOldSession);

        // Update security metadata
        $_SESSION[self::$sessionPrefix . 'last_regeneration'] = time();
        $_SESSION[self::$sessionPrefix . 'creation_time'] = $_SESSION[self::$sessionPrefix . 'creation_time'] ?? time();
    }

    /**
     * Call on successful login.
     */
    public static function onLogin(): void
    {
        self::regenerate(true);
        $_SESSION[self::$sessionPrefix . 'login_time'] = time();
        $_SESSION[self::$sessionPrefix . 'ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $_SESSION[self::$sessionPrefix . 'user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION[self::$sessionPrefix . 'fingerprint'] = self::generateFingerprint();
        $_SESSION[self::$sessionPrefix . 'validated'] = true;
    }

    /* ============================================================
       SESSION VALIDATION
       ============================================================ */

    /**
     * Validate the current session for tampering.
     * Returns false if session should be destroyed.
     */
    public static function validate(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return true;
        }

        // Check if session was properly initialized
        if (!isset($_SESSION[self::$sessionPrefix . 'fingerprint'])) {
            return false;
        }

        // Verify fingerprint (IP + User Agent consistency)
        if (!self::verifyFingerprint()) {
            Logger::warning('Session fingerprint mismatch', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'session_ip' => $_SESSION[self::$sessionPrefix . 'ip'] ?? 'unknown'
            ]);
            return false;
        }

        // Check idle timeout
        if (self::isIdle()) {
            Logger::warning('Session idle timeout', ['user_id' => $_SESSION['user_id'] ?? 'unknown']);
            return false;
        }

        // Check session age
        if (self::isExpired()) {
            Logger::warning('Session expired', ['user_id' => $_SESSION['user_id'] ?? 'unknown']);
            return false;
        }

        return true;
    }

    /**
     * Call this on every request to validate and update session.
     */
    public static function check(): bool
    {
        if (!self::validate()) {
            self::destroy();
            return false;
        }

        // Update last activity
        $_SESSION[self::$sessionPrefix . 'last_activity'] = time();

        // Periodic regeneration (every 15 minutes)
        $lastRegen = $_SESSION[self::$sessionPrefix . 'last_regeneration'] ?? 0;
        if (time() - $lastRegen > 900) {
            self::regenerate(false);
        }

        return true;
    }

    /* ============================================================
       CONCURRENT SESSION LIMITS
       ============================================================ */

    /**
     * Set maximum concurrent sessions per user.
     */
    public static function setMaxSessions(int $max): void
    {
        $_SESSION[self::$sessionPrefix . 'max_sessions'] = $max;
    }

    /**
     * Check and enforce concurrent session limit.
     */
    public static function enforceSessionLimit(int $userId): bool
    {
        $maxSessions = $_SESSION[self::$sessionPrefix . 'max_sessions'] ?? 3;
        $sessionDir = storage_path('sessions');

        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0755, true);
        }

        $userSessionFile = $sessionDir . '/user_' . $userId . '.json';
        $activeSessions = [];

        if (file_exists($userSessionFile)) {
            $activeSessions = json_decode(file_get_contents($userSessionFile), true) ?: [];
        }

        // Clean expired sessions
        $activeSessions = array_filter($activeSessions, function ($session) {
            return time() - $session['last_activity'] < 86400;
        });

        // Enforce limit
        if (count($activeSessions) >= $maxSessions) {
            // Remove oldest session
            usort($activeSessions, fn($a, $b) => $a['login_time'] <=> $b['login_time']);
            array_shift($activeSessions);
        }

        // Add current session
        $activeSessions[session_id()] = [
            'session_id' => session_id(),
            'login_time' => time(),
            'last_activity' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        file_put_contents($userSessionFile, json_encode($activeSessions));

        return true;
    }

    /**
     * Get active sessions for a user.
     */
    public static function getUserSessions(int $userId): array
    {
        $sessionDir = storage_path('sessions');
        $userSessionFile = $sessionDir . '/user_' . $userId . '.json';

        if (!file_exists($userSessionFile)) {
            return [];
        }

        $sessions = json_decode(file_get_contents($userSessionFile), true) ?: [];

        return array_map(function ($session) {
            return [
                'session_id' => $session['session_id'],
                'ip' => $session['ip'],
                'user_agent' => $session['user_agent'],
                'login_time' => date('Y-m-d H:i:s', $session['login_time']),
                'last_activity' => date('Y-m-d H:i:s', $session['last_activity']),
                'is_current' => $session['session_id'] === session_id(),
            ];
        }, $sessions);
    }

    /**
     * Revoke a specific session.
     */
    public static function revokeSession(int $userId, string $sessionId): void
    {
        $sessionDir = storage_path('sessions');
        $userSessionFile = $sessionDir . '/user_' . $userId . '.json';

        if (!file_exists($userSessionFile)) {
            return;
        }

        $sessions = json_decode(file_get_contents($userSessionFile), true) ?: [];
        unset($sessions[$sessionId]);

        file_put_contents($userSessionFile, json_encode($sessions));
    }

    /**
     * Revoke all sessions except current.
     */
    public static function revokeAllExceptCurrent(int $userId): void
    {
        $sessionDir = storage_path('sessions');
        $userSessionFile = $sessionDir . '/user_' . $userId . '.json';

        if (!file_exists($userSessionFile)) {
            return;
        }

        $sessions = json_decode(file_get_contents($userSessionFile), true) ?: [];
        $currentSession = $sessions[session_id()] ?? null;

        if ($currentSession) {
            file_put_contents($userSessionFile, json_encode([session_id() => $currentSession]));
        } else {
            @unlink($userSessionFile);
        }
    }

    /* ============================================================
       DEVICE TRACKING
       ============================================================ */

    /**
     * Get device information for the current session.
     */
    public static function getDeviceInfo(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $userAgent,
            'browser' => self::detectBrowser($userAgent),
            'platform' => self::detectPlatform($userAgent),
            'is_mobile' => self::isMobile($userAgent),
            'login_time' => date('Y-m-d H:i:s', $_SESSION[self::$sessionPrefix . 'login_time'] ?? time()),
        ];
    }

    /* ============================================================
       IDLE TIMEOUT
       ============================================================ */

    /**
     * Set idle timeout in seconds.
     */
    public static function setIdleTimeout(int $seconds): void
    {
        $_SESSION[self::$sessionPrefix . 'idle_timeout'] = $seconds;
    }

    /**
     * Set session expiry in seconds.
     */
    public static function setExpiry(int $seconds): void
    {
        $_SESSION[self::$sessionPrefix . 'expiry'] = $seconds;
    }

    /**
     * Check if session is idle.
     */
    public static function isIdle(): bool
    {
        $timeout = $_SESSION[self::$sessionPrefix . 'idle_timeout'] ?? 1800; // 30 min default
        $lastActivity = $_SESSION[self::$sessionPrefix . 'last_activity'] ?? time();

        return (time() - $lastActivity) > $timeout;
    }

    /**
     * Check if session has expired.
     */
    public static function isExpired(): bool
    {
        $expiry = $_SESSION[self::$sessionPrefix . 'expiry'] ?? 86400; // 24 hours default
        $creationTime = $_SESSION[self::$sessionPrefix . 'creation_time'] ?? time();

        return (time() - $creationTime) > $expiry;
    }

    /* ============================================================
       DESTROY
       ============================================================ */

    /**
     * Destroy the current session completely.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            return;
        }

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /* ============================================================
       INTERNAL
       ============================================================ */

    private static function initializeSecurity(): void
    {
        $now = time();

        if (!isset($_SESSION[self::$sessionPrefix . 'creation_time'])) {
            $_SESSION[self::$sessionPrefix . 'creation_time'] = $now;
            $_SESSION[self::$sessionPrefix . 'last_regeneration'] = $now;
            $_SESSION[self::$sessionPrefix . 'last_activity'] = $now;
            $_SESSION[self::$sessionPrefix . 'fingerprint'] = self::generateFingerprint();
            $_SESSION[self::$sessionPrefix . 'ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $_SESSION[self::$sessionPrefix . 'user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
    }

    private static function generateFingerprint(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return hash('sha256', $ip . '|' . $ua);
    }

    private static function verifyFingerprint(): bool
    {
        $currentFingerprint = self::generateFingerprint();
        $storedFingerprint = $_SESSION[self::$sessionPrefix . 'fingerprint'] ?? '';

        return hash_equals($storedFingerprint, $currentFingerprint);
    }

    private static function detectBrowser(string $ua): string
    {
        if (stripos($ua, 'Firefox') !== false) return 'Firefox';
        if (stripos($ua, 'Chrome') !== false) return 'Chrome';
        if (stripos($ua, 'Safari') !== false) return 'Safari';
        if (stripos($ua, 'Edge') !== false) return 'Edge';
        if (stripos($ua, 'Opera') !== false || stripos($ua, 'OPR') !== false) return 'Opera';
        if (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false) return 'Internet Explorer';
        return 'Unknown';
    }

    private static function detectPlatform(string $ua): string
    {
        if (stripos($ua, 'Windows') !== false) return 'Windows';
        if (stripos($ua, 'Macintosh') !== false) return 'macOS';
        if (stripos($ua, 'Linux') !== false) return 'Linux';
        if (stripos($ua, 'Android') !== false) return 'Android';
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) return 'iOS';
        return 'Unknown';
    }

    private static function isMobile(string $ua): bool
    {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone/i', $ua) === 1;
    }
}
