<?php

namespace Framework\Api;

class Sse
{
    private static string $channelDir = '';
    private static int $heartbeatInterval = 15;
    private static int $maxConnectionTime = 300;

    public static function configure(array $config): void
    {
        self::$heartbeatInterval = $config['heartbeat'] ?? self::$heartbeatInterval;
        self::$maxConnectionTime = $config['max_time'] ?? self::$maxConnectionTime;
    }

    public static function stream(callable $generator, array $options = []): never
    {
        self::setHeaders();

        $clientId = bin2hex(random_bytes(8));
        $startTime = time();
        $lastId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? $options['last_event_id'] ?? null;
        $retry = $options['retry'] ?? 3000;

        echo "retry: {$retry}\n\n";
        self::flush();

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => exit);
            pcntl_signal(SIGINT, fn() => exit);
        }

        while (true) {
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }

            if (time() - $startTime > self::$maxConnectionTime) {
                self::sendEvent('close', ['reason' => 'max_time_exceeded'], $clientId);
                break;
            }

            $data = $generator($clientId, $lastId);

            if ($data !== null) {
                $lastId = self::sendEvent('message', $data, $clientId);
            }

            $elapsed = time() - $startTime;
            if ($elapsed % self::$heartbeatInterval === 0) {
                echo ": heartbeat\n\n";
                self::flush();
            }

            usleep(500000);
        }
    }

    public static function sendEvent(string $event, mixed $data, ?string $id = null): string
    {
        $eventId = $id ?? bin2hex(random_bytes(8));

        if ($eventId) {
            echo "id: {$eventId}\n";
        }

        if ($event !== 'message') {
            echo "event: {$event}\n";
        }

        if (is_array($data) || is_object($data)) {
            echo "data: " . json_encode($data) . "\n\n";
        } else {
            echo "data: {$data}\n\n";
        }

        self::flush();

        return $eventId;
    }

    public static function broadcast(string $channel, mixed $data, ?string $eventId = null): void
    {
        self::initChannelDir();

        $channelFile = self::getChannelFile($channel);
        $entry = [
            'id' => $eventId ?? bin2hex(random_bytes(8)),
            'timestamp' => time(),
            'data' => $data,
        ];

        $messages = [];
        if (file_exists($channelFile)) {
            $messages = json_decode(file_get_contents($channelFile), true) ?: [];
        }

        $messages[] = $entry;

        if (count($messages) > 100) {
            $messages = array_slice($messages, -50);
        }

        file_put_contents($channelFile, json_encode($messages));
    }

    public static function channelStream(string $channel, array $options = []): never
    {
        self::initChannelDir();
        self::setHeaders();

        $clientId = bin2hex(random_bytes(8));
        $startTime = time();
        $lastId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? $options['last_event_id'] ?? null;
        $seenIds = [];

        echo "retry: 3000\n\n";
        self::flush();

        while (true) {
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }

            if (time() - $startTime > self::$maxConnectionTime) {
                self::sendEvent('close', ['reason' => 'max_time_exceeded'], $clientId);
                break;
            }

            $channelFile = self::getChannelFile($channel);

            if (file_exists($channelFile)) {
                $messages = json_decode(file_get_contents($channelFile), true) ?: [];

                foreach ($messages as $msg) {
                    if (!in_array($msg['id'], $seenIds) && $msg['id'] !== $lastId) {
                        if ($lastId === null || $msg['timestamp'] > (self::getTimestampFromId($lastId, $messages) ?? 0)) {
                            self::sendEvent('message', $msg['data'], $msg['id']);
                            $seenIds[] = $msg['id'];

                            if (count($seenIds) > 100) {
                                $seenIds = array_slice($seenIds, -50);
                            }
                        }
                    }
                }
            }

            $elapsed = time() - $startTime;
            if ($elapsed % self::$heartbeatInterval === 0) {
                echo ": heartbeat\n\n";
                self::flush();
            }

            usleep(500000);
        }
    }

    private static function setHeaders(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }

        ini_set('zlib.output_compression', 'Off');
        ini_set('output_buffering', 'Off');
        ini_set('implicit_flush', 'On');

        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    private static function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    private static function initChannelDir(): void
    {
        if (empty(self::$channelDir)) {
            self::$channelDir = storage_path('sse/channels');
            if (!is_dir(self::$channelDir)) {
                mkdir(self::$channelDir, 0755, true);
            }
        }
    }

    private static function getChannelFile(string $channel): string
    {
        self::initChannelDir();
        return self::$channelDir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '.json';
    }

    private static function getTimestampFromId(?string $id, array $messages): ?int
    {
        if (!$id) {
            return null;
        }

        foreach ($messages as $msg) {
            if ($msg['id'] === $id) {
                return $msg['timestamp'];
            }
        }

        return null;
    }
}
