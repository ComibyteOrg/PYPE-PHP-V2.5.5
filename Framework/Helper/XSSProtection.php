<?php
namespace Framework\Helper;

use HTMLPurifier;
use HTMLPurifier_Config;

class XSSProtection
{
    private static $purifier;

    public static function getPurifier()
    {
        if (!self::$purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,strong,em,u,a[href],img[src|alt],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,hr');
            $config->set('CSS.AllowedProperties', '');
            self::$purifier = new HTMLPurifier($config);
        }
        return self::$purifier;
    }

    public static function clean($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'clean'], $input);
        }
        return self::getPurifier()->purify($input);
    }

    public static function cleanArray($array)
    {
        $cleaned = [];
        foreach ($array as $key => $value) {
            $cleaned[$key] = self::clean($value);
        }
        return $cleaned;
    }
}