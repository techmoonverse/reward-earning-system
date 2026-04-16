<?php
declare(strict_types=1);

class Referral {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function awardCommission(int $earnedByUserId, int $earnedPoints, float $earnedCash): void {
        $refs = $this->getAllReferrers($earnedByUserId);
        foreach ($refs as $ref) {
            $commission = $earnedCash * (float)$ref['commission_rate'];
            $commissionPoints = (int)round($earnedPoints * (float)$ref['commission_rate']);
            if ($commission <= 0) continue;

            $this->db->prepare(
                'UPDATE users SET balance=balance+?, total_earned=total_earned+?, points=points+? WHERE id=?'
            )->execute([$commission, $commission, $commissionPoints, $ref['referrer_id']]);

            $this->db->prepare(
                "INSERT INTO transactions (user_id, type, amount, points, status, description)
                 VALUES (?, 'commission', ?, ?, 'completed', ?)"
            )->execute([
                $ref['referrer_id'],
                $commission,
                $commissionPoints,
                "Level {$ref['level']} commission from user #{$earnedByUserId}"
            ]);

            $this->db->prepare(
                'UPDATE referrals SET total_earned=total_earned+? WHERE referrer_id=? AND referred_id=? AND level=?'
            )->execute([$commission, $ref['referrer_id'], $earnedByUserId, $ref['level']]);
        }
    }

    private function getAllReferrers(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.username FROM referrals r
             JOIN users u ON u.id = r.referrer_id
             WHERE r.referred_id = ? AND r.is_flagged = 0 ORDER BY r.level ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getUserReferrals(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.username, u.created_at as joined_at, u.total_earned as referred_earned
             FROM referrals r
             JOIN users u ON u.id = r.referred_id
             WHERE r.referrer_id = ? AND r.level = 1
             ORDER BY r.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getReferralStats(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(CASE WHEN level=1 THEN 1 END) as direct_referrals,
                SUM(total_earned) as total_commission,
                COUNT(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as last_30_days
             FROM referrals r
             WHERE referrer_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }

    public function getTopReferrers(int $limit = 10): array {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username,
                COUNT(CASE WHEN r.level=1 THEN 1 END) as referral_count,
                SUM(r.total_earned) as total_earned
             FROM referrals r
             JOIN users u ON u.id = r.referrer_id
             WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND r.level=1
             GROUP BY u.id ORDER BY referral_count DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function awardReferralSignupBonus(int $referrerId, int $referredId): void {
        $alreadyRewarded = $this->db->prepare(
            'SELECT reward_given FROM referrals WHERE referrer_id=? AND referred_id=? AND level=1 LIMIT 1'
        );
        $alreadyRewarded->execute([$referrerId, $referredId]);
        $row = $alreadyRewarded->fetch();
        if (!$row || $row['reward_given']) return;

        $signupBonus = 50;
        $cashBonus = $signupBonus / DEFAULT_POINTS_PER_DOLLAR;

        $this->db->prepare('UPDATE users SET points=points+?, balance=balance+?, total_earned=total_earned+? WHERE id=?')
                 ->execute([$signupBonus, $cashBonus, $cashBonus, $referrerId]);

        $this->db->prepare("INSERT INTO transactions (user_id, type, amount, points, status, description) VALUES (?,'referral',?,?,'completed',?)")
                 ->execute([$referrerId, $cashBonus, $signupBonus, "Referral signup bonus for user #{$referredId}"]);

        $this->db->prepare('UPDATE referrals SET reward_given=1 WHERE referrer_id=? AND referred_id=? AND level=1')
                 ->execute([$referrerId, $referredId]);
    }
}
