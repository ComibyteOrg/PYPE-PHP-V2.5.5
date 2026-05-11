<?php
namespace Framework\Mail;

use Framework\Logging\Logger;

class Mailer
{
    private static $instance = null;
    private $driver;
    private $config;

    private function __construct()
    {
        $this->driver = $_ENV['MAIL_DRIVER'] ?? 'log';
        $this->config = [
            'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
            'port' => $_ENV['MAIL_PORT'] ?? 25,
            'username' => $_ENV['MAIL_USERNAME'] ?? null,
            'password' => $_ENV['MAIL_PASSWORD'] ?? null,
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? null,
            'from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@example.com',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Pype Framework'
        ];
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function send($to, $subject, $body, $attachments = [])
    {
        return self::getInstance()->deliver($to, $subject, $body, $attachments);
    }

    private function deliver($to, $subject, $body, $attachments = [])
    {
        if ($this->driver === 'log') {
            // Log the email instead of sending it
            Logger::info('Email would be sent', [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
                'attachments' => count($attachments)
            ]);
            return true;
        }

        // For now, we'll just implement the log driver
        // In a real implementation, you would integrate with PHPMailer or similar
        Logger::info('Email sent via ' . $this->driver, [
            'to' => $to,
            'subject' => $subject,
            'body_length' => strlen($body),
            'attachments' => count($attachments)
        ]);

        return true;
    }

    public static function queue($to, $subject, $body, $delay = 0)
    {
        // For now, just send immediately
        // In a real implementation, you would queue this
        return self::send($to, $subject, $body);
    }
}