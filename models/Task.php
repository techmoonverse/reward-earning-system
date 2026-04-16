<?php
declare(strict_types=1);

class Task {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getAll(bool $activeOnly = true): array {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        return $this->db->query("SELECT * FROM tasks {$where} ORDER BY id ASC")->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (title, type, description, reward_points, duration_seconds, url, is_active, daily_limit)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['title'],
            $data['type'],
            $data['description'] ?? '',
            (int)($data['reward_points'] ?? 0),
            (int)($data['duration_seconds'] ?? 30),
            $data['url'] ?? null,
            (int)($data['is_active'] ?? 1),
            (int)($data['daily_limit'] ?? 1),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $allowed = ['title','type','description','reward_points','duration_seconds','url','is_active','daily_limit'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "{$col} = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        $params[] = $id;
        return $this->db->prepare('UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = ?')
                        ->execute($params);
    }

    public function delete(int $id): bool {
        return $this->db->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
    }

    public function getUserTaskStatus(int $userId, int $taskId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM user_tasks WHERE user_id=? AND task_id=? AND DATE(started_at)=CURDATE() ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $taskId]);
        return $stmt->fetch() ?: null;
    }

    public function hasCompletedToday(int $userId, int $taskId): bool {
        $stmt = $this->db->prepare(
            "SELECT id FROM user_tasks WHERE user_id=? AND task_id=? AND status='completed' AND DATE(completed_at)=CURDATE() LIMIT 1"
        );
        $stmt->execute([$userId, $taskId]);
        return (bool)$stmt->fetch();
    }

    public function countCompletedToday(int $userId, int $taskId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM user_tasks WHERE user_id=? AND task_id=? AND status='completed' AND DATE(completed_at)=CURDATE()"
        );
        $stmt->execute([$userId, $taskId]);
        return (int)$stmt->fetch()['cnt'];
    }

    public function startTask(int $userId, int $taskId, string $token, string $ip, string $fingerprint): int {
        $stmt = $this->db->prepare(
            'INSERT INTO user_tasks (user_id, task_id, status, task_token, ip_address, device_fingerprint, started_at)
             VALUES (?, ?, "started", ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $taskId, $token, $ip, $fingerprint]);
        return (int)$this->db->lastInsertId();
    }

    public function completeTask(int $userTaskId, int $userId, int $taskId, int $points, float $cash): bool {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE user_tasks SET status='completed', completed_at=NOW(), reward_given=1, ad_viewed=1 WHERE id=?"
            )->execute([$userTaskId]);

            $this->db->prepare(
                'UPDATE users SET points=points+?, balance=balance+?, total_earned=total_earned+? WHERE id=?'
            )->execute([$points, $cash, $cash, $userId]);

            $this->db->prepare(
                "INSERT INTO transactions (user_id, type, amount, points, status, description, created_at)
                 VALUES (?, 'earn', ?, ?, 'completed', ?, NOW())"
            )->execute([$userId, $cash, $points, "Task #{$taskId} reward"]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function markAdViewed(int $userTaskId): bool {
        return $this->db->prepare('UPDATE user_tasks SET ad_viewed=1 WHERE id=?')
                        ->execute([$userTaskId]);
    }

    public function getWithStatusForUser(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT t.*,
                COALESCE(ut.status, 'not_started') as user_status,
                COALESCE(ut.reward_given, 0) as reward_given,
                (SELECT COUNT(*) FROM user_tasks ut2
                 WHERE ut2.user_id=? AND ut2.task_id=t.id AND ut2.status='completed' AND DATE(ut2.completed_at)=CURDATE()
                ) as completed_today
             FROM tasks t
             LEFT JOIN user_tasks ut ON ut.user_id=? AND ut.task_id=t.id AND DATE(ut.started_at)=CURDATE()
             WHERE t.is_active=1
             GROUP BY t.id
             ORDER BY t.id ASC"
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }
}
