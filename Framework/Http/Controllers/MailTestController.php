<?php

namespace Framework\Http\Controllers;

use Framework\Helper\EmailService;
use Framework\Helper\ApiResponse;

class MailTestController
{
    public function sendTest()
    {
        $emailService = new EmailService();
        $to = $_GET['to'] ?? 'test@example.com';
        $subject = "Test Email from Pype PHP V2";
        $body = "<h1>Success!</h1><p>The mailing system is working perfectly.</p>";

        $result = $emailService->sendEmail($to, $subject, $body);

        if ($result) {
            return ApiResponse::success(null, "Test email sent successfully to $to");
        } else {
            return ApiResponse::error("Failed to send test email. Check logs for details.", 500);
        }
    }
}
