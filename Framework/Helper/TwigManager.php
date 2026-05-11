<?php

namespace Framework\Helper;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Framework\Helper\Auth;
use Framework\Router\Route;

class TwigManager
{
    private static ?Environment $twig = null;

    public static function getInstance(): Environment
    {
        if (self::$twig === null) {
            $viewPath = Route::getViewPath();
            $loader = new FilesystemLoader($viewPath);
            self::$twig = new Environment($loader, [
                'cache' => false, // Set to a cache directory in production
                'debug' => true,
            ]);

            // Add custom filters
            self::$twig->addFilter(new TwigFilter('uppercase', function ($string) {
                return strtoupper($string);
            }));

            self::$twig->addFilter(new TwigFilter('limit', function ($array, $limit) {
                return array_slice($array, 0, $limit);
            }));

            // Add global auth function
            self::$twig->addFunction(new TwigFunction('auth', function () {
                return new Auth();
            }));

            // Add global CSRF function
            self::$twig->addFunction(new TwigFunction('csrf_field', function () {
                return \Framework\Helper\CSRF::getTokenField();
            }, ['is_safe' => ['html']]));

            // Register global helper functions
            $functions = [
                'sanitize',
                'redirect',
                'set_alert',
                'writetxt',
                'deletetxt',
                'returnJson',
                'excerpt',
                'readingTime',
                'dd',
                'env',
                'asset',
                'url',
                'slugify',
                'session',
                'old',
                'csrfField',
                'csrf_token',
                'array_get',
                'base_path',
                'app_path',
                'storage_path',
                'method',
                'input',
                'csrf_verify',
                'verifyCsrf',
                'csrfInput',
                'db_path',
                'upload',
                'check',
                'logout',
                'flash',
                'getFlash'
            ];

            foreach ($functions as $fn) {
                if (function_exists($fn)) {
                    self::$twig->addFunction(new TwigFunction($fn, $fn, ['is_safe' => ['html']]));
                }
            }
        }

        return self::$twig;
    }

    public static function render(string $template, array $data = []): string
    {
        $twig = self::getInstance();
        return $twig->render($template, $data);
    }
}
