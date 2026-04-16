<?php
declare(strict_types=1);

class Security {
    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    // ---- CSRF ----
    public function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function csrfField(): string {
        $token = $this->generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // ---- reCAPTCHA v3 ----
    public function verifyRecaptcha(string $token, string $action = 'submit', float $minScore = 0.5): bool {
        $secret = $this->config['recaptcha']['secret_key'];
        if (empty($secret)) return true; // skip if not configured

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = http_build_query(['secret' => $secret, 'response' => $token]);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $data]]);
        $result = @file_get_contents($url, false, $ctx);
        if (!$result) return false;
        $json = json_decode($result, true);
        return ($json['success'] ?? false) && ($json['score'] ?? 0) >= $minScore && ($json['action'] ?? '') === $action;
    }

    // ---- Task Tokens ----
    public function generateTaskToken(int $userId, int $taskId): string {
        $payload = json_encode(['uid' => $userId, 'tid' => $taskId, 'ts' => time()]);
        $sig = hash_hmac('sha256', $payload, $this->config['app']['secret']);
        return base64_encode($payload . '|' . $sig);
    }

    public function verifyTaskToken(string $token): ?array {
        $decoded = base64_decode($token, true);
        if (!$decoded) return null;
        $parts = explode('|', $decoded, 2);
        if (count($parts) !== 2) return null;
        [$payload, $sig] = $parts;
        $expected = hash_hmac('sha256', $payload, $this->config['app']['secret']);
        if (!hash_equals($expected, $sig)) return null;
        $data = json_decode($payload, true);
        return $data ?: null;
    }

    // ---- Output sanitization ----
    public static function h(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ---- IP helpers ----
    public static function getClientIp(): string {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    // ---- Device fingerprint (lightweight) ----
    public static function getDeviceFingerprint(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        return hash('sha256', $ua . $lang . self::getClientIp());
    }
}
