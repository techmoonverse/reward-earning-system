<?php
declare(strict_types=1);

class Transaction {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getUserTransactions(int $userId, int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            'SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public function getUserTransactionCount(int $userId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM transactions WHERE user_id=?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function createWithdrawal(int $userId, float $amount, string $method, string $accountDetails): int {
        $stmt = $this->db->prepare(
            "INSERT INTO withdrawals (user_id, amount, method, account_details, status, created_at)
             VALUES (?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$userId, $amount, $method, $accountDetails]);
        return (int)$this->db->lastInsertId();
    }

    public function getUserWithdrawals(int $userId): array {
        $stmt = $this->db->prepare('SELECT * FROM withdrawals WHERE user_id=? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function record(int $userId, string $type, float $amount, int $points, string $description = ''): int {
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (user_id, type, amount, points, status, description, created_at)
             VALUES (?, ?, ?, ?, 'completed', ?, NOW())"
        );
        $stmt->execute([$userId, $type, $amount, $points, $description]);
        return (int)$this->db->lastInsertId();
    }
}
