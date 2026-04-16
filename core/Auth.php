<?php
declare(strict_types=1);

class Auth {
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config) {
        $this->db = $db;
        $this->config = $config;
    }

    public function startSession(): void {
        $cfg = $this->config['session'];
        session_name($cfg['name']);
        ini_set('session.gc_maxlifetime', (string)$cfg['lifetime']);
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if (!isset($_SESSION)) {
            session_start();
        }
    }

    public function register(string $username, string $email, string $password, ?string $referralCode = null): array {
        // Validate inputs
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'error' => 'Username must be 3-50 characters.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        // Check duplicates
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Email or username already taken.'];
        }

        // Resolve referral
        $referredBy = null;
        if ($referralCode) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE referral_code = ? LIMIT 1');
            $stmt->execute([$referralCode]);
            $referrer = $stmt->fetch();
            if ($referrer) {
                $referredBy = (int)$referrer['id'];
            }
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $myReferralCode = $this->generateReferralCode();
        $verifyToken = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, referral_code, referred_by, email_verify_token)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash, $myReferralCode, $referredBy, $verifyToken]);
        $userId = (int)$this->db->lastInsertId();

        // Register referral relationship
        if ($referredBy) {
            $this->registerReferral($referredBy, $userId);
        }

        return ['success' => true, 'user_id' => $userId, 'verify_token' => $verifyToken];
    }

    public function login(string $email, string $password, string $ip): array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid credentials.'];
        }

        // Check lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success' => false, 'error' => "Account locked. Try again in {$remaining} minute(s)."];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->incrementLoginAttempts((int)$user['id']);
            return ['success' => false, 'error' => 'Invalid credentials.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => 'Account is suspended or banned.'];
        }

        // Reset login attempts and update last login
        $this->db->prepare('UPDATE users SET login_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?')
                 ->execute([$user['id']]);

        // Session security: regenerate session ID
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_ip']   = $ip;
        $_SESSION['login_time'] = time();

        return ['success' => true, 'user' => $user];
    }

    public function logout(): void {
        $_SESSION = [];
        session_destroy();
    }

    public function isLoggedIn(): bool {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public function isAdmin(): bool {
        return $this->isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    public function requireAdmin(): void {
        if (!$this->isAdmin()) {
            header('Location: /login.php');
            exit;
        }
    }

    public function verifyEmail(string $token): bool {
        $stmt = $this->db->prepare(
            'UPDATE users SET email_verified=1, email_verify_token=NULL WHERE email_verify_token=? AND email_verified=0'
        );
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    public function getCurrentUser(): ?array {
        if (!$this->isLoggedIn()) return null;
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    private function incrementLoginAttempts(int $userId): void {
        $this->db->prepare(
            'UPDATE users SET login_attempts = login_attempts + 1,
             locked_until = IF(login_attempts + 1 >= 5, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NULL)
             WHERE id = ?'
        )->execute([$userId]);
    }

    private function generateReferralCode(): string {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $stmt = $this->db->prepare('SELECT id FROM users WHERE referral_code = ? LIMIT 1');
            $stmt->execute([$code]);
        } while ($stmt->fetch());
        return $code;
    }

    private function registerReferral(int $referrerId, int $referredId): void {
        // Level 1
        $this->db->prepare(
            'INSERT INTO referrals (referrer_id, referred_id, level, commission_rate) VALUES (?,?,1,0.10)'
        )->execute([$referrerId, $referredId]);

        // Level 2: referrer's referrer
        $stmt = $this->db->prepare('SELECT referred_by FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$referrerId]);
        $row = $stmt->fetch();
        if ($row && $row['referred_by']) {
            $l2 = (int)$row['referred_by'];
            $this->db->prepare(
                'INSERT INTO referrals (referrer_id, referred_id, level, commission_rate) VALUES (?,?,2,0.05)'
            )->execute([$l2, $referredId]);

            // Level 3
            $stmt->execute([$l2]);
            $row2 = $stmt->fetch();
            if ($row2 && $row2['referred_by']) {
                $l3 = (int)$row2['referred_by'];
                $this->db->prepare(
                    'INSERT INTO referrals (referrer_id, referred_id, level, commission_rate) VALUES (?,?,3,0.02)'
                )->execute([$l3, $referredId]);
            }
        }
    }
}
