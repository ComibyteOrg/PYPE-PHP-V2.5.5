<?php
namespace Framework\Helper;

class Logger
{
    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }

    private static function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$level}: {$message}";

        if (!empty($context)) {
            $logMessage .= ' ' . json_encode($context);
        }

        // Ensure logs directory exists
        $logDir = base_path('Storage/logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        error_log($logMessage . PHP_EOL, 3, $logDir . '/app.log');
    }
}