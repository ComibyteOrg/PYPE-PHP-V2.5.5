<?php

namespace Framework\Middleware;

use Framework\Logging\Logger;

/**
 * HoneypotMiddleware
 * Bot protection using invisible honeypot fields and timing analysis.
 * Legitimate users won't see or fill honeypot fields; bots will.
 */
class HoneypotMiddleware
{
    private string $fieldName;
    private int $minTime;
    private bool $blockOnFail;

    public function __construct(?string $fieldName = null, int $minTime = 1, bool $blockOnFail = true)
    {
        $this->fieldName = $fieldName ?? 'my_name_is';
        $this->minTime = $minTime;
        $this->blockOnFail = $blockOnFail;
    }

    public function handle(array $params, callable $next)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $next($params);
        }

        // Check 1: Honeypot field should be empty
        if ($this->honeypotFilled()) {
            Logger::warning('Honeypot triggered — bot detected', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            ]);

            if ($this->blockOnFail) {
                $this->block();
            }
        }

        // Check 2: Form submission too fast
        if ($this->submittedTooFast()) {
            Logger::warning('Too-fast submission — likely bot', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'time_taken' => $this->getSubmissionTime(),
            ]);

            if ($this->blockOnFail) {
                $this->block();
            }
        }

        return $next($params);
    }

    /**
     * Check if the honeypot field was filled.
     */
    private function honeypotFilled(): bool
    {
        $value = $_POST[$this->fieldName] ?? '';
        return !empty($value);
    }

    /**
     * Check if form was submitted too quickly.
     */
    private function submittedTooFast(): bool
    {
        $timestamp = $_POST['_timestamp'] ?? null;

        if ($timestamp === null) {
            return false; // No timestamp check possible
        }

        $elapsed = time() - (int) $timestamp;
        return $elapsed < $this->minTime;
    }

    /**
     * Get the submission time in seconds.
     */
    private function getSubmissionTime(): int
    {
        $timestamp = $_POST['_timestamp'] ?? time();
        return time() - (int) $timestamp;
    }

    /**
     * Block the request silently.
     */
    private function block(): void
    {
        // Return 200 to avoid giving bots feedback
        http_response_code(200);
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
    }

    /**
     * Generate honeypot field HTML for forms.
     * Use this in your form templates.
     */
    public static function field(string $fieldName = 'my_name_is', string $valueName = 'my_email_is'): string
    {
        return sprintf(
            '<div style="display:none;position:absolute;left:-9999px;opacity:0;" aria-hidden="true">' .
            '<label for="%s">Leave this field empty</label>' .
            '<input type="text" name="%s" id="%s" value="" tabindex="-1" autocomplete="off">' .
            '<input type="hidden" name="_timestamp" value="%d">' .
            '</div>',
            $valueName,
            $fieldName,
            $valueName,
            time()
        );
    }

    /**
     * Generate a full honeypot field for Blade/Twig templates.
     */
    public static function render(string $fieldName = null): string
    {
        $name = $fieldName ?? 'my_name_is';
        return self::field($name);
    }
}
