<?php

namespace Framework\Helper;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $mailer;
    private $fromEmail;
    private $fromName;
    private $driver;

    public function __construct()
    {
        $this->driver = $_ENV['MAIL_DRIVER'] ?? 'mail';
        $this->mailer = new PHPMailer(true);

        if ($this->driver !== 'log') {
            $this->configure();
        }

        $this->fromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@example.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'CoExams';
    }

    /**
     * Configure the mailer
     */
    private function configure()
    {
        try {
            // Use SMTP if configured, otherwise use mail()
            if (!empty($_ENV['MAIL_HOST'])) {
                $this->mailer->isSMTP();
                $this->mailer->Host = $_ENV['MAIL_HOST'];
                $this->mailer->Port = $_ENV['MAIL_PORT'] ?? 587;

                // Only enable SMTPAuth if username/password are provided and not "null"
                $username = $_ENV['MAIL_USERNAME'] ?? '';
                $password = $_ENV['MAIL_PASSWORD'] ?? '';

                if (!empty($username) && $username !== 'null' && !empty($password) && $password !== 'null') {
                    $this->mailer->SMTPAuth = true;
                    $this->mailer->Username = $username;
                    $this->mailer->Password = $password;
                } else {
                    $this->mailer->SMTPAuth = false;
                }

                $encryption = $_ENV['MAIL_ENCRYPTION'] ?? '';
                if (!empty($encryption) && $encryption !== 'null') {
                    $this->mailer->SMTPSecure = $encryption;
                }
            } else {
                $this->mailer->isMail();
            }

            $this->mailer->CharSet = 'UTF-8';
        } catch (Exception $e) {
            error_log('Email configuration error: ' . $e->getMessage());
        }
    }

    /**
     * Send a simple email
     *
     * @param string $toEmail Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $altBody Plain text alternative (optional)
     * @return bool True if sent successfully
     */
    public function sendEmail($toEmail, $subject, $body, $altBody = '')
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->addAddress($toEmail);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;

            if (!empty($altBody)) {
                $this->mailer->AltBody = $altBody;
            }

            if ($this->driver === 'log') {
                $logMessage = "--- EMAIL LOG ---\n";
                $logMessage .= "To: $toEmail\n";
                $logMessage .= "From: {$this->fromName} <{$this->fromEmail}>\n";
                $logMessage .= "Subject: $subject\n";
                $logMessage .= "Body: $body\n";
                $logMessage .= "AltBody: $altBody\n";
                $logMessage .= "-----------------\n";

                Logger::info("Email logged due to MAIL_DRIVER=log", ['to' => $toEmail, 'subject' => $subject]);

                // Write to a local file as well
                $logPath = Helper::storage_path('logs/mail.log');
                if (!is_dir(dirname($logPath))) {
                    mkdir(dirname($logPath), 0755, true);
                }
                file_put_contents($logPath, $logMessage, FILE_APPEND);

                return true;
            }

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Email send error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using a template file
     *
     * @param string $toEmail Recipient email address
     * @param string $subject Email subject
     * @param string $templatePath Path to template file (relative to Resources/views/emails/)
     * @param array $data Data to pass to the template
     * @return bool True if sent successfully
     */
    public function sendTemplate($toEmail, $subject, $templatePath, $data = [])
    {
        try {
            // Get template base path from .env or use default
            $customPath = $_ENV['MAIL_TEMPLATE_PATH'] ?? null;

            if ($customPath) {
                // Use custom path from .env (can be absolute or relative to project root)
                if (substr($customPath, 0, 1) === '/' || substr($customPath, 1, 1) === ':') {
                    // Absolute path
                    $basePath = rtrim($customPath, '/\\') . '/';
                } else {
                    // Relative to project root
                    $basePath = dirname(__DIR__, 2) . '/' . trim($customPath, '/\\') . '/';
                }
            } else {
                // Default path
                $basePath = dirname(__DIR__, 2) . '/Resources/views/emails/';
            }

            $fullPath = $basePath . $templatePath;

            // Check if file exists
            if (!file_exists($fullPath)) {
                error_log("Email template not found: {$fullPath}");
                return false;
            }

            // Load and render template
            extract($data);
            ob_start();
            include $fullPath;
            $body = ob_get_clean();

            // Send the email
            return $this->sendEmail($toEmail, $subject, $body);
        } catch (Exception $e) {
            error_log('Email template error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy method for backward compatibility
     */
    public function sendMail($toEmail, $subject, $htmlContent, $plainTextContent = '')
    {
        return $this->sendEmail($toEmail, $subject, $htmlContent, $plainTextContent);
    }

}
