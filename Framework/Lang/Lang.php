<?php

namespace Framework\Lang;

/**
 * Localization (i18n) System
 * Multi-language support with translation files.
 *
 * Usage:
 * Lang::setLocale('fr');
 * echo Lang::get('messages.welcome');
 * echo __('messages.welcome');
 * echo trans_choice('messages.users', 5);
 */
class Lang
{
    protected static string $locale = 'en';
    protected static string $fallback = 'en';
    protected static array $translations = [];
    protected static string $path = '';

    public static function init(?string $locale = null, ?string $path = null): void
    {
        if ($locale !== null) {
            self::$locale = $locale;
        }

        $detected = self::detectLocale();
        if ($detected && $locale === null) {
            self::$locale = $detected;
        }

        self::$path = $path ?? (defined('BASE_PATH') ? BASE_PATH . '/Resources/lang' : __DIR__ . '/../../Resources/lang');
        self::load(self::$locale);
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        self::load($locale);
    }

    public static function setFallback(string $fallback): void
    {
        self::$fallback = $fallback;
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function getFallback(): string
    {
        return self::$fallback;
    }

    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? self::$locale;
        $translation = self::resolve($key, $locale);

        if ($translation === null) {
            $translation = self::resolve($key, self::$fallback);
        }

        if ($translation === null) {
            return $key;
        }

        return self::replace($translation, $replace);
    }

    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return self::get($key, $replace, $locale);
    }

    public static function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $translation = self::get($key, [], $locale);

        if (!str_contains($translation, '|')) {
            return self::replace($translation, array_merge($replace, ['count' => $number]));
        }

        $parts = array_map('trim', explode('|', $translation));
        $result = self::determinePluralForm($number, $parts);

        return self::replace($result, array_merge($replace, ['count' => $number]));
    }

    public static function transChoice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        return self::choice($key, $number, $replace, $locale);
    }

    public static function has(string $key): bool
    {
        return self::resolve($key, self::$locale) !== null;
    }

    public static function load(string $locale): void
    {
        if (isset(self::$translations[$locale])) {
            return;
        }

        self::$translations[$locale] = [];
        $localePath = self::$path . '/' . $locale;

        if (!is_dir($localePath)) {
            return;
        }

        $files = glob($localePath . '/*.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $data = include $file;
            if (is_array($data)) {
                self::$translations[$locale][$name] = $data;
            }
        }

        // Load JSON translations
        $jsonFile = $localePath . '.json';
        if (file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);
            if (is_array($data)) {
                self::$translations[$locale]['_json'] = $data;
            }
        }
    }

    public static function addLines(string $locale, string $file, array $lines): void
    {
        if (!isset(self::$translations[$locale])) {
            self::$translations[$locale] = [];
        }
        if (!isset(self::$translations[$locale][$file])) {
            self::$translations[$locale][$file] = [];
        }
        self::$translations[$locale][$file] = array_merge(self::$translations[$locale][$file], $lines);
    }

    public static function availableLocales(): array
    {
        if (!is_dir(self::$path)) {
            return [];
        }
        $dirs = glob(self::$path . '/*', GLOB_ONLYDIR);
        return array_map('basename', $dirs ?: []);
    }

    protected static function resolve(string $key, string $locale): ?string
    {
        if (!isset(self::$translations[$locale])) {
            return null;
        }

        // Check JSON translations first
        if (isset(self::$translations[$locale]['_json'][$key])) {
            return self::$translations[$locale]['_json'][$key];
        }

        // file.key.subkey format
        $parts = explode('.', $key);
        if (count($parts) < 2) {
            return null;
        }

        $file = array_shift($parts);
        if (!isset(self::$translations[$locale][$file])) {
            return null;
        }

        $value = self::$translations[$locale][$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }

        return is_string($value) ? $value : null;
    }

    protected static function replace(string $string, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $string = str_replace(':' . $key, (string) $value, $string);
        }
        return $string;
    }

    protected static function determinePluralForm(int $number, array $parts): string
    {
        if ($number === 0 && isset($parts[0])) {
            return $parts[0];
        }
        if ($number === 1 && isset($parts[1])) {
            return $parts[1];
        }
        return end($parts) ?: $parts[0];
    }

    protected static function detectLocale(): ?string
    {
        if (isset($_SESSION['locale'])) {
            return $_SESSION['locale'];
        }

        if (isset($_COOKIE['locale'])) {
            return $_COOKIE['locale'];
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            return $lang;
        }

        return null;
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return Lang::get($key, $replace);
    }
}

if (!function_exists('trans')) {
    function trans(string $key, array $replace = []): string
    {
        return Lang::get($key, $replace);
    }
}

if (!function_exists('trans_choice')) {
    function trans_choice(string $key, int $number, array $replace = []): string
    {
        return Lang::choice($key, $number, $replace);
    }
}
