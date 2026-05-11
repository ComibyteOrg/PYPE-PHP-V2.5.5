<?php
namespace Framework\Logging;

class Logger
{
    private static $instance = null;
    private $logPath;
    private $enabled = true;

    private function __construct()
    {
        $this->logPath = base_path('Storage/logs/app.log');

        // Ensure log directory exists
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function enable()
    {
        self::getInstance()->enabled = true;
    }

    public static function disable()
    {
        self::getInstance()->enabled = false;
    }

    public static function info($message, $context = [])
    {
        self::getInstance()->log('INFO', $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::getInstance()->log('ERROR', $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::getInstance()->log('WARNING', $message, $context);
    }

    public static function debug($message, $context = [])
    {
        self::getInstance()->log('DEBUG', $message, $context);
    }

    private function log($level, $message, $context = [])
    {
        if (!$this->enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$level}: {$message}";

        if (!empty($context)) {
            $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        error_log($logMessage . PHP_EOL, 3, $this->logPath);
    }

    public static function getLogPath()
    {
        return self::getInstance()->logPath;
    }
}