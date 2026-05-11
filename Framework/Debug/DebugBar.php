<?php

namespace Framework\Debug;

/**
 * Debug Bar — in-browser debug panel (dev only).
 *
 * Usage:
 * DebugBar::enable();
 * DebugBar::addMessage('User logged in');
 * DebugBar::log($query);
 * DebugBar::time('my-timer');
 * // ... code ...
 * DebugBar::stopTime('my-timer');
 * DebugBar::render(); // Automatically renders at end of request
 */
class DebugBar
{
    protected static bool $enabled = false;
    protected static array $messages = [];
    protected static array $queries = [];
    protected static array $timers = [];
    protected static array $memory = [];
    protected static array $files = [];
    protected static float $startTime;

    public static function enable(): void
    {
        if (!self::$enabled) {
            self::$enabled = true;
            self::$startTime = microtime(true);
            self::memory('start');
            register_shutdown_function([self::class, 'render']);
        }
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled && env('APP_DEBUG', false);
    }

    public static function addMessage(string $message, string $level = 'info'): void
    {
        self::$messages[] = [
            'message' => $message,
            'level' => $level,
            'time' => self::elapsed(),
        ];
    }

    public static function log(mixed $data, string $label = 'log'): void
    {
        $output = is_array($data) || is_object($data)
            ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : (string) $data;

        self::addMessage("{$label}: {$output}", 'log');
    }

    public static function info(string $message): void
    {
        self::addMessage($message, 'info');
    }

    public static function warning(string $message): void
    {
        self::addMessage($message, 'warning');
    }

    public static function error(string $message): void
    {
        self::addMessage($message, 'error');
    }

    public static function query(string $sql, array $bindings = [], float $time = 0): void
    {
        self::$queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'time_display' => self::formatTime($time),
        ];
    }

    public static function time(string $name): void
    {
        self::$timers[$name] = [
            'start' => microtime(true),
            'end' => null,
        ];
    }

    public static function stopTime(string $name): void
    {
        if (isset(self::$timers[$name])) {
            self::$timers[$name]['end'] = microtime(true);
        }
    }

    public static function memory(string $label = 'current'): void
    {
        self::$memory[] = [
            'label' => $label,
            'bytes' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'display' => self::formatBytes(memory_get_usage(true)),
        ];
    }

    public static function elapsed(): string
    {
        $elapsed = (microtime(true) - self::$startTime) * 1000;
        return self::formatTime($elapsed);
    }

    public static function getTotalQueryTime(): float
    {
        return array_sum(array_column(self::$queries, 'time'));
    }

    public static function render(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        $contentType = header('Content-Type');
        if (is_string($contentType) && str_contains($contentType, 'json')) {
            return;
        }

        $totalTime = self::elapsed();
        $totalQueries = count(self::$queries);
        $totalQueryTime = self::getTotalQueryTime();
        $memoryUsage = self::formatBytes(memory_get_usage(true));
        $messageCount = count(self::$messages);

        $html = self::renderHtml($totalTime, $totalQueries, $totalQueryTime, $memoryUsage, $messageCount);
        echo $html;
    }

    protected static function renderHtml(string $totalTime, int $totalQueries, float $totalQueryTime, string $memoryUsage, int $messageCount): string
    {
        $queriesHtml = '';
        foreach (self::$queries as $q) {
            $bindings = !empty($q['bindings']) ? 'Bindings: ' . htmlspecialchars(json_encode($q['bindings'])) : '';
            $queriesHtml .= "<tr><td>{$q['time_display']}</td><td><code>" . htmlspecialchars($q['sql']) . "</code></td><td>{$bindings}</td></tr>";
        }

        $messagesHtml = '';
        foreach (self::$messages as $m) {
            $messagesHtml .= "<tr><td>{$m['time']}</td><td class=\"level-{$m['level']}\">{$m['level']}</td><td>" . htmlspecialchars($m['message']) . "</td></tr>";
        }

        $timersHtml = '';
        foreach (self::$timers as $name => $timer) {
            $elapsed = $timer['end']
                ? ($timer['end'] - $timer['start']) * 1000
                : (microtime(true) - $timer['start']) * 1000;
            $timersHtml .= "<tr><td>{$name}</td><td>" . self::formatTime($elapsed) . "</td></tr>";
        }

        return <<<HTML
<style>
#pype-debug{position:fixed;bottom:0;left:0;right:0;font-family:monospace;font-size:12px;background:#1a1a2e;color:#eee;z-index:99999;max-height:300px;overflow:auto;border-top:2px solid #e94560}
#pype-debug .debug-tabs{display:flex;background:#16213e;position:sticky;top:0;z-index:1}
#pype-debug .debug-tabs button{background:none;border:none;color:#eee;padding:8px 16px;cursor:pointer;font-size:12px;border-bottom:2px solid transparent}
#pype-debug .debug-tabs button:hover{background:#0f3460}
#pype-debug .debug-tabs button.active{border-bottom-color:#e94560;color:#e94560}
#pype-debug .debug-panel{display:none;padding:10px}
#pype-debug .debug-panel.active{display:block}
#pype-debug .summary{display:flex;gap:20px;padding:4px 16px;background:#0f3460;font-size:11px}
#pype-debug table{width:100%;border-collapse:collapse}
#pype-debug th,#pype-debug td{padding:4px 8px;border-bottom:1px solid #16213e;text-align:left}
#pype-debug th{background:#16213e;color:#e94560}
#pype-debug .level-error{color:#e94560}
#pype-debug .level-warning{color:#f39c12}
#pype-debug .level-info{color:#3498db}
#pype-debug code{background:#0f3460;padding:2px 4px;border-radius:3px;font-size:11px}
</style>
<div id="pype-debug">
<div class="summary">
<span>⏱ {$totalTime}</span>
<span>🗄 {$totalQueries} queries ({$totalQueryTime}ms)</span>
<span>💾 {$memoryUsage}</span>
<span>💬 {$messageCount} messages</span>
</div>
<div class="debug-tabs">
<button class="active" onclick="pypeDebugTab('queries')">Queries ({$totalQueries})</button>
<button onclick="pypeDebugTab('messages')">Messages ({$messageCount})</button>
<button onclick="pypeDebugTab('timers')">Timers</button>
<button onclick="pypeDebugTab('memory')">Memory</button>
</div>
<div id="debug-queries" class="debug-panel active">
<table><thead><tr><th>Time</th><th>Query</th><th>Bindings</th></tr></thead><tbody>{$queriesHtml}</tbody></table>
</div>
<div id="debug-messages" class="debug-panel">
<table><thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead><tbody>{$messagesHtml}</tbody></table>
</div>
<div id="debug-timers" class="debug-panel">
<table><thead><tr><th>Timer</th><th>Duration</th></tr></thead><tbody>{$timersHtml}</tbody></table>
</div>
<div id="debug-memory" class="debug-panel">
<table><thead><tr><th>Label</th><th>Usage</th><th>Peak</th></tr></thead><tbody>
HTML;

        foreach (self::$memory as $m) {
            $html .= "<tr><td>{$m['label']}</td><td>{$m['display']}</td><td>" . self::formatBytes($m['peak']) . "</td></tr>";
        }

        $html .= "</tbody></table></div></div>";
        $html .= '<script>function pypeDebugTab(n){document.querySelectorAll(".debug-panel").forEach(function(e){e.classList.remove("active")});document.querySelectorAll(".debug-tabs button").forEach(function(e){e.classList.remove("active")});document.getElementById("debug-"+n).classList.add("active");event.target.classList.add("active")}</script>';

        return $html;
    }

    protected static function formatTime(float $ms): string
    {
        if ($ms < 1) {
            return round($ms * 1000, 1) . 'μs';
        }
        return round($ms, 1) . 'ms';
    }

    protected static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function reset(): void
    {
        self::$messages = [];
        self::$queries = [];
        self::$timers = [];
        self::$memory = [];
    }
}
