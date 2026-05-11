<?php

namespace Framework\Helper;

/**
 * Enhanced Input Sanitizer
 * Provides deep sanitization for all input types with context-aware rules.
 */
class InputSanitizer
{
    private static array $customRules = [];

    /**
     * Sanitize a single value based on its expected type.
     */
    public static function sanitize(mixed $value, string $type = 'string'): mixed
    {
        return match ($type) {
            'email' => self::sanitizeEmail($value),
            'url' => self::sanitizeUrl($value),
            'html' => self::sanitizeHtml($value),
            'int' => self::sanitizeInt($value),
            'float' => self::sanitizeFloat($value),
            'bool' => self::sanitizeBool($value),
            'filename' => self::sanitizeFilename($value),
            'path' => self::sanitizePath($value),
            'sql' => self::sanitizeSql($value),
            'json' => self::sanitizeJson($value),
            'phone' => self::sanitizePhone($value),
            'ip' => self::sanitizeIp($value),
            'uuid' => self::sanitizeUuid($value),
            'slug' => self::sanitizeSlug($value),
            default => self::sanitizeString($value),
        };
    }

    /**
     * Deep sanitize an entire array recursively.
     */
    public static function sanitizeArray(array $data, ?array $rules = null): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            $cleanKey = self::sanitizeString($key);

            if (is_array($value)) {
                $cleaned[$cleanKey] = isset($rules[$key])
                    ? self::sanitizeArray($value, $rules[$key])
                    : self::sanitizeArray($value);
            } else {
                $type = isset($rules[$key]) ? $rules[$key] : 'string';
                $cleaned[$cleanKey] = self::sanitize($value, $type);
            }
        }

        return $cleaned;
    }

    /**
     * Strip all null and empty-string values from an array.
     */
    public static function stripEmpty(array $data): array
    {
        return array_filter($data, fn($v) => $v !== null && $v !== '');
    }

    /**
     * Only allow specified keys from input array (whitelist).
     */
    public static function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Remove specified keys from input array (blacklist).
     */
    public static function except(array $data, array $keys): array
    {
        return array_diff_key($data, array_flip($keys));
    }

    /* ============================================================
       TYPE-SPECIFIC SANITIZERS
       ============================================================ */

    private static function sanitizeString(mixed $value): string
    {
        if ($value === null) return '';
        $value = (string) $value;
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        $value = self::stripNullBytes($value);
        return $value;
    }

    private static function sanitizeEmail(mixed $value): string
    {
        $value = self::sanitizeString($value);
        $value = strtolower($value);
        $value = filter_var($value, FILTER_SANITIZE_EMAIL);
        return $value;
    }

    private static function sanitizeUrl(mixed $value): string
    {
        $value = self::sanitizeString($value);
        $value = filter_var($value, FILTER_SANITIZE_URL);
        return $value;
    }

    private static function sanitizeHtml(mixed $value): string
    {
        if ($value === null) return '';
        return XSSProtection::clean((string) $value);
    }

    private static function sanitizeInt(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : 0;
    }

    private static function sanitizeFloat(mixed $value): float
    {
        if ($value === null || $value === '') return 0.0;
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false
            ? (float) $value
            : 0.0;
    }

    private static function sanitizeBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function sanitizeFilename(mixed $value): string
    {
        $value = self::sanitizeString($value);
        $value = preg_replace('/[^\w\.\-]/', '_', $value);
        $value = preg_replace('/\.+/', '.', $value);
        return trim($value, '.');
    }

    private static function sanitizePath(mixed $value): string
    {
        $value = self::sanitizeString($value);
        $value = str_replace(['../', '..\\'], '', $value);
        $value = preg_replace('/[^\w\/\.\-]/', '_', $value);
        return $value;
    }

    private static function sanitizeSql(mixed $value): string
    {
        $value = (string) $value;
        $value = self::stripNullBytes($value);
        $value = preg_replace('/[\x00\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $value);
        return $value;
    }

    private static function sanitizeJson(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private static function sanitizePhone(mixed $value): string
    {
        $value = self::sanitizeString($value);
        $value = preg_replace('/[^\d+\-().\s]/', '', $value);
        return $value;
    }

    private static function sanitizeIp(mixed $value): string
    {
        $value = self::sanitizeString($value);
        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : '0.0.0.0';
    }

    private static function sanitizeUuid(mixed $value): string
    {
        $value = self::sanitizeString($value);
        $value = strtolower($value);
        $value = preg_replace('/[^\w\-]/', '', $value);
        return $value;
    }

    private static function sanitizeSlug(mixed $value): string
    {
        return Helper::slugify((string) $value);
    }

    /* ============================================================
       UTILITIES
       ============================================================ */

    private static function stripNullBytes(string $value): string
    {
        return str_replace("\0", '', $value);
    }

    /**
     * Register a custom sanitizer rule.
     */
    public static function registerRule(string $name, callable $handler): void
    {
        self::$customRules[$name] = $handler;
    }

    /**
     * Apply a custom sanitizer rule.
     */
    public static function applyCustom(string $name, mixed $value): mixed
    {
        if (!isset(self::$customRules[$name])) {
            throw new \InvalidArgumentException("Custom sanitizer rule '{$name}' not found.");
        }
        return (self::$customRules[$name])($value);
    }

    /**
     * Limit string length without breaking words.
     */
    public static function limit(string $value, int $max, string $suffix = '...'): string
    {
        if (mb_strlen($value) <= $max) return $value;
        $truncated = mb_substr($value, 0, $max);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        return $truncated . $suffix;
    }

    /**
     * Strip all HTML tags (hard strip, no exceptions).
     */
    public static function stripAll(mixed $value): string
    {
        $value = (string) $value;
        $value = strip_tags($value);
        $value = self::stripNullBytes($value);
        return trim($value);
    }

    /**
     * Remove potentially dangerous characters for file system usage.
     */
    public static function sanitizeForFileSystem(mixed $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/[\\\\\/\:\*\?\"\<\>\|]/', '_', $value);
        $value = preg_replace('/[\x00-\x1f]/', '', $value);
        return trim($value, ' .');
    }
}
