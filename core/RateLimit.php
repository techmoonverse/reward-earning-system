<?php
declare(strict_types=1);

class RateLimit {
    private PDO $db;
    private int $maxAttempts;
    private int $windowSeconds;

    public function __construct(PDO $db, int $maxAttempts = 5, int $windowSeconds = 3600) {
        $this->db = $db;
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    public function check(string $ip, string $action): bool {
        $this->cleanup($ip, $action);
        $stmt = $this->db->prepare(
            'SELECT SUM(attempts) as total FROM rate_limit WHERE ip_address=? AND action=? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$ip, $action, $this->windowSeconds]);
        $row = $stmt->fetch();
        return ((int)($row['total'] ?? 0)) < $this->maxAttempts;
    }

    public function increment(string $ip, string $action): void {
        $this->db->prepare(
            'INSERT INTO rate_limit (ip_address, action, attempts, window_start) VALUES (?,?,1,NOW())'
        )->execute([$ip, $action]);
    }

    public function getRemainingAttempts(string $ip, string $action): int {
        $stmt = $this->db->prepare(
            'SELECT SUM(attempts) as total FROM rate_limit WHERE ip_address=? AND action=? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$ip, $action, $this->windowSeconds]);
        $row = $stmt->fetch();
        return max(0, $this->maxAttempts - (int)($row['total'] ?? 0));
    }

    private function cleanup(string $ip, string $action): void {
        $this->db->prepare(
            'DELETE FROM rate_limit WHERE ip_address=? AND action=? AND window_start <= DATE_SUB(NOW(), INTERVAL ? SECOND)'
        )->execute([$ip, $action, $this->windowSeconds]);
    }
}
