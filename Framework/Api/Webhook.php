<?php

namespace Framework\Api;

use Framework\Logging\Logger;

class Webhook
{
    private static string $table = 'webhooks';
    private static int $timeout = 30;
    private static int $maxRetries = 3;
    private static int $retryDelay = 60;
    private static array $defaultHeaders = [
        'Content-Type' => 'application/json',
        'User-Agent' => 'PypePHP-Webhook/2.5',
    ];

    public static function configure(array $config): void
    {
        self::$timeout = $config['timeout'] ?? self::$timeout;
        self::$maxRetries = $config['max_retries'] ?? self::$maxRetries;
        self::$retryDelay = $config['retry_delay'] ?? self::$retryDelay;
    }

    public static function create(string $event, string $url, array $config = []): int|false
    {
        self::ensureTable();

        try {
            $id = \Framework\Helper\DB::table(self::$table)->insert([
                'event' => $event,
                'url' => $url,
                'secret' => $config['secret'] ?? bin2hex(random_bytes(32)),
                'headers' => json_encode(array_merge(self::$defaultHeaders, $config['headers'] ?? [])),
                'is_active' => 1,
                'last_triggered_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Logger::info('Webhook created', ['event' => $event, 'url' => $url]);
            return $id;
        } catch (\Exception $e) {
            Logger::error('Failed to create webhook', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public static function trigger(string $event, array $payload = []): array
    {
        self::ensureTable();

        $webhooks = \Framework\Helper\DB::table(self::$table)
            ->where('event', $event)
            ->where('is_active', 1)
            ->get();

        $results = [];

        foreach ($webhooks as $webhook) {
            $result = self::deliver($webhook, $payload);
            $results[] = $result;

            if ($result['success']) {
                \Framework\Helper\DB::table(self::$table)
                    ->where('id', $webhook['id'])
                    ->update([
                        'last_triggered_at' => date('Y-m-d H:i:s'),
                        'success_count' => ($webhook['success_count'] ?? 0) + 1,
                    ], ['id' => $webhook['id']]);
            }
        }

        return $results;
    }

    public static function triggerAsync(string $event, array $payload = []): void
    {
        $logFile = storage_path('logs/webhook_queue.log');
        $entry = json_encode([
            'event' => $event,
            'payload' => $payload,
            'queued_at' => date('Y-m-d H:i:s'),
        ]) . PHP_EOL;

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function deliver(array $webhook, array $payload): array
    {
        $url = $webhook['url'];
        $secret = $webhook['secret'] ?? '';
        $headers = json_decode($webhook['headers'] ?? '{}', true) ?: self::$defaultHeaders;

        $body = json_encode([
            'event' => $webhook['event'],
            'timestamp' => time(),
            'data' => $payload,
        ]);

        if (!empty($secret)) {
            $signature = hash_hmac('sha256', $body, $secret);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
            $headers['X-Webhook-Id'] = $webhook['id'];
        }

        $attempts = 0;
        $lastError = '';

        while ($attempts <= self::$maxRetries) {
            $result = self::sendRequest($url, $body, $headers);

            if ($result['success']) {
                Logger::info('Webhook delivered', [
                    'webhook_id' => $webhook['id'],
                    'url' => $url,
                    'status' => $result['status_code'],
                    'attempts' => $attempts + 1,
                ]);

                return [
                    'success' => true,
                    'webhook_id' => $webhook['id'],
                    'url' => $url,
                    'status_code' => $result['status_code'],
                    'attempts' => $attempts + 1,
                ];
            }

            $lastError = $result['error'];
            $attempts++;

            if ($attempts <= self::$maxRetries) {
                usleep(self::$retryDelay * 1000000);
            }
        }

        Logger::error('Webhook delivery failed', [
            'webhook_id' => $webhook['id'],
            'url' => $url,
            'error' => $lastError,
            'attempts' => $attempts,
        ]);

        return [
            'success' => false,
            'webhook_id' => $webhook['id'],
            'url' => $url,
            'error' => $lastError,
            'attempts' => $attempts,
        ];
    }

    public static function activate(int $id): void
    {
        \Framework\Helper\DB::table(self::$table)
            ->where('id', $id)
            ->update(['is_active' => 1], ['id' => $id]);
    }

    public static function deactivate(int $id): void
    {
        \Framework\Helper\DB::table(self::$table)
            ->where('id', $id)
            ->update(['is_active' => 0], ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        \Framework\Helper\DB::table(self::$table)
            ->where('id', $id)
            ->delete(['id' => $id]);
    }

    public static function list(?string $event = null): array
    {
        $query = \Framework\Helper\DB::table(self::$table);
        if ($event) {
            $query = $query->where('event', $event);
        }
        return $query->get();
    }

    private static function sendRequest(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);

        $headerStrings = [];
        foreach ($headers as $key => $value) {
            $headerStrings[] = "{$key}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerStrings,
            CURLOPT_TIMEOUT => self::$timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return ['success' => true, 'status_code' => $statusCode];
        }

        return [
            'success' => false,
            'error' => "HTTP {$statusCode}: " . substr($response, 0, 200),
            'status_code' => $statusCode,
        ];
    }

    private static function ensureTable(): void
    {
        try {
            \Framework\Helper\DB::table(self::$table)->first();
        } catch (\Exception $e) {
            try {
                \Framework\Helper\DB::getConnection()->exec("
                    CREATE TABLE IF NOT EXISTS " . self::$table . " (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        event VARCHAR(100) NOT NULL,
                        url VARCHAR(500) NOT NULL,
                        secret VARCHAR(255),
                        headers TEXT,
                        is_active TINYINT(1) DEFAULT 1,
                        success_count INT DEFAULT 0,
                        failure_count INT DEFAULT 0,
                        last_triggered_at DATETIME,
                        created_at DATETIME
                    )
                ");
            } catch (\Exception $e2) {
            }
        }
    }
}
