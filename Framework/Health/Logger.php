<?php

namespace Framework\Health;

/**
 * Structured Logger — JSON logging with context and error tracking.
 *
 * Usage:
 * Logger::info('User logged in', ['user_id' => 1]);
 * Logger::error('Payment failed', ['order_id' => 123]);
 * Logger::channel('payments')->info('Payment processed');
 */
class Logger
{
    protected string $channel = 'app';
    protected static array $channels = [];

    public static function channel(string $name): self
    {
        if (!isset(self::$channels[$name])) {
            self::$channels[$name] = new self($name);
        }
        return self::$channels[$name];
    }

    public function __construct(string $channel = 'app')
    {
        $this->channel = $channel;
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::log('emergency', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log('alert', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log('notice', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $channel = env('LOG_CHANNEL', 'file');

        if ($channel === 'stdout') {
            self::writeStdout($level, $message, $context);
        } else {
            self::writeFile($level, $message, $context);
        }

        // Also use existing Log system
        if (class_exists('\Framework\Helper\Log')) {
            \Framework\Helper\Log::write($level, "[Structured] {$message}");
        }
    }

    public static function exception(\Throwable $e, array $context = []): void
    {
        self::error($e->getMessage(), array_merge($context, [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]));
    }

    public static function measure(string $label, callable $callback, array $context = []): mixed
    {
        $start = microtime(true);
        try {
            $result = $callback();
            $duration = round((microtime(true) - $start) * 1000, 2);
            self::info("{$label} completed", array_merge($context, ['duration_ms' => $duration]));
            return $result;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            self::error("{$label} failed", array_merge($context, [
                'duration_ms' => $duration,
                'exception' => $e->getMessage(),
            ]));
            throw $e;
        }
    }

    protected static function writeStdout(string $level, string $message, array $context): void
    {
        $entry = self::formatEntry($level, $message, $context);
        echo json_encode($entry) . "\n";
    }

    protected static function writeFile(string $level, string $message, array $context): void
    {
        $logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : __DIR__ . '/../../Storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $file = $logDir . '/structured-' . date('Y-m-d') . '.log';
        $entry = self::formatEntry($level, $message, $context);
        file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND);
    }

    protected static function formatEntry(string $level, string $message, array $context): array
    {
        return [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'pid' => getmypid(),
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
        ];
    }
}
