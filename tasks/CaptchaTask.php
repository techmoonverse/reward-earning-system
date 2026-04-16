<?php
declare(strict_types=1);

class CaptchaTask implements TaskInterface {
    private PDO $db;
    private Security $security;
    private Task $taskModel;

    public function __construct(PDO $db, Security $security, Task $taskModel) {
        $this->db = $db;
        $this->security = $security;
        $this->taskModel = $taskModel;
    }

    public function getType(): string { return TASK_TYPE_CAPTCHA; }

    public function start(int $userId, int $taskId, string $ip, string $fingerprint): array {
        $task = $this->taskModel->getById($taskId);
        if (!$task || !$task['is_active']) {
            return ['success' => false, 'message' => 'Task not available.'];
        }
        if ($this->taskModel->hasCompletedToday($userId, $taskId)) {
            return ['success' => false, 'message' => 'Already completed today.'];
        }
        $token = $this->security->generateTaskToken($userId, $taskId);
        $utId  = $this->taskModel->startTask($userId, $taskId, $token, $ip, $fingerprint);
        return ['success' => true, 'token' => $token, 'user_task_id' => $utId, 'task' => $task];
    }

    public function complete(int $userId, int $taskId, string $token, array $extra = []): array {
        $tokenData = $this->security->verifyTaskToken($token);
        if (!$tokenData || $tokenData['uid'] !== $userId || $tokenData['tid'] !== $taskId) {
            return ['success' => false, 'message' => 'Invalid task token.'];
        }
        $elapsed = time() - $tokenData['ts'];
        $task    = $this->taskModel->getById($taskId);
        $minDur  = (int)floor($task['duration_seconds'] * TASK_MIN_DURATION_RATIO);
        if ($elapsed < $minDur) {
            return ['success' => false, 'message' => 'Task completed too fast.'];
        }
        if ($this->taskModel->hasCompletedToday($userId, $taskId)) {
            return ['success' => false, 'message' => 'Already completed today.'];
        }
        if (!empty($extra['recaptcha_token'])) {
            if (!$this->security->verifyRecaptcha($extra['recaptcha_token'], 'task_complete')) {
                return ['success' => false, 'message' => 'reCAPTCHA validation failed.'];
            }
        }
        $stmt = $this->db->prepare(
            "SELECT id FROM user_tasks WHERE user_id=? AND task_id=? AND status='started' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $taskId]);
        $ut = $stmt->fetch();
        if (!$ut) return ['success' => false, 'message' => 'No active task session found.'];

        $points = (int)$task['reward_points'];
        $cash   = $points / DEFAULT_POINTS_PER_DOLLAR;
        if ($this->taskModel->completeTask((int)$ut['id'], $userId, $taskId, $points, $cash)) {
            return ['success' => true, 'points' => $points, 'cash' => $cash, 'message' => "Earned {$points} points!"];
        }
        return ['success' => false, 'message' => 'Could not complete task. Try again.'];
    }
}
