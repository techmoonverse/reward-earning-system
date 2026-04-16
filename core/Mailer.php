<?php
declare(strict_types=1);

class Mailer {
    private array $config;
    private array $appConfig;

    public function __construct(array $config) {
        $this->config = $config['mail'];
        $this->appConfig = $config['app'];
    }

    public function sendVerificationEmail(string $to, string $username, string $token): bool {
        $verifyUrl = $this->appConfig['url'] . '/verify-email.php?token=' . urlencode($token);
        $subject = 'Verify your email – ' . $this->config['from_name'];
        $body = $this->wrapTemplate("Hi " . htmlspecialchars($username) . ",<br><br>
            Please verify your email address by clicking the button below.<br><br>
            <a href='{$verifyUrl}' style='background:#6c47ff;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block'>Verify Email</a><br><br>
            Or copy this link: {$verifyUrl}<br><br>
            This link expires in 24 hours.", $subject);
        return $this->send($to, $subject, $body);
    }

    public function sendPasswordResetEmail(string $to, string $username, string $token): bool {
        $resetUrl = $this->appConfig['url'] . '/reset-password.php?token=' . urlencode($token);
        $subject = 'Password Reset – ' . $this->config['from_name'];
        $body = $this->wrapTemplate("Hi " . htmlspecialchars($username) . ",<br><br>
            You requested a password reset.<br><br>
            <a href='{$resetUrl}' style='background:#6c47ff;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block'>Reset Password</a><br><br>
            This link expires in 1 hour. If you did not request this, ignore this email.", $subject);
        return $this->send($to, $subject, $body);
    }

    private function send(string $to, string $subject, string $htmlBody): bool {
        // Validate recipient to prevent email header injection
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            return false;
        }
        // Use PHP's mail() as fallback; in production replace with PHPMailer/SMTP
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->config['from_name'] . ' <' . $this->config['from'] . '>',
            'X-Mailer: PHP/' . phpversion(),
        ];
        return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    private function wrapTemplate(string $content, string $title): string {
        $siteName = htmlspecialchars($this->config['from_name']);
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="font-family:Arial,sans-serif;background:#0f0f1a;color:#e2e8f0;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#1a1a2e;border-radius:12px;padding:32px">
  <h2 style="color:#6c47ff">{$siteName}</h2>
  <div>{$content}</div>
  <hr style="border-color:#333;margin:24px 0">
  <p style="color:#666;font-size:12px">© {$siteName}. All rights reserved.</p>
</div></body></html>
HTML;
    }
}
