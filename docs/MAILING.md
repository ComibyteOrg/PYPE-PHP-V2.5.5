# Mailing Guide

## Overview

Pype PHP provides a simple mailing system through the `Mailer` class. Currently, it supports a **log driver** that logs emails instead of sending them (perfect for development). The architecture is designed to support additional drivers like SMTP, Sendmail, or third-party services.

---

## Configuration

### Environment Variables

Set mail configuration in your `.env` file:

```env
MAIL_DRIVER=log
MAIL_HOST=localhost
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_EMAIL=noreply@example.com
MAIL_FROM_NAME=Pype Framework
```

### Driver Options

| Driver | Description |
|--------|-------------|
| `log` | Logs emails to file (development) |
| `smtp` | Send via SMTP server (future) |
| `sendmail` | Send via sendmail (future) |

---

## Basic Usage

### `Mailer::send($to, $subject, $body, $attachments = [])`

Send an email.

```php
use Framework\Mail\Mailer;

// Basic email
Mailer::send(
    'user@example.com',
    'Welcome to Our Site',
    '<h1>Hello!</h1><p>Welcome to our platform.</p>'
);

// With HTML content
Mailer::send(
    'user@example.com',
    'Order Confirmation',
    '
    <html>
        <body>
            <h2>Thank you for your order!</h2>
            <p>Order #12345 has been confirmed.</p>
            <table>
                <tr><td>Item</td><td>Price</td></tr>
                <tr><td>Product A</td><td>$29.99</td></tr>
            </table>
        </body>
    </html>
    '
);
```

---

## Email Queue

### `Mailer::queue($to, $subject, $body, $delay = 0)`

Queue an email for sending (currently sends immediately).

```php
// Queue email
Mailer::queue(
    'user@example.com',
    'Welcome',
    'Hello! Thanks for joining.'
);

// Queue with delay (future feature)
Mailer::queue(
    'user@example.com',
    'Reminder',
    'Don\'t forget your appointment!',
    3600 // Delay in seconds
);
```

---

## Common Use Cases

### Welcome Email

```php
function sendWelcomeEmail($user)
{
    Mailer::send(
        $user->email,
        'Welcome to ' . env('APP_NAME'),
        "
        <h1>Welcome, {$user->name}!</h1>
        <p>Thank you for joining our platform.</p>
        <p>Your account has been created successfully.</p>
        <a href='" . url('/login') . "'>Login now</a>
        "
    );
}
```

### Password Reset

```php
function sendPasswordReset($user, $token)
{
    $resetUrl = url('/reset-password?token=' . $token . '&email=' . $user->email);
    
    Mailer::send(
        $user->email,
        'Reset Your Password',
        "
        <h2>Password Reset Request</h2>
        <p>Click the link below to reset your password:</p>
        <a href='{$resetUrl}'>Reset Password</a>
        <p>This link expires in 1 hour.</p>
        <p>If you didn't request this, ignore this email.</p>
        "
    );
}
```

### Order Confirmation

```php
function sendOrderConfirmation($order)
{
    $items = '';
    foreach ($order->items as $item) {
        $items .= "<tr><td>{$item->name}</td><td>\${$item->price}</td></tr>";
    }
    
    Mailer::send(
        $order->email,
        'Order Confirmation #' . $order->id,
        "
        <h2>Order Confirmed!</h2>
        <p>Thank you for your purchase.</p>
        <table border='1'>
            <tr><th>Item</th><th>Price</th></tr>
            {$items}
        </table>
        <p><strong>Total: \${$order->total}</strong></p>
        "
    );
}
```

### Contact Form Notification

```php
function sendContactNotification($data)
{
    Mailer::send(
        env('MAIL_FROM_EMAIL'),
        'New Contact Form Submission',
        "
        <h3>New Message</h3>
        <p><strong>Name:</strong> {$data['name']}</p>
        <p><strong>Email:</strong> {$data['email']}</p>
        <p><strong>Subject:</strong> {$data['subject']}</p>
        <p><strong>Message:</strong></p>
        <p>{$data['message']}</p>
        "
    );
}
```

---

## Using Twig Templates for Emails

Create email templates in `Resources/views/emails/`:

```twig
{# Resources/views/emails/welcome.twig #}
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #4CAF50; color: white; padding: 20px; }
        .content { padding: 20px; }
        .footer { background: #f1f1f1; padding: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome, {{ name }}!</h1>
        </div>
        <div class="content">
            <p>Thank you for joining our platform.</p>
            <p>We're excited to have you on board.</p>
            <a href="{{ login_url }}" style="background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; margin-top: 20px;">Get Started</a>
        </div>
        <div class="footer">
            <p>&copy; {{ year }} {{ app_name }}</p>
        </div>
    </div>
</body>
</html>
```

Use in controller:

```php
use Framework\Mail\Mailer;
use Framework\Helper\TwigManager;

// Render email template
$body = TwigManager::render('emails/welcome.twig', [
    'name' => $user->name,
    'login_url' => url('/login'),
    'year' => date('Y'),
    'app_name' => env('APP_NAME')
]);

Mailer::send($user->email, 'Welcome!', $body);
```

---

## Log Driver Output

When using the `log` driver, emails are logged to `Storage/logs/app.log`:

```
[2024-01-15 10:30:00] INFO: Email would be sent {"to": "user@example.com", "subject": "Welcome", "body": "<h1>Hello!</h1>...", "attachments": 0}
```

This is useful for:
- Development testing
- Verifying email content
- Not sending real emails during development

---

## Future Driver Support

The Mailer architecture supports adding new drivers. Future implementations could include:

### SMTP Driver

```php
// Future: SMTP configuration
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
```

### SendGrid Driver

```php
// Future: SendGrid
MAIL_DRIVER=sendgrid
MAIL_API_KEY=your_api_key
```

---

## Best Practices

1. **Use HTML templates** for consistent email design
2. **Include plain text fallback** for email clients that don't support HTML
3. **Test with log driver** before switching to SMTP
4. **Never send passwords** via email
5. **Use queue for bulk emails** to avoid timeouts
6. **Include unsubscribe links** for marketing emails
7. **Test across email clients** (Gmail, Outlook, Apple Mail)
