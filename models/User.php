<?php
declare(strict_types=1);

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function creditPoints(int $userId, int $points, float $cashAmount = 0.0): bool {
        $stmt = $this->db->prepare(
            'UPDATE users SET points = points + ?, balance = balance + ?, total_earned = total_earned + ? WHERE id = ?'
        );
        return $stmt->execute([$points, $cashAmount, $cashAmount, $userId]);
    }

    public function deductBalance(int $userId, float $amount): bool {
        $stmt = $this->db->prepare(
            'UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?'
        );
        $stmt->execute([$amount, $userId, $amount]);
        return $stmt->rowCount() > 0;
    }

    public function updateProfile(int $userId, array $data): bool {
        $allowed = ['username'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (isset($data[$col])) {
                $sets[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        $params[] = $userId;
        $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($params);
    }

    public function changePassword(int $userId, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$hash, $userId]);
    }

    public function getLoyaltyLevel(int $totalPoints): string {
        foreach (LOYALTY_TIERS as $level => $threshold) {
            if ($totalPoints >= $threshold) return $level;
        }
        return 'bronze';
    }

    public function getTopEarners(string $period = 'week', int $limit = 10): array {
        $interval = $period === 'month' ? 'INTERVAL 30 DAY' : 'INTERVAL 7 DAY';
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, SUM(t.amount) as earned
             FROM transactions t
             JOIN users u ON u.id = t.user_id
             WHERE t.type = 'earn' AND t.created_at >= DATE_SUB(NOW(), {$interval})
             GROUP BY u.id
             ORDER BY earned DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getStats(int $userId): array {
        $user = $this->findById($userId);
        if (!$user) return [];

        // Today's earnings
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(points),0) as today_points FROM transactions
             WHERE user_id=? AND type='earn' AND DATE(created_at)=CURDATE()"
        );
        $stmt->execute([$userId]);
        $todayRow = $stmt->fetch();

        // Tasks completed today
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM user_tasks WHERE user_id=? AND status='completed' AND DATE(completed_at)=CURDATE()"
        );
        $stmt->execute([$userId]);
        $tasksRow = $stmt->fetch();

        // Current streak
        $streak = $this->getCurrentStreak($userId);

        return [
            'balance'       => (float)$user['balance'],
            'points'        => (int)$user['points'],
            'total_earned'  => (float)$user['total_earned'],
            'today_points'  => (int)$todayRow['today_points'],
            'tasks_today'   => (int)$tasksRow['cnt'],
            'streak'        => $streak,
            'loyalty_level' => $this->getLoyaltyLevel((int)$user['points']),
        ];
    }

    public function getCurrentStreak(int $userId): int {
        $stmt = $this->db->prepare(
            'SELECT streak_count FROM daily_checkin WHERE user_id=? ORDER BY checkin_date DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) return 0;

        // Check if streak is still valid (last checkin was yesterday or today)
        $stmt2 = $this->db->prepare(
            'SELECT checkin_date FROM daily_checkin WHERE user_id=? ORDER BY checkin_date DESC LIMIT 1'
        );
        $stmt2->execute([$userId]);
        $dateRow = $stmt2->fetch();
        if (!$dateRow) return 0;
        $lastDate = new DateTime($dateRow['checkin_date']);
        $today = new DateTime('today');
        $diff = (int)$today->diff($lastDate)->days;
        if ($diff > 1) return 0; // streak broken
        return (int)$row['streak_count'];
    }
}
